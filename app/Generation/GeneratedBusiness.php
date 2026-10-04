<?php

namespace App\Generation;

use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\Concerns\Guard;

/**
 * A fictional business for sale, as drawn by the BusinessGenerator. It has
 * a location when the generator had real commercial points to place it on.
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
        public ?Location $location = null,
    ) {
        Guard::notBlank('name', $name);
        Guard::notBlank('streetType', $streetType);
        Guard::nonNegative('traspasoCents', $traspasoCents);
        Guard::nonNegative('equipmentAgeYears', $equipmentAgeYears);
        Guard::between('baseReputation', $baseReputation, 0, 100);
    }
}
