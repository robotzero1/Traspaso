<?php

use App\Generation\CompetitorCandidate;
use App\Generation\CompetitorPicker;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

function candidates(int $count): array
{
    return array_map(fn (int $i) => new CompetitorCandidate("market-{$i}", "Café {$i}", 30, $i % 10 + 1, 50.0), range(1, $count));
}

it('picks up to the configured number of nearby rivals, nearest first', function () {
    $config = SimulationFixtures::parameters()['competitors'];
    $picked = (new CompetitorPicker(SimulationFixtures::sheet()))->pick(candidates(12), new SeededRng(1));
    $distances = array_map(fn ($c) => $c->distanceMetres, $picked);

    expect($picked)->toHaveCount($config['nearby_count'])
        ->and($distances)->toBe(collect($distances)->sort()->values()->all())
        ->and(min($distances))->toBeGreaterThanOrEqual($config['distance_metres']['min'])
        ->and(max($distances))->toBeLessThanOrEqual($config['distance_metres']['max']);
});

it('takes everyone when there are few neighbours', function () {
    expect((new CompetitorPicker(SimulationFixtures::sheet()))->pick(candidates(2), new SeededRng(1)))->toHaveCount(2)
        ->and((new CompetitorPicker(SimulationFixtures::sheet()))->pick([], new SeededRng(1)))->toBe([]);
});

it('derives quality from condition and keeps name, seats and reputation', function () {
    $config = SimulationFixtures::parameters()['competitors']['quality'];
    [$rival] = (new CompetitorPicker(SimulationFixtures::sheet()))->pick([new CompetitorCandidate('market-3', 'Bar Uno', 44, 7, 61.0)], new SeededRng(1));

    expect($rival->id)->toBe('market-3')
        ->and($rival->name)->toBe('Bar Uno')
        ->and($rival->seats)->toBe(44)
        ->and($rival->reputation)->toBe(61.0)
        ->and($rival->quality)->toBe((float) ($config['base'] + 7 * $config['per_condition']));
});

it('picks the same rivals for the same seed', function () {
    $picker = new CompetitorPicker(SimulationFixtures::sheet());

    expect($picker->pick(candidates(10), new SeededRng(4)))->toEqual($picker->pick(candidates(10), new SeededRng(4)))
        ->and($picker->pick(candidates(10), new SeededRng(4)))->not->toEqual($picker->pick(candidates(10), new SeededRng(5)));
});

it('picks the nearest located rivals within range, at their real distance', function () {
    $config = SimulationFixtures::parameters()['competitors'];
    $at = fn (string $id, float $metres) => new CompetitorCandidate($id, $id, 30, 5, 50.0, $metres);

    $picked = (new CompetitorPicker(SimulationFixtures::sheet()))->pick([
        $at('far', $config['distance_metres']['max'] + 1),
        $at('c', 300.0),
        $at('a', 10.0),
        $at('b', 120.0),
    ], new SeededRng(1));

    expect(array_map(fn ($c) => $c->id, $picked))->toBe(['a', 'b', 'c'])
        // Never closer than the configured minimum.
        ->and($picked[0]->distanceMetres)->toBe((float) $config['distance_metres']['min'])
        ->and($picked[1]->distanceMetres)->toBe(120.0);
});

it('stops at the configured number even when more are close', function () {
    $count = SimulationFixtures::parameters()['competitors']['nearby_count'];
    $close = array_map(fn (int $i) => new CompetitorCandidate("c{$i}", "C{$i}", 30, 5, 50.0, 50.0 + $i), range(1, $count + 4));

    $picked = (new CompetitorPicker(SimulationFixtures::sheet()))->pick($close, new SeededRng(1));

    expect($picked)->toHaveCount($count)
        ->and(end($picked)->id)->toBe("c{$count}");
});

it('tops up from places that are not for sale when too few listings are near', function () {
    $config = SimulationFixtures::parameters()['competitors'];
    $at = fn (string $id, float $metres) => new CompetitorCandidate($id, $id, 30, 5, 50.0, $metres);

    $picked = (new CompetitorPicker(SimulationFixtures::sheet()))->pick(
        [$at('listing-far', 450.0), $at('listing-out', $config['distance_metres']['max'] + 1)],
        new SeededRng(1),
        [$at('osm-3', 300.0), $at('osm-1', 100.0), $at('osm-2', 200.0), $at('osm-4', 400.0), $at('osm-5', 420.0), $at('osm-out', 900.0)],
    );

    // The listing in range comes first; the nearest places make up the rest.
    expect(array_map(fn ($c) => $c->id, $picked))->toBe(['osm-1', 'osm-2', 'osm-3', 'osm-4', 'listing-far'])
        ->and($picked)->toHaveCount($config['nearby_count']);
});

it('leaves the places out when enough listings are near', function () {
    $at = fn (string $id, float $metres) => new CompetitorCandidate($id, $id, 30, 5, 50.0, $metres);
    $listings = array_map(fn (int $i) => $at("market-{$i}", 100.0 + $i), range(1, 6));

    $picked = (new CompetitorPicker(SimulationFixtures::sheet()))->pick($listings, new SeededRng(1), [$at('osm-1', 10.0)]);

    expect(array_map(fn ($c) => $c->id, $picked))->not->toContain('osm-1');
});
