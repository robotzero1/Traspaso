<?php

use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Kitchen;
use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Demand\PotentialCustomers;
use App\Simulation\Demand\SeasonalFactors;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

function season(float $multiplier = 1.0, int $openDays = 26): SeasonalFactors
{
    return new SeasonalFactors($multiplier, 30, $openDays, 0.5);
}

function evenNeighbourhood(): NeighbourhoodProfile
{
    // Every driver at its reference value.
    return new NeighbourhoodProfile('Even', 60_000, 5.0, 5.0, 5.0, 5.0, 50.0);
}

it('computes potential from footfall, intensity, appeal, hours, days and season', function () {
    $parameters = SimulationFixtures::parameters();
    $profile = SimulationFixtures::profile()->with(neighbourhood: evenNeighbourhood(), footfall: 10.0, kitchen: Kitchen::Basic);
    $part = $parameters['day_parts']['morning'];

    $expected = $parameters['demand']['potential_per_hour_at_footfall_10']
        * $part['intensity']
        * 1.0 // an even neighbourhood's mix
        * $parameters['appeal']['category']['cafe']['morning']
        * ($part['end_hour'] - $part['start_hour'])
        * 26 * 1.1 * 0.9;

    expect((new PotentialCustomers(SimulationFixtures::sheet()))->forDayPart($profile, DayPart::Morning, season(1.1), 0.9))
        ->toEqualWithDelta($expected, 1e-6);
});

it('scales with footfall', function () {
    $potential = new PotentialCustomers(SimulationFixtures::sheet());
    $at = fn (float $footfall) => $potential->forDayPart(SimulationFixtures::profile()->with(footfall: $footfall), DayPart::Lunch, season());

    expect($at(0.0))->toBe(0.0)
        ->and($at(8.0))->toBeGreaterThan($at(4.0))
        ->and($at(4.0))->toBeGreaterThan($at(2.0));
});

it('scales with open days and seasonality', function () {
    $potential = new PotentialCustomers(SimulationFixtures::sheet());
    $profile = SimulationFixtures::profile();

    expect($potential->forDayPart($profile, DayPart::Morning, season(1.0, 20)) * 1.5)
        ->toEqualWithDelta($potential->forDayPart($profile, DayPart::Morning, season(1.0, 30)), 1e-6)
        ->and($potential->forDayPart($profile, DayPart::Morning, season(1.15)))
        ->toBeGreaterThan($potential->forDayPart($profile, DayPart::Morning, season(0.85)));
});

it('scores an even neighbourhood at 1 in every day part', function (DayPart $part) {
    expect((new PotentialCustomers(SimulationFixtures::sheet()))->demandMix(evenNeighbourhood(), $part))
        ->toEqualWithDelta(1.0, 1e-9);
})->with(DayPart::cases());

it('shifts demand towards the day parts a neighbourhood suits', function () {
    $potential = new PotentialCustomers(SimulationFixtures::sheet());
    $offices = new NeighbourhoodProfile('Offices', 60_000, 1.0, 1.0, 10.0, 5.0, 50.0);
    $nightlife = new NeighbourhoodProfile('Nightlife', 60_000, 9.0, 9.0, 1.0, 5.0, 50.0);

    expect($potential->demandMix($offices, DayPart::Lunch))->toBeGreaterThan($potential->demandMix($offices, DayPart::Night))
        ->and($potential->demandMix($nightlife, DayPart::Night))->toBeGreaterThan($potential->demandMix($nightlife, DayPart::Lunch));
});

it("doesn't count location twice: a uniformly busier neighbourhood has the same mix", function (DayPart $part) {
    $potential = new PotentialCustomers(SimulationFixtures::sheet());
    $quiet = new NeighbourhoodProfile('Quiet', 30_000, 2.0, 2.0, 2.0, 2.0, 50.0);
    $busy = new NeighbourhoodProfile('Busy', 60_000, 4.0, 4.0, 4.0, 4.0, 50.0);

    expect($potential->demandMix($busy, $part))->toEqualWithDelta($potential->demandMix($quiet, $part), 1e-9);
})->with(DayPart::cases());

it('gives a café-bar more evening trade than a café', function () {
    $potential = new PotentialCustomers(SimulationFixtures::sheet());
    $cafe = SimulationFixtures::profile();
    $cafeBar = $cafe->with(category: BusinessCategory::CafeBar);

    expect($potential->forDayPart($cafeBar, DayPart::Evening, season()))
        ->toBeGreaterThan($potential->forDayPart($cafe, DayPart::Evening, season()));
});

it('rewards a kitchen at lunch', function () {
    $potential = new PotentialCustomers(SimulationFixtures::sheet());
    $profile = SimulationFixtures::profile();

    expect($potential->forDayPart($profile->with(kitchen: Kitchen::Full), DayPart::Lunch, season()))
        ->toBeGreaterThan($potential->forDayPart($profile->with(kitchen: Kitchen::None), DayPart::Lunch, season()))
        ->and($potential->forDayPart($profile->with(kitchen: Kitchen::Full), DayPart::Morning, season()))
        ->toBe($potential->forDayPart($profile->with(kitchen: Kitchen::None), DayPart::Morning, season()));
});

it('draws clamped, deterministic demand noise', function () {
    $potential = new PotentialCustomers(SimulationFixtures::sheet());
    $draws = array_map(fn (int $seed) => $potential->noise(new SeededRng($seed)), range(1, 500));

    expect(min($draws))->toBeGreaterThanOrEqual(0.8)
        ->and(max($draws))->toBeLessThanOrEqual(1.2)
        ->and(array_sum($draws) / count($draws))->toEqualWithDelta(1.0, 0.01)
        ->and($potential->noise(new SeededRng(9)))->toBe($potential->noise(new SeededRng(9)));
});

it('rejects an unknown demand driver', function () {
    $parameters = SimulationFixtures::parameters();
    $parameters['day_parts']['morning']['demand_mix'] = ['weather' => 1.0];

    (new PotentialCustomers(new ParameterSheet($parameters)))->demandMix(evenNeighbourhood(), DayPart::Morning);
})->throws(InvalidArgumentException::class, 'Unknown demand driver [weather]');
