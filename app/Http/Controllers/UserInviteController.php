<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInvite;
use App\Services\EmailService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UserInviteController extends Controller
{
    public function create(Request $request): View
    {
        abort_if(! config('pixelfed.user_invites.enabled'), 404);
        abort_unless($request->user() !== null, 403);

        return view('settings.invites.create');
    }

    public function show(Request $request): View
    {
        abort_if(! config('pixelfed.user_invites.enabled'), 404);
        abort_unless($request->user() !== null, 403);
        $invites = UserInvite::whereUserId(Auth::id())->paginate(10);
        $limit = config('pixelfed.user_invites.limit.total');
        $used = UserInvite::whereUserId(Auth::id())->count();

        return view('settings.invites.home', ['invites' => $invites, 'limit' => $limit, 'used' => $used]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! config('pixelfed.user_invites.enabled'), 404);
        abort_unless($request->user() !== null, 403);
        $this->validate($request, [
            'email' => 'required|email|unique:users|unique:user_invites',
            'message' => 'nullable|string|max:500',
            'tos' => 'required|accepted',
        ]);

        $email = $request->input('email');

        $userCount = UserInvite::whereUserId(Auth::id())->count();
        $userLimit = config('pixelfed.user_invites.limit.total');

        abort_if($userCount >= $userLimit, 400);
        abort_if(EmailService::isBanned($email), 400);
        abort_if(User::whereEmail($email)->exists(), 400);

        $invite = new UserInvite;
        $invite->user_id = Auth::id();
        $invite->profile_id = $request->user()->profile_id;
        $invite->email = $email;
        $invite->message = $request->input('message');
        $invite->key = Str::random(random_int(6, 9)).'_'.Str::random(random_int(14, 20)).'_'.Str::random(random_int(32, 64));
        $invite->token = Str::random(random_int(32, 69));
        $invite->save();

        // Mail::to($email)->send(new UserInviteMail($invite));

        return redirect(route('settings.invites'));
    }
}
