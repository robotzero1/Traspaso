<?php

namespace App\Generation;

/**
 * Another business in the neighbourhood that could become a rival.
 */
final readonly class CompetitorCandidate
{
    public function __construct(
        public string $id,
        public string $name,
        public int $seats,
        public int $condition,
        public float $reputation,
    ) {}
}
