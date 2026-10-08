<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The player's café, listed for sale (SPEC §12).
 *
 * @property int $id
 * @property int $game_id
 * @property int $business_id
 * @property int $asking_cents
 * @property bool $agency
 * @property Carbon $listed_on
 * @property Carbon|null $withdrawn_on
 * @property Carbon|null $accepted_on
 * @property Carbon|null $completes_on
 * @property Carbon|null $completed_on
 * @property int|null $price_cents
 * @property array<string, int>|null $costs
 */
#[Fillable(['game_id', 'business_id', 'asking_cents', 'agency', 'listed_on', 'withdrawn_on', 'accepted_on', 'completes_on', 'completed_on', 'price_cents', 'costs'])]
class SaleListing extends Model
{
    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<SaleOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(SaleOffer::class)->orderBy('id');
    }

    /** Listed, and neither withdrawn nor sold. */
    public function isLive(): bool
    {
        return $this->withdrawn_on === null && $this->completed_on === null;
    }

    protected function casts(): array
    {
        return [
            'agency' => 'boolean',
            'listed_on' => 'date',
            'withdrawn_on' => 'date',
            'accepted_on' => 'date',
            'completes_on' => 'date',
            'completed_on' => 'date',
            'costs' => 'array',
        ];
    }
}
