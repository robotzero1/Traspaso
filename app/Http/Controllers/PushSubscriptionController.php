<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Devices register (and unregister) for push from the browser. */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        // An endpoint belongs to one browser; if it was someone else's, it's this user's now.
        PushSubscription::query()->updateOrCreate(['endpoint' => $data['endpoint']], [
            'user_id' => $request->user()->id,
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
        ]);

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $endpoint = $request->validate(['endpoint' => ['required', 'string']])['endpoint'];
        $request->user()->pushSubscriptions()->where('endpoint', $endpoint)->delete();

        return response()->noContent();
    }
}
