<?php

namespace App\Game;

use App\Generation\Location;
use App\Models\FootfallPoint;
use App\Models\Neighbourhood;
use App\Models\PointOfInterest;

/**
 * Reads the geo data a market is generated from: the footfall surface's
 * commercial points and the real cafés and bars that can be rivals.
 */
final class MarketData
{
    /**
     * Commercial points by neighbourhood name, or null before geo:build has
     * produced a footfall surface.
     *
     * @return array<string, list<Location>>|null
     */
    public function commercialPoints(): ?array
    {
        if (FootfallPoint::query()->doesntExist()) {
            return null;
        }

        $names = Neighbourhood::query()->pluck('name', 'id');
        $byNeighbourhood = [];

        foreach (FootfallPoint::query()->orderBy('id')->lazy(2000) as $point) {
            $byNeighbourhood[$names[$point->neighbourhood_id]][] = new Location(
                lat: $point->lat,
                lng: $point->lng,
                footfall: $point->footfall,
                footfallByDayPart: $point->footfallByDayPart(),
                streetType: $point->street_type,
                pointId: $point->id,
            );
        }

        return $byNeighbourhood;
    }

    /**
     * Real cafés and bars (OSM) of the given POI types, keyed as rivals.
     *
     * @param  list<string>  $types
     * @return list<array{key: string, lat: float, lng: float}>
     */
    public function rivalPlaces(array $types, GameMapper $mapper): array
    {
        return PointOfInterest::query()->whereIn('type', $types)->whereNotNull('osm_id')->orderBy('id')->get()
            ->map(fn (PointOfInterest $p) => ['key' => $mapper->unlistedKey($p->osm_id), 'lat' => $p->lat, 'lng' => $p->lng])
            ->all();
    }
}
