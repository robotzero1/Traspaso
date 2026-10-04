<?php

namespace App\Balance;

use App\Generation\GeneratedBusiness;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\MonthResult;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/**
 * A scripted player for the balancing pass (milestone 9): which business
 * it buys and how it runs it.
 */
interface Strategy
{
    /** Short key, e.g. "thoughtful". */
    public function key(): string;

    /** One line for the report. */
    public function description(): string;

    /** Share of starting capital kept back as working capital, not spent on the purchase. */
    public function reserveShare(): float;

    /**
     * @param  list<GeneratedBusiness>  $affordable  never empty
     */
    public function choose(array $affordable, ParameterSheet $sheet, SeededRng $rng): GeneratedBusiness;

    public function openingDecisions(GeneratedBusiness $business, Decisions $defaults, ParameterSheet $sheet): Decisions;

    /** Decisions for the next month, after seeing this one. */
    public function adjust(Decisions $current, MonthResult $result, GeneratedBusiness $business, ParameterSheet $sheet): Decisions;
}
