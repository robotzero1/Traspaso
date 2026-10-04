<?php

namespace App\Generation;

use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/**
 * Rivals that aren't for sale: real cafés and bars near the player's
 * business (from OpenStreetMap), used when too few listings are nearby.
 * Only the position is real. Each gets a fictional name and drawn seats,
 * condition and reputation, so no real business is rated by the game.
 */
final readonly class UnlistedRivals
{
    public function __construct(private ParameterSheet $sheet) {}

    /**
     * @param  list<array{key: string, distance_metres: float}>  $places  nearest first
     * @param  list<string>  $takenNames  names already used in the market
     * @return list<CompetitorCandidate>
     */
    public function candidates(array $places, SeededRng $rng, array $takenNames = []): array
    {
        $names = new NameDrawer($this->sheet, $rng->fork('names'), $takenNames);
        $config = 'competitors.unlisted';

        return array_map(function (array $place) use ($rng, $names, $config) {
            $own = $rng->fork($place['key']);

            return new CompetitorCandidate(
                id: $place['key'],
                name: $names->draw($own->pick(BusinessCategory::cases())),
                seats: $own->int($this->sheet->int("{$config}.seats.min"), $this->sheet->int("{$config}.seats.max")),
                condition: $own->int($this->sheet->int("{$config}.condition.min"), $this->sheet->int("{$config}.condition.max")),
                reputation: round($own->floatBetween($this->sheet->float("{$config}.reputation.min"), $this->sheet->float("{$config}.reputation.max")), 1),
                distanceMetres: $place['distance_metres'],
            );
        }, $places);
    }
}
