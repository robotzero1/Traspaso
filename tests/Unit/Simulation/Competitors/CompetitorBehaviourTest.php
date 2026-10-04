<?php

use App\Simulation\Competitors\CompetitorBehaviour;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

function noiselessBehaviour(): CompetitorBehaviour
{
    $parameters = SimulationFixtures::parameters();
    $parameters['competitors']['price_noise_sd'] = 0.0;
    $parameters['competitors']['quality_noise_sd'] = 0.0;

    return new CompetitorBehaviour(new ParameterSheet($parameters));
}

function rival(float $distance = 100.0, float $price = 1.0, float $quality = 55.0, float $reputation = 50.0): CompetitorState
{
    return new CompetitorState('r', 'Rival', $distance, $price, $quality, $reputation, 30);
}

it('follows the player\'s prices when nearby', function () {
    $rate = SimulationFixtures::parameters()['competitors']['price_follow_rate'];
    [$next] = noiselessBehaviour()->next([rival()], 0.8, 1.0, new SeededRng(1));

    expect($next->priceLevel)->toEqualWithDelta(1.0 + $rate * (0.8 - 1.0), 1e-3);
});

it('ignores the player\'s prices when far away', function () {
    [$next] = noiselessBehaviour()->next([rival(distance: 900.0)], 0.8, 1.0, new SeededRng(1));

    expect($next->priceLevel)->toBe(1.0);
});

it('keeps prices within the configured band', function () {
    $config = SimulationFixtures::parameters()['competitors'];
    $competitors = [rival(price: 1.3)];

    for ($month = 0; $month < 50; $month++) {
        $competitors = noiselessBehaviour()->next($competitors, 2.0, 1.0, new SeededRng($month));
    }

    expect($competitors[0]->priceLevel)->toBeLessThanOrEqual($config['price_max']);
});

it('fights back with quality when the player out-attracts it', function () {
    [$losing] = noiselessBehaviour()->next([rival()], 1.0, 10.0, new SeededRng(1));
    [$winning] = noiselessBehaviour()->next([rival()], 1.0, 0.1, new SeededRng(1));

    expect($losing->quality)->toBeGreaterThan(55.0)
        ->and($winning->quality)->toBe(55.0);
});

it('moves reputation towards what quality and price deserve', function () {
    [$good] = noiselessBehaviour()->next([rival(quality: 90.0, reputation: 40.0)], 1.0, 0.1, new SeededRng(1));
    [$bad] = noiselessBehaviour()->next([rival(price: 1.3, quality: 30.0, reputation: 60.0)], 1.3, 0.1, new SeededRng(1));

    expect($good->reputation)->toBeGreaterThan(40.0)
        ->and($bad->reputation)->toBeLessThan(60.0);
});

it('drifts deterministically with noise', function () {
    $behaviour = new CompetitorBehaviour(SimulationFixtures::sheet());
    $once = $behaviour->next([rival(), rival(distance: 250.0)->with(id: 'r2')], 1.0, 1.0, new SeededRng(3));

    expect($once)->toEqual($behaviour->next([rival(), rival(distance: 250.0)->with(id: 'r2')], 1.0, 1.0, new SeededRng(3)))
        ->and($once[0]->quality)->not->toBe(55.0);
});
