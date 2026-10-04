<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\Decisions;
use App\Simulation\Data\ParameterSheet;

/**
 * How many people are on the floor while open, and how many customers
 * an hour they can serve. The owner works alongside the staff. Every open
 * hour needs at least staff.min_on_shift people: hours the owner and staff
 * can't cover are filled by paid part-time cover (MonthlyCosts charges
 * them), so opening longer costs wages, not just utilities.
 */
final readonly class Staffing
{
    public function __construct(
        public float $peopleOnShift,
        public float $customersPerHour,
        public float $coverHoursPerWeek = 0.0,
    ) {}

    public static function for(Decisions $decisions, ParameterSheet $sheet): self
    {
        $weeklyPeopleHours = $decisions->staffCount * $sheet->float('staff.full_time_hours_per_week')
            + $sheet->float('service.owner_hours_per_week');
        $weeklyOpenHours = (new DayPartSchedule($sheet))->hoursPerWeek($decisions);
        $cover = max(0.0, $weeklyOpenHours * $sheet->float('staff.min_on_shift') - $weeklyPeopleHours);
        $peopleOnShift = ($weeklyPeopleHours + $cover) / $weeklyOpenHours;

        return new self(
            peopleOnShift: $peopleOnShift,
            customersPerHour: $peopleOnShift * $sheet->float('service.customers_per_person_hour'),
            coverHoursPerWeek: $cover,
        );
    }
}
