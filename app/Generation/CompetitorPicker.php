<?php

namespace App\Generation;

use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/**
 * Turns neighbouring businesses into the rivals the engine models when
 * the player buys. Until real locations arrive (milestone 8) distances
 * are drawn at random.
 */
final readonly class CompetitorPicker
{
    public function __construct(private ParameterSheet $sheet) {}

    /**
     * @param  list<CompetitorCandidate>  $candidates
     * @return list<CompetitorState>
     */
    public function pick(array $candidates, SeededRng $rng): array
    {
        $chosen = array_slice($rng->fork('choice')->shuffle($candidates), 0, $this->sheet->int('competitors.nearby_count'));
        $competitors = [];

        foreach ($chosen as $candidate) {
            $own = $rng->fork($candidate->id);
            $quality = $this->sheet->float('competitors.quality.base')
                + $this->sheet->float('competitors.quality.per_condition') * $candidate->condition;

            $competitors[] = new CompetitorState(
                id: $candidate->id,
                name: $candidate->name,
                distanceMetres: round($own->floatBetween(
                    $this->sheet->float('competitors.distance_metres.min'),
                    $this->sheet->float('competitors.distance_metres.max'),
                )),
                priceLevel: round($own->floatBetween(
                    $this->sheet->float('competitors.price_level.min'),
                    $this->sheet->float('competitors.price_level.max'),
                ), 2),
                quality: min(100.0, max(0.0, $quality)),
                reputation: $candidate->reputation,
                seats: $candidate->seats,
            );
        }

        usort($competitors, fn (CompetitorState $a, CompetitorState $b) => $a->distanceMetres <=> $b->distanceMetres);

        return $competitors;
    }
}
