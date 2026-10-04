<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\ParameterSheet;

/**
 * Step 1: seasonality and weather. Turns the calendar month into a demand
 * multiplier, the number of trading days and how usable the terrace is.
 */
final readonly class Seasonality
{
    /** Calendar facts, not tunables. February is taken as 28 days. */
    private const DAYS_IN_MONTH = [1 => 31, 2 => 28, 3 => 31, 4 => 30, 5 => 31, 6 => 30, 7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31];

    public function __construct(private ParameterSheet $sheet) {}

    public function forMonth(int $calendarMonth, int $openDaysPerWeek): SeasonalFactors
    {
        $days = self::DAYS_IN_MONTH[$calendarMonth];
        $terraceDays = $this->sheet->float("terrace_usable_days.days.{$calendarMonth}");

        return new SeasonalFactors(
            demandMultiplier: $this->sheet->float("seasonality.multipliers.{$calendarMonth}"),
            daysInMonth: $days,
            openDays: (int) round($days * $openDaysPerWeek / 7),
            terraceUsableShare: min(1.0, max(0.0, $terraceDays / $days)),
        );
    }
}
