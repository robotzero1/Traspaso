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
