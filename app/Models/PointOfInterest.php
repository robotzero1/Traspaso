<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $type
 * @property string $name
 * @property float $lat
 * @property float $lng
 * @property string|null $osm_id
 */
#[Fillable(['type', 'name', 'lat', 'lng', 'osm_id'])]
class PointOfInterest extends Model
{
    protected $table = 'points_of_interest';

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
        ];
    }
}
