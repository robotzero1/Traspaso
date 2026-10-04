<?php

use App\Generation\Takeover;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\QualityTier;
use Tests\Support\SimulationFixtures;

function takeover(): Takeover
{
    return new Takeover(SimulationFixtures::sheet());
}

it('starts the business from its listing', function () {
    $config = SimulationFixtures::parameters()['takeover'];
    $profile = SimulationFixtures::profile()->with(condition: 8);

    $state = takeover()->initialState($profile, 62.5, 7, 1_234_500);

    expect($state->profile)->toBe($profile)
        ->and($state->cashCents)->toBe(1_234_500)
        ->and($state->reputation)->toBe(62.5)
        ->and($state->equipmentAgeMonths)->toBe(84)
        ->and($state->staffCount)->toBe($config['staff_count'])
        ->and($state->equipmentHealth)->toBe((float) ($config['equipment_health']['base'] + 8 * $config['equipment_health']['per_condition']))
        ->and($state->pendingEvents)->toBe([])
        ->and($state->modifiers)->toBe([]);
});

it('keeps better premises in better shape', function () {
    $profile = SimulationFixtures::profile();

    expect(takeover()->initialState($profile->with(condition: 9), 50.0, 5, 0)->equipmentHealth)
        ->toBeGreaterThan(takeover()->initialState($profile->with(condition: 2), 50.0, 5, 0)->equipmentHealth);
});

it('starts with the configured decisions', function () {
    $decisions = takeover()->defaultDecisions();

    expect($decisions->priceLevel)->toBe(1.0)
        ->and($decisions->openDayParts)->toBe([DayPart::Morning, DayPart::Lunch, DayPart::Afternoon])
        ->and($decisions->qualityTier)->toBe(QualityTier::Standard)
        ->and($decisions->eventChoices)->toBe([]);
});

it('asks a deposit of a number of months of rent', function () {
    $months = SimulationFixtures::parameters()['purchase']['deposit_months_of_rent'];

    expect(takeover()->depositCents(SimulationFixtures::profile()))->toBe(SimulationFixtures::profile()->rentMonthCents * $months);
});
