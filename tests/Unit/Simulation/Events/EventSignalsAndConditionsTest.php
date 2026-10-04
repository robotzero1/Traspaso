<?php

use App\Simulation\Data\DayPart;
use App\Simulation\Events\EventConditions;
use App\Simulation\Events\EventSignals;
use Tests\Support\SimulationFixtures;

function signals(array $state = [], float $quality = 55.0, float $utilisation = 0.5): EventSignals
{
    return EventSignals::from(SimulationFixtures::state()->with(...$state), $quality, $utilisation, 0.8);
}

it('evaluates base + factor × signal', function () {
    $s = signals(['equipmentHealth' => 60.0, 'staffMorale' => 40.0, 'staffCount' => 3]);

    expect($s->evaluate(['base' => 0.02]))->toBe(0.02)
        ->and($s->evaluate(['base' => 0.02, 'equipment_wear' => 0.1]))->toEqualWithDelta(0.02 + 0.1 * 0.4, 1e-9)
        ->and($s->evaluate(['low_morale' => 0.5, 'staff_count' => 0.01]))->toEqualWithDelta(0.3 + 0.03, 1e-9);
});

it('measures overwork above the comfortable utilisation only', function () {
    expect(signals(utilisation: 0.5)->evaluate(['overwork' => 1.0]))->toBe(0.0)
        ->and(signals(utilisation: 1.1)->evaluate(['overwork' => 1.0]))->toEqualWithDelta(0.3, 1e-9);
});

it('clamps to 0–1', function () {
    expect(signals()->evaluate(['base' => 2.0]))->toBe(1.0)
        ->and(signals()->evaluate(['base' => -1.0]))->toBe(0.0);
});

it('rejects unknown signals', function () {
    signals()->evaluate(['weather' => 1.0]);
})->throws(InvalidArgumentException::class, 'Unknown event signal [weather]');

function conditions(array $overrides = []): EventConditions
{
    return new EventConditions(...array_merge([
        'calendarMonth' => 6,
        'openDayParts' => [DayPart::Morning, DayPart::Evening],
        'hasTerrace' => true,
        'staffCount' => 2,
        'competitorCount' => 1,
    ], $overrides));
}

it('checks requirements', function (array $requires, array $overrides, bool $allowed) {
    expect(conditions($overrides)->allow($requires))->toBe($allowed);
})->with([
    'no requirements' => [[], [], true],
    'month matches' => [['months' => [5, 6]], [], true],
    'month outside' => [['months' => [5, 6]], ['calendarMonth' => 9], false],
    'open in the evening' => [['open_any' => ['evening', 'night']], [], true],
    'closed in the evening' => [['open_any' => ['evening', 'night']], ['openDayParts' => [DayPart::Morning]], false],
    'has a terrace' => [['terrace' => true], [], true],
    'no terrace' => [['terrace' => true], ['hasTerrace' => false], false],
    'enough staff' => [['min_staff' => 2], [], true],
    'too few staff' => [['min_staff' => 1], ['staffCount' => 0], false],
    'has competitors' => [['has_competitors' => true], [], true],
    'no competitors' => [['has_competitors' => true], ['competitorCount' => 0], false],
    'all must hold' => [['months' => [6], 'min_staff' => 3], [], false],
]);

it('rejects unknown requirements', function () {
    conditions()->allow(['moon_phase' => 'full']);
})->throws(InvalidArgumentException::class, 'Unknown event requirement [moon_phase]');
