<?php

namespace App\Models;

use App\Simulation\Data\DayPart;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point on a commercial street with its estimated footfall (0–10),
 * overall and per day part. Derived from OpenStreetMap (ODbL).
 *
 * @property int $id
 * @property int $neighbourhood_id
 * @property float $lat
 * @property float $lng
 * @property int|null $osm_way_id
 * @property string $street_type
 * @property float $footfall
 */
#[Fillable([
    'neighbourhood_id', 'lat', 'lng', 'osm_way_id', 'street_type', 'poi_score', 'centrality_score', 'catchment_score',
    'transport_score', 'footfall', 'footfall_morning', 'footfall_lunch', 'footfall_afternoon', 'footfall_evening', 'footfall_night',
])]
#[WithoutTimestamps]
class FootfallPoint extends Model
{
    /** @return BelongsTo<Neighbourhood, $this> */
    public function neighbourhood(): BelongsTo
    {
        return $this->belongsTo(Neighbourhood::class);
    }

    /** @return array<string, float> day part → footfall */
    public function footfallByDayPart(): array
    {
        $byPart = [];

        foreach (DayPart::cases() as $part) {
            $byPart[$part->value] = (float) $this->getAttribute("footfall_{$part->value}");
        }

        return $byPart;
    }

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'footfall' => 'float',
            'footfall_morning' => 'float',
            'footfall_lunch' => 'float',
            'footfall_afternoon' => 'float',
            'footfall_evening' => 'float',
            'footfall_night' => 'float',
        ];
    }
}
