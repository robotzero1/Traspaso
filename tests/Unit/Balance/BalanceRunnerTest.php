<?php

use App\Balance\BalanceMarket;
use App\Balance\BalanceReport;
use App\Balance\BalanceRunner;
use App\Balance\GameOutcome;
use App\Balance\Strategies\Careless;
use App\Balance\Strategies\Cheapest;
use App\Balance\Strategies\DefaultSettings;
use App\Balance\Strategies\Premium;
use App\Balance\Strategies\Thoughtful;
use App\Generation\Location;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\EventRecord;
use Tests\Support\SimulationFixtures;

/** A small city: each fixture neighbourhood gets a grid of commercial points, with real cafés among them. */
function balanceMarket(bool $located = true): BalanceMarket
{
    $points = [];
    $places = [];

    foreach (SimulationFixtures::neighbourhoods() as $n => $neighbourhood) {
        for ($i = 0; $i < 80; $i++) {
            $lat = 41.60 + $n * 0.02 + intdiv($i, 10) * 0.0004;
            $lng = -0.90 + ($i % 10) * 0.0005;
            $footfall = round(1 + ($i % 9) + $n * 0.2, 1);
            $points[$neighbourhood->name][] = new Location($lat, $lng, min(10.0, $footfall), array_fill_keys(array_map(fn (DayPart $p) => $p->value, DayPart::cases()), min(10.0, $footfall)), 'main_street');

            if ($i % 4 === 0) {
                $places[] = ['key' => "osm-node-{$n}{$i}", 'lat' => $lat + 0.0001, 'lng' => $lng];
            }
        }
    }

    return new BalanceMarket(
        parameters: SimulationFixtures::parameters(),
        neighbourhoods: SimulationFixtures::neighbourhoods(),
        points: $located ? $points : null,
        rivalPlaces: $located ? $places : [],
    );
}

it('plays games day by day on the daily engine, close to the monthly one', function () {
    $daily = new BalanceRunner(balanceMarket(), daily: true);
    $monthly = new BalanceRunner(balanceMarket());
    $game = $daily->play(new DefaultSettings, 3);

    expect($game)->toEqual($daily->play(new DefaultSettings, 3))
        ->and($game->monthsPlayed)->toBe($monthly->play(new DefaultSettings, 3)->monthsPlayed)
        ->and($game->traspasoCents)->toBe($monthly->play(new DefaultSettings, 3)->traspasoCents)
        ->and($game->totalProfitCents)->not->toBe($monthly->play(new DefaultSettings, 3)->totalProfitCents);
});

it('plays the same game for the same seed and strategy', function () {
    $runner = new BalanceRunner(balanceMarket());

    expect($runner->play(new Thoughtful, 7))->toEqual($runner->play(new Thoughtful, 7))
        ->and($runner->play(new Thoughtful, 7))->not->toEqual($runner->play(new Thoughtful, 8));
});

it('draws starting capital from the range a player can choose, in whole thousands', function () {
    $range = SimulationFixtures::parameters()['game']['starting_capital_cents'];
    $runner = new BalanceRunner(balanceMarket());

    foreach (range(1, 20) as $seed) {
        $capital = $runner->play(new DefaultSettings, $seed)->startingCapitalCents;

        expect($capital)->toBeGreaterThanOrEqual($range['min'])->toBeLessThanOrEqual($range['max'])
            ->and($capital % 100_000)->toBe(0);
    }
});

it('plays a whole year or ends at bankruptcy, valuing what is left', function () {
    $runner = new BalanceRunner(balanceMarket());

    foreach (range(1, 10) as $seed) {
        $outcome = $runner->play(new Careless, $seed);

        expect($outcome->bought)->toBeTrue()
            ->and($outcome->monthsPlayed)->toBeLessThanOrEqual(12);

        if ($outcome->bankrupt()) {
            expect($outcome->monthsPlayed)->toBe($outcome->bankruptInMonth);
        } else {
            expect($outcome->monthsPlayed)->toBe(12);
        }
    }
});

it('works without a footfall surface, and gives the located market rivals from real cafés', function () {
    $located = (new BalanceRunner(balanceMarket()))->play(new Thoughtful, 3);
    $plain = (new BalanceRunner(balanceMarket(located: false)))->play(new Thoughtful, 3);

    expect($located->rivals)->toBe(SimulationFixtures::parameters()['competitors']['nearby_count'])
        ->and($plain->bought)->toBeTrue();
});

it('makes each strategy buy the way it says', function () {
    $runner = new BalanceRunner(balanceMarket());
    $cheapest = array_map(fn (int $s) => $runner->play(new Cheapest, $s), range(1, 15));
    $premium = array_map(fn (int $s) => $runner->play(new Premium, $s), range(1, 15));

    $avg = fn (array $outcomes, string $field) => array_sum(array_map(fn (GameOutcome $o) => $o->{$field}, $outcomes)) / count($outcomes);

    expect($avg($cheapest, 'traspasoCents'))->toBeLessThan($avg($premium, 'traspasoCents'))
        ->and($avg($premium, 'footfall'))->toBeGreaterThan($avg($cheapest, 'footfall'));
});

it('rewards careful play over careless play', function () {
    $runner = new BalanceRunner(balanceMarket());
    $median = function (string $class) use ($runner) {
        $changes = array_map(fn (int $s) => $runner->play(new $class, $s)->change(), range(1, 30));
        sort($changes);

        return $changes[15];
    };

    expect($median(Thoughtful::class))->toBeGreaterThan($median(Careless::class) + 0.5);
});

it('summarises outcomes as percentiles, share ahead and share bankrupt', function () {
    $outcome = fn (int $netWorth, ?int $bankruptIn = null) => new GameOutcome('x', 1, 100_000, true, 'A', 5.0, monthsPlayed: 12, bankruptInMonth: $bankruptIn, netWorthCents: $netWorth);
    $summary = BalanceReport::summary([
        $outcome(50_000, 4), $outcome(90_000), $outcome(110_000), $outcome(120_000), $outcome(200_000),
        new GameOutcome('x', 6, 100_000, bought: false, netWorthCents: 100_000),
    ]);

    expect($summary['games'])->toBe(6)
        ->and($summary['bought'])->toBe(5)
        ->and($summary['median'])->toEqualWithDelta(0.10, 1e-9)
        ->and($summary['p25'])->toEqualWithDelta(-0.10, 1e-9)
        ->and($summary['gained'])->toEqualWithDelta(0.6, 1e-9)
        ->and($summary['bankrupt'])->toEqualWithDelta(0.2, 1e-9);
});

it('checks the milestone targets', function () {
    $runner = new BalanceRunner(balanceMarket());
    $outcomes = [];

    foreach ([new Thoughtful, new DefaultSettings, new Careless] as $strategy) {
        foreach (range(1, 10) as $seed) {
            $outcomes[] = $runner->play($strategy, $seed);
        }
    }

    $targets = (new BalanceReport($outcomes))->targets();

    expect(array_column($targets, 'target'))->toContain('Careless player: 90% or more fail', 'Typical new owner (default settings): 20–25% fail in year 1')
        ->and(collect($targets)->firstWhere('target', 'Careless player: 90% or more fail')['pass'])->toBeTrue();
});

it('groups footfall into bands of two', function () {
    $at = fn (float $f) => BalanceReport::footfallBand(new GameOutcome('x', 1, 1, true, 'A', $f));

    expect($at(0.4))->toBe('0–2')->and($at(5.1))->toBe('4–6')->and($at(10.0))->toBe('8–10');
});

it('counts a café as failed in the year it closed', function () {
    $closedIn = fn (?int $year) => new GameOutcome('x', 1, 100_000, true, 'A', 5.0, years: 5, closedInYear: $year);

    expect($closedIn(null)->failed())->toBeFalse()
        ->and($closedIn(1)->failed())->toBeTrue()
        ->and($closedIn(3)->failed())->toBeFalse()
        ->and($closedIn(3)->closedBy(2))->toBeFalse()
        ->and($closedIn(3)->closedBy(3))->toBeTrue()
        ->and((new GameOutcome('x', 1, 100_000, bought: false))->failed())->toBeFalse();
});

it('closes a café at the end of a year that did not pay its owner, or when the cash runs out', function () {
    $runner = new BalanceRunner(balanceMarket());
    $pay = SimulationFixtures::parameters()['owner']['pay_month_cents'];

    foreach (range(1, 15) as $seed) {
        $outcome = $runner->play(new DefaultSettings, $seed, years: 3);

        if ($outcome->closedInYear === null) {
            expect($outcome->monthsPlayed)->toBe(36);
        } elseif ($outcome->bankrupt()) {
            expect($outcome->closedInYear)->toBe(intdiv($outcome->bankruptInMonth - 1, 12) + 1);
        } else {
            // Closed at a year end, short of the owner's pay that year.
            expect($outcome->monthsPlayed)->toBe(12 * $outcome->closedInYear)
                ->and($outcome->monthsPlayed % 12)->toBe(0);
        }
    }
});

it('reports how many are still open at the end of each year, never rising', function () {
    $runner = new BalanceRunner(balanceMarket());
    $open = BalanceReport::summary(array_map(fn (int $s) => $runner->play(new DefaultSettings, $s, years: 4), range(1, 20)))['open'];

    expect(array_keys($open))->toBe([1, 2, 3, 4]);

    for ($year = 2; $year <= 4; $year++) {
        expect($open[$year])->toBeLessThanOrEqual($open[$year - 1]);
    }
});

it('has careful players repair broken equipment when they can afford it', function () {
    $broken = new EventRecord('equipment_failure', 3, choices: ['repair', 'limp_on']);
    $state = fn (int $cash) => SimulationFixtures::state()->with(cashCents: $cash, pendingEvents: [$broken]);
    $sheet = SimulationFixtures::sheet();

    expect((new Thoughtful)->eventChoices($state(2_000_000), $sheet))->toBe([$broken->key() => 'repair'])
        ->and((new Thoughtful)->eventChoices($state(100_000), $sheet))->toBe([])
        ->and((new Careless)->eventChoices($state(2_000_000), $sheet))->toBe([]);
});
