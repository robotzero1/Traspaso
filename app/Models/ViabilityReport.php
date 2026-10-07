<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A viability check of one café (SPEC §11): its inputs, its status on the
 * queue, and its results. The full report shows once it is paid for.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $status queued, running, done or failed
 * @property array<string, mixed> $inputs
 * @property array<string, mixed>|null $results
 * @property Carbon|null $paid_at
 */
#[Fillable(['uuid', 'user_id', 'status', 'inputs', 'results', 'paid_at'])]
class ViabilityReport extends Model
{
    use HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function unlocked(): bool
    {
        return $this->paid_at !== null || config('viability.unlock_all');
    }

    protected function casts(): array
    {
        return ['inputs' => 'array', 'results' => 'array', 'paid_at' => 'datetime'];
    }
}
