<?php

use App\Simulation\Data\QualityTier;
use Tests\Support\SimulationFixtures;

it('accepts a typical month of decisions', function () {
    $decisions = SimulationFixtures::decisions()->with(priceLevel: 1.5, qualityTier: QualityTier::Premium);

    expect($decisions->priceLevel)->toBe(1.5)
        ->and($decisions->qualityTier)->toBe(QualityTier::Premium);
});

it('rejects impossible decisions', function (array $changes) {
    expect(fn () => SimulationFixtures::decisions()->with(...$changes))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'zero price level' => [['priceLevel' => 0.0]],
    'zero opening hours' => [['openingHoursPerDay' => 0]],
    '25 opening hours' => [['openingHoursPerDay' => 25]],
    'zero open days' => [['openDaysPerWeek' => 0]],
    '8 open days' => [['openDaysPerWeek' => 8]],
    'negative staff' => [['staffCount' => -1]],
    'negative marketing' => [['marketingSpendCents' => -1]],
]);

it('rejects impossible competitors', function (array $changes) {
    expect(fn () => SimulationFixtures::competitor()->with(...$changes))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'blank id' => [['id' => '']],
    'negative distance' => [['distanceMetres' => -1.0]],
    'zero price level' => [['priceLevel' => 0.0]],
    'quality above 100' => [['quality' => 100.1]],
    'negative reputation' => [['reputation' => -1.0]],
    'negative seats' => [['seats' => -1]],
]);
