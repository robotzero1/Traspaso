<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A month's P&L as stored. (The engine's App\Simulation\Data\MonthResult
 * is mapped onto this.)
 *
 * @property int $id
 * @property int $game_id
 * @property int $month
 * @property int $calendar_month
 * @property int $customers
 * @property int $revenue_cents
 * @property int $event_revenue_cents
 * @property int $cogs_cents
 * @property int $staff_cents
 * @property int $rent_cents
 * @property int $utilities_cents
 * @property int $marketing_cents
 * @property int $other_cents
 * @property int $taxes_cents
 * @property int $profit_cents
 * @property int $owner_pay_cents
 * @property int $cash_after_cents
 * @property list<array<string, mixed>> $day_parts
 * @property list<array<string, mixed>> $events
 */
#[Fillable([
    'game_id', 'month', 'calendar_month', 'customers', 'revenue_cents', 'event_revenue_cents', 'cogs_cents',
    'staff_cents', 'rent_cents', 'utilities_cents', 'marketing_cents', 'other_cents', 'taxes_cents',
    'profit_cents', 'owner_pay_cents', 'cash_after_cents', 'day_parts', 'events',
])]
class MonthResult extends Model
{
    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'calendar_month' => 'integer',
            'customers' => 'integer',
            'revenue_cents' => 'integer',
            'event_revenue_cents' => 'integer',
            'cogs_cents' => 'integer',
            'staff_cents' => 'integer',
            'rent_cents' => 'integer',
            'utilities_cents' => 'integer',
            'marketing_cents' => 'integer',
            'other_cents' => 'integer',
            'taxes_cents' => 'integer',
            'profit_cents' => 'integer',
            'owner_pay_cents' => 'integer',
            'cash_after_cents' => 'integer',
            'day_parts' => 'array',
            'events' => 'array',
        ];
    }
}
