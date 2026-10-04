<?php

use App\Generation\UnlistedRivals;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

function unlistedPlaces(): array
{
    return array_map(fn (int $i) => ['key' => "osm-node-{$i}", 'distance_metres' => 50.0 * $i], range(1, 8));
}

it('gives real places fictional names and drawn figures within the configured ranges', function () {
    $config = SimulationFixtures::parameters()['competitors']['unlisted'];
    $taken = ['Café El Cierzo', 'Bar La Esquina'];
    $rivals = (new UnlistedRivals(SimulationFixtures::sheet()))->candidates(unlistedPlaces(), new SeededRng(7), $taken);

    expect($rivals)->toHaveCount(8)
        ->and(array_column($rivals, 'distanceMetres'))->toBe(array_column(unlistedPlaces(), 'distance_metres'));

    $names = array_map(fn ($r) => $r->name, $rivals);
    expect(array_unique($names))->toHaveCount(8)
        ->and(array_intersect($names, $taken))->toBe([]);

    foreach ($rivals as $rival) {
        expect($rival->seats)->toBeGreaterThanOrEqual($config['seats']['min'])->toBeLessThanOrEqual($config['seats']['max'])
            ->and($rival->condition)->toBeGreaterThanOrEqual($config['condition']['min'])->toBeLessThanOrEqual($config['condition']['max'])
            ->and($rival->reputation)->toBeGreaterThanOrEqual($config['reputation']['min'])->toBeLessThanOrEqual($config['reputation']['max']);
    }
});

it('draws the same rivals for the same seed', function () {
    $rivals = new UnlistedRivals(SimulationFixtures::sheet());

    expect($rivals->candidates(unlistedPlaces(), new SeededRng(3)))->toEqual($rivals->candidates(unlistedPlaces(), new SeededRng(3)))
        ->and($rivals->candidates(unlistedPlaces(), new SeededRng(3)))->not->toEqual($rivals->candidates(unlistedPlaces(), new SeededRng(4)));
});
