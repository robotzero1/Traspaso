<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\ParameterSheet;

/**
 * The quality customers get, 0–100: the tier's score, cut by worn equipment.
 */
final readonly class QualityScore
{
    public function __construct(private ParameterSheet $sheet) {}

    public function score(BusinessState $state, Decisions $decisions): float
    {
        $tierScore = $this->sheet->float("quality.tier_scores.{$decisions->qualityTier->value}");
        $threshold = $this->sheet->float('quality.equipment_threshold');
        $minFactor = $this->sheet->float('quality.equipment_min_factor');

        $factor = $state->equipmentHealth >= $threshold
            ? 1.0
            : $minFactor + (1.0 - $minFactor) * $state->equipmentHealth / $threshold;

        return min(100.0, max(0.0, $tierScore * $factor));
    }
}
