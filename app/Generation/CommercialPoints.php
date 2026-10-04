<?php

namespace App\Generation;

use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Rng\SeededRng;

/**
 * Locations from the footfall surface: a random, not yet taken commercial
 * point in the business's neighbourhood. Each point is used at most once.
 */
final class CommercialPoints implements LocationSource
{
    /** @var array<string, list<Location>> neighbourhood name → free points */
    private array $free = [];

    /** @param iterable<string, list<Location>> $byNeighbourhood */
    public function __construct(iterable $byNeighbourhood)
    {
        foreach ($byNeighbourhood as $name => $locations) {
            $this->free[$name] = array_values($locations);
        }
    }

    public function draw(NeighbourhoodProfile $neighbourhood, SeededRng $rng): ?Location
    {
        $free = $this->free[$neighbourhood->name] ?? [];

        if ($free === []) {
            return null;
        }

        $index = $rng->int(0, count($free) - 1);
        $location = $free[$index];
        // Swap-remove: O(1), and still deterministic for a seed.
        $free[$index] = $free[count($free) - 1];
        array_pop($free);
        $this->free[$neighbourhood->name] = $free;

        return $location;
    }

    public function remaining(string $neighbourhood): int
    {
        return count($this->free[$neighbourhood] ?? []);
    }
}
