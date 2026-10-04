<?php

use App\Generation\Geo\Polygon;
use App\Generation\Geo\Ranking;
use App\Generation\Geo\RingAssembler;
use App\Generation\Geo\SpatialGrid;
use App\Generation\Geo\StreetGraph;

it('finds points within a radius', function () {
    $grid = new SpatialGrid(100.0);
    $grid->add(41.65, -0.88, 'here');
    $grid->add(41.6509, -0.88, 'north 100 m');
    $grid->add(41.66, -0.88, 'far');

    $found = collect($grid->within(41.65, -0.88, 150.0));

    expect($found->pluck('item')->sort()->values()->all())->toBe(['here', 'north 100 m'])
        ->and($found->firstWhere('item', 'north 100 m')['distance'])->toEqualWithDelta(100.0, 1.0)
        ->and((new SpatialGrid)->within(41.65, -0.88, 100.0))->toBe([]);
});

function square(float $size, float $lat = 41.65, float $lng = -0.88): array
{
    return [[$lng, $lat], [$lng + $size, $lat], [$lng + $size, $lat + $size], [$lng, $lat + $size], [$lng, $lat]];
}

it('tests points against polygons, holes included', function () {
    $polygon = new Polygon(['type' => 'Polygon', 'coordinates' => [square(0.01), square(0.002, 41.654, -0.876)]]);

    expect($polygon->contains(41.651, -0.879))->toBeTrue()
        ->and($polygon->contains(41.655, -0.875))->toBeFalse()   // in the hole
        ->and($polygon->contains(41.70, -0.879))->toBeFalse();   // outside
});

it('handles multipolygons and computes area', function () {
    // A 0.01° × 0.01° square at 41.65° N is about 1.11 km × 0.83 km.
    $one = new Polygon(['type' => 'Polygon', 'coordinates' => [square(0.01)]]);
    $two = new Polygon(['type' => 'MultiPolygon', 'coordinates' => [[square(0.01)], [square(0.01, 41.70)]]]);

    expect($one->areaKm2())->toEqualWithDelta(1.112 * 1.112 * cos(deg2rad(41.655)), 0.01)
        ->and($two->areaKm2())->toEqualWithDelta(2 * $one->areaKm2(), 0.01)
        ->and($two->contains(41.705, -0.875))->toBeTrue();
});

it('rejects other geometry types', function () {
    new Polygon(['type' => 'Point', 'coordinates' => [0, 0]]);
})->throws(InvalidArgumentException::class);

it('assembles rings from segments in any direction', function () {
    $rings = RingAssembler::assemble([
        [[0.0, 0.0], [1.0, 0.0]],
        [[1.0, 1.0], [1.0, 0.0]],       // reversed
        [[1.0, 1.0], [0.0, 1.0], [0.0, 0.0]],
    ]);

    expect($rings)->toHaveCount(1)
        ->and($rings[0])->toHaveCount(5)
        ->and($rings[0][0])->toBe(end($rings[0]));
});

it('closes a ring with a gap rather than losing it', function () {
    $rings = RingAssembler::assemble([[[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]]]);

    expect($rings[0][0])->toBe(end($rings[0]));
});

it('ranks values from 0 to 1 with shared ranks for ties', function () {
    expect(Ranking::percentiles(['a' => 10.0, 'b' => 30.0, 'c' => 20.0]))->toBe(['a' => 0.0, 'c' => 0.5, 'b' => 1.0])
        ->and(Ranking::percentiles([5.0, 5.0, 1.0]))->toEqual([0 => 0.75, 1 => 0.75, 2 => 0.0])
        ->and(Ranking::percentiles([7.0]))->toBe([0.5]);
});

function line(array $ids, float $spacingDeg = 0.0009): StreetGraph
{
    $graph = new StreetGraph;

    foreach ($ids as $i => $id) {
        $graph->addNode($id, 41.65 + $i * $spacingDeg, -0.88);
    }

    for ($i = 1; $i < count($ids); $i++) {
        $graph->addEdge($ids[$i - 1], $ids[$i]);
    }

    return $graph;
}

it('counts shortest paths through each edge', function () {
    // a — b — c, 100 m apart. Edge a–b carries a→b, a→c, b→a, c→a.
    $b = line(['a', 'b', 'c'])->localEdgeBetweenness(1000.0);

    expect($b[StreetGraph::edgeKey('a', 'b')])->toBe(4.0)
        ->and($b[StreetGraph::edgeKey('b', 'c')])->toBe(4.0);
});

it('only counts walks up to the radius', function () {
    // With a 150 m radius, a and c (200 m apart) don't count each other.
    $b = line(['a', 'b', 'c'])->localEdgeBetweenness(150.0);

    expect($b[StreetGraph::edgeKey('a', 'b')])->toBe(2.0);
});

it('splits ties between equal shortest paths', function () {
    // A diamond, mirror-symmetric north–south: a→c can go via b or via d,
    // two exactly equal paths, so each carries half.
    $graph = new StreetGraph;
    foreach (['a' => [0, 0], 'b' => [1, -1], 'c' => [2, 0], 'd' => [1, 1]] as $id => [$r, $c]) {
        $graph->addNode($id, 41.65 + $r * 0.0009, -0.88 + $c * 0.0012);
    }
    foreach ([['a', 'b'], ['b', 'c'], ['c', 'd'], ['d', 'a']] as [$x, $y]) {
        $graph->addEdge($x, $y);
    }

    $b = $graph->localEdgeBetweenness(1000.0);

    // a↔c split over both sides: edge a–b carries a→b, b→a, half of a→c and c→a.
    expect($b[StreetGraph::edgeKey('a', 'b')])->toEqualWithDelta($b[StreetGraph::edgeKey('a', 'd')], 1e-9)
        ->and(array_sum($b))->toEqualWithDelta(16.0, 1e-9);
});

it('scales up when sampling sources', function () {
    $graph = line(['a', 'b', 'c', 'd', 'e']);
    $full = $graph->localEdgeBetweenness(1000.0);
    $sampled = $graph->localEdgeBetweenness(1000.0, 0.5);

    expect(array_sum($sampled))->toEqualWithDelta(array_sum($full), array_sum($full) * 0.5);
});
