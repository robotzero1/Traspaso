<?php

namespace App\Generation;

/**
 * Another business that could become a rival. $distanceMetres is its
 * distance from the player's business when locations are known.
 */
final readonly class CompetitorCandidate
{
    public function __construct(
        public string $id,
        public string $name,
        public int $seats,
        public int $condition,
        public float $reputation,
        public ?float $distanceMetres = null,
    ) {}
}
