<?php

use App\Generation\Distributions\Normal;

it('matches known values of the normal CDF', function (float $z, float $p) {
    expect(Normal::cdf($z))->toEqualWithDelta($p, 1e-6);
})->with([
    [0.0, 0.5],
    [1.0, 0.8413447],
    [-1.0, 0.1586553],
    [1.959964, 0.975],
    [-2.575829, 0.005],
    [8.0, 1.0],
    [-8.0, 0.0],
]);

it('inverts the CDF', function (float $p) {
    expect(Normal::cdf(Normal::inverseCdf($p)))->toEqualWithDelta($p, 1e-6);
})->with([0.0001, 0.01, 0.02425, 0.1, 0.5, 0.9, 0.97575, 0.999, 0.9999]);

it('rejects probabilities outside (0, 1)', function (float $p) {
    expect(fn () => Normal::inverseCdf($p))->toThrow(InvalidArgumentException::class);
})->with([0.0, 1.0, -0.1, 1.1]);
