<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\ViabilityReport;
use App\Payments\PaymentGateway;
use App\Payments\Payments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Buying (SPEC §11): sends the buyer to Stripe Checkout and back. */
class PaymentController extends Controller
{
    public function __construct(private readonly Payments $payments, private readonly PaymentGateway $gateway) {}

    public function viabilityReport(Request $request, ViabilityReport $report): Response
    {
        $this->guard($request);

        if ($report->status !== 'done' || $report->unlocked()) {
            throw ValidationException::withMessages(['payment' => 'This report can\'t be bought now.']);
        }

        return Inertia::location($this->payments->checkout(
            'viability_report',
            route('payments.return'),
            route('viability.show', $report),
            $request->user(),
            $report,
        ));
    }

    public function capital(Request $request): Response
    {
        $this->guard($request);
        $tiers = collect(config('payments.products'))->filter(fn (array $p) => isset($p['capital_cents']));
        $tier = $request->validate(['tier' => ['required', Rule::in($tiers->keys()->all())]])['tier'];

        if ($tiers[$tier]['capital_cents'] <= Payments::maxCapitalCents($request->user())) {
            throw ValidationException::withMessages(['tier' => 'You can already start with that much.']);
        }

        return Inertia::location($this->payments->checkout($tier, route('payments.return'), route('games.index'), $request->user()));
    }

    /** Where Stripe sends the buyer back: confirm with Stripe, then show what they bought. */
    public function returned(Request $request): RedirectResponse
    {
        $purchase = $this->payments->confirm((string) $request->query('session_id'));

        if ($purchase?->viabilityReport) {
            return to_route('viability.show', $purchase->viabilityReport);
        }

        return to_route('games.index');
    }

    /** Stripe's webhook: the dependable confirmation, even if the buyer never comes back. */
    public function webhook(Request $request): Response
    {
        try {
            $event = $this->gateway->event($request->getContent(), $request->header('Stripe-Signature'));
        } catch (Throwable) {
            return response('Invalid signature', 400);
        }

        if (in_array($event['type'], ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && $event['paid'] && $event['session_id'] !== null) {
            $purchase = Purchase::query()->where('stripe_session_id', $event['session_id'])->first();

            if ($purchase !== null) {
                $this->payments->fulfil($purchase);
            }
        }

        return response('ok');
    }

    /** Payments must be set up, and the buyer must waive the withdrawal right for immediate delivery. */
    private function guard(Request $request): void
    {
        if (! $this->gateway->configured()) {
            throw ValidationException::withMessages(['payment' => 'Payments aren\'t set up on this server.']);
        }

        $request->validate(['waive_withdrawal' => ['accepted']], [
            'waive_withdrawal.accepted' => 'Please confirm you want it now and accept losing the 14-day right of withdrawal.',
        ]);
    }
}
