<?php

namespace App\Generation;

use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Rng\SeededRng;

/**
 * Supplies real locations for generated businesses. When it has none for a
 * neighbourhood, the generator falls back to footfall from the
 * neighbourhood indices and street type.
 */
interface LocationSource
{
    public function draw(NeighbourhoodProfile $neighbourhood, SeededRng $rng): ?Location;
}
