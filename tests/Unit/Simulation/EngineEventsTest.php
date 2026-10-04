<?php

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\MarketContext;
use App\Simulation\Data\Modifier;
use App\Simulation\Data\ModifierEffect;
use App\Simulation\Engine;
use App\Simulation\Exceptions\DecisionNotAllowed;
use App\Simulation\Rng\SeededRng;
use Tests\Support\EventFixtures;
use Tests\Support\SimulationFixtures;

function month(array $parameters, ?BusinessState $state = null, ?Decisions $decisions = null, int $gameMonth = 1, array $competitors = [])
{
    return (new Engine)->simulateMonth(
        $state ?? SimulationFixtures::state(),
        $decisions ?? SimulationFixtures::decisions(),
        new MarketContext(4, $gameMonth, $competitors, $parameters),
        new SeededRng(1),
    );
}

it('books a one-off event cost in the month it happens', function () {
    // One cuota band, so the lower income after the cost doesn't change it.
    $oneBand = SimulationFixtures::parameters();
    $oneBand['cuota_autonomo']['bands'] = [['max_income_cents' => null, 'cuota_cents' => 30_000]];

    $quiet = month(EventFixtures::quiet($oneBand));
    $burgled = month(EventFixtures::only('burglary', parameters: $oneBand));

    expect($burgled->events)->toHaveCount(1)
        ->and($burgled->events[0]->type)->toBe('burglary')
        ->and($burgled->costs->otherCents - $quiet->costs->otherCents)->toBe(100_000)
        ->and($burgled->customers)->toBe($quiet->customers);
});

it('starts lasting effects next month, then lets them run out', function () {
    $roadworks = month(EventFixtures::only('roadworks'));
    $state = $roadworks->stateAfter;

    expect($state->modifiers)->toHaveCount(1)
        ->and($state->modifiers[0]->monthsRemaining)->toBe(2);

    $quiet = EventFixtures::quiet();
    $first = month($quiet, $state, gameMonth: 2);
    $second = month($quiet, $first->stateAfter, gameMonth: 3);
    $third = month($quiet, $second->stateAfter, gameMonth: 4);

    $baseline = month($quiet, $state->with(modifiers: []), gameMonth: 2);

    expect($first->customers)->toBeLessThan($baseline->customers)
        ->and($second->stateAfter->modifiers)->toBe([])
        ->and($third->stateAfter->modifiers)->toBe([]);
});

it('holds an event with choices until the player answers', function () {
    $failure = month(EventFixtures::only('equipment_failure'));

    expect($failure->stateAfter->pendingEvents)->toHaveCount(1)
        ->and($failure->stateAfter->pendingEvents[0]->key())->toBe('1:equipment_failure')
        ->and($failure->stateAfter->equipmentHealth)->toBeLessThan(SimulationFixtures::state()->equipmentHealth - 10);
});

it("applies the player's choice at the start of the next month", function () {
    $oneBand = SimulationFixtures::parameters();
    $oneBand['cuota_autonomo']['bands'] = [['max_income_cents' => null, 'cuota_cents' => 30_000]];

    $state = month(EventFixtures::only('equipment_failure'))->stateAfter;
    $repair = SimulationFixtures::decisions()->with(eventChoices: ['1:equipment_failure' => 'repair']);

    $quiet = month(EventFixtures::quiet($oneBand), $state->with(pendingEvents: []), gameMonth: 2);
    $repaired = month(EventFixtures::quiet($oneBand), $state, $repair, gameMonth: 2);

    expect($repaired->resolvedEvents)->toHaveCount(1)
        ->and($repaired->resolvedEvents[0]->choice)->toBe('repair')
        ->and($repaired->stateAfter->pendingEvents)->toBe([])
        ->and($repaired->costs->otherCents - $quiet->costs->otherCents)->toBe(140_000)
        ->and($repaired->stateAfter->equipmentHealth)->toBeGreaterThan($quiet->stateAfter->equipmentHealth + 30);
});

it('applies the default choice when the player says nothing', function () {
    $state = month(EventFixtures::only('equipment_failure'))->stateAfter;
    $limping = month(EventFixtures::quiet(), $state, gameMonth: 2);
    $baseline = month(EventFixtures::quiet(), $state->with(pendingEvents: []), gameMonth: 2);

    expect($limping->resolvedEvents[0]->choice)->toBe('limp_on')
        ->and(array_sum(array_map(fn ($p) => $p->capacity, $limping->dayParts)))
        ->toBeLessThan(array_sum(array_map(fn ($p) => $p->capacity, $baseline->dayParts)))
        // limp_on lasts 2 months, counting this one.
        ->and($limping->stateAfter->modifiers)->toHaveCount(2)
        ->and($limping->stateAfter->modifiers[0]->monthsRemaining)->toBe(1);
});

it('rejects a choice the event does not offer', function () {
    $state = month(EventFixtures::only('equipment_failure'))->stateAfter;

    month(EventFixtures::quiet(), $state, SimulationFixtures::decisions()->with(eventChoices: ['1:equipment_failure' => 'kick_it']), gameMonth: 2);
})->throws(DecisionNotAllowed::class);

it('pays staff who are missing but loses their capacity', function () {
    $shortage = new Modifier('staff_quits', ModifierEffect::StaffShortage, 2, 1);
    $state = SimulationFixtures::state()->with(modifiers: [$shortage]);
    $decisions = SimulationFixtures::decisions()->with(staffCount: 2);

    $short = month(EventFixtures::quiet(), $state, $decisions);
    $full = month(EventFixtures::quiet(), SimulationFixtures::state(), $decisions);

    expect($short->costs->staffCents)->toBe($full->costs->staffCents)
        ->and(array_sum(array_map(fn ($p) => $p->capacity, $short->dayParts)))
        ->toBeLessThan(array_sum(array_map(fn ($p) => $p->capacity, $full->dayParts)));
});

it('raises rent and COGS through modifiers', function () {
    $state = SimulationFixtures::state()->with(modifiers: [
        new Modifier('rent_review', ModifierEffect::Rent, 1.05, null),
        new Modifier('supplier_price_rise', ModifierEffect::CogsShare, 0.02, 3),
    ]);

    $raised = month(EventFixtures::quiet(), $state);
    $normal = month(EventFixtures::quiet());

    expect($raised->costs->rentCents)->toBe((int) round($normal->costs->rentCents * 1.05))
        ->and($raised->costs->cogsCents)->toBeGreaterThan($normal->costs->cogsCents);
});

it('counts accepted catering as revenue', function () {
    $state = month(EventFixtures::only('catering_order'))->stateAfter;
    $accept = SimulationFixtures::decisions()->with(eventChoices: ['1:catering_order' => 'accept']);

    $accepted = month(EventFixtures::quiet(), $state, $accept, gameMonth: 2);
    $declined = month(EventFixtures::quiet(), $state, gameMonth: 2);

    expect($accepted->eventRevenueCents)->toBe(120_000)
        ->and($accepted->revenueCents - $declined->revenueCents)->toBe(120_000)
        ->and($accepted->revenueCents)->toBe(array_sum(array_map(fn ($p) => $p->revenueCents, $accepted->dayParts)) + 120_000);
});

it('opens and closes competitors', function () {
    $opened = month(EventFixtures::only('competitor_opens'), competitors: [SimulationFixtures::competitor()]);
    $closed = month(EventFixtures::only('competitor_closes'), competitors: [SimulationFixtures::competitor('a'), SimulationFixtures::competitor('b')->with(reputation: 10.0)]);

    expect($opened->competitorsAfter)->toHaveCount(2)
        ->and(array_map(fn ($c) => $c->id, $closed->competitorsAfter))->toBe(['a']);
});

it('moves competitors every month', function () {
    $result = month(EventFixtures::quiet(), competitors: [SimulationFixtures::competitor()]);

    expect($result->competitorsAfter[0])->not->toEqual(SimulationFixtures::competitor());
});
