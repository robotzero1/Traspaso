<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fictional business, generated for one game.
 *
 * @property int $id
 * @property int $game_id
 * @property int $market_index
 * @property int $neighbourhood_id
 * @property string $fictional_name
 * @property float|null $lat
 * @property float|null $lng
 * @property int|null $footfall_point_id
 * @property array<string, float>|null $footfall_by_day_part
 * @property string $street_type
 * @property string $category
 * @property int $floor_area_m2
 * @property int $indoor_seats
 * @property int $terrace_seats
 * @property int $rent_month_cents
 * @property int $traspaso_cents
 * @property string $licence
 * @property string $kitchen
 * @property int $condition
 * @property int $equipment_age_years
 * @property float $footfall
 * @property float $base_reputation
 * @property BusinessStatus $status
 * @property-read Neighbourhood $neighbourhood
 */
#[Fillable([
    'game_id', 'market_index', 'neighbourhood_id', 'fictional_name', 'lat', 'lng', 'footfall_point_id', 'street_type', 'category', 'floor_area_m2',
    'indoor_seats', 'terrace_seats', 'rent_month_cents', 'traspaso_cents', 'licence', 'kitchen', 'condition',
    'equipment_age_years', 'footfall', 'footfall_by_day_part', 'base_reputation', 'status',
])]
class Business extends Model
{
    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Neighbourhood, $this> */
    public function neighbourhood(): BelongsTo
    {
        return $this->belongsTo(Neighbourhood::class);
    }

    protected function casts(): array
    {
        return [
            'market_index' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'floor_area_m2' => 'integer',
            'indoor_seats' => 'integer',
            'terrace_seats' => 'integer',
            'rent_month_cents' => 'integer',
            'traspaso_cents' => 'integer',
            'condition' => 'integer',
            'equipment_age_years' => 'integer',
            'footfall' => 'float',
            'footfall_by_day_part' => 'array',
            'base_reputation' => 'float',
            'status' => BusinessStatus::class,
        ];
    }
}
