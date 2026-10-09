<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One manual pedestrian count (SPEC §8 Footfall estimate), exported to the
 * committed CSV with `geo:counts-export` for `geo:calibrate`.
 *
 * @property int $id
 * @property int $user_id
 * @property float $lat
 * @property float $lng
 * @property string $day_part
 * @property Carbon $counted_on
 * @property int $minutes
 * @property int $count
 * @property string|null $note
 */
#[Fillable(['user_id', 'lat', 'lng', 'day_part', 'counted_on', 'minutes', 'count', 'note'])]
class PedestrianCount extends Model
{
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'counted_on' => 'date',
        ];
    }
}
