<?php

namespace App\Simulation\Events;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\Modifier;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Demand\CaptureRate;
use App\Simulation\Rng\SeededRng;

/**
 * Applies event effects to the business and to the nearby competitors.
 * Cash effects are left to the engine, which books them in the month's
 * revenue and costs.
 */
final readonly class EffectApplier
{
    public function __construct(private ParameterSheet $sheet) {}

    /**
     * @param  bool  $inDays  count the new modifiers in days (an event during the month, in the daily engine)
     */
    public function applyToState(BusinessState $state, EventEffects $effects, string $source, bool $inDays = false): BusinessState
    {
        $modifiers = $effects->modifiersFor($source);

        if ($inDays) {
            $modifiers = array_map(fn (Modifier $m) => $m->inDays(), $modifiers);
        }

        return $state->with(
            reputation: $this->clamp($state->reputation + $effects->reputation),
            staffMorale: $this->clamp($state->staffMorale + $effects->morale),
            equipmentHealth: $this->clamp($state->equipmentHealth + $effects->equipmentHealth),
            modifiers: [...$state->modifiers, ...$modifiers],
        );
    }

    /**
     * @param  list<CompetitorState>  $competitors
     * @return list<CompetitorState>
     */
    public function applyToCompetitors(array $competitors, EventEffects $effects, int $gameMonth, SeededRng $rng): array
    {
        if ($effects->removeCompetitor !== null && $competitors !== []) {
            $competitors = $this->removeWeakest($competitors);
        }

        if ($effects->addCompetitor !== null) {
            $competitors[] = $this->newCompetitor($effects->addCompetitor, $gameMonth, $rng);
        }

        return $competitors;
    }

    /**
     * @param  list<CompetitorState>  $competitors
     * @return list<CompetitorState>
     */
    private function removeWeakest(array $competitors): array
    {
        $capture = new CaptureRate($this->sheet);
        $weakest = 0;
        $lowest = INF;

        foreach ($competitors as $i => $c) {
            $score = $capture->attractiveness($c->reputation, $c->priceLevel, $c->quality) * exp(-$c->distanceMetres / 1000);

            if ($score < $lowest) {
                [$weakest, $lowest] = [$i, $score];
            }
        }

        unset($competitors[$weakest]);

        return array_values($competitors);
    }

    /** @param array<string, mixed> $spec */
    private function newCompetitor(array $spec, int $gameMonth, SeededRng $rng): CompetitorState
    {
        $between = fn (string $key) => $rng->floatBetween($spec[$key]['min'], $spec[$key]['max']);

        return new CompetitorState(
            id: "opened-month-{$gameMonth}",
            name: $rng->pick($this->sheet->array('names.prefixes.cafe')).' '.$rng->pick($this->sheet->array('names.names')),
            distanceMetres: round($between('distance_metres')),
            priceLevel: round($between('price_level'), 2),
            quality: round($between('quality'), 1),
            reputation: (float) $spec['reputation'],
            seats: $rng->int($spec['seats']['min'], $spec['seats']['max']),
        );
    }

    private function clamp(float $score): float
    {
        return min(100.0, max(0.0, $score));
    }
}
