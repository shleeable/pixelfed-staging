<?php

namespace App\Services;

use App\Models\GroupComment;
use Illuminate\Support\Facades\Cache;

class GroupPostService
{
    const CACHE_KEY = 'pf:services:groups:post:';

    const COMMENT_COUNT_KEY = 'pf:services:groups:post:comment_count:';

    public static function key($gid, $pid): string
    {
        return self::CACHE_KEY.$gid.':'.$pid;
    }

    public static function del($gid, $pid)
    {
        return Cache::forget(self::key($gid, $pid));
    }

    public static function getCommentCount($pid, $cache = true)
    {
        if (! $cache) {
            return GroupComment::where('status_id', $pid)->orWhere('in_reply_to_id', $pid)->count();
        }

        return Cache::remember(self::COMMENT_COUNT_KEY.$pid, 3600, function () use ($pid) {
            return GroupComment::where('status_id', $pid)->orWhere('in_reply_to_id', $pid)->count();
        });
    }
}
