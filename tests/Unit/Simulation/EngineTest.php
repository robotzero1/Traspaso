<?php

use App\Simulation\Data\DayPart;
use App\Simulation\Data\DayPartResult;
use App\Simulation\Data\Licence;
use App\Simulation\Data\MarketContext;
use App\Simulation\Engine;
use App\Simulation\Exceptions\DecisionNotAllowed;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

function simulate(array $state = [], array $decisions = [], int $calendarMonth = 4, int $seed = 1, array $competitors = [])
{
    return (new Engine)->simulateMonth(
        SimulationFixtures::state()->with(...$state),
        SimulationFixtures::decisions()->with(...$decisions),
        SimulationFixtures::context($calendarMonth, $competitors),
        new SeededRng($seed),
    );
}

it('gives the same result for the same seed', function () {
    expect(serialize(simulate(seed: 5)))->toBe(serialize(simulate(seed: 5)))
        ->and(simulate(seed: 5)->revenueCents)->not->toBe(simulate(seed: 6)->revenueCents);
});

it('reports one result per open day part, adding up to the totals', function () {
    $result = simulate(decisions: ['openDayParts' => [DayPart::Morning, DayPart::Afternoon]]);

    expect(array_map(fn (DayPartResult $p) => $p->dayPart, $result->dayParts))->toBe([DayPart::Morning, DayPart::Afternoon])
        ->and($result->customers)->toBe(array_sum(array_map(fn ($p) => $p->covers, $result->dayParts)))
        ->and($result->revenueCents)->toBe(array_sum(array_map(fn ($p) => $p->revenueCents, $result->dayParts)));

    foreach ($result->dayParts as $part) {
        expect($part->covers)->toBeLessThanOrEqual($part->demand + 1)
            ->and($part->covers)->toBeLessThanOrEqual($part->capacity)
            ->and($part->demand)->toBeLessThanOrEqual($part->potentialCustomers);
    }
});

it('moves cash by the profit, less the owner\'s pay', function () {
    $result = simulate(['cashCents' => 1_000_000]);

    expect($result->ownerPayCents)->toBe(SimulationFixtures::parameters()['owner']['pay_month_cents'])
        ->and($result->cashAfterCents())->toBe(1_000_000 + $result->profitCents() - $result->ownerPayCents)
        ->and($result->profitAfterOwnerPayCents())->toBe($result->profitCents() - $result->ownerPayCents)
        ->and($result->stateAfter->equipmentAgeMonths)->toBe(SimulationFixtures::state()->equipmentAgeMonths + 1);
});

it('sells more in October than in August', function () {
    expect(simulate(calendarMonth: 10)->customers)->toBeGreaterThan(simulate(calendarMonth: 8)->customers);
});

it('sells more with more hours and more days open', function () {
    expect(simulate(decisions: ['openDaysPerWeek' => 7])->customers)->toBeGreaterThan(simulate(decisions: ['openDaysPerWeek' => 5])->customers)
        ->and(simulate(decisions: ['openDayParts' => [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon]])->customers)
        ->toBeGreaterThan(simulate(decisions: ['openDayParts' => [DayPart::Morning]])->customers);
});

it('turns customers away when understaffed at a busy spot', function () {
    $profile = SimulationFixtures::profile()->with(footfall: 10.0, licence: Licence::CafeBar);
    $result = simulate(['profile' => $profile, 'reputation' => 90.0], ['staffCount' => 0, 'openDayParts' => DayPart::cases(), 'openDaysPerWeek' => 7]);

    expect(array_sum(array_map(fn ($p) => $p->lostCovers(), $result->dayParts)))->toBeGreaterThan(0)
        ->and($result->stateAfter->staffMorale)->toBeLessThan(SimulationFixtures::state()->staffMorale);
});

it('loses customers to competitors', function () {
    expect(simulate(competitors: [SimulationFixtures::competitor()])->customers)->toBeLessThan(simulate()->customers);
});

it('refuses to open at night without a licence that allows it', function () {
    simulate(decisions: ['openDayParts' => [DayPart::Evening, DayPart::Night]]);
})->throws(DecisionNotAllowed::class, "A [cafe] licence doesn't allow opening for [night].");

it('opens at night with a café-bar licence', function () {
    $profile = SimulationFixtures::profile()->with(licence: Licence::CafeBar);

    expect(simulate(['profile' => $profile], ['openDayParts' => [DayPart::Night]])->customers)->toBeGreaterThan(0);
});

it('records the game month from the context', function () {
    $context = SimulationFixtures::context();
    $result = (new Engine)->simulateMonth(
        SimulationFixtures::state(),
        SimulationFixtures::decisions(),
        new MarketContext($context->calendarMonth, 7, [], $context->parameters),
        new SeededRng(1),
    );

    expect($result->gameMonth)->toBe(7);
});

it('keeps going past the first year', function () {
    $result = (new Engine)->simulateMonth(
        SimulationFixtures::state(),
        SimulationFixtures::decisions(),
        new MarketContext(3, 50, [], SimulationFixtures::parameters()),
        (new SeededRng(1))->fork('month-50'),
    );

    expect($result->gameMonth)->toBe(50);
});

it('scales demand by how the street has drifted, and moves the drift on', function () {
    $at = fn (float $trend) => simulate(['localTrend' => $trend]);
    $potential = fn ($result) => array_sum(array_map(fn ($p) => $p->potentialCustomers, $result->dayParts));

    expect($potential($at(0.5)))->toEqualWithDelta($potential($at(1.0)) / 2, 2)
        ->and($at(1.0)->stateAfter->localTrend)->not->toBe(1.0);
});
