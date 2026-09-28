<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function local(Request $request): View
    {
        $this->validate($request, [
            'layout' => 'nullable|string|in:grid,feed',
        ]);
        $layout = $request->input('layout', 'feed');

        return view('timeline.local', ['layout' => $layout]);
    }

    public function network(Request $request): View
    {
        abort_if(config('federation.network_timeline') == false, 404);
        $this->validate($request, [
            'layout' => 'nullable|string|in:grid,feed',
        ]);
        $layout = $request->input('layout', 'feed');

        return view('timeline.network', ['layout' => $layout]);
    }
}
