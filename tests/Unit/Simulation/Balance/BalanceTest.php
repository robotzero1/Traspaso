<?php

use Tests\Support\BalanceScenarios as Scenarios;

/*
 * The balance targets from SPEC §6, run against the real parameter sheet
 * with fixed seeds. Net worth values the business at the traspaso paid
 * until the Valuation step exists.
 *
 * "No single random event bankrupts a player with more than €5k of cash"
 * comes with events, in milestone 4.
 */

dataset('seeds', [1, 2, 3, 4, 5]);

it('ends year 1 between −10% and +25% for an average business with average decisions', function (int $seed) {
    $game = Scenarios::average($seed)->play(Scenarios::averageDecisions());

    expect($game->netWorthChange())->toBeGreaterThan(-0.10)->toBeLessThan(0.25);
})->with('seeds');

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
    expect(Scenarios::greatLocation()->play(Scenarios::averageDecisions())->totalProfitCents())
        ->toBeGreaterThan(Scenarios::mediocreLocation()->play(Scenarios::averageDecisions())->totalProfitCents());
});
