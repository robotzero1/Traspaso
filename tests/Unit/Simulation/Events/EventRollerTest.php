<?php

use App\Simulation\Data\DayPart;
use App\Simulation\Data\EventRecord;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Events\EventConditions;
use App\Simulation\Events\EventOccurrence;
use App\Simulation\Events\EventRoller;
use App\Simulation\Events\EventSignals;
use App\Simulation\Rng\SeededRng;
use Tests\Support\EventFixtures;
use Tests\Support\SimulationFixtures;

function rollWith(array $parameters, int $seed = 1, ?EventConditions $conditions = null): array
{
    return (new EventRoller(new ParameterSheet($parameters)))->roll(
        EventSignals::from(SimulationFixtures::state(), 55.0, 0.5, 0.8),
        $conditions ?? new EventConditions(6, [DayPart::Morning, DayPart::Evening], true, 2, 2),
        3,
        new SeededRng($seed),
    );
}

function types(array $occurrences): array
{
    return array_map(fn (EventOccurrence $o) => $o->record->type, $occurrences);
}

it('fires an event whose probability is 1', function () {
    $rolled = rollWith(EventFixtures::only('burglary'));

    expect(types($rolled))->toBe(['burglary'])
        ->and($rolled[0]->record->month)->toBe(3)
        ->and($rolled[0]->effects->costCents)->toBe(100_000)
        ->and($rolled[0]->record->payload['effects']['cost_cents'])->toBe(100_000);
});

it('never fires with no library', function () {
    expect(rollWith(EventFixtures::quiet()))->toBe([]);
});

it('skips events whose requirements fail', function () {
    $closedAtNight = new EventConditions(6, [DayPart::Morning], true, 2, 2);

    expect(rollWith(EventFixtures::only('noise_complaint'), conditions: $closedAtNight))->toBe([]);
});

it('caps events per month', function () {
    $parameters = SimulationFixtures::parameters();

    foreach ($parameters['events']['library'] as $type => $event) {
        $parameters['events']['library'][$type]['probability'] = ['base' => 1.0];
    }

    $parameters['events']['max_per_month'] = 3;

    expect(rollWith($parameters))->toHaveCount(3);
});

it('is deterministic and varies with the seed', function () {
    $parameters = SimulationFixtures::parameters();

    foreach ($parameters['events']['library'] as $type => $event) {
        $parameters['events']['library'][$type]['probability'] = ['base' => 0.2];
    }

    $bySeed = array_map(fn (int $seed) => types(rollWith($parameters, $seed)), range(1, 20));

    expect(types(rollWith($parameters, 7)))->toBe(types(rollWith($parameters, 7)))
        ->and(count(array_unique(array_map('serialize', $bySeed))))->toBeGreaterThan(5);
});

it('happens at about the configured rate', function () {
    $parameters = EventFixtures::only('burglary');
    $parameters['events']['library']['burglary']['probability'] = ['base' => 0.25];

    $hits = count(array_filter(range(1, 2000), fn (int $seed) => rollWith($parameters, $seed) !== []));

    expect($hits / 2000)->toEqualWithDelta(0.25, 0.03);
});

it('draws an outcome by weight and records it', function () {
    $outcomes = array_map(
        fn (int $seed) => rollWith(EventFixtures::only('health_inspection'), $seed)[0]->record->payload['outcome'],
        range(1, 300),
    );

    expect(array_unique($outcomes))->toContain('passed', 'minor_fine')
        ->and(rollWith(EventFixtures::only('health_inspection', 'serious_fine'))[0]->effects->costCents)->toBe(250_000);
});

it('offers choices and describes them', function () {
    $record = rollWith(EventFixtures::only('equipment_failure'))[0]->record;

    expect($record)->toBeInstanceOf(EventRecord::class)
        ->and($record->awaitsChoice())->toBeTrue()
        ->and($record->choices)->toBe(['repair', 'limp_on'])
        ->and($record->payload['default_choice'])->toBe('limp_on')
        ->and($record->payload['choice_effects']['repair']['cost_cents'])->toBe(140_000);
});
