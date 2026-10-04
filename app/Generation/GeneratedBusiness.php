<?php

namespace App\Generation;

use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\Concerns\Guard;

/**
 * A fictional business for sale, as drawn by the BusinessGenerator.
 * Persistence (and a map location on a real street) comes later.
 */
final readonly class GeneratedBusiness
{
    public function __construct(
        public string $name,
        public string $streetType,
        public BusinessProfile $profile,
        public int $traspasoCents,
        public int $equipmentAgeYears,
        /** 0–100. */
        public float $baseReputation,
    ) {
        Guard::notBlank('name', $name);
        Guard::notBlank('streetType', $streetType);
        Guard::nonNegative('traspasoCents', $traspasoCents);
        Guard::nonNegative('equipmentAgeYears', $equipmentAgeYears);
        Guard::between('baseReputation', $baseReputation, 0, 100);
    }
}
