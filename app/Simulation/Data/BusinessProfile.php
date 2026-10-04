<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;
use App\Simulation\Data\Concerns\Immutable;

/**
 * The fixed characteristics of a business: the premises, its licence and
 * its location. These don't change from month to month.
 */
final readonly class BusinessProfile
{
    use Immutable;

    public function __construct(
        public BusinessCategory $category,
        public Licence $licence,
        public Kitchen $kitchen,
        public NeighbourhoodProfile $neighbourhood,
        public int $floorAreaM2,
        public int $indoorSeats,
        public int $terraceSeats,
        public int $rentMonthCents,
        /** 0–10, derived from the neighbourhood indices and street type. */
        public float $footfall,
        /** 1–10, the state of the premises when the game starts. */
        public int $condition,
    ) {
        Guard::positive('floorAreaM2', $floorAreaM2);
        Guard::nonNegative('indoorSeats', $indoorSeats);
        Guard::nonNegative('terraceSeats', $terraceSeats);
        Guard::nonNegative('rentMonthCents', $rentMonthCents);
        Guard::between('footfall', $footfall, 0, 10);
        Guard::between('condition', $condition, 1, 10);
    }

    public function totalSeats(): int
    {
        return $this->indoorSeats + $this->terraceSeats;
    }
}
