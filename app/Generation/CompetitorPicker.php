<?php

namespace App\Generation;

use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/**
 * Turns nearby businesses into the rivals the engine models when the
 * player buys: the nearest ones within the configured distance, at their
 * real distance. Candidates without a known location are picked at random
 * and placed at a random distance.
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
        $chosen = array_slice($this->ranked($candidates, $rng), 0, $this->sheet->int('competitors.nearby_count'));
        $competitors = [];

        foreach ($chosen as $candidate) {
            $own = $rng->fork($candidate->id);
            $quality = $this->sheet->float('competitors.quality.base')
                + $this->sheet->float('competitors.quality.per_condition') * $candidate->condition;

            $competitors[] = new CompetitorState(
                id: $candidate->id,
                name: $candidate->name,
                distanceMetres: round($candidate->distanceMetres !== null
                    ? max($this->sheet->float('competitors.distance_metres.min'), $candidate->distanceMetres)
                    : $own->floatBetween(
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

    /**
     * Located candidates within range, nearest first; then the unlocated
     * ones in random order.
     *
     * @param  list<CompetitorCandidate>  $candidates
     * @return list<CompetitorCandidate>
     */
    private function ranked(array $candidates, SeededRng $rng): array
    {
        $max = $this->sheet->float('competitors.distance_metres.max');
        $located = array_values(array_filter(
            $candidates,
            fn (CompetitorCandidate $c) => $c->distanceMetres !== null && $c->distanceMetres <= $max,
        ));
        usort($located, fn (CompetitorCandidate $a, CompetitorCandidate $b) => [$a->distanceMetres, $a->id] <=> [$b->distanceMetres, $b->id]);

        $unlocated = array_values(array_filter($candidates, fn (CompetitorCandidate $c) => $c->distanceMetres === null));

        return [...$located, ...$rng->fork('choice')->shuffle($unlocated)];
    }
}
