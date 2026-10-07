<?php

namespace App\Balance\Strategies;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\ParameterSheet;

/** Never answers events: each takes its default choice. */
trait TakesDefaults
{
    public function eventChoices(BusinessState $state, ParameterSheet $sheet): array
    {
        return [];
    }
}
