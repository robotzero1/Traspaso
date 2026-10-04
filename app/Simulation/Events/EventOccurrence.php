<?php

namespace App\Simulation\Events;

use App\Simulation\Data\EventRecord;

/**
 * An event (or a resolved choice) together with the effects to apply now.
 */
final readonly class EventOccurrence
{
    public function __construct(
        public EventRecord $record,
        public EventEffects $effects,
    ) {}
}
