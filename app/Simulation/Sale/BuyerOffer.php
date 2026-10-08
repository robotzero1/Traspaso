<?php

namespace App\Simulation\Sale;

/** An offer from one buyer, and the most that buyer would pay (hidden from the seller). */
final readonly class BuyerOffer
{
    public function __construct(
        public int $amountCents,
        public int $limitCents,
    ) {}
}
