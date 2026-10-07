<?php

use App\Simulation\Data\DayPart;
use App\Simulation\Data\MarketContext;
use App\Simulation\Engine;
use App\Simulation\Rng\SeededRng;
use Tests\Support\BalanceGame;
use Tests\Support\BalanceScenarios as Scenarios;
use Tests\Support\EventFixtures;

/*
 * The balance targets from SPEC §6, run against the real parameter sheet
 * with fixed seeds. Net worth = cash + deposit + business value.
 */

dataset('seeds', [1, 2, 3, 4, 5]);

it('pays its owner and survives year 1 as an average business with average decisions', function () {
    // Three in four new cafés survive their first year (INE/DIRCE), so an
    // average one, run on the defaults, covers its owner's pay. Read over
    // many years, since events make any single one partly luck.
    $games = array_map(fn (int $seed) => Scenarios::average($seed)->play(Scenarios::averageDecisions()), range(1, 40));
    $paidOwner = array_filter($games, fn (BalanceGame $g) => $g->lowestCashCents() > 0
        && $g->totalProfitCents() >= 12 * Scenarios::parameters()['owner']['pay_month_cents']);

    expect(count($paidOwner) / count($games))->toBeGreaterThanOrEqual(0.8);
});

it('loses money at a great location with bad management', function (int $seed) {
    $game = Scenarios::greatLocation($seed)->play(Scenarios::badDecisions());

    expect($game->totalProfitCents())->toBeLessThan(0);
})->with('seeds');

it('lets a mediocre location with good management survive', function (int $seed) {
    $game = Scenarios::mediocreLocation($seed)->play(Scenarios::goodDecisions());

    expect($game->lowestCashCents())->toBeGreaterThan(0)
        ->and($game->netWorthChange())->toBeGreaterThan(-0.10);
})->with('seeds');

it('makes revenue fall within three months of pricing 50% above the local average', function (int $seed) {
    $baseline = Scenarios::average($seed)->play(Scenarios::averageDecisions(), 3)->revenueByMonth();
    $overpriced = Scenarios::average($seed)->play(Scenarios::averageDecisions()->with(priceLevel: 1.5), 3)->revenueByMonth();

    // The first month can bring in more (same customers, higher tickets)…
    // …but by month 3 the damage to reputation has cost more than it gained.
    expect($overpriced[2])->toBeLessThan($baseline[2])
        ->and($overpriced[2] / $baseline[2])->toBeLessThan($overpriced[0] / $baseline[0]);
})->with('seeds');

it('does better with good management than bad at the same location', function () {
    expect(Scenarios::greatLocation()->play(Scenarios::goodDecisions())->totalProfitCents())
        ->toBeGreaterThan(Scenarios::greatLocation()->play(Scenarios::badDecisions())->totalProfitCents());
});

it('rewards a better location under the same management', function () {
    // Averaged over seeds: a single year is partly luck (events, the street's drift).
    $average = fn (callable $game) => array_sum(array_map(fn (int $seed) => $game($seed)->play(Scenarios::averageDecisions())->totalProfitCents(), range(1, 10))) / 10;

    expect($average(Scenarios::greatLocation(...)))->toBeGreaterThan($average(Scenarios::mediocreLocation(...)));
});

/*
 * "No single random event bankrupts a player with more than €5k of cash on
 * hand": force each event (every outcome and every choice) on an average
 * business holding just over €5k, then play on while its effects last.
 */
dataset('single events', function () {
    $cases = [];

    foreach (Scenarios::parameters()['events']['library'] as $type => $event) {
        foreach (array_keys($event['outcomes'] ?? [null => null]) as $outcome) {
            foreach (array_keys($event['choices'] ?? [null => null]) as $choice) {
                $label = implode(' → ', array_filter([$type, $outcome, $choice]));
                $cases[$label] = [$type, $outcome ?: null, $choice ?: null];
            }
        }
    }

    return $cases;
});

it('survives any single event with more than €5k of cash', function (string $type, ?string $outcome, ?string $choice) {
    $start = Scenarios::business(Scenarios::averageNeighbourhood(), 5.0, 50)->with(cashCents: 500_001);
    $forced = EventFixtures::only($type, $outcome, Scenarios::parameters());
    $quiet = EventFixtures::quiet(Scenarios::parameters());

    $decisions = Scenarios::averageDecisions();

    if ($type === 'noise_complaint') {
        // Only happens to places open in the evening: the average schedule plus evening.
        $decisions = $decisions->with(openDayParts: [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon, DayPart::Evening]);
    }

    $engine = new Engine;
    $state = $start;
    $lowest = PHP_INT_MAX;

    // The event happens in month 1 (April); play on long enough for any
    // lasting effect to bite (the longest non-permanent one is 6 months).
    for ($month = 1; $month <= 4; $month++) {
        $choices = $choice !== null ? ["1:{$type}" => $choice] : [];
        $result = $engine->simulateMonth(
            $state,
            $decisions->with(eventChoices: $choices),
            new MarketContext(($month + 2) % 12 + 1, $month, Scenarios::typicalCompetitors(), $month === 1 ? $forced : $quiet),
            (new SeededRng(1))->fork("month-{$month}"),
        );

        $state = $result->stateAfter;
        $lowest = min($lowest, $state->cashCents);
    }

    expect($lowest)->toBeGreaterThanOrEqual(0);
})->with('single events');
