<?php

use App\Generation\Geo\Calibration;
use App\Generation\Geo\FootfallSurfaceBuilder;
use App\Generation\Geo\Geo;
use App\Generation\Geo\NeighbourhoodIndexer;
use App\Generation\Geo\OsmExtract;
use App\Generation\Geo\Polygon;
use App\Simulation\Data\DayPart;
use Tests\Support\GeoFixtures;

function geoConfig(): array
{
    return require dirname(__DIR__, 4).'/config/geo.php';
}

function fixtureStreets(): array
{
    return OsmExtract::streets(GeoFixtures::streets(), geoConfig()['street_highway_types']);
}

function fixturePois(): array
{
    return OsmExtract::pointsOfInterest(GeoFixtures::pointsOfInterest(), geoConfig()['poi_types'], geoConfig()['competitor_tags']);
}

function fixtureNeighbourhoods(): array
{
    return array_map(fn (array $b) => [...$b, 'population' => GeoFixtures::population()[$b['name']]], OsmExtract::boundaries(GeoFixtures::boundaries()));
}

function fixtureSurface(): array
{
    static $surface;

    return $surface ??= (new FootfallSurfaceBuilder(geoConfig()['footfall'], geoConfig()['street_types']))
        ->build(fixtureStreets(), fixturePois(), fixtureNeighbourhoods());
}

// Reading Overpass ------------------------------------------------------------

it('reads walkable streets with their node coordinates', function () {
    $streets = fixtureStreets();

    expect($streets)->toHaveCount(18)
        ->and(collect($streets)->pluck('highway')->unique()->sort()->values()->all())->toBe(['pedestrian', 'primary', 'residential'])
        ->and($streets[0]['nodes'][0])->toBe([GeoFixtures::nodeId(0, 0), GeoFixtures::lat(0), GeoFixtures::lng(0)]);
});

it('classifies points of interest and flags competitors', function () {
    $pois = collect(fixturePois());

    expect($pois->where('type', 'hospitality')->count())->toBe(10)
        ->and($pois->where('competitor', true)->count())->toBe(10)
        ->and($pois->where('type', 'office')->count())->toBe(6)
        ->and($pois->firstWhere('type', 'university'))->toMatchArray(['name' => 'Campus Este', 'osm_id' => 'way/7000', 'lat' => GeoFixtures::lat(6)])
        ->and($pois->where('type', 'station')->count())->toBe(1)
        ->and($pois->contains('type', null))->toBeFalse()
        ->and($pois)->toHaveCount(34);
});

it('assembles district boundaries from relation members', function () {
    $boundaries = OsmExtract::boundaries(GeoFixtures::boundaries());
    $west = new Polygon($boundaries[0]['geometry']);

    expect(array_column($boundaries, 'name'))->toBe(['West', 'East'])
        ->and($west->contains(GeoFixtures::lat(4), GeoFixtures::lng(2)))->toBeTrue()
        ->and($west->contains(GeoFixtures::lat(4), GeoFixtures::lng(6)))->toBeFalse();
});

// Neighbourhood indices -------------------------------------------------------

it('ignores notes in the index config', function () {
    $rows = NeighbourhoodIndexer::index(fixtureNeighbourhoods(), fixturePois(), ['source' => 'a note', 'office' => ['office' => 1.0]]);

    expect($rows[0])->toHaveKey('office')->not->toHaveKey('source');
});

it('derives indices by ranking densities across neighbourhoods', function () {
    $rows = collect(NeighbourhoodIndexer::index(fixtureNeighbourhoods(), fixturePois(), geoConfig()['indices']))->keyBy('name');

    expect($rows['West']['office'])->toBeGreaterThan($rows['East']['office'])
        ->and($rows['East']['student'])->toBeGreaterThan($rows['West']['student'])
        ->and($rows['West']['office'])->toBe(10.0)
        ->and($rows['East']['office'])->toBe(0.0)
        ->and($rows['West']['area_km2'])->toBeGreaterThan(0.3)
        ->and($rows['West']['competition_density'])->toBeGreaterThan(0.0)
        ->and($rows['West']['population'])->toBe(30_000)
        ->and($rows['West']['radius_m'])->toBeGreaterThan(0);
});

// The footfall surface ----------------------------------------------------------

it('measures densities over the built-up area when given', function () {
    $neighbourhoods = fixtureNeighbourhoods();
    $plain = collect(NeighbourhoodIndexer::index($neighbourhoods, fixturePois(), geoConfig()['indices']))->keyBy('name');

    $neighbourhoods[0]['built_up'] = ['area_km2' => $plain['West']['area_km2'] / 4, 'centre' => [41.6, -0.9]];
    $built = collect(NeighbourhoodIndexer::index($neighbourhoods, fixturePois(), geoConfig()['indices']))->keyBy('name');

    expect($built['West']['area_km2'])->toEqualWithDelta($plain['West']['area_km2'] / 4, 0.001)
        ->and($built['West']['boundary_area_km2'])->toBe($plain['West']['area_km2'])
        ->and($built['West']['competition_density'])->toEqualWithDelta(4 * $plain['West']['competition_density'], 0.2)
        ->and($built['West']['centre'])->toBe([41.6, -0.9])
        // A quarter of the area: half the radius.
        ->and($built['West']['radius_m'])->toEqualWithDelta($plain['West']['radius_m'] / 2, 1);
});

it('keeps only commercial points inside a neighbourhood', function () {
    $surface = fixtureSurface();
    $commercialRadius = geoConfig()['footfall']['commercial']['radius_metres'];
    $shops = array_filter(fixturePois(), fn ($p) => in_array($p['type'], ['shop', 'hospitality'], true));

    expect($surface)->not->toBeEmpty();

    foreach ($surface as $point) {
        $nearest = min(array_map(fn ($s) => Geo::distanceMetres($point['lat'], $point['lng'], $s['lat'], $s['lng']), $shops));

        expect($nearest)->toBeLessThanOrEqual($commercialRadius + 1)
            ->and($point['neighbourhood'])->toBeIn(['West', 'East']);
    }
});

it('scores the main avenue above side streets', function () {
    $byType = collect(fixtureSurface())->groupBy('street_type')->map(fn ($points) => $points->avg('footfall'));

    expect($byType['main_street'])->toBeGreaterThan($byType['side_street'] ?? 0);
});

it('spreads footfall over the configured scale', function () {
    $footfall = collect(fixtureSurface())->pluck('footfall');
    $scale = geoConfig()['footfall']['percentiles'];

    expect($footfall->min())->toBeGreaterThanOrEqual($scale[0])
        ->and($footfall->max())->toBeLessThanOrEqual($scale[100])
        ->and($footfall->max())->toBe((float) $scale[100])
        ->and($footfall->median())->toEqualWithDelta($scale[50], 1.5);
});

it('gives each day part its own footfall, following who is around', function () {
    $west = collect(fixtureSurface())->where('neighbourhood', 'West');
    $east = collect(fixtureSurface())->where('neighbourhood', 'East');

    foreach (DayPart::cases() as $part) {
        expect(fixtureSurface()[0])->toHaveKey("footfall_{$part->value}");
    }

    // The office district gains, relative to the east, at lunch over night.
    $lunchGap = $west->avg('footfall_lunch') - $east->avg('footfall_lunch');
    $nightGap = $west->avg('footfall_night') - $east->avg('footfall_night');

    expect($lunchGap)->toBeGreaterThan($nightGap);
});

it('stores component scores as ranks for calibration', function () {
    foreach (fixtureSurface() as $point) {
        foreach (['poi_score', 'centrality_score', 'catchment_score', 'transport_score', 'poi_morning_score'] as $key) {
            expect($point[$key])->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
        }
    }
});

it('builds the same surface from the same data', function () {
    $build = fn () => (new FootfallSurfaceBuilder(geoConfig()['footfall'], geoConfig()['street_types']))
        ->build(fixtureStreets(), fixturePois(), fixtureNeighbourhoods());

    expect($build())->toBe($build());
});

it('builds nothing without commercial streets', function () {
    $noShops = array_values(array_filter(fixturePois(), fn ($p) => ! in_array($p['type'], ['shop', 'hospitality'], true)));

    expect((new FootfallSurfaceBuilder(geoConfig()['footfall'], geoConfig()['street_types']))->build(fixtureStreets(), $noShops, fixtureNeighbourhoods()))->toBe([]);
});

// Calibration -----------------------------------------------------------------------

it('matches counts to the nearest surface point', function () {
    $point = fixtureSurface()[3];
    $counts = [
        ['lat' => $point['lat'] + 0.00005, 'lng' => $point['lng'], 'day_part' => 'morning', 'count' => 40],
        ['lat' => 41.0, 'lng' => -1.0, 'day_part' => 'morning', 'count' => 10],
    ];

    $matched = Calibration::match($counts, fixtureSurface());

    expect($matched)->toHaveCount(1)
        ->and($matched[0]['point'])->toBe($point)
        ->and($matched[0]['distance'])->toBeLessThan(10);
});

it('agrees perfectly with counts that follow the model', function () {
    $counts = array_map(fn (array $p) => ['lat' => $p['lat'], 'lng' => $p['lng'], 'day_part' => 'lunch', 'count' => $p['footfall_lunch'] * 10], array_slice(fixtureSurface(), 0, 30));
    $matched = Calibration::match($counts, fixtureSurface());

    expect(Calibration::spearman($matched))->toEqualWithDelta(1.0, 1e-9)
        ->and(Calibration::pValue(0.9, 20))->toBeLessThan(0.001)
        ->and(Calibration::pValue(0.1, 20))->toBeGreaterThan(0.5);
});

it('fits the component weights that best explain the counts', function () {
    // Counts driven only by transport: the fit should put its weight there.
    $counts = array_map(fn (array $p) => ['lat' => $p['lat'], 'lng' => $p['lng'], 'day_part' => 'morning', 'count' => $p['transport_score'] * 100], fixtureSurface());
    $fit = Calibration::fit(Calibration::match($counts, fixtureSurface()));

    expect($fit['weights']['transport'])->toBe(1.0)
        ->and($fit['spearman'])->toEqualWithDelta(1.0, 1e-9)
        ->and(array_sum($fit['weights']))->toEqualWithDelta(1.0, 1e-9);
});

it('needs at least three matched counts', function () {
    expect(Calibration::spearman([]))->toBeNan();
});
