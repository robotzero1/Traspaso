<?php

namespace App\Generation\Geo;

use App\Generation\Distributions\PercentileDistribution;
use App\Simulation\Data\DayPart;

/**
 * Builds the footfall surface (SPEC §8): points sampled along the streets,
 * kept where there are shops or hospitality nearby, each scored on four
 * components — nearby points of interest (per day part, by their timing),
 * street-network centrality, the catchment of residents and workers, and
 * public transport — ranked, combined and mapped onto the 0–10 footfall
 * scale. Each day part combines the components with its own weights.
 */
final class FootfallSurfaceBuilder
{
    /**
     * @param  array<string, mixed>  $config  config('geo.footfall')
     * @param  array<string, string>  $streetTypes  highway → game street type
     */
    public function __construct(private readonly array $config, private readonly array $streetTypes) {}

    /**
     * @param  list<array{id: int, highway: string, nodes: list<array{0: int, 1: float, 2: float}>}>  $streets
     * @param  list<array{type: string, lat: float, lng: float}>  $pois
     * @param  list<array{name: string, geometry: array<string, mixed>, population: int}>  $neighbourhoods
     * @param  (callable(string, int, int): void)|null  $progress
     * @return list<array<string, mixed>>
     */
    public function build(array $streets, array $pois, array $neighbourhoods, ?callable $progress = null): array
    {
        $poiGrid = new SpatialGrid(100.0);

        foreach ($pois as $poi) {
            $poiGrid->add($poi['lat'], $poi['lng'], $poi['type']);
        }

        $areas = array_map(fn (array $n) => [
            'name' => $n['name'],
            'polygon' => $polygon = new Polygon($n['geometry']),
            // Residents per built-up km² (see BuiltUpArea), else per km² of boundary.
            'density' => $n['population'] / max(0.01, $n['built_up']['area_km2'] ?? $polygon->areaKm2()),
        ], $neighbourhoods);

        $points = $this->sampleCommercialPoints($streets, $poiGrid, $areas);

        if ($points === []) {
            return [];
        }

        $betweenness = $this->streetGraph($streets)->localEdgeBetweenness(
            (float) $this->config['centrality']['radius_metres'],
            (float) $this->config['centrality']['sample_sources'],
            $progress ? fn (int $done, int $total) => $progress('centrality', $done, $total) : null,
        );

        $raw = $this->components($points, $poiGrid, $areas, $betweenness);

        return $this->combine($points, $raw);
    }

    private function streetGraph(array $streets): StreetGraph
    {
        $graph = new StreetGraph;

        foreach ($streets as $street) {
            foreach ($street['nodes'] as [$id, $lat, $lng]) {
                $graph->addNode($id, $lat, $lng);
            }

            for ($i = 1; $i < count($street['nodes']); $i++) {
                $graph->addEdge($street['nodes'][$i - 1][0], $street['nodes'][$i][0]);
            }
        }

        return $graph;
    }

    /**
     * Every sample_spacing metres along each street; kept if commercial and
     * inside a neighbourhood.
     *
     * @return list<array<string, mixed>>
     */
    private function sampleCommercialPoints(array $streets, SpatialGrid $poiGrid, array $areas): array
    {
        $spacing = (float) $this->config['sample_spacing_metres'];
        $commercial = $this->config['commercial'];
        $points = [];

        foreach ($streets as $street) {
            // Distance along the street of the next sample, and walked so far.
            $next = $spacing / 2;
            $walked = 0.0;

            for ($i = 1; $i < count($street['nodes']); $i++) {
                [$aId, $aLat, $aLng] = $street['nodes'][$i - 1];
                [$bId, $bLat, $bLng] = $street['nodes'][$i];
                $length = Geo::distanceMetres($aLat, $aLng, $bLat, $bLng);

                for (; $length > 0 && $next <= $walked + $length; $next += $spacing) {
                    $t = ($next - $walked) / $length;
                    $lat = $aLat + ($bLat - $aLat) * $t;
                    $lng = $aLng + ($bLng - $aLng) * $t;

                    $nearby = array_filter(
                        $poiGrid->within($lat, $lng, (float) $commercial['radius_metres']),
                        fn (array $hit) => in_array($hit['item'], $commercial['types'], true),
                    );

                    if (count($nearby) < $commercial['min_pois']) {
                        continue;
                    }

                    $area = $this->areaFor($lat, $lng, $areas);

                    if ($area === null) {
                        continue;
                    }

                    $points[] = [
                        'lat' => round($lat, 6),
                        'lng' => round($lng, 6),
                        'osm_way_id' => $street['id'],
                        'street_type' => $this->streetTypes[$street['highway']] ?? 'secondary_street',
                        'neighbourhood' => $area['name'],
                        'density' => $area['density'],
                        'edge' => StreetGraph::edgeKey($aId, $bId),
                    ];
                }

                $walked += $length;
            }
        }

        return $points;
    }

    private function areaFor(float $lat, float $lng, array $areas): ?array
    {
        foreach ($areas as $area) {
            if ($area['polygon']->contains($lat, $lng)) {
                return $area;
            }
        }

        return null;
    }

    /**
     * Raw component scores per point.
     *
     * @return array<string, list<float>>
     */
    private function components(array $points, SpatialGrid $poiGrid, array $areas, array $betweenness): array
    {
        $poi = $this->config['poi'];
        $transport = $this->config['transport'];
        $catchment = $this->config['catchment'];
        $dayParts = array_map(fn (DayPart $p) => $p->value, DayPart::cases());
        $circleKm2 = M_PI * ($catchment['radius_metres'] / 1000) ** 2;
        $raw = ['poi' => [], 'centrality' => [], 'catchment' => [], 'transport' => []];

        foreach ($dayParts as $part) {
            $raw["poi_{$part}"] = [];
        }

        foreach ($points as $i => $point) {
            $poiScore = 0.0;
            $byPart = array_fill_keys($dayParts, 0.0);

            foreach ($poiGrid->within($point['lat'], $point['lng'], (float) $poi['max_radius_metres']) as $hit) {
                $weight = ($poi['weights'][$hit['item']] ?? 0.0) * exp(-$hit['distance'] / $poi['decay_metres']);
                $poiScore += $weight;

                foreach ($dayParts as $part) {
                    $byPart[$part] += $weight * ($poi['timing'][$hit['item']][$part] ?? 1.0);
                }
            }

            $transportScore = 0.0;
            $offices = 0;

            foreach ($poiGrid->within($point['lat'], $point['lng'], (float) max($transport['max_radius_metres'], $catchment['radius_metres'])) as $hit) {
                if ($hit['distance'] <= $transport['max_radius_metres'] && isset($transport['weights'][$hit['item']])) {
                    $transportScore += $transport['weights'][$hit['item']] * exp(-$hit['distance'] / $transport['decay_metres']);
                }

                if ($hit['item'] === 'office' && $hit['distance'] <= $catchment['radius_metres']) {
                    $offices++;
                }
            }

            $raw['poi'][$i] = $poiScore;
            $raw['centrality'][$i] = $betweenness[$point['edge']] ?? 0.0;
            $raw['catchment'][$i] = $point['density'] * $circleKm2 + $offices * $catchment['workers_per_office'];
            $raw['transport'][$i] = $transportScore;

            foreach ($dayParts as $part) {
                $raw["poi_{$part}"][$i] = $byPart[$part];
            }
        }

        return $raw;
    }

    /**
     * Rank each component 0–1, combine by weight, and map the combined rank
     * onto the footfall scale — overall and per day part.
     *
     * @return list<array<string, mixed>>
     */
    private function combine(array $points, array $raw): array
    {
        $ranks = array_map(fn (array $values) => Ranking::percentiles($values), $raw);
        $weights = $this->config['component_weights'];
        $scale = new PercentileDistribution($this->config['percentiles']);
        $dayParts = array_map(fn (DayPart $p) => $p->value, DayPart::cases());

        $combined = ['all' => []];

        $partWeights = array_map(
            fn (string $part) => $this->config['component_weights_by_day_part'][$part] ?? $weights,
            array_combine($dayParts, $dayParts),
        );
        $mix = fn (array $w, int $i, string $poi) => $w['poi'] * $ranks[$poi][$i]
            + $w['centrality'] * $ranks['centrality'][$i]
            + $w['catchment'] * $ranks['catchment'][$i]
            + $w['transport'] * $ranks['transport'][$i];

        foreach ($points as $i => $point) {
            $combined['all'][$i] = $mix($weights, $i, 'poi');

            foreach ($dayParts as $part) {
                $combined[$part][$i] = $mix($partWeights[$part], $i, "poi_{$part}");
            }
        }

        $footfall = array_map(
            fn (array $scores) => array_map(fn (float $r) => round($scale->quantile($r), 1), Ranking::percentiles($scores)),
            $combined,
        );

        $rows = [];

        foreach ($points as $i => $point) {
            $row = [
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'osm_way_id' => $point['osm_way_id'],
                'neighbourhood' => $point['neighbourhood'],
                'street_type' => $point['street_type'],
                'poi_score' => round($ranks['poi'][$i], 4),
                'centrality_score' => round($ranks['centrality'][$i], 4),
                'catchment_score' => round($ranks['catchment'][$i], 4),
                'transport_score' => round($ranks['transport'][$i], 4),
            ];

            foreach ($dayParts as $part) {
                $row["poi_{$part}_score"] = round($ranks["poi_{$part}"][$i], 4);
            }

            $row['footfall'] = $footfall['all'][$i];

            foreach ($dayParts as $part) {
                $row["footfall_{$part}"] = $footfall[$part][$i];
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
