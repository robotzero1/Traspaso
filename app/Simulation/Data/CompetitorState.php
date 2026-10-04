<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;
use App\Simulation\Data\Concerns\Immutable;

/**
 * An AI-run café or bar near the player's business.
 */
final readonly class CompetitorState
{
    use Immutable;

    public function __construct(
        public string $id,
        public string $name,
        public float $distanceMetres,
        /** Relative to the local average, like Decisions::$priceLevel. */
        public float $priceLevel,
        public float $quality,
        public float $reputation,
        public int $seats,
    ) {
        Guard::notBlank('id', $id);
        Guard::notBlank('name', $name);
        Guard::nonNegative('distanceMetres', $distanceMetres);
        Guard::positive('priceLevel', $priceLevel);
        Guard::between('quality', $quality, 0, 100);
        Guard::between('reputation', $reputation, 0, 100);
        Guard::nonNegative('seats', $seats);
    }
}
