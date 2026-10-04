<?php

namespace App\Geo;

use App\Generation\Geo\BuiltUpArea;
use App\Generation\Geo\FootfallSurfaceBuilder;
use App\Generation\Geo\NeighbourhoodIndexer;
use App\Generation\Geo\OsmExtract;
use RuntimeException;

/**
 * geo:build — turns the raw downloads (and hand-collected sources) into the
 * committed files the seeders import:
 *
 *   neighbourhoods.geojson   boundaries, population and derived indices
 *   points_of_interest.csv   typed POIs with their OSM ids
 *   footfall_points.csv      the footfall surface (commercial points only)
 *   manifest.json            what was built from what, and the attribution
 */
final class GeoBuild
{
    /** @var list<string> */
    public array $warnings = [];

    /** @param array<string, mixed> $config config('geo') */
    public function __construct(private readonly GeoFiles $files, private readonly array $config) {}

    /**
     * @param  (callable(string, int, int): void)|null  $progress
     * @return array<string, int> counts of what was written
     */
    public function run(?callable $progress = null): array
    {
        $streetsJson = $this->files->readJson("{$this->files->rawPath}/streets.json")
            ?? throw new RuntimeException('No streets.json in '.$this->files->rawPath.'. Run geo:fetch first.');
        $poisJson = $this->files->readJson("{$this->files->rawPath}/pois.json")
            ?? throw new RuntimeException('No pois.json in '.$this->files->rawPath.'. Run geo:fetch first.');

        $streets = OsmExtract::streets($streetsJson, $this->config['street_highway_types']);
        // A relation's centre can land far away: "Universidad de Zaragoza"
        // spans campuses in three cities and centres 70 km south.
        [$south, $west, $north, $east] = $this->config['bbox'];
        $pois = array_values(array_filter(
            OsmExtract::pointsOfInterest($poisJson, $this->config['poi_types'], $this->config['competitor_tags']),
            fn (array $p) => $p['lat'] >= $south && $p['lat'] <= $north && $p['lng'] >= $west && $p['lng'] <= $east,
        ));
        $neighbourhoods = $this->neighbourhoods();

        if ($neighbourhoods === []) {
            throw new RuntimeException('No neighbourhood boundaries: add sources/neighbourhoods.geojson or fetch boundaries.json.');
        }

        $builtUp = (new BuiltUpArea((float) $this->config['built_up']['cell_metres'], (float) $this->config['built_up']['min_street_metres']))
            ->measure($neighbourhoods, $streets);

        foreach ($neighbourhoods as $i => $n) {
            $neighbourhoods[$i]['built_up'] = $builtUp[$i];

            if ($builtUp[$i] === null) {
                $this->warnings[] = "No built-up area found in [{$n['name']}]; using its whole boundary.";
            }
        }

        $indexed = NeighbourhoodIndexer::index($neighbourhoods, $pois, $this->config['indices']);
        $surface = (new FootfallSurfaceBuilder($this->config['footfall'], $this->config['street_types']))
            ->build($streets, $pois, $neighbourhoods, $progress);

        $this->writeNeighbourhoods($neighbourhoods, $indexed);
        $this->files->writeCsv("{$this->files->outputPath}/points_of_interest.csv", array_map(
            fn (array $p) => ['type' => $p['type'], 'name' => $p['name'], 'lat' => $p['lat'], 'lng' => $p['lng'], 'osm_id' => $p['osm_id']],
            $pois,
        ));
        $this->files->writeCsv("{$this->files->outputPath}/footfall_points.csv", $surface);

        $counts = [
            'neighbourhoods' => count($neighbourhoods),
            'streets' => count($streets),
            'points_of_interest' => count($pois),
            'footfall_points' => count($surface),
        ];

        $this->files->writeJson("{$this->files->outputPath}/manifest.json", [
            'built_at' => now()->toIso8601String(),
            'osm_timestamp' => $streetsJson['osm3s']['timestamp_osm_base'] ?? null,
            'bbox' => $this->config['bbox'],
            'counts' => $counts,
            'footfall' => [
                'component_weights' => $this->config['footfall']['component_weights'],
                'calibrated' => ! str_starts_with($this->config['footfall']['source'], 'PLACEHOLDER'),
            ],
            'attribution' => 'Contains information from OpenStreetMap (© OpenStreetMap contributors), available under the Open Database License (ODbL).',
            'warnings' => $this->warnings,
        ], pretty: true);

        return $counts;
    }

    /**
     * Boundaries from sources/neighbourhoods.geojson if present, otherwise
     * from the OSM download; population from sources/population.csv.
     *
     * @return list<array{name: string, geometry: array<string, mixed>, population: int}>
     */
    private function neighbourhoods(): array
    {
        $manual = $this->files->readJson("{$this->files->sourcesPath}/neighbourhoods.geojson");

        $boundaries = $manual !== null
            ? array_map(fn (array $f) => ['name' => (string) $f['properties']['name'], 'geometry' => $f['geometry']], $manual['features'] ?? [])
            : OsmExtract::boundaries($this->files->readJson("{$this->files->rawPath}/boundaries.json") ?? []);

        $excluded = array_map(self::normalise(...), $this->config['boundaries']['exclude'] ?? []);
        $boundaries = array_values(array_filter($boundaries, fn (array $b) => ! in_array(self::normalise($b['name']), $excluded, true)));

        $population = [];

        foreach ($this->files->readCsv("{$this->files->sourcesPath}/population.csv") as $row) {
            $population[self::normalise($row['name'])] = (int) $row['population'];
        }

        return array_map(function (array $b) use ($population) {
            $known = $population[self::normalise($b['name'])] ?? null;

            if ($known === null) {
                $this->warnings[] = "No population for [{$b['name']}] in sources/population.csv; using 0.";
            }

            return [...$b, 'population' => $known ?? 0];
        }, $boundaries);
    }

    /**
     * @param  list<array<string, mixed>>  $neighbourhoods
     * @param  list<array<string, mixed>>  $indexed
     */
    private function writeNeighbourhoods(array $neighbourhoods, array $indexed): void
    {
        $features = [];

        foreach ($neighbourhoods as $i => $n) {
            $row = $indexed[$i];
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'name' => $n['name'],
                    'population' => $row['population'],
                    'area_km2' => $row['area_km2'],
                    'boundary_area_km2' => $row['boundary_area_km2'],
                    'student' => $row['student'],
                    'tourist' => $row['tourist'],
                    'office' => $row['office'],
                    'transport' => $row['transport'],
                    'competition_density' => $row['competition_density'],
                    'centre' => $row['centre'],
                    'radius_m' => $row['radius_m'],
                ],
                'geometry' => $this->rounded($n['geometry']),
            ];
        }

        $this->files->writeJson("{$this->files->outputPath}/neighbourhoods.geojson", ['type' => 'FeatureCollection', 'features' => $features]);
    }

    /** Coordinates to 6 decimals (~10 cm) to keep the file small. */
    private function rounded(mixed $value): mixed
    {
        return is_array($value)
            ? array_map(fn ($v) => $this->rounded($v), $value)
            : (is_float($value) ? round($value, 6) : $value);
    }

    /**
     * "Casco Histórico", "casco historico" and "Distrito Casco Histórico"
     * all match.
     */
    public static function normalise(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $plain = trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($ascii)));

        return trim((string) preg_replace('/^(distrito|junta municipal|junta vecinal|barrio)\s+/', '', $plain));
    }
}
