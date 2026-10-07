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
        return $this->rollWith($signals, $conditions, $gameMonth, $rng, $this->sheet->int('events.max_per_month'), [], fn (float $p) => $p);
    }

    /**
     * One day's events for the daily engine: each event's monthly odds
     * spread over the month's days (1 − (1 − p)^(1/days)), with an event
     * type at most once a month and at most events.max_per_month in all.
     *
     * @param  list<string>  $earlierThisMonth  types that already happened this month
     * @param  array<string, mixed>  $payload  added to each event's payload (e.g. the date)
     * @return list<EventOccurrence>
     */
    public function rollDay(EventSignals $signals, EventConditions $conditions, int $gameMonth, int $daysInMonth, array $earlierThisMonth, SeededRng $rng, array $payload = []): array
    {
        $max = $this->sheet->int('events.max_per_month') - count($earlierThisMonth);

        if ($max <= 0) {
            return [];
        }

        return $this->rollWith(
            $signals, $conditions, $gameMonth, $rng, $max, $earlierThisMonth,
            fn (float $p) => $p >= 1.0 ? 1.0 : 1 - (1 - $p) ** (1 / $daysInMonth),
            $payload,
        );
    }

    /**
     * @param  list<string>  $skip
     * @param  callable(float): float  $odds
     * @param  array<string, mixed>  $payload
     * @return list<EventOccurrence>
     */
    private function rollWith(EventSignals $signals, EventConditions $conditions, int $gameMonth, SeededRng $rng, int $max, array $skip, callable $odds, array $payload = []): array
    {
        $happened = [];

        foreach ($rng->fork('order')->shuffle(array_keys($this->library->definitions)) as $type) {
            if (count($happened) >= $max) {
                break;
            }

            if (in_array($type, $skip, true)) {
                continue;
            }

            $definition = $this->library->get($type);
            $eventRng = $rng->fork($type);

            if (! $conditions->allow($definition->requires) || ! $eventRng->chance($odds($signals->evaluate($definition->probability)))) {
                continue;
            }

            $happened[] = $this->occur($definition, $signals, $gameMonth, $eventRng, $payload);
        }

        return $happened;
    }

    /** @param array<string, mixed> $payload */
    private function occur(EventDefinition $definition, EventSignals $signals, int $gameMonth, SeededRng $rng, array $payload = []): EventOccurrence
    {
        $effects = $definition->effects;

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
