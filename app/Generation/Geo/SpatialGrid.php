<?php

namespace App\Generation\Geo;

/**
 * Buckets points into a grid of roughly square cells so radius queries
 * only look at nearby cells. Uses an equirectangular projection around
 * the first point, which is accurate to well under 1% across a city.
 *
 * @template T
 */
final class SpatialGrid
{
    /** @var array<string, list<array{lat: float, lng: float, item: T}>> */
    private array $cells = [];

    private float $metresPerDegLat = 111_195.0;

    private ?float $metresPerDegLng = null;

    public function __construct(private readonly float $cellMetres = 200.0) {}

    /** @param T $item */
    public function add(float $lat, float $lng, mixed $item): void
    {
        $this->metresPerDegLng ??= 111_195.0 * cos(deg2rad($lat));
        $this->cells[$this->key($lat, $lng)][] = ['lat' => $lat, 'lng' => $lng, 'item' => $item];
    }

    /**
     * Items within $radius metres, with their distances.
     *
     * @return list<array{item: T, distance: float}>
     */
    public function within(float $lat, float $lng, float $radiusMetres): array
    {
        if ($this->metresPerDegLng === null) {
            return [];
        }

        [$cx, $cy] = $this->cell($lat, $lng);
        $reach = (int) ceil($radiusMetres / $this->cellMetres);
        $found = [];

        for ($x = $cx - $reach; $x <= $cx + $reach; $x++) {
            for ($y = $cy - $reach; $y <= $cy + $reach; $y++) {
                foreach ($this->cells["{$x}:{$y}"] ?? [] as $entry) {
                    $distance = $this->distance($lat, $lng, $entry['lat'], $entry['lng']);

                    if ($distance <= $radiusMetres) {
                        $found[] = ['item' => $entry['item'], 'distance' => $distance];
                    }
                }
            }
        }

        return $found;
    }

    /** Planar distance in metres; fine at city scale. */
    public function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dy = ($lat2 - $lat1) * $this->metresPerDegLat;
        $dx = ($lng2 - $lng1) * ($this->metresPerDegLng ?? 111_195.0 * cos(deg2rad($lat1)));

        return sqrt($dx * $dx + $dy * $dy);
    }

    private function key(float $lat, float $lng): string
    {
        [$x, $y] = $this->cell($lat, $lng);

        return "{$x}:{$y}";
    }

    /** @return array{0: int, 1: int} */
    private function cell(float $lat, float $lng): array
    {
        return [
            (int) floor($lng * $this->metresPerDegLng / $this->cellMetres),
            (int) floor($lat * $this->metresPerDegLat / $this->cellMetres),
        ];
    }
}
