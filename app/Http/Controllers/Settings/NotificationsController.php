<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Which nightly notifications a player gets, and turning them on per device. */
class NotificationsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/notifications', [
            'notify_daily_results' => $request->user()->notify_daily_results,
            'notify_events' => $request->user()->notify_events,
            'vapid_public_key' => config('webpush.public_key'),
            'devices' => $request->user()->pushSubscriptions()->count(),
            'nightly_at' => config('game.nightly_at'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate([
            'notify_daily_results' => ['required', 'boolean'],
            'notify_events' => ['required', 'boolean'],
        ]));

        return back();
    }
}
