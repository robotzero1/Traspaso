<?php

namespace App\Balance;

use App\Generation\CommercialPoints;
use App\Generation\Location;
use App\Simulation\Data\NeighbourhoodProfile;

/**
 * Everything a balance game needs from the database, loaded once: the
 * neighbourhoods, the footfall surface's commercial points, and the real
 * cafés and bars that can make up rivals.
 */
final readonly class BalanceMarket
{
    /**
     * @param  array<string, mixed>  $parameters  the market's parameter sheet
     * @param  list<NeighbourhoodProfile>  $neighbourhoods
     * @param  array<string, list<Location>>|null  $points  commercial points by neighbourhood; null without a footfall surface
     * @param  list<array{key: string, lat: float, lng: float}>  $rivalPlaces  real cafés and bars
     */
    public function __construct(
        public array $parameters,
        public array $neighbourhoods,
        public ?array $points = null,
        public array $rivalPlaces = [],
    ) {}

    /** A fresh set of points: each game uses each point at most once. */
    public function commercialPoints(): ?CommercialPoints
    {
        return $this->points === null ? null : new CommercialPoints($this->points);
    }
}
