<?php

use App\Simulation\Data\DayPart;
use App\Simulation\Data\Modifier;
use App\Simulation\Data\ModifierEffect;
use App\Simulation\Data\ModifierSet;

function modifier(ModifierEffect $effect, float $value, ?int $months = 2, array $dayParts = []): Modifier
{
    return new Modifier('test', $effect, $value, $months, $dayParts);
}

it('counts down and expires', function () {
    $modifier = modifier(ModifierEffect::Demand, 1.2, 2);

    expect($modifier->tick()->monthsRemaining)->toBe(1)
        ->and($modifier->tick()->tick())->toBeNull();
});

it('lasts forever when permanent', function () {
    $modifier = modifier(ModifierEffect::Rent, 1.05, null);

    expect($modifier->tick())->toBe($modifier);
});

it('applies to all day parts unless restricted', function () {
    expect(modifier(ModifierEffect::Demand, 1.2)->appliesTo(DayPart::Night))->toBeTrue()
        ->and(modifier(ModifierEffect::Demand, 1.2, 1, [DayPart::Morning])->appliesTo(DayPart::Night))->toBeFalse();
});

it('rejects invalid modifiers', function (Closure $make) {
    expect($make)->toThrow(InvalidArgumentException::class);
})->with([
    'zero months' => fn () => modifier(ModifierEffect::Demand, 1.1, 0),
    'negative demand multiplier' => fn () => modifier(ModifierEffect::Demand, -0.5),
    'blank source' => fn () => new Modifier(' ', ModifierEffect::Demand, 1.0, 1),
    'day parts of the wrong type' => fn () => modifier(ModifierEffect::Demand, 1.0, 1, ['morning']),
]);

it('multiplies multipliers and adds the rest', function () {
    $set = new ModifierSet([
        modifier(ModifierEffect::Demand, 1.2),
        modifier(ModifierEffect::Demand, 0.5, 1, [DayPart::Morning]),
        modifier(ModifierEffect::Capacity, 0.75),
        modifier(ModifierEffect::Rent, 1.05, null),
        modifier(ModifierEffect::QualityPenalty, 5),
        modifier(ModifierEffect::QualityPenalty, 6),
        modifier(ModifierEffect::CogsShare, 0.02),
        modifier(ModifierEffect::StaffShortage, 1),
        modifier(ModifierEffect::MonthlyCost, 12_000),
    ]);

    expect($set->demand(DayPart::Morning))->toEqualWithDelta(0.6, 1e-9)
        ->and($set->demand(DayPart::Lunch))->toEqualWithDelta(1.2, 1e-9)
        ->and($set->capacity(DayPart::Lunch))->toBe(0.75)
        ->and($set->rent())->toBe(1.05)
        ->and($set->qualityPenalty())->toBe(11.0)
        ->and($set->cogsShare())->toBe(0.02)
        ->and($set->staffShortage())->toBe(1)
        ->and($set->monthlyCostCents())->toBe(12_000);
});

it('is neutral when empty', function () {
    $set = new ModifierSet;

    expect($set->demand(DayPart::Lunch))->toBe(1.0)
        ->and($set->capacity(DayPart::Lunch))->toBe(1.0)
        ->and($set->rent())->toBe(1.0)
        ->and($set->qualityPenalty())->toBe(0.0)
        ->and($set->staffShortage())->toBe(0)
        ->and($set->monthlyCostCents())->toBe(0);
});

it('drops expired modifiers when ticking', function () {
    $set = new ModifierSet([modifier(ModifierEffect::Demand, 1.2, 1), modifier(ModifierEffect::Rent, 1.05, null), modifier(ModifierEffect::Capacity, 0.9, 3)]);

    expect(array_map(fn (Modifier $m) => $m->effect, $set->tick()))->toBe([ModifierEffect::Rent, ModifierEffect::Capacity]);
});

it('counts in days when an event happens during the month', function () {
    $modifier = modifier(ModifierEffect::Demand, 0.8, 2)->inDays();

    expect($modifier->daysRemaining)->toBe(61)
        // The month end leaves it alone; each day runs it down.
        ->and($modifier->tick())->toBe($modifier)
        ->and($modifier->tickDay()->daysRemaining)->toBe(60)
        ->and($modifier->with(daysRemaining: 1)->tickDay())->toBeNull()
        ->and(modifier(ModifierEffect::Rent, 1.05, null)->inDays()->daysRemaining)->toBeNull()
        ->and(modifier(ModifierEffect::Demand, 1.1, 2)->tickDay()->monthsRemaining)->toBe(2);
});

it('drops expired day-counted modifiers when ticking a day', function () {
    $set = new ModifierSet([
        modifier(ModifierEffect::Demand, 0.8)->with(daysRemaining: 1),
        modifier(ModifierEffect::Demand, 1.2)->with(daysRemaining: 3),
        modifier(ModifierEffect::Rent, 1.05),
    ]);

    expect(array_map(fn (Modifier $m) => [$m->value, $m->daysRemaining], $set->tickDay()))->toBe([[1.2, 2], [1.05, null]]);
});
