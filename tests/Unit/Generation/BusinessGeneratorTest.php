<?php

use App\Generation\BusinessGenerator;
use App\Generation\Distributions\PercentileDistribution;
use App\Generation\GeneratedBusiness;
use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\Licence;
use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

/*
 * These tests run the generator against the real parameter sheet, so they
 * keep passing (and keep meaning something) when placeholders are replaced
 * with researched numbers.
 */

const MARKET_SAMPLE_SIZE = 4_000;

function marketParameters(): array
{
    return require dirname(__DIR__, 3).'/config/market/zaragoza_cafe.php';
}

/** @return list<GeneratedBusiness> */
function largeMarket(): array
{
    static $market;

    return $market ??= (new BusinessGenerator(marketParameters()))
        ->generate(new SeededRng(2026), SimulationFixtures::neighbourhoods(), MARKET_SAMPLE_SIZE);
}

/**
 * Share of values below $threshold, counting values equal to it as half,
 * so rounding to the threshold doesn't bias the result.
 *
 * @param  list<int|float>  $values
 */
function shareBelow(array $values, float $threshold): float
{
    $below = 0.0;

    foreach ($values as $value) {
        $below += $value < $threshold ? 1.0 : ($value == $threshold ? 0.5 : 0.0);
    }

    return $below / count($values);
}

/** @param  list<int|float>  $values */
function ranks(array $values): array
{
    $order = array_keys($values);
    usort($order, fn ($a, $b) => $values[$a] <=> $values[$b]);
    $ranks = [];

    // Average ranks for ties.
    for ($i = 0; $i < count($order);) {
        $j = $i;

        while ($j + 1 < count($order) && $values[$order[$j + 1]] == $values[$order[$i]]) {
            $j++;
        }

        for ($k = $i; $k <= $j; $k++) {
            $ranks[$order[$k]] = ($i + $j) / 2;
        }

        $i = $j + 1;
    }

    ksort($ranks);

    return $ranks;
}

/** Spearman rank correlation. */
function spearman(array $xs, array $ys): float
{
    [$rx, $ry] = [ranks($xs), ranks($ys)];
    $n = count($xs);
    [$mx, $my] = [array_sum($rx) / $n, array_sum($ry) / $n];
    $cov = $vx = $vy = 0.0;

    for ($i = 0; $i < $n; $i++) {
        $cov += ($rx[$i] - $mx) * ($ry[$i] - $my);
        $vx += ($rx[$i] - $mx) ** 2;
        $vy += ($ry[$i] - $my) ** 2;
    }

    return $cov / sqrt($vx * $vy);
}

function shareWhere(array $businesses, Closure $predicate): float
{
    return count(array_filter($businesses, $predicate)) / count($businesses);
}

// Determinism ------------------------------------------------------------

it('generates the same market for the same seed', function () {
    $generate = fn (int $seed) => (new BusinessGenerator(marketParameters()))
        ->generate(new SeededRng($seed), SimulationFixtures::neighbourhoods());

    expect(serialize($generate(7)))->toBe(serialize($generate(7)))
        ->and(serialize($generate(7)))->not->toBe(serialize($generate(8)));
});

it('draws the market size from business_count unless a count is given', function (int $seed) {
    $generator = new BusinessGenerator(marketParameters());
    $count = count($generator->generate(new SeededRng($seed), SimulationFixtures::neighbourhoods()));

    expect($count)->toBeGreaterThanOrEqual(marketParameters()['business_count']['min'])
        ->toBeLessThanOrEqual(marketParameters()['business_count']['max'])
        ->and($generator->generate(new SeededRng($seed), SimulationFixtures::neighbourhoods(), 12))->toHaveCount(12)
        ->and($generator->generate(new SeededRng($seed), SimulationFixtures::neighbourhoods(), 0))->toBe([]);
})->with([1, 2, 3]);

// Distributions match the parameter sheet ---------------------------------

it('matches the configured percentiles', function (string $section, string $key, int|string $step, Closure $value) {
    $knots = marketParameters()[$section][$key];
    $distribution = new PercentileDistribution($knots);
    $step = is_int($step) ? $step : marketParameters()[$section][$step];
    $values = array_map($value, largeMarket());

    expect(min($values))->toBeGreaterThanOrEqual($knots[0])
        ->and(max($values))->toBeLessThanOrEqual($knots[100]);

    foreach (array_diff_key($knots, [0 => true, 100 => true]) as $percent => $threshold) {
        // Values are rounded to $step, so the ones equal to the threshold
        // came from within half a step of it. Each percentile is a kink in
        // the distribution, so that half-step isn't symmetric: compare with
        // the share the table predicts after rounding, not the raw percent.
        $expected = ($distribution->cdf($threshold - $step / 2) + $distribution->cdf($threshold + $step / 2)) / 2;

        expect(shareBelow($values, $threshold))
            ->toEqualWithDelta($expected, 0.025, "{$section} P{$percent}")
            ->and($expected)->toEqualWithDelta($percent / 100, 0.02, "{$section} P{$percent}: rounding step is too coarse");
    }
})->with([
    'rent' => ['rent', 'percentiles_cents', 'rounding_cents', fn (GeneratedBusiness $b) => $b->profile->rentMonthCents],
    'traspaso' => ['traspaso', 'percentiles_cents', 'rounding_cents', fn (GeneratedBusiness $b) => $b->traspasoCents],
    'floor area' => ['floor_area_m2', 'percentiles', 1, fn (GeneratedBusiness $b) => $b->profile->floorAreaM2],
    'equipment age' => ['equipment_age_years', 'percentiles', 1, fn (GeneratedBusiness $b) => $b->equipmentAgeYears],
]);

it('rounds money to the configured steps', function () {
    $parameters = marketParameters();

    foreach (largeMarket() as $business) {
        expect($business->profile->rentMonthCents % $parameters['rent']['rounding_cents'])->toBe(0)
            ->and($business->traspasoCents % $parameters['traspaso']['rounding_cents'])->toBe(0);
    }
});

it('keeps condition within 1–10 around the configured median', function () {
    $conditions = array_map(fn (GeneratedBusiness $b) => $b->profile->condition, largeMarket());
    $median = (new PercentileDistribution(marketParameters()['condition']['percentiles']))->quantile(0.5);

    expect(min($conditions))->toBeGreaterThanOrEqual(1)
        ->and(max($conditions))->toBeLessThanOrEqual(10)
        ->and(shareBelow($conditions, $median))->toEqualWithDelta(0.5, 0.05);
});

it('matches the configured category, licence and kitchen weights', function () {
    $parameters = marketParameters();
    $market = largeMarket();
    $normalise = fn (array $weights) => array_map(fn ($w) => $w / array_sum($weights), $weights);

    foreach ($normalise($parameters['categories']['weights']) as $category => $share) {
        expect(shareWhere($market, fn ($b) => $b->profile->category->value === $category))
            ->toEqualWithDelta($share, 0.03, "category {$category}");
    }

    foreach (['licences' => 'licence', 'kitchens' => 'kitchen'] as $section => $field) {
        foreach ($parameters[$section]['weights'] as $category => $weights) {
            $inCategory = array_values(array_filter($market, fn ($b) => $b->profile->category->value === $category));

            foreach ($normalise($weights) as $option => $share) {
                expect(shareWhere($inCategory, fn ($b) => $b->profile->{$field}->value === $option))
                    ->toEqualWithDelta($share, 0.04, "{$category} {$field} {$option}");
            }
        }
    }
});

it('never gives a café-bar a plain café licence', function () {
    foreach (largeMarket() as $business) {
        if ($business->profile->category === BusinessCategory::CafeBar) {
            expect($business->profile->licence)->not->toBe(Licence::Cafe);
        }
    }
});

it('matches the configured seating', function () {
    $seating = marketParameters()['seating'];
    $market = largeMarket();
    $perM2 = array_map(fn ($b) => $b->profile->indoorSeats / $b->profile->floorAreaM2, $market);

    expect(array_sum($perM2) / count($perM2))->toEqualWithDelta($seating['indoor_seats_per_m2'], 0.02)
        ->and(shareWhere($market, fn ($b) => $b->profile->terraceSeats > 0))
        ->toEqualWithDelta($seating['terrace_probability'], 0.03);

    foreach ($market as $business) {
        $terrace = $business->profile->terraceSeats;

        expect($business->profile->indoorSeats)->toBeGreaterThanOrEqual($seating['min_indoor_seats'])
            ->and($terrace % $seating['seats_per_terrace_table'])->toBe(0);

        if ($terrace > 0) {
            expect($terrace / $seating['seats_per_terrace_table'])
                ->toBeGreaterThanOrEqual($seating['terrace_tables']['min'])
                ->toBeLessThanOrEqual($seating['terrace_tables']['max']);
        }
    }
});

it('keeps base reputation within the configured bounds', function () {
    $config = marketParameters()['base_reputation'];
    $reputations = array_map(fn ($b) => $b->baseReputation, largeMarket());

    expect(min($reputations))->toBeGreaterThanOrEqual($config['min'])
        ->and(max($reputations))->toBeLessThanOrEqual($config['max'])
        ->and(array_sum($reputations) / count($reputations))->toEqualWithDelta($config['mean'], 1.5);
});

it('matches the configured street type weights', function () {
    $types = marketParameters()['footfall']['street_types'];
    $total = array_sum(array_column($types, 'weight'));

    foreach ($types as $type => $config) {
        expect(shareWhere(largeMarket(), fn ($b) => $b->streetType === $type))
            ->toEqualWithDelta($config['weight'] / $total, 0.03, $type);
    }
});

// Relationships ---------------------------------------------------------

it('charges more rent for busier and bigger premises', function () {
    $market = largeMarket();
    $rent = array_map(fn ($b) => $b->profile->rentMonthCents, $market);

    expect(spearman(array_map(fn ($b) => $b->profile->footfall, $market), $rent))->toBeGreaterThan(0.3)
        ->and(spearman(array_map(fn ($b) => $b->profile->floorAreaM2, $market), $rent))->toBeGreaterThan(0.25);
});

it('asks a higher traspaso for busier, better-kept businesses', function () {
    $market = largeMarket();
    $traspaso = array_map(fn ($b) => $b->traspasoCents, $market);

    expect(spearman(array_map(fn ($b) => $b->profile->footfall, $market), $traspaso))->toBeGreaterThan(0.3)
        ->and(spearman(array_map(fn ($b) => $b->profile->condition, $market), $traspaso))->toBeGreaterThan(0.2);
});

it('pairs worse condition with older equipment', function () {
    $market = largeMarket();

    expect(spearman(
        array_map(fn ($b) => $b->profile->condition, $market),
        array_map(fn ($b) => $b->equipmentAgeYears, $market),
    ))->toBeLessThan(-0.3);
});

it('gives main streets more footfall than side streets', function () {
    $average = function (string $type) {
        $footfall = array_map(
            fn ($b) => $b->profile->footfall,
            array_filter(largeMarket(), fn ($b) => $b->streetType === $type),
        );

        return array_sum($footfall) / count($footfall);
    };

    expect($average('main_street'))->toBeGreaterThan($average('side_street') + 2.0);
});

it('places more listings per resident in commercial neighbourhoods', function () {
    $perResident = [];

    foreach (SimulationFixtures::neighbourhoods() as $neighbourhood) {
        $count = count(array_filter(largeMarket(), fn ($b) => $b->profile->neighbourhood->name === $neighbourhood->name));
        $perResident[$neighbourhood->name] = $count / $neighbourhood->population;
    }

    expect($perResident['Old town'])->toBeGreaterThan($perResident['Residential'] * 1.5)
        ->and($perResident['Business district'])->toBeGreaterThan($perResident['Residential'] * 1.5);
});

it('never places a business in a neighbourhood with no residents', function () {
    $empty = new NeighbourhoodProfile('Industrial estate', 0, 0.0, 0.0, 5.0, 2.0, 1.0);
    $market = (new BusinessGenerator(marketParameters()))
        ->generate(new SeededRng(3), [...SimulationFixtures::neighbourhoods(), $empty], 500);

    foreach ($market as $business) {
        expect($business->profile->neighbourhood)->not->toBe($empty);
    }
});

it('gives every business a unique name', function () {
    $names = array_map(fn ($b) => $b->name, largeMarket());

    expect(array_unique($names))->toHaveCount(count($names));
});

// Invalid input -----------------------------------------------------------

it('needs at least one neighbourhood', function () {
    (new BusinessGenerator(marketParameters()))->generate(new SeededRng(1), []);
})->throws(InvalidArgumentException::class, 'non-empty list of neighbourhoods');

it('rejects a negative count', function () {
    (new BusinessGenerator(marketParameters()))->generate(new SeededRng(1), SimulationFixtures::neighbourhoods(), -1);
})->throws(InvalidArgumentException::class);

it('rejects correlations that explain more than all the variance', function () {
    $parameters = marketParameters();
    $parameters['rent']['correlation'] = ['location' => 0.8, 'floor_area' => 0.7];

    new BusinessGenerator($parameters);
})->throws(InvalidArgumentException::class, 'Correlations in [rent]');

it('rejects unknown correlation factors', function () {
    $parameters = marketParameters();
    $parameters['traspaso']['correlation'] = ['weather' => 0.5];

    (new BusinessGenerator($parameters))->generate(new SeededRng(1), SimulationFixtures::neighbourhoods(), 1);
})->throws(InvalidArgumentException::class, 'Unknown correlation factor [weather]');

it('rejects a parameter sheet with a missing section', function () {
    $parameters = marketParameters();
    unset($parameters['traspaso']);

    new BusinessGenerator($parameters);
})->throws(InvalidArgumentException::class, 'Market parameter [traspaso.percentiles_cents] is not defined.');
