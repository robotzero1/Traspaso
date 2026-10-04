<?php

use App\Simulation\Data\DayPart;
use App\Simulation\Demand\Covers;
use App\Simulation\Demand\SeasonalFactors;
use App\Simulation\Demand\Staffing;
use Tests\Support\SimulationFixtures;

it('serves demand when there is room', function () {
    expect((new Covers(SimulationFixtures::sheet()))->covers(120.7, 500.0))->toBe(120);
});

it('turns customers away beyond capacity', function () {
    expect((new Covers(SimulationFixtures::sheet()))->covers(800.0, 450.4))->toBe(450)
        ->and((new Covers(SimulationFixtures::sheet()))->covers(-5.0, 100.0))->toBe(0);
});

it('caps capacity by seats when staff are plentiful', function () {
    $parameters = SimulationFixtures::parameters();
    $profile = SimulationFixtures::profile()->with(indoorSeats: 20, terraceSeats: 10);
    $season = new SeasonalFactors(1.0, 30, 25, 0.5);
    $plentyOfStaff = new Staffing(peopleOnShift: 50.0, customersPerHour: 10_000.0);
    $part = $parameters['day_parts']['lunch'];
    $hours = ($part['end_hour'] - $part['start_hour']) * 25;

    expect((new Covers(SimulationFixtures::sheet()))->capacity($profile, DayPart::Lunch, $season, $plentyOfStaff))
        ->toEqualWithDelta((20 + 10 * 0.5) * $part['turnover_per_seat_hour'] * $hours, 1e-9);
});

it('caps capacity by the people on shift when seats are plentiful', function () {
    $profile = SimulationFixtures::profile()->with(indoorSeats: 500);
    $season = new SeasonalFactors(1.0, 30, 25, 0.5);
    $thinStaff = new Staffing(peopleOnShift: 0.5, customersPerHour: 9.0);
    $covers = new Covers(SimulationFixtures::sheet());

    expect($covers->capacity($profile, DayPart::Lunch, $season, $thinStaff))
        ->toEqualWithDelta($covers->serviceCapacity(DayPart::Lunch, $season, $thinStaff), 1e-9);
});

it('uses the terrace more in good weather', function () {
    $covers = new Covers(SimulationFixtures::sheet());
    $profile = SimulationFixtures::profile()->with(indoorSeats: 10, terraceSeats: 30);
    $staff = new Staffing(peopleOnShift: 50.0, customersPerHour: 10_000.0);

    expect($covers->capacity($profile, DayPart::Afternoon, new SeasonalFactors(1.0, 30, 25, 0.9), $staff))
        ->toBeGreaterThan($covers->capacity($profile, DayPart::Afternoon, new SeasonalFactors(1.0, 30, 25, 0.2), $staff));
});
