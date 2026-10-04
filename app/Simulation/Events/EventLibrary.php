<?php

namespace App\Simulation\Events;

use App\Simulation\Data\ParameterSheet;
use InvalidArgumentException;

/**
 * The events a market can throw at the player, from `events.library`.
 */
final readonly class EventLibrary
{
    /** @var array<string, EventDefinition> */
    public array $definitions;

    public function __construct(ParameterSheet $sheet)
    {
        $definitions = [];

        foreach ($sheet->array('events.library') as $type => $config) {
            $definitions[$type] = EventDefinition::fromConfig($type, $config);
        }

        $this->definitions = $definitions;
    }

    public function get(string $type): EventDefinition
    {
        return $this->definitions[$type] ?? throw new InvalidArgumentException("Unknown event [{$type}].");
    }
}
