<?php

namespace App\Generation\Geo;

use App\Simulation\Rng\SeededRng;

/**
 * Places a business at a uniformly random point inside a circle. Until
 * real commercial streets arrive (milestone 8), a neighbourhood is its
 * centre and radius.
 */
final class LocationPlacer
{
    /**
     * @return array{0: float, 1: float} [lat, lng], rounded to ~10 cm
     */
    public static function inCircle(float $lat, float $lng, float $radiusMetres, SeededRng $rng): array
    {
        // √u keeps the density uniform over the disc instead of bunching at the centre.
        $distance = $radiusMetres * sqrt($rng->float());
        [$pLat, $pLng] = Geo::offset($lat, $lng, $distance, $rng->floatBetween(0.0, 360.0));

        return [round($pLat, 6), round($pLng, 6)];
    }
}
