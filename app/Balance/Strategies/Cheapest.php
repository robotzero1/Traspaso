<?php

namespace App\Balance\Strategies;

use App\Balance\Strategy;
use App\Generation\GeneratedBusiness;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\MonthResult;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/** Buys the cheapest traspaso going and runs it lean on the defaults. */
final class Cheapest implements Strategy
{
    use TakesDefaults;

    public function key(): string
    {
        return 'cheapest';
    }

    public function description(): string
    {
        return 'Cheapest traspaso, default settings with one employee';
    }

    public function reserveShare(): float
    {
        return 0.0;
    }

    public function choose(array $affordable, ParameterSheet $sheet, SeededRng $rng): GeneratedBusiness
    {
        usort($affordable, fn (GeneratedBusiness $a, GeneratedBusiness $b) => $a->traspasoCents <=> $b->traspasoCents);

        return $affordable[0];
    }

    public function openingDecisions(GeneratedBusiness $business, Decisions $defaults, ParameterSheet $sheet): Decisions
    {
        return Hours::allowed($defaults->with(staffCount: 1), $business, $sheet);
    }

    public function adjust(Decisions $current, MonthResult $result, GeneratedBusiness $business, ParameterSheet $sheet): Decisions
    {
        return $current;
    }
}
