<?php

namespace Database\Seeders;

use App\Geo\GeoFiles;
use App\Models\FootfallPoint;
use App\Models\Neighbourhood;
use App\Simulation\Data\DayPart;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the footfall surface from the committed
 * zaragoza/footfall_points.csv. Without it (before geo:build has been run)
 * there are no points, and businesses fall back to generated footfall.
 * Run after NeighbourhoodSeeder.
 */
class FootfallPointSeeder extends Seeder
{
    public function run(): void
    {
        $files = GeoFiles::fromConfig();
        $rows = $files->readCsv("{$files->outputPath}/footfall_points.csv");

        if ($rows === []) {
            return;
        }

        $neighbourhoods = Neighbourhood::query()->pluck('id', 'name');

        DB::transaction(function () use ($rows, $neighbourhoods) {
            FootfallPoint::query()->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                $insert = [];

                foreach ($chunk as $r) {
                    if (! isset($neighbourhoods[$r['neighbourhood']])) {
                        continue;
                    }

                    $row = [
                        'neighbourhood_id' => $neighbourhoods[$r['neighbourhood']],
                        'lat' => (float) $r['lat'],
                        'lng' => (float) $r['lng'],
                        'osm_way_id' => $r['osm_way_id'] !== '' ? (int) $r['osm_way_id'] : null,
                        'street_type' => $r['street_type'],
                        'poi_score' => (float) $r['poi_score'],
                        'centrality_score' => (float) $r['centrality_score'],
                        'catchment_score' => (float) $r['catchment_score'],
                        'transport_score' => (float) $r['transport_score'],
                        'footfall' => (float) $r['footfall'],
                    ];

                    foreach (DayPart::cases() as $part) {
                        $row["footfall_{$part->value}"] = (float) $r["footfall_{$part->value}"];
                    }

                    $insert[] = $row;
                }

                FootfallPoint::query()->insert($insert);
            }
        });
    }
}
