<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\ParameterSheet;

/**
 * Step 4: customers actually served in a day part — demand, capped by
 * what the seats and the people on shift can handle.
 */
final readonly class Covers
{
    public function __construct(private ParameterSheet $sheet) {}

    public function capacity(BusinessProfile $profile, DayPart $part, SeasonalFactors $season, Staffing $staffing): float
    {
        $hours = (new DayPartSchedule($this->sheet))->hours($part) * $season->openDays;
        $seats = $profile->indoorSeats + $profile->terraceSeats * $season->terraceUsableShare;

        $seatCapacity = $seats * $this->sheet->float("day_parts.{$part->value}.turnover_per_seat_hour") * $hours;
        $serviceCapacity = $staffing->customersPerHour * $hours;

        return min($seatCapacity, $serviceCapacity);
    }

    /** The customers on the floor and staff could have served, ignoring seats. */
    public function serviceCapacity(DayPart $part, SeasonalFactors $season, Staffing $staffing): float
    {
        return $staffing->customersPerHour * (new DayPartSchedule($this->sheet))->hours($part) * $season->openDays;
    }

    public function covers(float $demand, float $capacity): int
    {
        return (int) floor(max(0.0, min($demand, $capacity)));
    }
}
