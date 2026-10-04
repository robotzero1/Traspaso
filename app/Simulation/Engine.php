<?php

namespace App\Simulation;

use App\Simulation\Costs\MonthlyCosts;
use App\Simulation\Data\BusinessState;
use App\Simulation\Data\DayPartResult;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\MarketContext;
use App\Simulation\Data\MonthResult;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Demand\CaptureRate;
use App\Simulation\Demand\Covers;
use App\Simulation\Demand\PotentialCustomers;
use App\Simulation\Demand\QualityScore;
use App\Simulation\Demand\Revenue;
use App\Simulation\Demand\Seasonality;
use App\Simulation\Demand\Staffing;
use App\Simulation\Exceptions\DecisionNotAllowed;
use App\Simulation\Rng\SeededRng;
use App\Simulation\State\StateEvolution;

/**
 * Simulates one month of trading (SPEC §6). Each step lives in its own
 * class; this only wires them together.
 *
 * Pass a generator dedicated to this month, e.g.
 * (new SeededRng($game->seed))->fork("month-{$n}"), so replaying a month
 * gives the same result.
 *
 * Not yet simulated: events (step 7) and competitor moves (step 9).
 * Competitors pass through unchanged and no events happen.
 */
final class Engine
{
    public function simulateMonth(BusinessState $state, Decisions $decisions, MarketContext $context, SeededRng $rng): MonthResult
    {
        $sheet = new ParameterSheet($context->parameters);
        $this->guardLicence($state, $decisions, $sheet);

        $profile = $state->profile;
        $potential = new PotentialCustomers($sheet);
        $covers = new Covers($sheet);
        $revenue = new Revenue($sheet);

        // 1. Seasonality & weather
        $season = (new Seasonality($sheet))->forMonth($context->calendarMonth, $decisions->openDaysPerWeek);

        // 3. Capture rate (needs quality, which doesn't depend on demand)
        $quality = (new QualityScore($sheet))->score($state, $decisions);
        $capture = (new CaptureRate($sheet))->rate($state, $decisions, $quality, $context->competitors);

        $staffing = Staffing::for($decisions, $sheet);
        $noise = $potential->noise($rng->fork('demand'));
        $dayParts = [];
        $totalDemand = 0.0;
        $totalServiceCapacity = 0.0;

        foreach ($decisions->openDayParts as $part) {
            // 2. Potential customers
            $partPotential = $potential->forDayPart($profile, $part, $season, $noise);

            // 4. Covers
            $partDemand = $partPotential * $capture;
            $partCapacity = $covers->capacity($profile, $part, $season, $staffing);
            $partCovers = $covers->covers($partDemand, $partCapacity);

            // 5. Revenue
            $partRevenue = $revenue->netRevenueCents($partCovers, $revenue->ticketCents($profile, $part, $decisions));

            $totalDemand += $partDemand;
            $totalServiceCapacity += $covers->serviceCapacity($part, $season, $staffing);
            $dayParts[] = new DayPartResult(
                dayPart: $part,
                potentialCustomers: (int) round($partPotential),
                demand: (int) round($partDemand),
                capacity: (int) floor($partCapacity),
                covers: $partCovers,
                revenueCents: $partRevenue,
            );
        }

        $revenueCents = array_sum(array_map(fn (DayPartResult $part) => $part->revenueCents, $dayParts));

        // 6. Costs
        $costs = (new MonthlyCosts($sheet))->calculate($state, $decisions, $season, $revenueCents);
        $cashAfter = $state->cashCents + $revenueCents - $costs->totalCents();

        // 8. State evolution
        $utilisation = $totalServiceCapacity > 0 ? $totalDemand / $totalServiceCapacity : 0.0;
        $stateAfter = (new StateEvolution($sheet))->next($state, $decisions, $quality, $utilisation, $cashAfter);

        // 10. Result
        return new MonthResult(
            gameMonth: $context->gameMonth,
            customers: array_sum(array_map(fn (DayPartResult $part) => $part->covers, $dayParts)),
            revenueCents: $revenueCents,
            costs: $costs,
            stateAfter: $stateAfter,
            competitorsAfter: $context->competitors,
            events: [],
            dayParts: $dayParts,
        );
    }

    private function guardLicence(BusinessState $state, Decisions $decisions, ParameterSheet $sheet): void
    {
        $licence = $state->profile->licence->value;
        $allowed = $sheet->array("licence_day_parts.{$licence}");

        foreach ($decisions->openDayParts as $part) {
            if (! in_array($part->value, $allowed, true)) {
                throw new DecisionNotAllowed("A [{$licence}] licence doesn't allow opening for [{$part->value}].");
            }
        }
    }
}
