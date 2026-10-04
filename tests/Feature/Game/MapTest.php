<?php

use App\Actions\Game\StartGame;
use App\Generation\Geo\Geo;
use App\Models\Business;
use App\Models\FootfallPoint;
use App\Models\Neighbourhood;
use App\Models\PointOfInterest;
use App\Models\User;
use Database\Seeders\NeighbourhoodSeeder;
use Database\Seeders\PointOfInterestSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([NeighbourhoodSeeder::class, PointOfInterestSeeder::class]);
    $this->user = User::factory()->create();
});

it('loads neighbourhood areas and points of interest from the committed files', function () {
    // Roughly Zaragoza's municipal area.
    $inZaragoza = fn (float $lat, float $lng) => $lat > 41.55 && $lat < 41.75 && $lng > -1.05 && $lng < -0.75;

    expect(Neighbourhood::query()->whereNull('centre_lat')->count())->toBe(0)
        ->and(PointOfInterest::query()->count())->toBeGreaterThan(5);

    foreach (Neighbourhood::all() as $n) {
        expect($inZaragoza($n->centre_lat, $n->centre_lng))->toBeTrue($n->name)
            ->and($n->radius_m)->toBeGreaterThan(0);
    }

    foreach (PointOfInterest::all() as $p) {
        expect($inZaragoza($p->lat, $p->lng))->toBeTrue($p->name);
    }
});

it('places every business inside its neighbourhood', function () {
    $game = app(StartGame::class)->handle($this->user, 5_000_000, seed: 3);

    foreach ($game->businesses()->with('neighbourhood')->get() as $b) {
        $n = $b->neighbourhood;

        expect(Geo::distanceMetres($n->centre_lat, $n->centre_lng, $b->lat, $b->lng))->toBeLessThanOrEqual($n->radius_m + 1);
    }
});

it('places businesses the same way for the same seed', function () {
    $positions = fn (int $seed) => app(StartGame::class)->handle($this->user, 5_000_000, seed: $seed)
        ->businesses()->orderBy('market_index')->get()->map(fn (Business $b) => [$b->lat, $b->lng])->all();

    expect($positions(5))->toBe($positions(5))
        ->and($positions(5))->not->toBe($positions(6));
});

it('sends the map with OpenStreetMap attribution, areas, landmarks and locations', function () {
    $game = app(StartGame::class)->handle($this->user, 5_000_000, seed: 3);

    $this->actingAs($this->user)->get(route('games.show', $game))->assertInertia(fn (Assert $page) => $page
        ->where('map.tile_url', config('map.tile_url'))
        ->where('map.attribution', fn (string $attribution) => str_contains($attribution, 'OpenStreetMap'))
        ->where('map.placeholder', ! PointOfInterest::query()->whereNotNull('osm_id')->exists())
        ->where('map.has_footfall', false)
        ->where('map.footfall_exponent', config('market.zaragoza_cafe.demand.footfall_exponent'))
        ->where('map.day_part_intensity.morning', config('market.zaragoza_cafe.day_parts.morning.intensity'))
        ->has('map.day_part_intensity', 5)
        ->has('map.neighbourhoods', Neighbourhood::query()->count())
        ->has('map.points_of_interest', PointOfInterest::query()
            ->when(PointOfInterest::query()->whereNotNull('osm_id')->exists(), fn ($q) => $q->whereIn('type', config('geo.map_poi_types')))
            ->count())
        ->has('businesses.0', fn (Assert $b) => $b->whereType('lat', 'double')->whereType('lng', 'double')->etc()));
});

it('locates your business and your rivals on the map', function () {
    $game = app(StartGame::class)->handle($this->user, 5_000_000, seed: 3);
    $business = $game->businesses()->orderBy('traspaso_cents')->first();
    $this->actingAs($this->user)->post(route('games.purchase', $game), ['business_id' => $business->id]);

    // A rival that opened during the game has no listing of its own.
    $game->competitors()->create([
        'key' => 'opened-month-2', 'name' => 'Café Nuevo', 'distance_metres' => 200,
        'price_level' => 1.0, 'quality' => 60, 'reputation' => 45, 'seats' => 30,
    ]);

    $props = $this->actingAs($this->user)->get(route('games.show', $game))->viewData('page')['props'];
    $opened = collect($props['competitors'])->firstWhere('key', 'opened-month-2');

    expect($props['business']['lat'])->toBe($business->lat)
        ->and(collect($props['competitors'])->every(fn ($c) => $c['lat'] !== null && $c['lng'] !== null))->toBeTrue()
        ->and(Geo::distanceMetres($business->lat, $business->lng, $opened['lat'], $opened['lng']))->toEqualWithDelta(200.0, 1.0);
});

it('serves the footfall surface to signed-in players only', function () {
    $neighbourhood = Neighbourhood::query()->first();
    FootfallPoint::query()->create([
        'neighbourhood_id' => $neighbourhood->id, 'lat' => 41.6512, 'lng' => -0.8811, 'street_type' => 'main_street',
        'poi_score' => 0.9, 'centrality_score' => 0.8, 'catchment_score' => 0.7, 'transport_score' => 0.6,
        'footfall' => 8.4, 'footfall_morning' => 7.0, 'footfall_lunch' => 9.1, 'footfall_afternoon' => 8.0,
        'footfall_evening' => 6.5, 'footfall_night' => 2.0,
    ]);

    $this->get(route('map.footfall'))->assertRedirect(route('login'));

    $this->actingAs($this->user)->getJson(route('map.footfall'))
        ->assertOk()
        ->assertExactJson([
            'columns' => ['lat', 'lng', 'footfall', 'footfall_morning', 'footfall_lunch', 'footfall_afternoon', 'footfall_evening', 'footfall_night'],
            'points' => [[41.6512, -0.8811, 8.4, 7.0, 9.1, 8.0, 6.5, 2.0]],
        ]);

    $game = app(StartGame::class)->handle($this->user, 5_000_000, seed: 3);
    $this->actingAs($this->user)->get(route('games.show', $game))
        ->assertInertia(fn (Assert $page) => $page->where('map.has_footfall', true));
});
