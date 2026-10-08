<?php

namespace App\Models;

use App\Enums\GameStatus;
use App\Simulation\Data\CalendarDate;
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
 * @property Carbon|null $started_on
 * @property Carbon|null $last_simulated_on
 * @property list<array{from: string, changes: array<string, mixed>}>|null $scheduled_decisions
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
 * @property Carbon|null $closes_on
 * @property array<string, int>|null $closure
 * @property-read Business|null $business
 * @property-read GameBusinessState|null $latestState
 */
#[Fillable([
    'user_id', 'market', 'seed', 'start_date', 'started_on', 'last_simulated_on', 'scheduled_decisions', 'starting_capital_cents', 'cash_cents', 'current_month',
    'status', 'business_id', 'deposit_cents', 'decisions', 'sold_for_cents', 'final_net_worth_cents', 'ended_at', 'closes_on', 'closure',
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

    /** @return HasMany<SaleListing, $this> */
    public function saleListings(): HasMany
    {
        return $this->hasMany(SaleListing::class)->orderBy('id');
    }

    /** The listing that is live now (listed, not withdrawn or sold), if any. */
    public function liveListing(): ?SaleListing
    {
        return $this->saleListings()->whereNull('withdrawn_on')->whereNull('completed_on')->reorder()->latest('id')->first();
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

    /** @return HasMany<DayResult, $this> */
    public function dayResults(): HasMany
    {
        return $this->hasMany(DayResult::class)->orderBy('date');
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

    /** All the game's months (game.months) played; waiting for the player to sell or keep. */
    public function isAwaitingEnd(): bool
    {
        $months = config("market.{$this->market}.game.months");

        return $months !== null && $this->isActive() && $this->business_id !== null && $this->current_month > $months;
    }

    /** Today on the game's clock (Europe/Madrid). */
    public static function today(): CalendarDate
    {
        return CalendarDate::parse(now(config('game.timezone'))->toDateString());
    }

    /** The next day to simulate. */
    public function nextDay(): CalendarDate
    {
        return CalendarDate::parse($this->last_simulated_on->toDateString())->addDays(1);
    }

    /** Calendar month (1–12) of a game month, counting from the start date. */
    public function calendarMonth(int $gameMonth): int
    {
        return ($this->start_date->month + $gameMonth - 2) % 12 + 1;
    }

    /** The first day of a game month. */
    public function firstDayOf(int $gameMonth): CalendarDate
    {
        return CalendarDate::parse($this->start_date->toDateString())->addMonths($gameMonth - 1);
    }

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'start_date' => 'date',
            'started_on' => 'date',
            'last_simulated_on' => 'date',
            'scheduled_decisions' => 'array',
            'starting_capital_cents' => 'integer',
            'cash_cents' => 'integer',
            'current_month' => 'integer',
            'status' => GameStatus::class,
            'deposit_cents' => 'integer',
            'decisions' => 'array',
            'sold_for_cents' => 'integer',
            'final_net_worth_cents' => 'integer',
            'ended_at' => 'datetime',
            'closes_on' => 'date',
            'closure' => 'array',
        ];
    }
}
