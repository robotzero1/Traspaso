<?php

use App\Simulation\Rng\SeededRng;

function rngDraws(SeededRng $rng, int $count = 20): array
{
    return array_map(fn () => $rng->int(0, 1_000_000), range(1, $count));
}

it('produces the same sequence for the same seed', function () {
    expect(rngDraws(new SeededRng(42)))->toBe(rngDraws(new SeededRng(42)));
});

it('produces different sequences for different seeds', function () {
    expect(rngDraws(new SeededRng(42)))->not->toBe(rngDraws(new SeededRng(43)));
});

it('keeps its seed', function () {
    expect((new SeededRng(-7))->seed)->toBe(-7);
});

it('is stable across runs and platforms', function () {
    // If this fails, every saved game would replay differently.
    // Only update these values deliberately.
    $rng = new SeededRng(12345);

    expect([$rng->int(1, 100), $rng->int(1, 100), $rng->int(1, 100)])->toBe([73, 79, 91])
        ->and((new SeededRng(12345))->fork('month-1')->seed)->toBe(-2145251489820107486);
});

it('returns integers within inclusive bounds and reaches both ends', function () {
    $rng = new SeededRng(1);
    $seen = [];

    for ($i = 0; $i < 2_000; $i++) {
        $value = $rng->int(3, 7);
        expect($value)->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(7);
        $seen[$value] = true;
    }

    expect(array_keys($seen))->toEqualCanonicalizing([3, 4, 5, 6, 7]);
});

it('returns floats in [0, 1) with a mean near 0.5', function () {
    $rng = new SeededRng(2);
    $sum = 0.0;

    for ($i = 0; $i < 10_000; $i++) {
        $value = $rng->float();
        expect($value)->toBeGreaterThanOrEqual(0.0)->toBeLessThan(1.0);
        $sum += $value;
    }

    expect($sum / 10_000)->toBeGreaterThan(0.49)->toBeLessThan(0.51);
});

it('returns floats within a range', function () {
    $rng = new SeededRng(3);

    for ($i = 0; $i < 1_000; $i++) {
        expect($rng->floatBetween(2.5, 4.0))->toBeGreaterThanOrEqual(2.5)->toBeLessThan(4.0);
    }

    expect($rng->floatBetween(1.5, 1.5))->toBe(1.5);
});

it('respects the probability in chance()', function () {
    $rng = new SeededRng(4);
    $hits = 0;

    for ($i = 0; $i < 10_000; $i++) {
        $hits += $rng->chance(0.3) ? 1 : 0;
    }

    expect($hits / 10_000)->toBeGreaterThan(0.28)->toBeLessThan(0.32);
});

it('never fires chance(0) and always fires chance(1)', function () {
    $rng = new SeededRng(5);

    for ($i = 0; $i < 1_000; $i++) {
        expect($rng->chance(0.0))->toBeFalse()
            ->and($rng->chance(1.0))->toBeTrue();
    }
});

it('draws normals with the requested mean and standard deviation', function () {
    $rng = new SeededRng(6);
    $samples = array_map(fn () => $rng->normal(50.0, 10.0), range(1, 20_000));

    $mean = array_sum($samples) / count($samples);
    $variance = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $samples)) / count($samples);

    expect($mean)->toBeGreaterThan(49.7)->toBeLessThan(50.3)
        ->and(sqrt($variance))->toBeGreaterThan(9.8)->toBeLessThan(10.2);
});

it('returns the mean when the standard deviation is zero', function () {
    expect((new SeededRng(7))->normal(12.0, 0.0))->toBe(12.0);
});

it('picks every element of a list', function () {
    $rng = new SeededRng(8);
    $seen = [];

    for ($i = 0; $i < 500; $i++) {
        $seen[$rng->pick(['a' => 'x', 'b' => 'y', 'c' => 'z'])] = true;
    }

    expect(array_keys($seen))->toEqualCanonicalizing(['x', 'y', 'z']);
});

it('picks weighted keys in proportion to their weights', function () {
    $rng = new SeededRng(9);
    $counts = ['rare' => 0, 'common' => 0, 'never' => 0];

    for ($i = 0; $i < 10_000; $i++) {
        $counts[$rng->weightedKey(['rare' => 1, 'common' => 3, 'never' => 0])]++;
    }

    expect($counts['never'])->toBe(0)
        ->and($counts['common'] / 10_000)->toBeGreaterThan(0.73)->toBeLessThan(0.77);
});

it('shuffles without losing or adding elements', function () {
    $items = range(1, 30);
    $shuffled = (new SeededRng(10))->shuffle($items);

    expect($shuffled)->toEqualCanonicalizing($items)
        ->and($shuffled)->not->toBe($items)
        ->and($shuffled)->toBe((new SeededRng(10))->shuffle($items));
});

it('forks deterministic streams without advancing the parent', function () {
    $parent = new SeededRng(11);
    $untouched = new SeededRng(11);

    $a = rngDraws($parent->fork('demand'));
    $b = rngDraws((new SeededRng(11))->fork('demand'));

    expect($a)->toBe($b)
        ->and(rngDraws($parent))->toBe(rngDraws($untouched));
});

it('gives each fork label its own stream', function () {
    $rng = new SeededRng(12);

    expect(rngDraws($rng->fork('events')))->not->toBe(rngDraws($rng->fork('competitors')))
        ->and(rngDraws($rng->fork('month-1')))->not->toBe(rngDraws($rng->fork('month-2')))
        ->and(rngDraws($rng->fork('events')))->not->toBe(rngDraws(new SeededRng(12)));
});

it('rejects invalid arguments', function (Closure $call) {
    expect($call)->toThrow(InvalidArgumentException::class);
})->with([
    'int min > max' => fn () => (new SeededRng(1))->int(5, 4),
    'float min > max' => fn () => (new SeededRng(1))->floatBetween(2.0, 1.0),
    'chance below 0' => fn () => (new SeededRng(1))->chance(-0.1),
    'chance above 1' => fn () => (new SeededRng(1))->chance(1.1),
    'negative std dev' => fn () => (new SeededRng(1))->normal(0.0, -1.0),
    'pick from empty' => fn () => (new SeededRng(1))->pick([]),
    'negative weight' => fn () => (new SeededRng(1))->weightedKey(['a' => -1, 'b' => 2]),
    'all-zero weights' => fn () => (new SeededRng(1))->weightedKey(['a' => 0]),
]);
