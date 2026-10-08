<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_listing_id
 * @property string $buyer
 * @property int $amount_cents
 * @property int $limit_cents
 * @property Carbon $made_on
 * @property Carbon $expires_on
 * @property string $status open, countered, accepted, rejected, lapsed or walked
 * @property int|null $counter_cents
 * @property-read SaleListing $listing
 */
#[Fillable(['sale_listing_id', 'buyer', 'amount_cents', 'limit_cents', 'made_on', 'expires_on', 'status', 'counter_cents'])]
class SaleOffer extends Model
{
    /** @return BelongsTo<SaleListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(SaleListing::class, 'sale_listing_id');
    }

    protected function casts(): array
    {
        return [
            'made_on' => 'date',
            'expires_on' => 'date',
        ];
    }
}
