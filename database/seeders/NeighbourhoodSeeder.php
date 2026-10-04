<?php

namespace Database\Seeders;

use App\Models\Neighbourhood;
use Illuminate\Database\Seeder;

/**
 * Loads neighbourhoods from the committed files in database/seeders/geo:
 * the built zaragoza/neighbourhoods.geojson (real boundaries, population
 * and derived indices) when it exists, otherwise the placeholder estimates.
 * Never fetches anything at runtime.
 */
class NeighbourhoodSeeder extends Seeder
{
    public function run(): void
    {
        $built = base_path(config('geo.output_path')).'/neighbourhoods.geojson';

        is_file($built) ? $this->fromBuiltFile($built) : $this->fromPlaceholders();
    }

    private function fromBuiltFile(string $path): void
    {
        $features = json_decode((string) file_get_contents($path), true)['features'] ?? [];
        $names = [];

        foreach ($features as $feature) {
            $p = $feature['properties'];
            $names[] = $p['name'];

            Neighbourhood::query()->updateOrCreate(['name' => $p['name']], [
                'population' => $p['population'],
                'student_index' => $p['student'],
                'tourist_index' => $p['tourist'],
                'office_index' => $p['office'],
                'transport_index' => $p['transport'],
                'competition_density' => $p['competition_density'],
                'centre_lat' => $p['centre'][0],
                'centre_lng' => $p['centre'][1],
                'radius_m' => $p['radius_m'],
                'boundary' => $feature['geometry'],
            ]);
        }

        // Placeholder districts the real data doesn't have, unless a game uses them.
        Neighbourhood::query()->whereNotIn('name', $names)->whereDoesntHave('businesses')->delete();
    }

    private function fromPlaceholders(): void
    {
        $data = require __DIR__.'/geo/zaragoza_neighbourhoods.php';

        foreach ($data['neighbourhoods'] as $row) {
            Neighbourhood::query()->updateOrCreate(['name' => $row['name']], [
                'population' => $row['population'],
                'student_index' => $row['student'],
                'tourist_index' => $row['tourist'],
                'office_index' => $row['office'],
                'transport_index' => $row['transport'],
                'competition_density' => $row['competition_density'],
                'centre_lat' => $row['centre'][0] ?? null,
                'centre_lng' => $row['centre'][1] ?? null,
                'radius_m' => $row['radius_m'] ?? null,
                'boundary' => $row['boundary'] ?? null,
            ]);
        }
    }
}
