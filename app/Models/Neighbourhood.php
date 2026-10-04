<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $population
 * @property float $student_index
 * @property float $tourist_index
 * @property float $office_index
 * @property float $transport_index
 * @property float $competition_density
 * @property float|null $centre_lat
 * @property float|null $centre_lng
 * @property int|null $radius_m
 * @property array<string, mixed>|null $boundary
 */
#[Fillable([
    'name', 'population', 'student_index', 'tourist_index', 'office_index', 'transport_index', 'competition_density',
    'centre_lat', 'centre_lng', 'radius_m', 'boundary',
])]
class Neighbourhood extends Model
{
    /** @return HasMany<Business, $this> */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    protected function casts(): array
    {
        return [
            'population' => 'integer',
            'student_index' => 'float',
            'tourist_index' => 'float',
            'office_index' => 'float',
            'transport_index' => 'float',
            'competition_density' => 'float',
            'centre_lat' => 'float',
            'centre_lng' => 'float',
            'radius_m' => 'integer',
            'boundary' => 'array',
        ];
    }
}
