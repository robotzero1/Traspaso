<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;
use App\Simulation\Data\Concerns\Immutable;
use InvalidArgumentException;

/**
 * What the player chooses for the coming month.
 */
final readonly class Decisions
{
    use Immutable;

    /**
     * @param  list<DayPart>  $openDayParts
     */
    public function __construct(
        /** Prices relative to the local average: 1.0 is average, 1.5 is 50% above. */
        public float $priceLevel,
        /** Which parts of the day the business opens for. Gaps are allowed (a split shift). */
        public array $openDayParts,
        public int $openDaysPerWeek,
        public int $staffCount,
        public int $marketingSpendCents,
        public QualityTier $qualityTier,
    ) {
        Guard::positive('priceLevel', $priceLevel);
        Guard::listOf('openDayParts', $openDayParts, DayPart::class);

        if ($openDayParts === []) {
            throw new InvalidArgumentException('openDayParts must contain at least one day part.');
        }

        if (count(array_unique(array_map(fn (DayPart $part) => $part->value, $openDayParts))) !== count($openDayParts)) {
            throw new InvalidArgumentException('openDayParts must not repeat a day part.');
        }
        Guard::between('openDaysPerWeek', $openDaysPerWeek, 1, 7);
        Guard::nonNegative('staffCount', $staffCount);
        Guard::nonNegative('marketingSpendCents', $marketingSpendCents);
    }

    public function isOpenFor(DayPart $part): bool
    {
        return in_array($part, $this->openDayParts, true);
    }
}
