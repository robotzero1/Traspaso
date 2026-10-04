<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\DayPart;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\ParameterSheet;

/**
 * Hours per day part, and the opening hours that follow from decisions.
 */
final readonly class DayPartSchedule
{
    public function __construct(private ParameterSheet $sheet) {}

    public function hours(DayPart $part): float
    {
        return $this->sheet->float("day_parts.{$part->value}.end_hour")
            - $this->sheet->float("day_parts.{$part->value}.start_hour");
    }

    public function hoursPerDay(Decisions $decisions): float
    {
        return array_sum(array_map(fn (DayPart $part) => $this->hours($part), $decisions->openDayParts));
    }

    public function hoursPerWeek(Decisions $decisions): float
    {
        return $this->hoursPerDay($decisions) * $decisions->openDaysPerWeek;
    }
}
