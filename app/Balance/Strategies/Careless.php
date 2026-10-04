<?php

namespace App\Balance\Strategies;

use App\Balance\Strategy;
use App\Generation\GeneratedBusiness;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\MonthResult;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Data\QualityTier;
use App\Simulation\Rng\SeededRng;

/** Overprices cheap stock, overstaffs, opens all hours and splashes out on ads. */
final class Careless implements Strategy
{
    public function key(): string
    {
        return 'careless';
    }

    public function description(): string
    {
        return 'Random café; 50% above average prices, budget stock, 6 staff, open all hours 7 days, €2,000/month ads';
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
        return Hours::allowed($defaults->with(
            priceLevel: 1.5,
            openDayParts: DayPart::cases(),
            openDaysPerWeek: 7,
            staffCount: 6,
            marketingSpendCents: 200_000,
            qualityTier: QualityTier::Budget,
        ), $business, $sheet);
    }

    public function adjust(Decisions $current, MonthResult $result, GeneratedBusiness $business, ParameterSheet $sheet): Decisions
    {
        return $current;
    }
}
