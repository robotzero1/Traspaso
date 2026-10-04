<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The player's business at the end of a month (month 0 = when bought).
 *
 * @property int $id
 * @property int $game_id
 * @property int $month
 * @property float $reputation
 * @property int $staff_count
 * @property float $staff_morale
 * @property float $equipment_health
 * @property int $equipment_age_months
 * @property float $stock_quality
 * @property list<array<string, mixed>> $modifiers
 * @property list<array<string, mixed>> $pending_events
 * @property array<string, mixed>|null $decisions
 */
#[Fillable([
    'game_id', 'month', 'reputation', 'staff_count', 'staff_morale', 'equipment_health', 'equipment_age_months',
    'stock_quality', 'modifiers', 'pending_events', 'decisions',
])]
class GameBusinessState extends Model
{
    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'reputation' => 'float',
            'staff_count' => 'integer',
            'staff_morale' => 'float',
            'equipment_health' => 'float',
            'equipment_age_months' => 'integer',
            'stock_quality' => 'float',
            'modifiers' => 'array',
            'pending_events' => 'array',
            'decisions' => 'array',
        ];
    }
}
