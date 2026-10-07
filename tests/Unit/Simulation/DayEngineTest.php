<?php

use App\Simulation\Data\CalendarDate;
use App\Simulation\Data\DayContext;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\DayResult;
use App\Simulation\Data\Licence;
use App\Simulation\Data\MarketContext;
use App\Simulation\Data\Modifier;
use App\Simulation\Data\ModifierEffect;
use App\Simulation\DayEngine;
use App\Simulation\Engine;
use App\Simulation\Exceptions\DecisionNotAllowed;
use App\Simulation\Rng\SeededRng;
use Tests\Support\EventFixtures;
use Tests\Support\SimulationFixtures;

function playDay(string $date, array $state = [], array $decisions = [], ?array $parameters = null, int $seed = 1, array $eventsThisMonth = [], bool $settle = false): DayResult
{
    $date = CalendarDate::parse($date);

    return (new DayEngine)->simulateDay(
        SimulationFixtures::state()->with(...$state),
        SimulationFixtures::decisions()->with(...$decisions),
        new DayContext($date, 1, [SimulationFixtures::competitor()], $parameters ?? EventFixtures::quiet(), $eventsThisMonth, $settle),
        (new SeededRng($seed))->fork('month-1'),
    );
}

function playMonth(int $year, int $month, array $state = [], array $decisions = [], ?array $parameters = null, int $seed = 1)
{
    $parameters ??= SimulationFixtures::parameters();

    return (new DayEngine)->simulateMonth(
        SimulationFixtures::state()->with(...$state),
        SimulationFixtures::decisions()->with(...$decisions),
        new MarketContext($month, 1, [SimulationFixtures::competitor()], $parameters),
        new CalendarDate($year, $month, 1),
        (new SeededRng($seed))->fork('month-1'),
    );
}

it('gives the same day for the same seed', function () {
    expect(serialize(playDay('2027-04-14', seed: 4)))->toBe(serialize(playDay('2027-04-14', seed: 4)))
        ->and(playDay('2027-04-14', seed: 4)->revenueCents)->not->toBe(playDay('2027-04-14', seed: 5)->revenueCents);
});

it('trades nothing on a closed day, but the equipment still wears', function () {
    // Six days a week closes Mondays for a daytime café.
    $day = playDay('2027-04-12');

    expect($day->open)->toBeFalse()
        ->and($day->customers)->toBe(0)
        ->and($day->revenueCents)->toBe(0)
        ->and($day->dayParts)->toBe([])
        ->and($day->stateAfter->reputation)->toBe(SimulationFixtures::state()->reputation)
        ->and($day->stateAfter->equipmentHealth)->toBeLessThan(SimulationFixtures::state()->equipmentHealth);
});

it('takes in the day\'s takings and pays for the stock sold from them', function () {
    $day = playDay('2027-04-14', ['cashCents' => 500_000]);
    $cogsShare = SimulationFixtures::parameters()['cogs']['share_of_revenue']['standard'];

    expect($day->open)->toBeTrue()
        ->and($day->customers)->toBe(array_sum(array_map(fn ($p) => $p->covers, $day->dayParts)))
        ->and($day->revenueCents)->toBe(array_sum(array_map(fn ($p) => $p->revenueCents, $day->dayParts)))
        ->and($day->cogsCents)->toBe((int) round($day->revenueCents * $cogsShare))
        ->and($day->stateAfter->cashCents)->toBe(500_000 + $day->cashFlowCents());
});

it('sells more on Saturday evenings than on Monday evenings', function () {
    $evenings = ['openDayParts' => [DayPart::Evening], 'openDaysPerWeek' => 7];
    $total = fn (string $weekday) => array_sum(array_map(
        fn (int $seed) => playDay($weekday, decisions: $evenings, seed: $seed)->customers,
        range(1, 20),
    ));

    expect($total('2027-04-17'))->toBeGreaterThan(1.5 * $total('2027-04-12'));
});

it('sells less in the rain', function () {
    $rainy = $fair = [];

    foreach (range(1, 300) as $seed) {
        $day = playDay('2027-04-14', seed: $seed);
        $day->weather->kind->value === 'rain' ? $rainy[] = $day->customers : $fair[] = $day->customers;
    }

    expect($rainy)->not->toBeEmpty()
        ->and(array_sum($rainy) / count($rainy))->toBeLessThan(0.95 * array_sum($fair) / count($fair));
});

it('enforces the licence', function () {
    playDay('2027-04-14', ['profile' => SimulationFixtures::profile()->with(licence: Licence::Cafe)], ['openDayParts' => [DayPart::Night]]);
})->throws(DecisionNotAllowed::class);

it('rolls events with their monthly odds spread over the days, once a month each', function () {
    // Burglary is forced: monthly odds of 1 make it certain on any day.
    $day = playDay('2027-04-14', parameters: EventFixtures::only('burglary'));

    expect(array_map(fn ($e) => $e->type, $day->events))->toBe(['burglary'])
        ->and($day->events[0]->payload['date'])->toBe('2027-04-14')
        ->and($day->eventCostCents)->toBe(100_000)
        ->and(playDay('2027-04-15', parameters: EventFixtures::only('burglary'), eventsThisMonth: ['burglary'])->events)->toBe([])
        ->and(playDay('2027-04-15', parameters: EventFixtures::only('burglary'), eventsThisMonth: ['a', 'b'])->events)->toBe([]);
});

it('happens about as often in a month of days as the monthly odds say', function () {
    $parameters = EventFixtures::only('burglary');
    $parameters['events']['library']['burglary']['probability'] = ['base' => 0.3];
    $months = 0;

    foreach (range(1, 400) as $seed) {
        $months += count(array_filter(playMonth(2027, 4, parameters: $parameters, seed: $seed)->month->events, fn ($e) => $e->type === 'burglary'));
    }

    expect($months / 400)->toEqualWithDelta(0.3, 0.06);
});

it('starts an event\'s lasting effects the next day, counted in days', function () {
    $parameters = EventFixtures::only('roadworks');
    $day = playDay('2027-04-14', parameters: $parameters);
    $modifier = collect($day->stateAfter->modifiers)->firstWhere('source', 'roadworks');

    // Roadworks: demand × 0.8 for 2 months, so about 61 days.
    expect($modifier->daysRemaining)->toBe(61)
        ->and($day->dayParts[0]->potentialCustomers)->toBe(playDay('2027-04-14')->dayParts[0]->potentialCustomers);
});

it('runs down modifiers counted in days, and leaves monthly ones to the month end', function () {
    $daily = new Modifier('roadworks', ModifierEffect::Demand, 0.8, 2, daysRemaining: 1);
    $monthly = new Modifier('rent_review', ModifierEffect::Rent, 1.05, 2);
    $day = playDay('2027-04-14', ['modifiers' => [$daily, $monthly]]);

    expect(array_map(fn ($m) => $m->source, $day->stateAfter->modifiers))->toBe(['rent_review'])
        ->and($day->stateAfter->modifiers[0]->monthsRemaining)->toBe(2);
});

it('settles events waiting for a choice before trading', function () {
    $day = playDay(
        '2027-04-01',
        ['pendingEvents' => [EventFixtures::pending('equipment_failure')]],
        ['eventChoices' => ['1:equipment_failure' => 'repair']],
        settle: true,
    );

    expect($day->resolvedEvents[0]->choice)->toBe('repair')
        ->and($day->eventCostCents)->toBe(140_000)
        ->and($day->stateAfter->pendingEvents)->toBe([])
        ->and(playDay('2027-04-01', ['pendingEvents' => [EventFixtures::pending('equipment_failure')]])->stateAfter->pendingEvents)->toBe([]);
});

it('waits for the player\'s answer until the event\'s deadline, then takes the default', function () {
    $pending = EventFixtures::pending('equipment_failure')->with(payload: ['date' => '2027-04-10']);
    $deadline = SimulationFixtures::parameters()['events']['deadline_days'];

    expect(playDay('2027-04-12', ['pendingEvents' => [$pending]])->stateAfter->pendingEvents)->toHaveCount(1)
        ->and(playDay('2027-04-12', ['pendingEvents' => [$pending]], ['eventChoices' => ['1:equipment_failure' => 'repair']])->resolvedEvents[0]->choice)->toBe('repair')
        ->and(playDay(CalendarDate::parse('2027-04-10')->addDays($deadline)->toString(), ['pendingEvents' => [$pending]])->resolvedEvents[0]->choice)->toBe('limp_on');
});

it('plays a whole month and settles its bills at month end', function () {
    $start = 1_500_000;
    $played = playMonth(2027, 4, ['cashCents' => $start]);
    $month = $played->month;
    $days = $played->days;

    expect($days)->toHaveCount(30)
        ->and($days[0]->date->toString())->toBe('2027-04-01')
        ->and($days[29]->date->toString())->toBe('2027-04-30')
        ->and($month->customers)->toBe(array_sum(array_map(fn ($d) => $d->customers, $days)))
        ->and($month->revenueCents)->toBe(array_sum(array_map(fn ($d) => $d->revenueCents, $days)))
        ->and($month->costs->cogsCents)->toBe(array_sum(array_map(fn ($d) => $d->cogsCents, $days)))
        ->and(array_sum(array_map(fn ($p) => $p->covers, $month->dayParts)))->toBe($month->customers)
        ->and($month->cashAfterCents())->toBe($start + $month->profitCents() - $month->ownerPayCents)
        ->and($days[29]->stateAfter->cashCents)->toBe($start + array_sum(array_map(fn ($d) => $d->cashFlowCents(), $days)))
        ->and($month->stateAfter->equipmentAgeMonths)->toBe(SimulationFixtures::state()->equipmentAgeMonths + 1)
        ->and($month->stateAfter->localTrend)->not->toBe(1.0);
});

it('moves reputation over a month about as far as the monthly engine does', function () {
    $state = ['reputation' => 30.0];
    $daily = playMonth(2027, 4, $state, parameters: EventFixtures::quiet())->month->stateAfter;
    $monthly = (new Engine)->simulateMonth(
        SimulationFixtures::state()->with(...$state),
        SimulationFixtures::decisions(),
        new MarketContext(4, 1, [SimulationFixtures::competitor()], EventFixtures::quiet()),
        (new SeededRng(1))->fork('month-1'),
    )->stateAfter;

    expect($daily->reputation)->toEqualWithDelta($monthly->reputation, 1.5)
        ->and($daily->staffMorale)->toEqualWithDelta($monthly->staffMorale, 1.5)
        ->and($daily->equipmentHealth)->toEqualWithDelta($monthly->equipmentHealth, 0.01);
});

/*
 * SPEC §11: over a month the days add up to what the monthly engine
 * produced, so stage one's calibration carries over. Same seeds, every
 * month of a year. Demand matches; covers and revenue come out a little
 * lower, because each day is capped by that day's seats and staff, and a
 * busy Saturday can turn people away when the month as a whole wouldn't.
 */
it('adds up over a year to what the monthly engine gives', function (array $decisions, float $minRevenue) {
    $demand = ['monthly' => 0, 'daily' => 0];
    $revenue = ['monthly' => 0, 'daily' => 0];

    foreach (range(1, 12) as $calendarMonth) {
        foreach (range(1, 6) as $seed) {
            $context = new MarketContext($calendarMonth, 1, [SimulationFixtures::competitor()], SimulationFixtures::parameters());
            $rng = (new SeededRng($seed))->fork('month-1');
            $monthly = (new Engine)->simulateMonth(SimulationFixtures::state(), SimulationFixtures::decisions()->with(...$decisions), $context, $rng);
            $daily = (new DayEngine)->simulateMonth(SimulationFixtures::state(), SimulationFixtures::decisions()->with(...$decisions), $context, new CalendarDate(2027, $calendarMonth, 1), $rng)->month;

            $demand['monthly'] += array_sum(array_map(fn ($p) => $p->demand, $monthly->dayParts));
            $demand['daily'] += array_sum(array_map(fn ($p) => $p->demand, $daily->dayParts));
            $revenue['monthly'] += $monthly->revenueCents;
            $revenue['daily'] += $daily->revenueCents;
        }
    }

    expect($demand['daily'] / $demand['monthly'])->toEqualWithDelta(1.0, 0.015)
        ->and($revenue['daily'] / $revenue['monthly'])->toBeBetween($minRevenue, 1.01);
})->with([
    'seven days, plenty of staff' => [['openDaysPerWeek' => 7, 'staffCount' => 5], 0.985],
    'default six days' => [[], 0.96],
    'evenings' => [['openDayParts' => [DayPart::Afternoon, DayPart::Evening], 'openDaysPerWeek' => 6], 0.95],
]);
