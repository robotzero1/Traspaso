<?php

namespace App\Generation\Geo;

/**
 * Reads Overpass API JSON into the shapes the pipeline uses:
 *
 * - streets: ['id', 'highway', 'nodes' => list<[id, lat, lng]>]
 * - points of interest: ['type', 'name', 'lat', 'lng', 'osm_id', 'competitor']
 * - boundaries: ['name', 'geometry' => GeoJSON MultiPolygon]
 */
final class OsmExtract
{
    /**
     * @param  array<string, mixed>  $overpass  response to `way[highway~…]; out body; >; out skel qt;`
     * @param  list<string>  $highwayTypes
     * @return list<array{id: int, highway: string, nodes: list<array{0: int, 1: float, 2: float}>}>
     */
    public static function streets(array $overpass, array $highwayTypes): array
    {
        $nodes = [];

        foreach ($overpass['elements'] ?? [] as $element) {
            if ($element['type'] === 'node') {
                $nodes[$element['id']] = [(float) $element['lat'], (float) $element['lon']];
            }
        }

        $streets = [];

        foreach ($overpass['elements'] ?? [] as $element) {
            $highway = $element['tags']['highway'] ?? null;

            if ($element['type'] !== 'way' || ! in_array($highway, $highwayTypes, true)) {
                continue;
            }

            $wayNodes = [];

            foreach ($element['nodes'] ?? [] as $id) {
                if (isset($nodes[$id])) {
                    $wayNodes[] = [$id, $nodes[$id][0], $nodes[$id][1]];
                }
            }

            if (count($wayNodes) >= 2) {
                $streets[] = ['id' => $element['id'], 'highway' => $highway, 'nodes' => $wayNodes];
            }
        }

        return $streets;
    }

    /**
     * @param  array<string, mixed>  $overpass  response with `out center tags`
     * @param  list<array{type: string, tags: array<string, list<string>|string>}>  $types  first match wins
     * @param  array<string, list<string>>  $competitorTags
     * @return list<array{type: string, name: string, lat: float, lng: float, osm_id: string, competitor: bool}>
     */
    public static function pointsOfInterest(array $overpass, array $types, array $competitorTags): array
    {
        $points = [];

        foreach ($overpass['elements'] ?? [] as $element) {
            $tags = $element['tags'] ?? [];
            $lat = $element['lat'] ?? $element['center']['lat'] ?? null;
            $lng = $element['lon'] ?? $element['center']['lon'] ?? null;
            $type = self::classify($tags, $types);

            if ($type === null || $lat === null || $lng === null) {
                continue;
            }

            $points[] = [
                'type' => $type,
                'name' => (string) ($tags['name'] ?? ''),
                'lat' => round((float) $lat, 6),
                'lng' => round((float) $lng, 6),
                'osm_id' => "{$element['type']}/{$element['id']}",
                'competitor' => self::matches($tags, $competitorTags),
            ];
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $overpass  response to `rel[boundary=administrative]…; out geom;`
     * @return list<array{name: string, geometry: array<string, mixed>}>
     */
    public static function boundaries(array $overpass): array
    {
        $boundaries = [];

        foreach ($overpass['elements'] ?? [] as $element) {
            if ($element['type'] !== 'relation' || ! isset($element['tags']['name'])) {
                continue;
            }

            $outer = $inner = [];

            foreach ($element['members'] ?? [] as $member) {
                if ($member['type'] !== 'way' || empty($member['geometry'])) {
                    continue;
                }

                $segment = array_map(fn (array $p) => [(float) $p['lon'], (float) $p['lat']], $member['geometry']);
                ($member['role'] ?? 'outer') === 'inner' ? $inner[] = $segment : $outer[] = $segment;
            }

            $polygons = array_map(fn (array $ring) => [$ring], RingAssembler::assemble($outer));

            foreach (RingAssembler::assemble($inner) as $hole) {
                foreach ($polygons as $i => $rings) {
                    if ((new Polygon(['type' => 'Polygon', 'coordinates' => [$rings[0]]]))->contains($hole[0][1], $hole[0][0])) {
                        $polygons[$i][] = $hole;
                        break;
                    }
                }
            }

            if ($polygons !== []) {
                $boundaries[] = ['name' => $element['tags']['name'], 'geometry' => ['type' => 'MultiPolygon', 'coordinates' => $polygons]];
            }
        }

        return $boundaries;
    }

    /**
     * @param  array<string, string>  $tags
     * @param  list<array{type: string, tags: array<string, list<string>|string>}>  $types
     */
    private static function classify(array $tags, array $types): ?string
    {
        foreach ($types as $definition) {
            if (self::matches($tags, $definition['tags'])) {
                return $definition['type'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $tags
     * @param  array<string, list<string>|string>  $rules  key => values, or '*' for any value
     */
    private static function matches(array $tags, array $rules): bool
    {
        foreach ($rules as $key => $values) {
            if (isset($tags[$key]) && ($values === '*' || in_array($tags[$key], (array) $values, true))) {
                return true;
            }
        }

        return false;
    }
}
