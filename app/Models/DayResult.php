<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A day's trading as stored. (The engine's App\Simulation\Data\DayResult
 * is mapped onto this.)
 *
 * @property int $id
 * @property int $game_id
 * @property Carbon $date
 * @property int $month
 * @property bool $open
 * @property string $weather
 * @property bool $terrace_usable
 * @property int $customers
 * @property int $revenue_cents
 * @property int $event_revenue_cents
 * @property int $cogs_cents
 * @property int $event_cost_cents
 * @property int $cash_after_cents
 * @property list<array<string, mixed>> $day_parts
 * @property list<string> $events
 */
#[Fillable([
    'game_id', 'date', 'month', 'open', 'weather', 'terrace_usable', 'customers', 'revenue_cents', 'event_revenue_cents',
    'cogs_cents', 'event_cost_cents', 'cash_after_cents', 'day_parts', 'events',
])]
class DayResult extends Model
{
    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'month' => 'integer',
            'open' => 'boolean',
            'terrace_usable' => 'boolean',
            'customers' => 'integer',
            'revenue_cents' => 'integer',
            'event_revenue_cents' => 'integer',
            'cogs_cents' => 'integer',
            'event_cost_cents' => 'integer',
            'cash_after_cents' => 'integer',
            'day_parts' => 'array',
            'events' => 'array',
        ];
    }
}
