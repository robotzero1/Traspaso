<?php

namespace Database\Seeders;

use App\Models\Neighbourhood;
use Illuminate\Database\Seeder;

/**
 * Loads neighbourhoods from the committed file in database/seeders/geo.
 * Never fetches anything at runtime.
 */
class NeighbourhoodSeeder extends Seeder
{
    public function run(): void
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
            ]);
        }
    }
}
