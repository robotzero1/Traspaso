<?php

namespace App\Models;

use App\Enums\GameStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $market
 * @property int $seed
 * @property Carbon $start_date
 * @property int $starting_capital_cents
 * @property int $cash_cents
 * @property int $current_month
 * @property GameStatus $status
 * @property int|null $business_id
 * @property int $deposit_cents
 * @property array<string, mixed>|null $decisions
 * @property int|null $sold_for_cents
 * @property int|null $final_net_worth_cents
 * @property Carbon|null $ended_at
 * @property-read Business|null $business
 * @property-read GameBusinessState|null $latestState
 */
#[Fillable([
    'user_id', 'market', 'seed', 'start_date', 'starting_capital_cents', 'cash_cents', 'current_month',
    'status', 'business_id', 'deposit_cents', 'decisions', 'sold_for_cents', 'final_net_worth_cents', 'ended_at',
])]
class Game extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return HasMany<Business, $this> */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    /** @return HasMany<GameBusinessState, $this> */
    public function states(): HasMany
    {
        return $this->hasMany(GameBusinessState::class);
    }

    /** @return HasOne<GameBusinessState, $this> */
    public function latestState(): HasOne
    {
        return $this->hasOne(GameBusinessState::class)->ofMany('month', 'max');
    }

    /** @return HasMany<MonthResult, $this> */
    public function monthResults(): HasMany
    {
        return $this->hasMany(MonthResult::class)->orderBy('month');
    }

    /** @return HasMany<GameEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class)->orderBy('month');
    }

    /** @return HasMany<GameCompetitor, $this> */
    public function competitors(): HasMany
    {
        return $this->hasMany(GameCompetitor::class)->orderBy('distance_metres');
    }

    public function isActive(): bool
    {
        return $this->status === GameStatus::Active;
    }

    /** All twelve months played; waiting for the player to sell or keep. */
    public function isAwaitingEnd(): bool
    {
        return $this->isActive() && $this->business_id !== null && $this->current_month > config("market.{$this->market}.game.months");
    }

    /** Calendar month (1–12) of a game month, counting from the start date. */
    public function calendarMonth(int $gameMonth): int
    {
        return ($this->start_date->month + $gameMonth - 2) % 12 + 1;
    }

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'start_date' => 'date',
            'starting_capital_cents' => 'integer',
            'cash_cents' => 'integer',
            'current_month' => 'integer',
            'status' => GameStatus::class,
            'deposit_cents' => 'integer',
            'decisions' => 'array',
            'sold_for_cents' => 'integer',
            'final_net_worth_cents' => 'integer',
            'ended_at' => 'datetime',
        ];
    }
}
