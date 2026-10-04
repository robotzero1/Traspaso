<?php

namespace App\Generation\Geo;

use InvalidArgumentException;

/**
 * A GeoJSON Polygon or MultiPolygon: point-in-polygon tests (holes
 * respected), area and centroid. Coordinates are [lng, lat] as in GeoJSON.
 */
final readonly class Polygon
{
    /** @var list<list<list<array{0: float, 1: float}>>> polygons → rings → [lng, lat] */
    public array $polygons;

    /** @param array<string, mixed> $geometry a GeoJSON geometry */
    public function __construct(array $geometry)
    {
        $this->polygons = match ($geometry['type'] ?? null) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => throw new InvalidArgumentException('Expected a GeoJSON Polygon or MultiPolygon.'),
        };
    }

    public function contains(float $lat, float $lng): bool
    {
        foreach ($this->polygons as $rings) {
            if (! $this->inRing($lat, $lng, $rings[0])) {
                continue;
            }

            $inHole = false;

            foreach (array_slice($rings, 1) as $hole) {
                $inHole = $inHole || $this->inRing($lat, $lng, $hole);
            }

            if (! $inHole) {
                return true;
            }
        }

        return false;
    }

    /** Area in km² (equirectangular around the polygon, fine at city scale). */
    public function areaKm2(): float
    {
        [$lat0] = $this->centroid();
        $kx = 111.195 * cos(deg2rad($lat0));
        $ky = 111.195;
        $area = 0.0;

        foreach ($this->polygons as $rings) {
            foreach ($rings as $i => $ring) {
                $ringArea = abs($this->shoelace($ring, $kx, $ky));
                $area += $i === 0 ? $ringArea : -$ringArea;
            }
        }

        return $area;
    }

    /**
     * The mean of the outer rings' vertices; good enough to label a district.
     *
     * @return array{0: float, 1: float} [lat, lng]
     */
    public function centroid(): array
    {
        $lat = $lng = 0.0;
        $n = 0;

        foreach ($this->polygons as $rings) {
            foreach ($rings[0] as [$x, $y]) {
                $lng += $x;
                $lat += $y;
                $n++;
            }
        }

        return [$lat / max(1, $n), $lng / max(1, $n)];
    }

    /** @param list<array{0: float, 1: float}> $ring */
    private function inRing(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** @param list<array{0: float, 1: float}> $ring */
    private function shoelace(array $ring, float $kx, float $ky): float
    {
        $sum = 0.0;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $sum += ($ring[$j][0] * $kx) * ($ring[$i][1] * $ky) - ($ring[$i][0] * $kx) * ($ring[$j][1] * $ky);
        }

        return $sum / 2;
    }
}
