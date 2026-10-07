<?php

namespace App\Viability;

use App\Balance\Strategies\Hours;
use App\Balance\Strategies\RepairsWhenAffordable;
use App\Balance\Strategy;
use App\Generation\GeneratedBusiness;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\MonthResult;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;
use LogicException;

/**
 * The viability check's owner: runs the café the way the user said they
 * would, all five years, and repairs broken equipment when the cash allows,
 * like the typical owner the model is calibrated on.
 */
final class FixedPlan implements Strategy
{
    use RepairsWhenAffordable;

    public function __construct(private readonly ViabilityInput $input) {}

    public function key(): string
    {
        return 'viability';
    }

    public function description(): string
    {
        return 'The user\'s café, run as they described';
    }

    public function reserveShare(): float
    {
        return 0.0;
    }

    public function choose(array $affordable, ParameterSheet $sheet, SeededRng $rng): GeneratedBusiness
    {
        throw new LogicException('The viability check plays a given café; it never chooses one.');
    }

    public function openingDecisions(GeneratedBusiness $business, Decisions $defaults, ParameterSheet $sheet): Decisions
    {
        return Hours::allowed($defaults->with(
            priceLevel: $this->input->priceLevel,
            openDayParts: $this->input->openDayParts,
            staffCount: $this->input->staffCount,
            qualityTier: $this->input->qualityTier,
        ), $business, $sheet);
    }

    public function adjust(Decisions $current, MonthResult $result, GeneratedBusiness $business, ParameterSheet $sheet): Decisions
    {
        return $current;
    }
}
