<?php

namespace App\Simulation\Events;

use App\Simulation\Data\EventRecord;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/**
 * Step 7: rolls this month's random events.
 *
 * Events are tried in a random order, each with its own forked RNG
 * stream, and at most `events.max_per_month` happen.
 */
final readonly class EventRoller
{
    private EventLibrary $library;

    public function __construct(private ParameterSheet $sheet)
    {
        $this->library = new EventLibrary($sheet);
    }

    /**
     * @return list<EventOccurrence>
     */
    public function roll(EventSignals $signals, EventConditions $conditions, int $gameMonth, SeededRng $rng): array
    {
        $max = $this->sheet->int('events.max_per_month');
        $happened = [];

        foreach ($rng->fork('order')->shuffle(array_keys($this->library->definitions)) as $type) {
            if (count($happened) >= $max) {
                break;
            }

            $definition = $this->library->get($type);
            $eventRng = $rng->fork($type);

            if (! $conditions->allow($definition->requires) || ! $eventRng->chance($signals->evaluate($definition->probability))) {
                continue;
            }

            $happened[] = $this->occur($definition, $signals, $gameMonth, $eventRng);
        }

        return $happened;
    }

    private function occur(EventDefinition $definition, EventSignals $signals, int $gameMonth, SeededRng $rng): EventOccurrence
    {
        $effects = $definition->effects;
        $payload = [];

        if ($definition->outcomes !== []) {
            $weights = array_map(fn (array $o) => $signals->evaluate($o['weight']), $definition->outcomes);
            $outcome = (string) $rng->weightedKey($weights);
            $effects = $definition->outcomes[$outcome]['effects'];
            $payload['outcome'] = $outcome;
        }

        $payload['effects'] = $effects->toArray();

        if ($definition->choices !== []) {
            $payload['choice_effects'] = array_map(fn (EventEffects $e) => $e->toArray(), $definition->choices);
            $payload['default_choice'] = $definition->defaultChoice;
        }

        return new EventOccurrence(
            new EventRecord($definition->type, $gameMonth, $payload, $definition->choiceNames()),
            $effects,
        );
    }
}
