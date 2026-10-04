<?php

namespace App\Simulation\Demand;

/**
 * What the time of year means for one month of trading.
 */
final readonly class SeasonalFactors
{
    public function __construct(
        public float $demandMultiplier,
        public int $daysInMonth,
        public int $openDays,
        /** Share of open days the terrace can be used, 0–1. */
        public float $terraceUsableShare,
    ) {}
}
