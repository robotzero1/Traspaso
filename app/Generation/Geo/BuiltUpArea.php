<?php

namespace App\Generation\Geo;

/**
 * The built-up part of each neighbourhood. District boundaries often take
 * in farmland, river banks and industrial estates (Zaragoza's Torrero-La Paz
 * reaches far into the countryside), so densities per km² of the whole
 * boundary understate how busy its streets are. This counts only the grid
 * cells with a city's worth of streets in them.
 *
 * Each cell goes to the neighbourhood that holds its centre; the error at
 * the edges is a fraction of a cell either way.
 */
final readonly class BuiltUpArea
{
    // Cells are square in metres at this latitude (Zaragoza's); a fixed
    // reference keeps them the same however the streets are ordered.
    private const float REFERENCE_LAT = 41.65;

    public function __construct(private float $cellMetres, private float $minStreetMetres) {}

    /**
     * @param  list<array{name: string, geometry: array<string, mixed>}>  $neighbourhoods
     * @param  list<array{nodes: list<array{0: int, 1: float, 2: float}>}>  $streets
     * @return list<array{area_km2: float, centre: array{0: float, 1: float}}|null> by neighbourhood index; null where nothing is built up
     */
    public function measure(array $neighbourhoods, array $streets): array
    {
        $lengths = $this->streetLengthByCell($streets);
        $polygons = array_map(fn (array $n) => new Polygon($n['geometry']), $neighbourhoods);
        $boxes = array_map(fn (Polygon $p) => $this->boundingBox($p), $polygons);
        $cells = array_fill(0, count($neighbourhoods), []);

        foreach ($lengths as $key => $metres) {
            if ($metres < $this->minStreetMetres) {
                continue;
            }

            [$lat, $lng] = $this->centreOf($key);

            foreach ($polygons as $i => $polygon) {
                [$south, $west, $north, $east] = $boxes[$i];

                if ($lat >= $south && $lat <= $north && $lng >= $west && $lng <= $east && $polygon->contains($lat, $lng)) {
                    $cells[$i][] = [$lat, $lng];
                    break;
                }
            }
        }

        $cellKm2 = ($this->cellMetres / 1000) ** 2;

        return array_map(fn (array $built) => $built === [] ? null : [
            'area_km2' => count($built) * $cellKm2,
            'centre' => [
                array_sum(array_column($built, 0)) / count($built),
                array_sum(array_column($built, 1)) / count($built),
            ],
        ], $cells);
    }

    /**
     * Metres of street per cell. Segments are cut into pieces of at most
     * half a cell, each counted in the cell of its midpoint, so a long
     * straight road spreads its length along the cells it crosses.
     *
     * @return array<string, float>
     */
    private function streetLengthByCell(array $streets): array
    {
        $lengths = [];

        foreach ($streets as $street) {
            for ($i = 1; $i < count($street['nodes']); $i++) {
                [, $aLat, $aLng] = $street['nodes'][$i - 1];
                [, $bLat, $bLng] = $street['nodes'][$i];
                $length = Geo::distanceMetres($aLat, $aLng, $bLat, $bLng);
                $pieces = max(1, (int) ceil($length / ($this->cellMetres / 2)));

                for ($k = 0; $k < $pieces; $k++) {
                    $t = ($k + 0.5) / $pieces;
                    $key = $this->keyOf($aLat + ($bLat - $aLat) * $t, $aLng + ($bLng - $aLng) * $t);
                    $lengths[$key] = ($lengths[$key] ?? 0.0) + $length / $pieces;
                }
            }
        }

        return $lengths;
    }

    private function degLat(): float
    {
        return $this->cellMetres / 111_195.0;
    }

    private function degLng(): float
    {
        return $this->cellMetres / (111_195.0 * cos(deg2rad(self::REFERENCE_LAT)));
    }

    private function keyOf(float $lat, float $lng): string
    {
        return (int) floor($lat / $this->degLat()).':'.(int) floor($lng / $this->degLng());
    }

    /** @return array{0: float, 1: float} */
    private function centreOf(string $key): array
    {
        [$y, $x] = array_map('intval', explode(':', $key));

        return [($y + 0.5) * $this->degLat(), ($x + 0.5) * $this->degLng()];
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} south, west, north, east */
    private function boundingBox(Polygon $polygon): array
    {
        $lats = $lngs = [];

        foreach ($polygon->polygons as $rings) {
            foreach ($rings[0] as [$lng, $lat]) {
                $lats[] = $lat;
                $lngs[] = $lng;
            }
        }

        return [min($lats), min($lngs), max($lats), max($lngs)];
    }
}
