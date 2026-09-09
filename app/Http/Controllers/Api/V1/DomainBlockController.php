<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\HomeFeedPipeline\FeedRemoveDomainPipeline;
use App\Jobs\ProfilePipeline\ProfilePurgeFollowersByDomain;
use App\Jobs\ProfilePipeline\ProfilePurgeNotificationsByDomain;
use App\Models\UserDomainBlock;
use App\Services\UserFilterService;
use App\Util\ActivityPub\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class DomainBlockController extends Controller
{
    public function json($res, $code = 200, $headers = []): JsonResponse
    {
        return response()->json($res, $code, $headers, JSON_UNESCAPED_SLASHES);
    }

    public function index(Request $request)
    {
        abort_if(! $request->user(), 403);

        $this->validate($request, [
            'limit' => 'sometimes|integer|min:1|max:200',
        ]);
        $limit = $request->input('limit', 100);
        $id = $request->user()->profile_id;
        $filters = UserDomainBlock::whereProfileId($id)->orderByDesc('id')->cursorPaginate($limit);
        $links = null;
        $headers = [];

        if ($filters->nextCursor()) {
            $links .= '<'.$filters->nextPageUrl().'&limit='.$limit.'>; rel="next"';
        }

        if ($filters->previousCursor()) {
            if ($links != null) {
                $links .= ', ';
            }
            $links .= '<'.$filters->previousPageUrl().'&limit='.$limit.'>; rel="prev"';
        }

        if ($links) {
            $headers = ['Link' => $links];
        }

        return $this->json($filters->pluck('domain'), 200, $headers);
    }

    public function store(Request $request)
    {
        abort_if(! $request->user(), 403);

        $this->validate($request, [
            'domain' => 'required|min:1|max:120',
        ]);

        $pid = $request->user()->profile_id;

        $domain = trim($request->input('domain'));

        // validateUrl canonicalizes IDN hosts to punycode and returns the
        // canonical URL. Use that return value: parse_url() on a raw UTF-8 IDN
        // URL corrupts multibyte bytes, whereas parse_url() on the canonical
        // (ASCII/punycode) URL is safe.
        $canonical = Helpers::validateUrl($domain);

        if ($canonical == false) {
            return abort(500, 'Invalid domain or already blocked by server admins');
        }

        $host = strtolower(parse_url($canonical, PHP_URL_HOST));

        // Persist the canonical (punycode) form so an IDN block row matches the
        // wire form stored in profiles.domain. normalizeHost is idempotent for
        // ASCII hosts; fall back to the parsed host if it cannot be normalized.
        $domain = Helpers::normalizeHost($host) ?? $host;

        abort_if(config_cache('pixelfed.domain.app') == $domain, 400, 'Cannot ban your own server');

        $existingCount = UserDomainBlock::whereProfileId($pid)->count();
        $maxLimit = (int) config_cache('instance.user_filters.max_domain_blocks');
        $errorMsg = __('profile.block.domain.max', ['max' => $maxLimit]);

        abort_if($existingCount >= $maxLimit, 400, $errorMsg);

        $block = UserDomainBlock::updateOrCreate([
            'profile_id' => $pid,
            'domain' => $domain,
        ]);

        if ($block->wasRecentlyCreated) {
            Bus::batch([
                [
                    new FeedRemoveDomainPipeline($pid, $domain),
                    new ProfilePurgeNotificationsByDomain($pid, $domain),
                    new ProfilePurgeFollowersByDomain($pid, $domain),
                ],
            ])->allowFailures()->onQueue('feed')->dispatch();

            Cache::forget('profile:following:'.$pid);
            UserFilterService::domainBlocks($pid, true);
        }

        return $this->json([]);
    }

    public function delete(Request $request)
    {
        abort_if(! $request->user(), 403);

        $this->validate($request, [
            'domain' => 'required|min:1|max:120',
        ]);

        $pid = $request->user()->profile_id;

        $domain = strtolower(trim($request->input('domain')));

        // Match both Unicode and punycode forms so an unblock removes the row
        // regardless of which form it was stored in.
        $forms = Helpers::domainEquivalentForms($domain);

        if (empty($forms)) {
            $forms = [$domain];
        }

        $filters = UserDomainBlock::whereProfileId($pid)->whereIn('domain', $forms)->delete();

        UserFilterService::domainBlocks($pid, true);

        return $this->json([]);
    }
}
