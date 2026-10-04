<?php

namespace Tests\Support;

/**
 * A small synthetic city shaped like Overpass API responses, for testing
 * the geo pipeline without network access or real data.
 *
 * - A 9×9 grid of streets ~100 m apart around (41.650, −0.880).
 * - Row 4 is a primary avenue; column 2 is a pedestrian street; the rest
 *   are residential.
 * - Two districts: "West" (columns 0–4) and "East" (columns 4–8). West's
 *   boundary comes as two segments, one reversed, to exercise assembly.
 * - Shops and cafés line the avenue; offices cluster in the west; a
 *   university sits in the east; bus stops along the avenue; a tram stop
 *   at its middle.
 */
final class GeoFixtures
{
    public const LAT0 = 41.650;

    public const LNG0 = -0.880;

    public const DLAT = 0.0009;   // ~100 m

    public const DLNG = 0.0012;   // ~100 m at this latitude

    public static function lat(float $row): float
    {
        return round(self::LAT0 + $row * self::DLAT, 6);
    }

    public static function lng(float $col): float
    {
        return round(self::LNG0 + $col * self::DLNG, 6);
    }

    public static function nodeId(int $row, int $col): int
    {
        return 1000 + $row * 10 + $col;
    }

    /** @return array<string, mixed> */
    public static function streets(): array
    {
        $elements = [];

        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                $elements[] = ['type' => 'node', 'id' => self::nodeId($r, $c), 'lat' => self::lat($r), 'lon' => self::lng($c)];
            }
        }

        for ($r = 0; $r < 9; $r++) {
            $elements[] = [
                'type' => 'way', 'id' => 100 + $r,
                'nodes' => array_map(fn ($c) => self::nodeId($r, $c), range(0, 8)),
                'tags' => ['highway' => $r === 4 ? 'primary' : 'residential', 'name' => "Calle {$r}"],
            ];
        }

        for ($c = 0; $c < 9; $c++) {
            $elements[] = [
                'type' => 'way', 'id' => 200 + $c,
                'nodes' => array_map(fn ($r) => self::nodeId($r, $c), range(0, 8)),
                'tags' => ['highway' => $c === 2 ? 'pedestrian' : 'residential'],
            ];
        }

        // Not walkable for the game: ignored.
        $elements[] = ['type' => 'way', 'id' => 999, 'nodes' => [self::nodeId(0, 0), self::nodeId(8, 8)], 'tags' => ['highway' => 'motorway']];

        return ['elements' => $elements];
    }

    /** @return array<string, mixed> */
    public static function pointsOfInterest(): array
    {
        $elements = [];
        $id = 5000;
        $node = function (string $key, string $value, float $row, float $col, ?string $name = null) use (&$elements, &$id) {
            $elements[] = ['type' => 'node', 'id' => $id++, 'lat' => self::lat($row), 'lon' => self::lng($col), 'tags' => array_filter([$key => $value, 'name' => $name])];
        };

        // Shops and cafés along the avenue, a few elsewhere.
        foreach (range(0, 8) as $c) {
            $node('shop', 'clothes', 4.05, $c + 0.3);
            $node('amenity', $c % 2 ? 'cafe' : 'bar', 3.95, $c + 0.6, "Bar {$c}");
        }

        $node('shop', 'bakery', 7.05, 6.5);
        $node('amenity', 'cafe', 1.05, 1.5);

        // Offices in the west.
        foreach ([[1, 1], [1.2, 1.3], [2, 1], [2.2, 3], [3, 0.5], [2.8, 2.2]] as [$r, $c]) {
            $node('office', 'company', $r, $c);
        }

        // A university in the east, as a way with a centre.
        $elements[] = ['type' => 'way', 'id' => 7000, 'center' => ['lat' => self::lat(6), 'lon' => self::lng(7)], 'tags' => ['amenity' => 'university', 'name' => 'Campus Este']];

        // Transport along the avenue.
        foreach ([1, 3, 5, 7] as $c) {
            $node('highway', 'bus_stop', 4.1, $c);
        }

        $node('railway', 'tram_stop', 4.1, 4, 'Plaza');
        $node('tourism', 'museum', 5, 3, 'Museo');
        $node('leisure', 'park', 7, 1, 'Parque');
        // No matching tags: ignored.
        $node('natural', 'tree', 2, 2);

        return ['elements' => $elements];
    }

    /** @return array<string, mixed> */
    public static function boundaries(): array
    {
        $point = fn (float $r, float $c) => ['lat' => self::lat($r), 'lon' => self::lng($c)];

        return ['elements' => [
            [
                'type' => 'relation', 'id' => 1, 'tags' => ['name' => 'West', 'admin_level' => '9', 'boundary' => 'administrative'],
                'members' => [
                    ['type' => 'way', 'role' => 'outer', 'geometry' => [$point(-0.5, -0.5), $point(-0.5, 4.5), $point(8.5, 4.5)]],
                    // Reversed: must be flipped to join.
                    ['type' => 'way', 'role' => 'outer', 'geometry' => [$point(-0.5, -0.5), $point(8.5, -0.5), $point(8.5, 4.5)]],
                ],
            ],
            [
                'type' => 'relation', 'id' => 2, 'tags' => ['name' => 'East', 'admin_level' => '9', 'boundary' => 'administrative'],
                'members' => [
                    ['type' => 'way', 'role' => 'outer', 'geometry' => [
                        $point(-0.5, 4.5), $point(-0.5, 8.5), $point(8.5, 8.5), $point(8.5, 4.5), $point(-0.5, 4.5),
                    ]],
                ],
            ],
        ]];
    }

    /** @return array<string, int> */
    public static function population(): array
    {
        return ['West' => 30_000, 'East' => 10_000];
    }
}
