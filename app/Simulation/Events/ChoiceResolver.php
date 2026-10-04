<?php

namespace App\Simulation\Events;

use App\Simulation\Data\Decisions;
use App\Simulation\Data\EventRecord;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Exceptions\DecisionNotAllowed;

/**
 * Settles last month's events that were waiting for a choice: the
 * player's pick from Decisions, or the event's default.
 */
final readonly class ChoiceResolver
{
    private EventLibrary $library;

    public function __construct(ParameterSheet $sheet)
    {
        $this->library = new EventLibrary($sheet);
    }

    /**
     * @param  list<EventRecord>  $pending
     * @return list<EventOccurrence>
     */
    public function resolve(array $pending, Decisions $decisions): array
    {
        $resolved = [];

        foreach ($pending as $event) {
            $definition = $this->library->get($event->type);
            $choice = $decisions->choiceFor($event) ?? $definition->defaultChoice;

            if (! in_array($choice, $event->choices, true)) {
                throw new DecisionNotAllowed("[{$choice}] is not a choice for event [{$event->key()}].");
            }

            $resolved[] = new EventOccurrence($event->with(choice: $choice), $definition->choices[$choice]);
        }

        return $resolved;
    }
}
