<?php

use App\Simulation\Data\QualityTier;
use App\Simulation\Demand\QualityScore;
use Tests\Support\SimulationFixtures;

function quality(QualityTier $tier, float $equipmentHealth = 80.0): float
{
    return (new QualityScore(SimulationFixtures::sheet()))->score(
        SimulationFixtures::state()->with(equipmentHealth: $equipmentHealth),
        SimulationFixtures::decisions()->with(qualityTier: $tier),
    );
}

it('uses the tier score while equipment is healthy', function (QualityTier $tier) {
    expect(quality($tier))->toBe((float) SimulationFixtures::parameters()['quality']['tier_scores'][$tier->value]);
})->with(QualityTier::cases());

it('ranks the tiers', function () {
    expect(quality(QualityTier::Premium))->toBeGreaterThan(quality(QualityTier::Standard))
        ->and(quality(QualityTier::Standard))->toBeGreaterThan(quality(QualityTier::Budget));
});

it('drops with worn equipment, down to the minimum factor', function () {
    $config = SimulationFixtures::parameters()['quality'];
    $tierScore = $config['tier_scores']['standard'];

    expect(quality(QualityTier::Standard, $config['equipment_threshold']))->toBe((float) $tierScore)
        ->and(quality(QualityTier::Standard, $config['equipment_threshold'] / 2))
        ->toEqualWithDelta($tierScore * (1 + $config['equipment_min_factor']) / 2, 1e-9)
        ->and(quality(QualityTier::Standard, 0.0))->toEqualWithDelta($tierScore * $config['equipment_min_factor'], 1e-9);
});
