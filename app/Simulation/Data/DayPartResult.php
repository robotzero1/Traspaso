<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;

/**
 * How one day part went in a month, so the UI can show which hours pay.
 */
final readonly class DayPartResult
{
    public function __construct(
        public DayPart $dayPart,
        /** People who might have come in. */
        public int $potentialCustomers,
        /** Customers who wanted to come in, before capacity limits. */
        public int $demand,
        /** Customers the seats and staff could have served. */
        public int $capacity,
        public int $covers,
        public int $revenueCents,
    ) {
        foreach (['potentialCustomers', 'demand', 'capacity', 'covers', 'revenueCents'] as $field) {
            Guard::nonNegative($field, $this->{$field});
        }
    }

    /** Customers turned away because the place was full or short-staffed. */
    public function lostCovers(): int
    {
        return max(0, $this->demand - $this->covers);
    }
}
