<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\ParameterSheet;

/**
 * Step 3: the share of potential customers who choose this business,
 * driven by reputation, price against the local average, quality,
 * marketing and how attractive the nearby competitors are.
 */
final readonly class CaptureRate
{
    public function __construct(private ParameterSheet $sheet) {}

    /**
     * @param  list<CompetitorState>  $competitors
     */
    public function rate(BusinessState $state, Decisions $decisions, float $quality, array $competitors): float
    {
        $own = $this->attractiveness($state->reputation, $decisions->priceLevel, $quality)
            * $this->conditionFactor($state->profile->condition)
            * $this->marketingFactor($decisions->marketingSpendCents);

        $pressure = $this->competitionPressure($competitors)
            + $this->densityPressure($state->profile->neighbourhood->competitionDensity);

        return $this->sheet->float('capture.base_rate') * $own / (1 + $pressure);
    }

    public function attractiveness(float $reputation, float $priceLevel, float $quality): float
    {
        $reputationFactor = $this->sheet->float('capture.reputation.base')
            + $this->sheet->float('capture.reputation.per_point') * $reputation;
        $qualityFactor = $this->sheet->float('capture.quality.base')
            + $this->sheet->float('capture.quality.per_point') * $quality;

        return $reputationFactor * $priceLevel ** -$this->sheet->float('capture.price_elasticity') * $qualityFactor;
    }

    public function marketingFactor(int $spendCents): float
    {
        return 1 + $this->sheet->float('capture.marketing.max_boost')
            * (1 - exp(-$spendCents / $this->sheet->float('capture.marketing.scale_cents')));
    }

    public function conditionFactor(int $condition): float
    {
        return $this->sheet->float('capture.condition.base') + $this->sheet->float('capture.condition.per_point') * $condition;
    }

    /** Pressure from all the cafés and bars around that the game doesn't model one by one. */
    public function densityPressure(float $competitionDensity): float
    {
        return $this->sheet->float('capture.competition.density_weight')
            * $competitionDensity / $this->sheet->float('capture.competition.density_reference');
    }

    /**
     * Σ competitor attractiveness, fading with distance, times the weight.
     *
     * @param  list<CompetitorState>  $competitors
     */
    public function competitionPressure(array $competitors): float
    {
        $decay = $this->sheet->float('capture.competition.distance_decay_metres');
        $pressure = 0.0;

        foreach ($competitors as $competitor) {
            $pressure += exp(-$competitor->distanceMetres / $decay)
                * $this->attractiveness($competitor->reputation, $competitor->priceLevel, $competitor->quality);
        }

        return $this->sheet->float('capture.competition.weight') * $pressure;
    }
}
