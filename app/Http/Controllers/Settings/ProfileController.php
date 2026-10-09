<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Everything we keep about the user, as JSON (GDPR art. 15 and 20).
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => $user->only(['name', 'email', 'email_verified_at', 'created_at', 'notify_daily_results', 'notify_events']),
            'games' => $user->games()->with(['businesses', 'monthResults', 'dayResults', 'events'])->get()->toArray(),
            'viability_reports' => $user->viabilityReports()->get(['uuid', 'status', 'inputs', 'results', 'paid_at', 'created_at'])->toArray(),
            'purchases' => $user->purchases()->get(['product', 'amount_cents', 'currency', 'status', 'withdrawal_waived_at', 'paid_at', 'created_at'])->toArray(),
            'pedestrian_counts' => $user->pedestrianCounts()->get(['lat', 'lng', 'day_part', 'counted_on', 'minutes', 'count', 'note'])->toArray(),
            'push_subscriptions' => $user->pushSubscriptions()->get(['endpoint', 'created_at'])->toArray(),
        ];

        return response()->json($data, options: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Disposition', 'attachment; filename="traspaso-my-data.json"');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
