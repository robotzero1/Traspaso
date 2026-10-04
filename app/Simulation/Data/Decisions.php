<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;
use App\Simulation\Data\Concerns\Immutable;

/**
 * What the player chooses for the coming month.
 */
final readonly class Decisions
{
    use Immutable;

    public function __construct(
        /** Prices relative to the local average: 1.0 is average, 1.5 is 50% above. */
        public float $priceLevel,
        public int $openingHoursPerDay,
        public int $openDaysPerWeek,
        public int $staffCount,
        public int $marketingSpendCents,
        public QualityTier $qualityTier,
    ) {
        Guard::positive('priceLevel', $priceLevel);
        Guard::between('openingHoursPerDay', $openingHoursPerDay, 1, 24);
        Guard::between('openDaysPerWeek', $openDaysPerWeek, 1, 7);
        Guard::nonNegative('staffCount', $staffCount);
        Guard::nonNegative('marketingSpendCents', $marketingSpendCents);
    }
}
