<?php

use App\Simulation\Data\CompetitorState;
use App\Simulation\Events\EffectApplier;
use App\Simulation\Events\EventEffects;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

function applier(): EffectApplier
{
    return new EffectApplier(SimulationFixtures::sheet());
}

it('shifts scores and adds modifiers', function () {
    $state = SimulationFixtures::state();
    $effects = EventEffects::fromConfig([
        'reputation' => -6, 'morale' => -5, 'equipment_health' => 10,
        'modifiers' => [['effect' => 'capacity', 'value' => 0.75, 'months' => 2]],
    ]);

    $after = applier()->applyToState($state, $effects, 'equipment_failure');

    expect($after->reputation)->toBe($state->reputation - 6)
        ->and($after->staffMorale)->toBe($state->staffMorale - 5)
        ->and($after->equipmentHealth)->toBe($state->equipmentHealth + 10)
        ->and($after->modifiers)->toHaveCount(1)
        ->and($after->modifiers[0]->source)->toBe('equipment_failure')
        ->and($after->cashCents)->toBe($state->cashCents);
});

it('keeps scores within 0–100', function () {
    $after = applier()->applyToState(
        SimulationFixtures::state()->with(reputation: 3.0, equipmentHealth: 95.0),
        EventEffects::fromConfig(['reputation' => -10, 'equipment_health' => 40]),
        'x',
    );

    expect($after->reputation)->toBe(0.0)
        ->and($after->equipmentHealth)->toBe(100.0);
});

it('opens a new competitor within the configured ranges', function () {
    $spec = SimulationFixtures::parameters()['events']['library']['competitor_opens']['effects']['add_competitor'];
    $after = applier()->applyToCompetitors([SimulationFixtures::competitor()], EventEffects::fromConfig(['add_competitor' => $spec]), 4, new SeededRng(1));
    $new = $after[1];

    expect($after)->toHaveCount(2)
        ->and($new->id)->toBe('opened-month-4')
        ->and($new->distanceMetres)->toBeGreaterThanOrEqual($spec['distance_metres']['min'])->toBeLessThanOrEqual($spec['distance_metres']['max'])
        ->and($new->seats)->toBeGreaterThanOrEqual($spec['seats']['min'])->toBeLessThanOrEqual($spec['seats']['max']);
});

it('closes the least attractive competitor', function () {
    $strong = new CompetitorState('strong', 'Strong', 100.0, 1.0, 80.0, 80.0, 30);
    $weak = new CompetitorState('weak', 'Weak', 100.0, 1.2, 30.0, 25.0, 30);

    $after = applier()->applyToCompetitors([$strong, $weak], EventEffects::fromConfig(['remove_competitor' => 'weakest']), 4, new SeededRng(1));

    expect(array_map(fn ($c) => $c->id, $after))->toBe(['strong']);
});
