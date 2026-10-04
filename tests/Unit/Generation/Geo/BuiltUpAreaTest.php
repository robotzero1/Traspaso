<?php

use App\Generation\Geo\BuiltUpArea;
use App\Generation\Geo\Geo;
use App\Generation\Geo\OsmExtract;
use Tests\Support\GeoFixtures;

function builtUpStreets(): array
{
    return OsmExtract::streets(GeoFixtures::streets(), ['primary', 'residential', 'pedestrian']);
}

/** A rectangle in fixture grid units (rows, columns ~100 m apart). */
function gridRectangle(string $name, float $row0, float $col0, float $row1, float $col1): array
{
    $p = fn (float $r, float $c) => [GeoFixtures::lng($c), GeoFixtures::lat($r)];

    return ['name' => $name, 'geometry' => ['type' => 'Polygon', 'coordinates' => [[
        $p($row0, $col0), $p($row0, $col1), $p($row1, $col1), $p($row1, $col0), $p($row0, $col0),
    ]]]];
}

it('counts only the built-up part of a district that takes in countryside', function () {
    // The 800 m street grid inside a district reaching ~3 km out into fields.
    $district = gridRectangle('Wide', -0.5, -0.5, 30, 30);
    [$measured] = (new BuiltUpArea(200, 400))->measure([$district], builtUpStreets());

    // The grid covers ~0.64 km²; whole cells round it up a little.
    expect($measured['area_km2'])->toBeGreaterThan(0.4)->toBeLessThan(1.0)
        ->and(Geo::distanceMetres($measured['centre'][0], $measured['centre'][1], GeoFixtures::lat(4), GeoFixtures::lng(4)))
        ->toBeLessThan(150);
});

it('ignores a lone road through fields', function () {
    $road = [['id' => 1, 'highway' => 'unclassified', 'nodes' => [
        [1, GeoFixtures::lat(20), GeoFixtures::lng(0)],
        [2, GeoFixtures::lat(20), GeoFixtures::lng(25)],
    ]]];

    expect((new BuiltUpArea(200, 400))->measure([gridRectangle('Fields', 10, -1, 30, 30)], $road))->toBe([null]);
});

it('gives each cell to one district', function () {
    $streets = builtUpStreets();
    $areas = (new BuiltUpArea(200, 400))->measure(
        [gridRectangle('West', -0.5, -0.5, 8.5, 4), gridRectangle('East', -0.5, 4, 8.5, 8.5)],
        $streets,
    );
    [$whole] = (new BuiltUpArea(200, 400))->measure([gridRectangle('All', -0.5, -0.5, 8.5, 8.5)], $streets);

    expect($areas[0]['area_km2'] + $areas[1]['area_km2'])->toEqualWithDelta($whole['area_km2'], 1e-9)
        ->and($areas[0]['centre'][1])->toBeLessThan($areas[1]['centre'][1]);
});
