<?php

use App\Simulation\Data\DayPart;
use App\Simulation\Data\ModifierEffect;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Events\EventDefinition;
use App\Simulation\Events\EventEffects;
use App\Simulation\Events\EventLibrary;
use Tests\Support\SimulationFixtures;

it('reads the library from the parameter sheet', function () {
    $library = new EventLibrary(SimulationFixtures::sheet());

    expect(count($library->definitions))->toBeGreaterThanOrEqual(15)
        ->and($library->get('equipment_failure')->choiceNames())->toBe(['repair', 'limp_on'])
        ->and($library->get('equipment_failure')->defaultChoice)->toBe('limp_on');
});

it('offers choices in several events', function () {
    $withChoices = array_filter((new EventLibrary(SimulationFixtures::sheet()))->definitions, fn ($d) => $d->choices !== []);

    expect(count($withChoices))->toBeGreaterThanOrEqual(5);
});

it('turns modifier config into modifiers', function () {
    $effects = EventEffects::fromConfig(['modifiers' => [
        ['effect' => 'demand', 'value' => 1.2, 'months' => 1, 'day_parts' => ['evening']],
        ['effect' => 'rent', 'value' => 1.05, 'months' => null],
    ]]);

    [$demand, $rent] = $effects->modifiersFor('heatwave');

    expect($demand->effect)->toBe(ModifierEffect::Demand)
        ->and($demand->source)->toBe('heatwave')
        ->and($demand->dayParts)->toBe([DayPart::Evening])
        ->and($rent->monthsRemaining)->toBeNull();
});

it('describes effects without the empty ones', function () {
    expect(EventEffects::fromConfig(['cost_cents' => 500, 'reputation' => -2])->toArray())
        ->toBe(['cost_cents' => 500, 'reputation' => -2.0]);
});

it('rejects invalid event config', function (Closure $read) {
    expect($read)->toThrow(InvalidArgumentException::class);
})->with([
    'unknown effect' => fn () => EventEffects::fromConfig(['teleport' => true]),
    'negative cost' => fn () => EventEffects::fromConfig(['cost_cents' => -1]),
    'unknown modifier effect' => fn () => EventEffects::fromConfig(['modifiers' => [['effect' => 'luck', 'value' => 1, 'months' => 1]]]),
    'no probability' => fn () => EventDefinition::fromConfig('x', []),
    'default choice not offered' => fn () => EventDefinition::fromConfig('x', ['probability' => ['base' => 0.1], 'choices' => ['a' => []], 'default_choice' => 'b']),
    'default without choices' => fn () => EventDefinition::fromConfig('x', ['probability' => ['base' => 0.1], 'default_choice' => 'b']),
    'choices and outcomes' => fn () => EventDefinition::fromConfig('x', [
        'probability' => ['base' => 0.1],
        'choices' => ['a' => []], 'default_choice' => 'a',
        'outcomes' => ['o' => ['weight' => ['base' => 1]]],
    ]),
    'unknown event' => fn () => (new EventLibrary(SimulationFixtures::sheet()))->get('alien_invasion'),
]);

it('validates every event in the real library', function () {
    $parameters = SimulationFixtures::parameters();

    foreach ($parameters['events']['library'] as $type => $config) {
        expect(EventDefinition::fromConfig($type, $config))->toBeInstanceOf(EventDefinition::class);
    }

    expect(new EventLibrary(new ParameterSheet($parameters)))->toBeInstanceOf(EventLibrary::class);
});
