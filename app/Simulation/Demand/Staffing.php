<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\Decisions;
use App\Simulation\Data\ParameterSheet;

/**
 * How many people are on the floor while open, and how many customers
 * an hour they can serve. The owner works alongside the staff.
 */
final readonly class Staffing
{
    public function __construct(
        public float $peopleOnShift,
        public float $customersPerHour,
    ) {}

    public static function for(Decisions $decisions, ParameterSheet $sheet): self
    {
        $weeklyPeopleHours = $decisions->staffCount * $sheet->float('staff.full_time_hours_per_week')
            + $sheet->float('service.owner_hours_per_week');
        $weeklyOpenHours = (new DayPartSchedule($sheet))->hoursPerWeek($decisions);
        $peopleOnShift = $weeklyPeopleHours / $weeklyOpenHours;

        return new self(
            peopleOnShift: $peopleOnShift,
            customersPerHour: $peopleOnShift * $sheet->float('service.customers_per_person_hour'),
        );
    }
}
