<?php

namespace App\Payments;

use App\Models\Purchase;
use App\Models\User;
use App\Models\ViabilityReport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Buying (SPEC §11): opens a Stripe Checkout session for a product and
 * fulfils the purchase once Stripe says it's paid, whichever arrives
 * first, the webhook or the buyer coming back. Fulfilling twice does
 * nothing more.
 */
final class Payments
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /** @return string the Checkout page to send the buyer to */
    public function checkout(string $product, string $returnUrl, string $cancelUrl, ?User $user = null, ?ViabilityReport $report = null): string
    {
        $item = config("payments.products.{$product}") ?? throw new InvalidArgumentException("Unknown product [{$product}].");
        $purchase = Purchase::query()->create([
            'user_id' => $user?->id,
            'viability_report_id' => $report?->id,
            'product' => $product,
            'amount_cents' => $item['price_cents'],
            'currency' => config('payments.currency'),
            'withdrawal_waived_at' => now(),
        ]);

        $taxRate = config('payments.stripe.tax_rate_id');
        $session = $this->gateway->checkout(array_filter([
            'mode' => 'payment',
            'line_items' => [array_filter([
                'quantity' => 1,
                'price_data' => [
                    'currency' => config('payments.currency'),
                    'unit_amount' => $item['price_cents'],
                    'product_data' => ['name' => $item['name']],
                ],
                'tax_rates' => $taxRate ? [$taxRate] : null,
            ])],
            // A receipt by email, and an invoice showing the IVA.
            'invoice_creation' => ['enabled' => true],
            'customer_email' => $user?->email,
            'client_reference_id' => (string) $purchase->id,
            'metadata' => ['purchase_id' => (string) $purchase->id],
            'success_url' => $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ], fn ($v) => $v !== null));

        $purchase->update(['stripe_session_id' => $session['id']]);

        return $session['url'];
    }

    /** Asks Stripe about the session and fulfils it if it's paid. */
    public function confirm(string $sessionId): ?Purchase
    {
        $purchase = Purchase::query()->where('stripe_session_id', $sessionId)->first();

        if ($purchase !== null && $purchase->status !== 'paid' && $this->gateway->session($sessionId)['paid']) {
            $this->fulfil($purchase);
        }

        return $purchase?->refresh();
    }

    public function fulfil(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($locked->status === 'paid') {
                return;
            }

            $locked->update(['status' => 'paid', 'paid_at' => now()]);

            if ($locked->product === 'viability_report') {
                $locked->viabilityReport?->update(['paid_at' => now()]);
            }
        });
    }

    /** The most starting capital this account may choose: free, or the best tier it bought. */
    public static function maxCapitalCents(?User $user): int
    {
        $free = (int) config('payments.free_capital_cents');

        if ($user === null) {
            return $free;
        }

        $tiers = collect(config('payments.products'))->filter(fn (array $p) => isset($p['capital_cents']));
        $bought = $user->purchases()->where('status', 'paid')->whereIn('product', $tiers->keys())->pluck('product');

        return max([$free, ...$bought->map(fn (string $p) => $tiers[$p]['capital_cents'])->all()]);
    }
}
