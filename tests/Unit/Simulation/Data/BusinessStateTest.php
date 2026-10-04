<?php

use App\Simulation\Data\BusinessState;
use Tests\Support\SimulationFixtures;

it('returns a changed copy from with() and leaves the original alone', function () {
    $state = SimulationFixtures::state();
    $changed = $state->with(reputation: 72.5, cashCents: -500);

    expect($changed)->toBeInstanceOf(BusinessState::class)
        ->and($changed->reputation)->toBe(72.5)
        ->and($changed->cashCents)->toBe(-500)
        ->and($changed->profile)->toBe($state->profile)
        ->and($state->reputation)->toBe(50.0)
        ->and($state->cashCents)->toBe(2_000_000);
});

it('validates values passed to with()', function () {
    SimulationFixtures::state()->with(reputation: 101.0);
})->throws(InvalidArgumentException::class, 'reputation must be between 0 and 100');

it('rejects unknown properties in with()', function () {
    SimulationFixtures::state()->with(happiness: 3);
})->throws(InvalidArgumentException::class, 'unknown property [happiness]');

it('rejects positional arguments in with()', function () {
    SimulationFixtures::state()->with(3);
})->throws(InvalidArgumentException::class, 'unknown property');

it('rejects out-of-range state', function (array $changes) {
    expect(fn () => SimulationFixtures::state()->with(...$changes))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'reputation below 0' => [['reputation' => -0.1]],
    'negative staff' => [['staffCount' => -1]],
    'morale above 100' => [['staffMorale' => 100.5]],
    'equipment health below 0' => [['equipmentHealth' => -1.0]],
    'negative equipment age' => [['equipmentAgeMonths' => -1]],
    'stock quality above 100' => [['stockQuality' => 120.0]],
]);

it('is readonly', function () {
    $state = SimulationFixtures::state();
    $state->reputation = 10.0;
})->throws(Error::class);
