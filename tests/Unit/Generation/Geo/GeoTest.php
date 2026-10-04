<?php

use App\Generation\Geo\Geo;
use App\Generation\Geo\LocationPlacer;
use App\Simulation\Rng\SeededRng;

it('measures great-circle distances', function () {
    // One degree of latitude on a 6,371 km sphere.
    expect(Geo::distanceMetres(0.0, 0.0, 1.0, 0.0))->toEqualWithDelta(111_194.9, 0.5)
        ->and(Geo::distanceMetres(41.65, -0.88, 41.65, -0.88))->toBe(0.0)
        // Symmetric.
        ->and(Geo::distanceMetres(41.6566, -0.8784, 41.6509, -0.8794))
        ->toEqualWithDelta(Geo::distanceMetres(41.6509, -0.8794, 41.6566, -0.8784), 1e-6);
});

it('offsets a point by a distance and bearing', function (float $bearing) {
    [$lat, $lng] = Geo::offset(41.65, -0.88, 750.0, $bearing);

    expect(Geo::distanceMetres(41.65, -0.88, $lat, $lng))->toEqualWithDelta(750.0, 0.01);
})->with([0.0, 45.0, 90.0, 180.0, 271.5]);

it('moves north for bearing 0 and east for bearing 90', function () {
    [$northLat, $northLng] = Geo::offset(41.65, -0.88, 1000.0, 0.0);
    [$eastLat, $eastLng] = Geo::offset(41.65, -0.88, 1000.0, 90.0);

    expect($northLat)->toBeGreaterThan(41.65)
        ->and($northLng)->toEqualWithDelta(-0.88, 1e-9)
        ->and($eastLng)->toBeGreaterThan(-0.88);
});

it('places points inside the circle, spread over its area', function () {
    $distances = array_map(function (int $seed) {
        [$lat, $lng] = LocationPlacer::inCircle(41.65, -0.88, 800.0, new SeededRng($seed));

        return Geo::distanceMetres(41.65, -0.88, $lat, $lng);
    }, range(1, 2000));

    // Uniform over a disc: mean distance from the centre is 2/3 of the radius.
    expect(max($distances))->toBeLessThanOrEqual(800.5)
        ->and(array_sum($distances) / count($distances))->toEqualWithDelta(800 * 2 / 3, 15);
});

it('places the same point for the same seed', function () {
    expect(LocationPlacer::inCircle(41.65, -0.88, 800.0, new SeededRng(3)))
        ->toBe(LocationPlacer::inCircle(41.65, -0.88, 800.0, new SeededRng(3)));
});
