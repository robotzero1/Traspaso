<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something bought through Stripe Checkout: pending until Stripe confirms
 * the payment, then paid.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $viability_report_id
 * @property string $product
 * @property int $amount_cents
 * @property string $currency
 * @property string $status pending or paid
 * @property string|null $stripe_session_id
 * @property Carbon $withdrawal_waived_at
 * @property Carbon|null $paid_at
 */
#[Fillable(['user_id', 'viability_report_id', 'product', 'amount_cents', 'currency', 'status', 'stripe_session_id', 'withdrawal_waived_at', 'paid_at'])]
class Purchase extends Model
{
    use Prunable;

    /** Checkouts never paid for go after ops.keep_pending_purchases_days (paid ones stay: tax law). */
    public function prunable(): Builder
    {
        return static::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subDays(config('ops.keep_pending_purchases_days')));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ViabilityReport, $this> */
    public function viabilityReport(): BelongsTo
    {
        return $this->belongsTo(ViabilityReport::class);
    }

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'withdrawal_waived_at' => 'datetime', 'paid_at' => 'datetime'];
    }
}
