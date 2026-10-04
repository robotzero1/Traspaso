<?php

use App\Simulation\Events\ChoiceResolver;
use App\Simulation\Exceptions\DecisionNotAllowed;
use Tests\Support\EventFixtures;
use Tests\Support\SimulationFixtures;

function resolver(): ChoiceResolver
{
    return new ChoiceResolver(SimulationFixtures::sheet());
}

it("applies the player's choice", function () {
    $event = EventFixtures::pending('equipment_failure', 2);
    $decisions = SimulationFixtures::decisions()->with(eventChoices: ['2:equipment_failure' => 'repair']);

    [$resolved] = resolver()->resolve([$event], $decisions);

    expect($resolved->record->choice)->toBe('repair')
        ->and($resolved->effects->costCents)->toBe(140_000)
        ->and($resolved->effects->equipmentHealth)->toBe(40.0);
});

it('falls back to the default choice', function () {
    [$resolved] = resolver()->resolve([EventFixtures::pending('equipment_failure')], SimulationFixtures::decisions());

    expect($resolved->record->choice)->toBe('limp_on')
        ->and($resolved->effects->modifiers)->toHaveCount(2);
});

it('ignores choices for events that are not pending', function () {
    $decisions = SimulationFixtures::decisions()->with(eventChoices: ['9:rent_review' => 'negotiate']);

    expect(resolver()->resolve([], $decisions))->toBe([]);
});

it('rejects a choice the event does not offer', function () {
    $decisions = SimulationFixtures::decisions()->with(eventChoices: ['1:equipment_failure' => 'pray']);

    resolver()->resolve([EventFixtures::pending('equipment_failure')], $decisions);
})->throws(DecisionNotAllowed::class, '[pray] is not a choice for event [1:equipment_failure].');
