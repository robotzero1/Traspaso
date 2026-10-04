<?php

use App\Simulation\Data\DayPart;
use App\Simulation\Demand\DayPartSchedule;
use App\Simulation\Demand\Staffing;
use Tests\Support\SimulationFixtures;

it('spreads staff and owner hours over the opening hours', function () {
    $parameters = SimulationFixtures::parameters();
    $decisions = SimulationFixtures::decisions()->with(staffCount: 2, openDaysPerWeek: 6);
    $openHours = (new DayPartSchedule(SimulationFixtures::sheet()))->hoursPerWeek($decisions);
    $peopleHours = 2 * $parameters['staff']['full_time_hours_per_week'] + $parameters['service']['owner_hours_per_week'];

    $staffing = Staffing::for($decisions, SimulationFixtures::sheet());

    expect($staffing->peopleOnShift)->toEqualWithDelta($peopleHours / $openHours, 1e-9)
        ->and($staffing->customersPerHour)
        ->toEqualWithDelta($peopleHours / $openHours * $parameters['service']['customers_per_person_hour'], 1e-9);
});

it('thins out as opening hours grow', function () {
    $short = SimulationFixtures::decisions()->with(openDayParts: [DayPart::Morning]);
    $long = SimulationFixtures::decisions()->with(openDayParts: DayPart::cases());

    expect(Staffing::for($short, SimulationFixtures::sheet())->peopleOnShift)
        ->toBeGreaterThan(Staffing::for($long, SimulationFixtures::sheet())->peopleOnShift);
});

it('counts the hours of each day part', function () {
    $schedule = new DayPartSchedule(SimulationFixtures::sheet());
    $decisions = SimulationFixtures::decisions()->with(openDayParts: [DayPart::Morning, DayPart::Night], openDaysPerWeek: 5);
    $morning = $schedule->hours(DayPart::Morning);
    $night = $schedule->hours(DayPart::Night);

    expect($schedule->hoursPerDay($decisions))->toBe($morning + $night)
        ->and($schedule->hoursPerWeek($decisions))->toBe(5 * ($morning + $night));
});

it('needs someone on the floor every open hour, filling the gap with part-time cover', function () {
    $parameters = SimulationFixtures::parameters();
    $sheet = SimulationFixtures::sheet();
    $long = SimulationFixtures::decisions()->with(staffCount: 0, openDayParts: DayPart::cases(), openDaysPerWeek: 7);
    $openHours = (new DayPartSchedule($sheet))->hoursPerWeek($long);
    $staffing = Staffing::for($long, $sheet);

    expect($staffing->coverHoursPerWeek)
        ->toEqualWithDelta($openHours * $parameters['staff']['min_on_shift'] - $parameters['service']['owner_hours_per_week'], 1e-9)
        ->and($staffing->peopleOnShift)->toEqualWithDelta($parameters['staff']['min_on_shift'], 1e-9);
});

it('needs no cover when the owner and staff fill the hours', function () {
    $short = SimulationFixtures::decisions()->with(staffCount: 1, openDayParts: [DayPart::Morning, DayPart::Lunch], openDaysPerWeek: 6);

    expect(Staffing::for($short, SimulationFixtures::sheet())->coverHoursPerWeek)->toBe(0.0);
});
