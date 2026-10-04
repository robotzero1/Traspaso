<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_id
 * @property string $key
 * @property int|null $business_id
 * @property string $name
 * @property float $distance_metres
 * @property float $price_level
 * @property float $quality
 * @property float $reputation
 * @property int $seats
 * @property-read Business|null $business
 */
#[Fillable(['game_id', 'key', 'business_id', 'name', 'distance_metres', 'price_level', 'quality', 'reputation', 'seats'])]
class GameCompetitor extends Model
{
    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * The listing it came from; null for rivals opened by events.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected function casts(): array
    {
        return [
            'distance_metres' => 'float',
            'price_level' => 'float',
            'quality' => 'float',
            'reputation' => 'float',
            'seats' => 'integer',
        ];
    }
}
