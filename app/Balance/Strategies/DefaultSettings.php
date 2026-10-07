<?php

namespace App\Balance\Strategies;

use App\Balance\Strategy;
use App\Generation\GeneratedBusiness;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\MonthResult;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/** Buys any café it can afford and never touches the default settings. */
final class DefaultSettings implements Strategy
{
    use RepairsWhenAffordable;

    public function key(): string
    {
        return 'default';
    }

    public function description(): string
    {
        return 'Random affordable café, default settings all year';
    }

    public function reserveShare(): float
    {
        return 0.0;
    }

    public function choose(array $affordable, ParameterSheet $sheet, SeededRng $rng): GeneratedBusiness
    {
        return $rng->pick($affordable);
    }

    public function openingDecisions(GeneratedBusiness $business, Decisions $defaults, ParameterSheet $sheet): Decisions
    {
        return Hours::allowed($defaults, $business, $sheet);
    }

    public function adjust(Decisions $current, MonthResult $result, GeneratedBusiness $business, ParameterSheet $sheet): Decisions
    {
        return $current;
    }
}
