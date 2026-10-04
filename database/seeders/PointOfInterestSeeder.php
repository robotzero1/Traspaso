<?php

namespace Database\Seeders;

use App\Models\PointOfInterest;
use Illuminate\Database\Seeder;

/**
 * Loads points of interest from the committed file in database/seeders/geo.
 */
class PointOfInterestSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__.'/geo/zaragoza_points_of_interest.php';

        foreach ($data['points'] as $point) {
            PointOfInterest::query()->updateOrCreate(
                ['name' => $point['name'], 'type' => $point['type']],
                ['lat' => $point['lat'], 'lng' => $point['lng'], 'osm_id' => $point['osm_id'] ?? null],
            );
        }
    }
}
