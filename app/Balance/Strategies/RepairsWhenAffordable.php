<?php

namespace App\Balance\Strategies;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\EventRecord;
use App\Simulation\Data\ParameterSheet;

/**
 * Answers events as a careful owner would: repairs broken equipment when
 * the cash allows (keeping three months of the owner's pay in hand),
 * otherwise takes the default.
 */
trait RepairsWhenAffordable
{
    public function eventChoices(BusinessState $state, ParameterSheet $sheet): array
    {
        $choices = [];

        foreach ($state->pendingEvents as $event) {
            /** @var EventRecord $event */
            if (! in_array('repair', $event->choices, true)) {
                continue;
            }

            $cost = (int) ($sheet->array("events.library.{$event->type}.choices.repair")['cost_cents'] ?? 0);

            if ($state->cashCents - $cost > 3 * $sheet->int('owner.pay_month_cents')) {
                $choices[$event->key()] = 'repair';
            }
        }

        return $choices;
    }
}
