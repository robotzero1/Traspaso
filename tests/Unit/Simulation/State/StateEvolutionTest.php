<?php

use App\Simulation\State\StateEvolution;
use Tests\Support\SimulationFixtures;

function evolution(): StateEvolution
{
    return new StateEvolution(SimulationFixtures::sheet());
}

function nextState(array $state = [], array $decisions = [], float $quality = 55.0, float $utilisation = 0.5, int $cash = 123)
{
    return evolution()->next(
        SimulationFixtures::state()->with(...$state),
        SimulationFixtures::decisions()->with(...$decisions),
        $quality,
        $utilisation,
        $cash,
    );
}

it('records cash, staff and quality and ages the equipment', function () {
    $state = SimulationFixtures::state();
    $next = nextState(decisions: ['staffCount' => 4], quality: 61.5, cash: -999);

    expect($next->cashCents)->toBe(-999)
        ->and($next->staffCount)->toBe(4)
        ->and($next->stockQuality)->toBe(61.5)
        ->and($next->equipmentAgeMonths)->toBe($state->equipmentAgeMonths + 1)
        ->and($next->profile)->toEqual($state->profile);
});

it('moves reputation part of the way towards its target', function () {
    $rate = SimulationFixtures::parameters()['reputation']['adjustment_rate'];
    $state = SimulationFixtures::state();
    $target = evolution()->reputationTarget(55.0, 1.0, evolution()->serviceScore($state->staffMorale, 0.5));

    expect(nextState()->reputation)->toEqualWithDelta($state->reputation + $rate * ($target - $state->reputation), 1e-9);
});

it('rewards quality and punishes overpricing in the reputation target', function () {
    expect(evolution()->reputationTarget(80.0, 1.0, 50.0))->toBeGreaterThan(evolution()->reputationTarget(35.0, 1.0, 50.0))
        ->and(evolution()->reputationTarget(55.0, 1.5, 50.0))->toBeLessThan(evolution()->reputationTarget(55.0, 1.0, 50.0) - 20)
        ->and(evolution()->reputationTarget(55.0, 0.9, 50.0))->toBeGreaterThan(evolution()->reputationTarget(55.0, 1.0, 50.0));
});

it('punishes overpricing more than it rewards discounts', function () {
    $base = evolution()->reputationTarget(55.0, 1.0, 50.0);

    expect($base - evolution()->reputationTarget(55.0, 1.2, 50.0))
        ->toBeGreaterThan(evolution()->reputationTarget(55.0, 0.8, 50.0) - $base);
});

it('lets service suffer when the floor is overloaded', function () {
    expect(evolution()->serviceScore(70.0, 1.5))->toBeLessThan(evolution()->serviceScore(70.0, 0.5))
        ->and(evolution()->serviceScore(70.0, 0.5))->toBe(evolution()->serviceScore(70.0, 0.1))
        ->and(evolution()->serviceScore(90.0, 0.5))->toBeGreaterThan(evolution()->serviceScore(40.0, 0.5));
});

it('wears staff down when overworked, and lets them recover', function () {
    $tired = nextState(['staffMorale' => 70.0], utilisation: 1.6)->staffMorale;
    $recovering = nextState(['staffMorale' => 30.0], utilisation: 0.4)->staffMorale;

    expect($tired)->toBeLessThan(70.0)
        ->and($recovering)->toBeGreaterThan(30.0);
});

it('wears equipment faster as it ages, never below zero', function () {
    expect(evolution()->wear(180))->toBeGreaterThan(evolution()->wear(12))
        ->and(nextState(['equipmentHealth' => 80.0])->equipmentHealth)->toBe(80.0 - evolution()->wear(SimulationFixtures::state()->equipmentAgeMonths))
        ->and(nextState(['equipmentHealth' => 0.5])->equipmentHealth)->toBe(0.0);
});

it('keeps scores within 0–100', function () {
    $worst = nextState(['reputation' => 0.0, 'staffMorale' => 0.0], ['priceLevel' => 3.0], quality: 0.0, utilisation: 5.0);
    $best = nextState(['reputation' => 100.0, 'staffMorale' => 100.0], ['priceLevel' => 0.5], quality: 100.0, utilisation: 0.0);

    expect($worst->reputation)->toBe(0.0)
        ->and($worst->staffMorale)->toBe(0.0)
        ->and($best->reputation)->toBeLessThanOrEqual(100.0)
        ->and($best->staffMorale)->toBeLessThanOrEqual(100.0);
});
