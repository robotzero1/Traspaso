<?php

use App\Simulation\Data\CompetitorState;
use App\Simulation\Demand\CaptureRate;
use Tests\Support\SimulationFixtures;

function capture(array $state = [], array $decisions = [], float $quality = 55.0, array $competitors = []): float
{
    return (new CaptureRate(SimulationFixtures::sheet()))->rate(
        SimulationFixtures::state()->with(...$state),
        SimulationFixtures::decisions()->with(...$decisions),
        $quality,
        $competitors,
    );
}

it('rises with reputation', function () {
    expect(capture(['reputation' => 80.0]))->toBeGreaterThan(capture(['reputation' => 50.0]))
        ->and(capture(['reputation' => 50.0]))->toBeGreaterThan(capture(['reputation' => 10.0]));
});

it('falls as prices rise above the local average', function () {
    expect(capture(decisions: ['priceLevel' => 0.9]))->toBeGreaterThan(capture())
        ->and(capture())->toBeGreaterThan(capture(decisions: ['priceLevel' => 1.5]));
});

it('follows the configured price elasticity', function () {
    $elasticity = SimulationFixtures::parameters()['capture']['price_elasticity'];

    expect(capture(decisions: ['priceLevel' => 1.5]) / capture())->toEqualWithDelta(1.5 ** -$elasticity, 1e-9);
});

it('rises with quality and the condition of the premises', function () {
    $profile = SimulationFixtures::profile();

    expect(capture(quality: 80.0))->toBeGreaterThan(capture(quality: 35.0))
        ->and(capture(['profile' => $profile->with(condition: 9)]))->toBeGreaterThan(capture(['profile' => $profile->with(condition: 3)]));
});

it('gets diminishing returns from marketing', function () {
    $rate = new CaptureRate(SimulationFixtures::sheet());
    $maxBoost = SimulationFixtures::parameters()['capture']['marketing']['max_boost'];

    $firstHundred = $rate->marketingFactor(10_000) - $rate->marketingFactor(0);
    $tenthHundred = $rate->marketingFactor(100_000) - $rate->marketingFactor(90_000);

    expect($rate->marketingFactor(0))->toBe(1.0)
        ->and($firstHundred)->toBeGreaterThan($tenthHundred * 3)
        ->and($rate->marketingFactor(10_000_000))->toBeLessThanOrEqual(1 + $maxBoost);
});

it('loses customers to nearby, attractive competitors', function () {
    $near = new CompetitorState('near', 'Near', 50.0, 1.0, 60.0, 70.0, 30);

    expect(capture(competitors: [$near]))->toBeLessThan(capture())
        ->and(capture(competitors: [$near->with(distanceMetres: 600.0)]))->toBeGreaterThan(capture(competitors: [$near]))
        ->and(capture(competitors: [$near->with(reputation: 20.0)]))->toBeGreaterThan(capture(competitors: [$near]))
        ->and(capture(competitors: [$near->with(priceLevel: 1.4)]))->toBeGreaterThan(capture(competitors: [$near]));
});

it('feels the general competition in a dense neighbourhood', function () {
    $profile = SimulationFixtures::profile();
    $dense = $profile->with(neighbourhood: $profile->neighbourhood->with(competitionDensity: 150.0));
    $sparse = $profile->with(neighbourhood: $profile->neighbourhood->with(competitionDensity: 10.0));

    expect(capture(['profile' => $sparse]))->toBeGreaterThan(capture(['profile' => $dense]));
});

it('stays a small share of passers-by', function () {
    expect(capture())->toBeGreaterThan(0.0)->toBeLessThan(0.2)
        ->and(capture(['reputation' => 100.0], ['priceLevel' => 0.7, 'marketingSpendCents' => 1_000_000], 100.0))->toBeLessThan(0.5);
});
