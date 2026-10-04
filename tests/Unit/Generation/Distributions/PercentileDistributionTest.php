<?php

use App\Generation\Distributions\PercentileDistribution;

function rentTable(): PercentileDistribution
{
    return new PercentileDistribution([0 => 350, 10 => 500, 50 => 725, 90 => 1150, 100 => 2000]);
}

it('returns the configured value at each percentile', function (float $u, float $value) {
    expect(rentTable()->quantile($u))->toEqualWithDelta($value, 1e-9);
})->with([[0.0, 350], [0.1, 500], [0.5, 725], [0.9, 1150], [1.0, 2000]]);

it('interpolates linearly between percentiles', function () {
    expect(rentTable()->quantile(0.3))->toEqualWithDelta(612.5, 1e-9)
        ->and(rentTable()->quantile(0.95))->toEqualWithDelta(1575.0, 1e-9);
});

it('has a cdf that inverts the quantile function', function (float $u) {
    $table = rentTable();

    expect($table->cdf($table->quantile($u)))->toEqualWithDelta($u, 1e-9);
})->with([0.0, 0.05, 0.1, 0.33, 0.5, 0.77, 0.9, 0.99, 1.0]);

it('clamps the cdf outside the range', function () {
    expect(rentTable()->cdf(100))->toBe(0.0)
        ->and(rentTable()->cdf(5000))->toBe(1.0);
});

it('accepts unsorted tables and flat stretches', function () {
    $table = new PercentileDistribution([100 => 10, 0 => 1, 50 => 5, 60 => 5]);

    expect($table->quantile(0.55))->toBe(5.0)
        ->and($table->cdf(5))->toBe(0.6)
        ->and($table->min())->toBe(1.0)
        ->and($table->max())->toBe(10.0);
});

it('rejects invalid tables', function (array $knots) {
    expect(fn () => new PercentileDistribution($knots))->toThrow(InvalidArgumentException::class);
})->with([
    'no minimum' => [[10 => 1, 100 => 2]],
    'no maximum' => [[0 => 1, 90 => 2]],
    'decreasing' => [[0 => 5, 50 => 4, 100 => 6]],
    'percent above 100' => [[0 => 1, 100 => 2, 110 => 3]],
    'non-numeric value' => [[0 => 1, 100 => 'lots']],
]);

it('rejects u outside 0–1', function () {
    rentTable()->quantile(1.01);
})->throws(InvalidArgumentException::class);
