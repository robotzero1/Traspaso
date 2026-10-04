<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;
use App\Simulation\Data\Concerns\Immutable;

/**
 * A snapshot of the player's business at the start (or end) of a month.
 * Scores run 0–100. Cash can go negative; the game decides what that means.
 */
final readonly class BusinessState
{
    use Immutable;

    public function __construct(
        public BusinessProfile $profile,
        public int $cashCents,
        public float $reputation,
        public int $staffCount,
        public float $staffMorale,
        public float $equipmentHealth,
        public int $equipmentAgeMonths,
        public float $stockQuality,
    ) {
        Guard::between('reputation', $reputation, 0, 100);
        Guard::nonNegative('staffCount', $staffCount);
        Guard::between('staffMorale', $staffMorale, 0, 100);
        Guard::between('equipmentHealth', $equipmentHealth, 0, 100);
        Guard::nonNegative('equipmentAgeMonths', $equipmentAgeMonths);
        Guard::between('stockQuality', $stockQuality, 0, 100);
    }
}
