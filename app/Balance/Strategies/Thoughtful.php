<?php

namespace App\Balance\Strategies;

use App\Balance\Strategy;
use App\Generation\GeneratedBusiness;
use App\Simulation\Costs\MonthlyCosts;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\DayPartResult;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\MonthResult;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Demand\DayPartSchedule;
use App\Simulation\Rng\SeededRng;

/**
 * A sensible player: keeps some working capital, buys a spot with good
 * footfall for its rent and price, opens when people are about, and each
 * month drops a day part that doesn't pay its way and staffs to demand.
 * No price games, no special insight: what a careful first-timer reading
 * the game's screens would do.
 */
final class Thoughtful implements Strategy
{
    public function key(): string
    {
        return 'thoughtful';
    }

    public function description(): string
    {
        return 'Good footfall for the money (one of the 5 best), its 3 busiest day parts; each month closes a day part that costs more than it brings in and staffs to demand';
    }

    public function reserveShare(): float
    {
        return 0.2;
    }

    public function choose(array $affordable, ParameterSheet $sheet, SeededRng $rng): GeneratedBusiness
    {
        // People passing per euro of monthly outgoings, counting the
        // traspaso spread over 4 years, with room enough to serve them.
        $score = fn (GeneratedBusiness $b) => ($b->profile->footfall ** 0.8)
            * min($b->profile->indoorSeats + $b->profile->terraceSeats, 50)
            / ($b->profile->rentMonthCents + $b->traspasoCents / 48);
        usort($affordable, fn (GeneratedBusiness $a, GeneratedBusiness $b) => $score($b) <=> $score($a));

        return $rng->pick(array_slice($affordable, 0, 5));
    }

    public function openingDecisions(GeneratedBusiness $business, Decisions $defaults, ParameterSheet $sheet): Decisions
    {
        return Hours::busiest($defaults, $business, $sheet, 3);
    }

    public function adjust(Decisions $current, MonthResult $result, GeneratedBusiness $business, ParameterSheet $sheet): Decisions
    {
        $decisions = $this->dropLosingDayPart($current, $result, $sheet);

        // Staff to demand: turning many away means hire; a quiet floor means let one go.
        $demand = array_sum(array_map(fn (DayPartResult $p) => $p->demand, $result->dayParts));
        $lost = array_sum(array_map(fn (DayPartResult $p) => $p->lostCovers(), $result->dayParts));
        $capacity = array_sum(array_map(fn (DayPartResult $p) => $p->capacity, $result->dayParts));
        $limit = $sheet->int('decision_limits.staff_count.max');

        if ($demand > 0 && $lost / $demand > 0.1 && $decisions->staffCount < min(4, $limit)) {
            return $decisions->with(staffCount: $decisions->staffCount + 1);
        }

        if ($capacity > 0 && $result->customers / $capacity < 0.4 && $decisions->staffCount > 1) {
            return $decisions->with(staffCount: $decisions->staffCount - 1);
        }

        return $decisions;
    }

    /**
     * The weakest day part, if its gross margin doesn't cover what closing
     * it would save (its utilities and any part-time cover it needs); at
     * least two stay open.
     */
    private function dropLosingDayPart(Decisions $decisions, MonthResult $result, ParameterSheet $sheet): Decisions
    {
        if (count($decisions->openDayParts) <= 2) {
            return $decisions;
        }

        $schedule = new DayPartSchedule($sheet);
        $costs = new MonthlyCosts($sheet);
        $days = $decisions->openDaysPerWeek * 52 / 12;
        $cogs = $sheet->float("cogs.share_of_revenue.{$decisions->qualityTier->value}");
        $worst = null;
        $worstMargin = 0.0;

        foreach ($result->dayParts as $part) {
            $without = $decisions->with(openDayParts: array_values(array_filter($decisions->openDayParts, fn (DayPart $p) => $p !== $part->dayPart)));
            $saved = $costs->coverCents($decisions) - $costs->coverCents($without)
                + $sheet->float('utilities.per_open_hour_cents') * $schedule->hours($part->dayPart) * $days;
            $margin = $part->revenueCents * (1 - $cogs) - $saved;

            if ($margin < $worstMargin) {
                [$worst, $worstMargin] = [$part->dayPart, $margin];
            }
        }

        return $worst === null ? $decisions : $decisions->with(
            openDayParts: array_values(array_filter($decisions->openDayParts, fn (DayPart $p) => $p !== $worst)),
        );
    }
}
