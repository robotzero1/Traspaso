<?php

namespace App\Generation\Geo;

/**
 * Small-scale geodesy on a spherical Earth. Good to well under a metre
 * across a city, which is all the game needs.
 */
final class Geo
{
    private const EARTH_RADIUS_M = 6_371_000;

    /** Great-circle (haversine) distance in metres. */
    public static function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /**
     * The point $distance metres from (lat, lng) on the given bearing
     * (degrees clockwise from north).
     *
     * @return array{0: float, 1: float} [lat, lng]
     */
    public static function offset(float $lat, float $lng, float $distanceMetres, float $bearingDegrees): array
    {
        $angular = $distanceMetres / self::EARTH_RADIUS_M;
        $bearing = deg2rad($bearingDegrees);
        $lat1 = deg2rad($lat);
        $lng1 = deg2rad($lng);

        $lat2 = asin(sin($lat1) * cos($angular) + cos($lat1) * sin($angular) * cos($bearing));
        $lng2 = $lng1 + atan2(sin($bearing) * sin($angular) * cos($lat1), cos($angular) - sin($lat1) * sin($lat2));

        return [rad2deg($lat2), rad2deg($lng2)];
    }
}
