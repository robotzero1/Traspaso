<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_id
 * @property int $month
 * @property string $type
 * @property array<string, mixed> $payload
 * @property list<string> $choices
 * @property string|null $choice
 * @property int|null $resolved_month
 */
#[Fillable(['game_id', 'month', 'type', 'payload', 'choices', 'choice', 'resolved_month'])]
class GameEvent extends Model
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
            'payload' => 'array',
            'choices' => 'array',
            'resolved_month' => 'integer',
        ];
    }
}
