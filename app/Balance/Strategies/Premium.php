<?php

namespace App\Balance\Strategies;

use App\Balance\Strategy;
use App\Generation\GeneratedBusiness;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\MonthResult;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Data\QualityTier;
use App\Simulation\Rng\SeededRng;

/** The busiest spot it can afford, run upmarket: dearer, better, well staffed. */
final class Premium implements Strategy
{
    public function key(): string
    {
        return 'premium';
    }

    public function description(): string
    {
        return 'Busiest affordable spot, premium quality at 15% above average prices, 3 staff, €300/month marketing';
    }

    public function reserveShare(): float
    {
        return 0.1;
    }

    public function choose(array $affordable, ParameterSheet $sheet, SeededRng $rng): GeneratedBusiness
    {
        usort($affordable, fn (GeneratedBusiness $a, GeneratedBusiness $b) => $b->profile->footfall <=> $a->profile->footfall);

        return $affordable[0];
    }

    public function openingDecisions(GeneratedBusiness $business, Decisions $defaults, ParameterSheet $sheet): Decisions
    {
        return Hours::busiest($defaults->with(
            priceLevel: 1.15,
            staffCount: 3,
            marketingSpendCents: 30_000,
            qualityTier: QualityTier::Premium,
        ), $business, $sheet, 4);
    }

    public function adjust(Decisions $current, MonthResult $result, GeneratedBusiness $business, ParameterSheet $sheet): Decisions
    {
        return $current;
    }
}
