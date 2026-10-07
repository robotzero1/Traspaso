<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;

/**
 * Everything outside the business that the engine needs for one month:
 * the date, nearby competitors and the market parameter sheet. The
 * parameters come from config/market/*.php, passed in by the caller,
 * because the engine can't read config itself.
 */
final readonly class MarketContext
{
    /**
     * @param  list<CompetitorState>  $competitors
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        /** Calendar month, 1 = January. Drives seasonality. */
        public int $calendarMonth,
        /** Months since the business was bought, from 1. Games may run for years. */
        public int $gameMonth,
        public array $competitors,
        public array $parameters,
    ) {
        Guard::between('calendarMonth', $calendarMonth, 1, 12);
        Guard::positive('gameMonth', $gameMonth);
        Guard::listOf('competitors', $competitors, CompetitorState::class);
    }

    /**
     * Look up a parameter by dot-notation key, e.g. "rent.median".
     * A missing key is a bug in the parameter sheet, so it throws.
     */
    public function parameter(string $key): mixed
    {
        return (new ParameterSheet($this->parameters))->get($key);
    }
}
