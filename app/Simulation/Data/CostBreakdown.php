<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;

/**
 * One month's costs, in cents.
 */
final readonly class CostBreakdown
{
    public function __construct(
        public int $cogsCents,
        public int $staffCents,
        public int $rentCents,
        public int $utilitiesCents,
        public int $marketingCents,
        /** Insurance, maintenance, cuota de autónomo and anything else. */
        public int $otherCents,
        public int $taxesCents,
    ) {
        foreach (get_object_vars($this) as $field => $cents) {
            Guard::nonNegative($field, $cents);
        }
    }

    public function totalCents(): int
    {
        return $this->cogsCents
            + $this->staffCents
            + $this->rentCents
            + $this->utilitiesCents
            + $this->marketingCents
            + $this->otherCents
            + $this->taxesCents;
    }
}
