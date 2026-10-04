<?php

namespace App\Generation\Geo;

/**
 * Derives each neighbourhood's indices from the points of interest inside
 * it: a weighted density per km² for each index, ranked across the city
 * onto 0–10; and competition density as cafés and bars per km².
 */
final class NeighbourhoodIndexer
{
    /**
     * @param  list<array{name: string, geometry: array<string, mixed>, population: int}>  $neighbourhoods
     * @param  list<array{type: string, lat: float, lng: float, competitor: bool}>  $pois
     * @param  array<string, array<string, float>|string>  $indexWeights  index → POI type → weight (other keys, like a source note, are ignored)
     * @return list<array<string, mixed>> one row per neighbourhood
     */
    public static function index(array $neighbourhoods, array $pois, array $indexWeights): array
    {
        $indexWeights = array_filter($indexWeights, 'is_array');

        $rows = [];
        $densities = [];

        foreach ($neighbourhoods as $i => $n) {
            $polygon = new Polygon($n['geometry']);
            $area = max(0.01, $polygon->areaKm2());
            $counts = [];
            $competitors = 0;

            foreach ($pois as $poi) {
                if ($polygon->contains($poi['lat'], $poi['lng'])) {
                    $counts[$poi['type']] = ($counts[$poi['type']] ?? 0) + 1;
                    $competitors += $poi['competitor'] ? 1 : 0;
                }
            }

            foreach ($indexWeights as $index => $weights) {
                $weighted = 0.0;

                foreach ($weights as $type => $weight) {
                    $weighted += $weight * ($counts[$type] ?? 0);
                }

                $densities[$index][$i] = $weighted / $area;
            }

            [$lat, $lng] = $polygon->centroid();
            $rows[$i] = [
                'name' => $n['name'],
                'population' => $n['population'],
                'area_km2' => round($area, 3),
                'competition_density' => round($competitors / $area, 1),
                'centre' => [round($lat, 6), round($lng, 6)],
                // For the map's fallback circle: the radius of a circle with the same area.
                'radius_m' => (int) round(sqrt($area / M_PI) * 1000),
                'poi_counts' => $counts,
            ];
        }

        foreach ($densities as $index => $values) {
            foreach (Ranking::percentiles($values) as $i => $rank) {
                $rows[$i][$index] = round($rank * 10, 1);
            }
        }

        return array_values($rows);
    }
}
