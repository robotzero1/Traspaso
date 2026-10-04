<?php

use App\Actions\Game\StartGame;
use App\Generation\Geo\OsmExtract;
use App\Geo\GeoFiles;
use App\Models\Business;
use App\Models\FootfallPoint;
use App\Models\Neighbourhood;
use App\Models\PointOfInterest;
use App\Models\User;
use Database\Seeders\FootfallPointSeeder;
use Database\Seeders\NeighbourhoodSeeder;
use Database\Seeders\PointOfInterestSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\GeoFixtures;

/*
 * The whole pipeline on the synthetic city: fetch (faked Overpass) → build →
 * seed → play. Paths point at a scratch folder so the committed files are
 * never touched.
 */

beforeEach(function () {
    $this->root = 'storage/framework/testing/geo-'.uniqid();
    config([
        'geo.raw_path' => "{$this->root}/raw",
        'geo.sources_path' => "{$this->root}/out/sources",
        'geo.output_path' => "{$this->root}/out",
    ]);

    File::ensureDirectoryExists(base_path("{$this->root}/raw"));
    File::ensureDirectoryExists(base_path("{$this->root}/out/sources"));
    File::put(base_path("{$this->root}/out/sources/population.csv"), "# a comment\nname,population,year,source\nWest,30000,2025,test\n\"Distrito East\",10000,2025,test\n");
});

afterEach(function () {
    File::deleteDirectory(base_path($this->root));
});

function writeRawFixtures(string $root): void
{
    File::put(base_path("{$root}/raw/streets.json"), json_encode(GeoFixtures::streets()));
    File::put(base_path("{$root}/raw/pois.json"), json_encode(GeoFixtures::pointsOfInterest()));
    File::put(base_path("{$root}/raw/boundaries.json"), json_encode(GeoFixtures::boundaries()));
}

it('downloads streets, points of interest and boundaries from Overpass', function () {
    Http::fake([
        '*' => function (Request $request) {
            $query = $request['data'];

            return Http::response(match (true) {
                str_contains($query, 'highway"~') => GeoFixtures::streets(),
                str_contains($query, 'out center') => GeoFixtures::pointsOfInterest(),
                default => GeoFixtures::boundaries(),
            });
        },
    ]);

    $this->artisan('geo:fetch')->assertSuccessful();

    foreach (['streets', 'pois', 'boundaries'] as $name) {
        expect(base_path("{$this->root}/raw/{$name}.json"))->toBeFile();
    }

    Http::assertSentCount(3);
    // Overpass rejects generic User-Agents with 406.
    Http::assertSent(fn (Request $r) => str_starts_with($r->header('User-Agent')[0] ?? '', 'Traspaso/'));
    Http::assertSent(fn (Request $r) => str_contains($r['data'], '[bbox:41.6,-0.96,41.7,-0.82]') || str_contains($r['data'], 'area['));
});

it('explains a missing certificate bundle', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate'));

    $this->artisan('geo:fetch')
        ->expectsOutputToContain('curl.cainfo')
        ->assertFailed();
});

it('reports a failed download', function () {
    Http::fake(['*' => Http::response('Too many requests', 429)]);

    $this->artisan('geo:fetch')->assertFailed();
});

it('builds the committed files from the downloads', function () {
    writeRawFixtures($this->root);

    $this->artisan('geo:build')->assertSuccessful();

    $out = base_path("{$this->root}/out");
    $neighbourhoods = json_decode(File::get("{$out}/neighbourhoods.geojson"), true);
    $manifest = json_decode(File::get("{$out}/manifest.json"), true);

    expect(array_column(array_column($neighbourhoods['features'], 'properties'), 'name'))->toBe(['West', 'East'])
        // "Distrito East" in the population file matched "East".
        ->and($neighbourhoods['features'][1]['properties']['population'])->toBe(10_000)
        ->and($neighbourhoods['features'][0]['properties']['office'])->toBe(10.0)
        ->and($neighbourhoods['features'][0]['geometry']['type'])->toBe('MultiPolygon')
        ->and("{$out}/points_of_interest.csv")->toBeFile()
        ->and("{$out}/footfall_points.csv")->toBeFile()
        ->and($manifest['counts']['neighbourhoods'])->toBe(2)
        ->and($manifest['counts']['footfall_points'])->toBeGreaterThan(10)
        ->and($manifest['attribution'])->toContain('OpenStreetMap')
        ->and($manifest['footfall']['calibrated'])->toBeFalse();
});

it('fails clearly without downloads', function () {
    $this->artisan('geo:build')->assertFailed()->expectsOutputToContain('Run geo:fetch first');
});

it('prefers a hand-supplied district file over OSM boundaries', function () {
    writeRawFixtures($this->root);
    File::delete(base_path("{$this->root}/raw/boundaries.json"));
    File::put(base_path("{$this->root}/out/sources/neighbourhoods.geojson"), json_encode([
        'type' => 'FeatureCollection',
        'features' => array_map(fn (array $b) => ['type' => 'Feature', 'properties' => ['name' => $b['name']], 'geometry' => $b['geometry']],
            OsmExtract::boundaries(GeoFixtures::boundaries())),
    ]));

    $this->artisan('geo:build')->assertSuccessful();

    expect(json_decode(File::get(base_path("{$this->root}/out/neighbourhoods.geojson")), true)['features'])->toHaveCount(2);
});

it('seeds from the built files and plays on the footfall surface', function () {
    writeRawFixtures($this->root);
    $this->artisan('geo:build')->assertSuccessful();

    // Placeholders first, as on an existing install; the real data replaces them.
    config(['geo.output_path' => "{$this->root}/nowhere"]);
    $this->seed(NeighbourhoodSeeder::class);
    expect(Neighbourhood::query()->count())->toBe(15);

    config(['geo.output_path' => "{$this->root}/out"]);
    $this->seed([NeighbourhoodSeeder::class, PointOfInterestSeeder::class, FootfallPointSeeder::class]);

    expect(Neighbourhood::query()->pluck('name')->sort()->values()->all())->toBe(['East', 'West'])
        ->and(Neighbourhood::query()->where('name', 'West')->sole()->boundary['type'])->toBe('MultiPolygon')
        ->and(PointOfInterest::query()->whereNull('osm_id')->count())->toBe(0)
        ->and(FootfallPoint::query()->count())->toBeGreaterThan(10);

    $user = User::factory()->create();
    $game = app(StartGame::class)->handle($user, 5_000_000, seed: 1);
    $onPoints = $game->businesses()->whereNotNull('footfall_point_id')->with('neighbourhood')->get();

    // Every point gets used before any business falls back.
    expect($onPoints->count())->toBe(min(FootfallPoint::query()->count(), $game->businesses()->count()));

    foreach ($onPoints as $business) {
        $point = FootfallPoint::query()->find($business->footfall_point_id);

        expect([$business->lat, $business->lng])->toBe([$point->lat, $point->lng])
            ->and($business->footfall)->toBe($point->footfall)
            ->and($business->footfall_by_day_part)->toBe($point->footfallByDayPart())
            ->and($business->neighbourhood_id)->toBe($point->neighbourhood_id);
    }

    // The map now shows OSM data: real boundaries, landmark types only, no placeholder note.
    $this->actingAs($user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->where('map.placeholder', false)
        ->where('map.neighbourhoods.0.boundary.type', 'MultiPolygon')
        ->where('map.points_of_interest', fn ($pois) => collect($pois)->every(fn ($p) => in_array($p['type'], config('geo.map_poi_types'), true))));

    // And a business on the surface plays with its own day-part footfall.
    $business = $onPoints->first();
    $this->actingAs($user)->post(route('games.purchase', $game), ['business_id' => $business->id]);
    $this->actingAs($user)->post(route('games.months.store', $game))->assertSessionHasNoErrors();

    expect($game->monthResults()->count())->toBe(1);
});

it('calibrates the surface against pedestrian counts', function () {
    writeRawFixtures($this->root);
    $this->artisan('geo:build')->assertSuccessful();

    $points = app(GeoFiles::class, ['rawPath' => '', 'sourcesPath' => '', 'outputPath' => ''])
        ->readCsv(base_path("{$this->root}/out/footfall_points.csv"));
    $lines = ['lat,lng,day_part,date,minutes,count,notes'];

    foreach (array_slice($points, 0, 12) as $p) {
        $lines[] = "{$p['lat']},{$p['lng']},lunch,2026-10-01,10,".((float) $p['footfall_lunch'] * 7).',';
    }

    File::put(base_path("{$this->root}/out/sources/pedestrian_counts.csv"), implode("\n", $lines)."\n");

    $this->artisan('geo:calibrate --fit')
        ->expectsOutputToContain('Matched to a surface point')
        ->expectsOutputToContain('Rank correlation with the model')
        ->expectsOutputToContain('Best weights')
        ->assertSuccessful();
});

it('asks for counts before calibrating', function () {
    $this->artisan('geo:calibrate')->assertFailed();
});
