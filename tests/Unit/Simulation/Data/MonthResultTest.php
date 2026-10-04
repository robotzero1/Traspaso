<?php

use App\Simulation\Data\CostBreakdown;
use App\Simulation\Data\EventRecord;
use App\Simulation\Data\MonthResult;
use Tests\Support\SimulationFixtures;

it('totals the cost breakdown', function () {
    expect(SimulationFixtures::costs()->totalCents())->toBe(907_500);
});

it('rejects negative costs', function (string $field) {
    $values = [
        'cogsCents' => 0, 'staffCents' => 0, 'rentCents' => 0, 'utilitiesCents' => 0,
        'marketingCents' => 0, 'otherCents' => 0, 'taxesCents' => 0,
    ];

    expect(fn () => new CostBreakdown(...array_merge($values, [$field => -1])))
        ->toThrow(InvalidArgumentException::class, "{$field} must not be negative");
})->with(['cogsCents', 'staffCents', 'rentCents', 'utilitiesCents', 'marketingCents', 'otherCents', 'taxesCents']);

it('derives profit from revenue and costs, and cash from the closing state', function () {
    $result = new MonthResult(
        gameMonth: 3,
        customers: 4_000,
        revenueCents: 1_000_000,
        costs: SimulationFixtures::costs(),
        stateAfter: SimulationFixtures::state()->with(cashCents: 2_092_500),
    );

    expect($result->profitCents())->toBe(92_500)
        ->and($result->cashAfterCents())->toBe(2_092_500);
});

it('allows a loss', function () {
    $result = new MonthResult(
        gameMonth: 1,
        customers: 0,
        revenueCents: 0,
        costs: SimulationFixtures::costs(),
        stateAfter: SimulationFixtures::state(),
    );

    expect($result->profitCents())->toBe(-907_500);
});

it('rejects invalid results', function (array $overrides) {
    $make = fn () => new MonthResult(...array_merge([
        'gameMonth' => 1,
        'customers' => 0,
        'revenueCents' => 0,
        'costs' => SimulationFixtures::costs(),
        'stateAfter' => SimulationFixtures::state(),
    ], $overrides));

    expect($make)->toThrow(InvalidArgumentException::class);
})->with([
    'game month 13' => [['gameMonth' => 13]],
    'negative customers' => [['customers' => -1]],
    'negative revenue' => [['revenueCents' => -1]],
    'events of the wrong type' => [['events' => ['heatwave']]],
    'competitors of the wrong type' => [['competitorsAfter' => [SimulationFixtures::state()]]],
]);

it('tracks whether an event is waiting for a choice', function () {
    $event = new EventRecord('equipment_failure', ['repair_cost_cents' => 140_000], ['repair', 'limp_on']);

    expect($event->awaitsChoice())->toBeTrue()
        ->and((new EventRecord('equipment_failure', [], ['repair', 'limp_on'], 'repair'))->awaitsChoice())->toBeFalse()
        ->and((new EventRecord('heatwave'))->awaitsChoice())->toBeFalse();
});

it('rejects invalid events', function (Closure $make) {
    expect($make)->toThrow(InvalidArgumentException::class);
})->with([
    'blank type' => fn () => new EventRecord(''),
    'choice not offered' => fn () => new EventRecord('inspection', [], ['pay_fine'], 'appeal'),
    'choice without options' => fn () => new EventRecord('heatwave', [], [], 'stay_open'),
    'duplicate choices' => fn () => new EventRecord('inspection', [], ['pay', 'pay']),
    'keyed choices' => fn () => new EventRecord('inspection', [], ['a' => 'pay']),
]);
