<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;
use App\Simulation\Data\Concerns\Immutable;

/**
 * What the engine knows about a neighbourhood. Indices run 0–10;
 * competition density is cafés and bars per km².
 */
final readonly class NeighbourhoodProfile
{
    use Immutable;

    public function __construct(
        public string $name,
        public int $population,
        public float $studentIndex,
        public float $touristIndex,
        public float $officeIndex,
        public float $transportIndex,
        public float $competitionDensity,
    ) {
        Guard::notBlank('name', $name);
        Guard::nonNegative('population', $population);
        Guard::between('studentIndex', $studentIndex, 0, 10);
        Guard::between('touristIndex', $touristIndex, 0, 10);
        Guard::between('officeIndex', $officeIndex, 0, 10);
        Guard::between('transportIndex', $transportIndex, 0, 10);
        Guard::nonNegative('competitionDensity', $competitionDensity);
    }
}
