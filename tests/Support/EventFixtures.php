<?php

namespace Tests\Support;

use App\Simulation\Data\EventRecord;

/**
 * Parameter sheets that force or silence events, for tests.
 */
final class EventFixtures
{
    /**
     * The real sheet with no random events. The library stays, so events
     * already waiting for a choice can still be resolved.
     */
    public static function quiet(?array $parameters = null): array
    {
        $parameters ??= SimulationFixtures::parameters();

        foreach ($parameters['events']['library'] as $type => $event) {
            $parameters['events']['library'][$type]['probability'] = ['base' => 0.0];
        }

        return $parameters;
    }

    /**
     * The real sheet where only $type can happen, and always does. For an
     * event with outcomes, $outcome forces that one.
     */
    public static function only(string $type, ?string $outcome = null, ?array $parameters = null): array
    {
        $parameters ??= SimulationFixtures::parameters();
        $event = $parameters['events']['library'][$type];
        $event['probability'] = ['base' => 1.0];
        unset($event['requires']['months']);

        if ($outcome !== null) {
            foreach ($event['outcomes'] as $name => $config) {
                $event['outcomes'][$name]['weight'] = ['base' => $name === $outcome ? 1.0 : 0.0];
            }
        }

        $parameters['events']['library'] = [$type => $event];

        return $parameters;
    }

    /** An event as it would wait for a choice. */
    public static function pending(string $type, int $month = 1): EventRecord
    {
        $choices = array_keys(SimulationFixtures::parameters()['events']['library'][$type]['choices']);

        return new EventRecord($type, $month, [], $choices);
    }
}
