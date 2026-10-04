<?php

namespace App\Generation;

/**
 * Where a business stands on the map, and the footfall there.
 */
final readonly class Location
{
    /**
     * @param  array<string, float>  $footfallByDayPart  day part → footfall 0–10
     */
    public function __construct(
        public float $lat,
        public float $lng,
        public float $footfall,
        public array $footfallByDayPart,
        public string $streetType,
        public ?int $pointId = null,
    ) {}
}
