<?php

use App\Simulation\Valuation\BusinessValuation;
use Tests\Support\SimulationFixtures;

function valuation(): BusinessValuation
{
    return new BusinessValuation(SimulationFixtures::sheet());
}

it('values a business with no trading history at its location and fixtures', function () {
    $config = SimulationFixtures::parameters()['valuation'];
    $state = SimulationFixtures::state()->with(equipmentHealth: 100.0);

    expect(valuation()->valueCents($state, 2_000_000, []))
        ->toBe((int) (round(2_000_000 * ($config['location_share'] + $config['fixtures_share']) / $config['rounding_cents']) * $config['rounding_cents']));
});

it('wears the fixtures down with the equipment', function () {
    $healthy = valuation()->fixturesCents(SimulationFixtures::state()->with(equipmentHealth: 100.0), 2_000_000);
    $worn = valuation()->fixturesCents(SimulationFixtures::state()->with(equipmentHealth: 0.0), 2_000_000);

    expect($worn)->toEqualWithDelta($healthy * SimulationFixtures::parameters()['valuation']['equipment_base'], 1e-6);
});

it('adds goodwill from recent profits and reputation', function () {
    $state = SimulationFixtures::state();

    expect(valuation()->goodwillCents($state, [100_000, 100_000]))->toBeGreaterThan(0.0)
        ->and(valuation()->goodwillCents($state->with(reputation: 90.0), [100_000]))
        ->toBeGreaterThan(valuation()->goodwillCents($state->with(reputation: 20.0), [100_000]))
        ->and(valuation()->valueCents($state, 2_000_000, [200_000]))
        ->toBeGreaterThan(valuation()->valueCents($state, 2_000_000, [0]));
});

it('counts only the most recent months', function () {
    $months = SimulationFixtures::parameters()['valuation']['profit_months'];
    $old = array_fill(0, 5, -1_000_000);
    $recent = array_fill(0, $months, 100_000);

    expect(valuation()->goodwillCents(SimulationFixtures::state(), [...$old, ...$recent]))
        ->toBe(valuation()->goodwillCents(SimulationFixtures::state(), $recent));
});

it('gives no goodwill, never negative, for a loss-maker', function () {
    expect(valuation()->goodwillCents(SimulationFixtures::state(), [-300_000, -100_000]))->toBe(0.0);
});

it('rounds to the configured step', function () {
    $step = SimulationFixtures::parameters()['valuation']['rounding_cents'];

    expect(valuation()->valueCents(SimulationFixtures::state(), 1_234_567, [87_654]) % $step)->toBe(0);
});
