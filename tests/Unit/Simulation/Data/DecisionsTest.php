<?php

use App\Simulation\Data\DayPart;
use App\Simulation\Data\QualityTier;
use Tests\Support\SimulationFixtures;

it('accepts a typical month of decisions', function () {
    $decisions = SimulationFixtures::decisions()->with(priceLevel: 1.5, qualityTier: QualityTier::Premium);

    expect($decisions->priceLevel)->toBe(1.5)
        ->and($decisions->qualityTier)->toBe(QualityTier::Premium);
});

it('allows a split shift', function () {
    $decisions = SimulationFixtures::decisions()->with(openDayParts: [DayPart::Morning, DayPart::Evening]);

    expect($decisions->isOpenFor(DayPart::Morning))->toBeTrue()
        ->and($decisions->isOpenFor(DayPart::Evening))->toBeTrue()
        ->and($decisions->isOpenFor(DayPart::Afternoon))->toBeFalse();
});

it('rejects impossible decisions', function (array $changes) {
    expect(fn () => SimulationFixtures::decisions()->with(...$changes))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'zero price level' => [['priceLevel' => 0.0]],
    'no day parts' => [['openDayParts' => []]],
    'repeated day part' => [['openDayParts' => [DayPart::Lunch, DayPart::Lunch]]],
    'day part of the wrong type' => [['openDayParts' => ['lunch']]],
    'keyed day parts' => [['openDayParts' => ['a' => DayPart::Lunch]]],
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
