<?php

namespace Database\Seeders;

use App\Geo\GeoFiles;
use App\Models\PointOfInterest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads points of interest from the committed files in database/seeders/geo:
 * the built zaragoza/points_of_interest.csv (OSM, with ids) when it exists,
 * otherwise the hand-placed placeholder landmarks.
 */
class PointOfInterestSeeder extends Seeder
{
    public function run(): void
    {
        $files = GeoFiles::fromConfig();
        $rows = $files->readCsv("{$files->outputPath}/points_of_interest.csv");

        if ($rows === []) {
            $data = require __DIR__.'/geo/zaragoza_points_of_interest.php';

            foreach ($data['points'] as $point) {
                PointOfInterest::query()->updateOrCreate(
                    ['name' => $point['name'], 'type' => $point['type']],
                    ['lat' => $point['lat'], 'lng' => $point['lng'], 'osm_id' => $point['osm_id'] ?? null],
                );
            }

            return;
        }

        DB::transaction(function () use ($rows) {
            PointOfInterest::query()->delete();
            $now = now();

            foreach (array_chunk($rows, 500) as $chunk) {
                PointOfInterest::query()->insert(array_map(fn (array $r) => [
                    'type' => $r['type'],
                    'name' => $r['name'],
                    'lat' => (float) $r['lat'],
                    'lng' => (float) $r['lng'],
                    'osm_id' => $r['osm_id'] ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        });
    }
}
