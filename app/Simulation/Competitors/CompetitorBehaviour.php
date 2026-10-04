<?php

namespace App\Simulation\Competitors;

use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Demand\CaptureRate;
use App\Simulation\Rng\SeededRng;
use App\Simulation\State\StateEvolution;

/**
 * Step 9: each nearby competitor adjusts.
 *
 * - Rivals within the follow radius move their prices towards the
 *   player's, so a price war spreads and a premium invites undercutting.
 * - Quality drifts a little at random; a rival that the player
 *   out-attracts improves its quality to fight back.
 * - Reputation moves towards a target from quality and price, like the
 *   player's (with average service).
 */
final readonly class CompetitorBehaviour
{
    public function __construct(private ParameterSheet $sheet) {}

    /**
     * @param  list<CompetitorState>  $competitors
     * @return list<CompetitorState>
     */
    public function next(array $competitors, float $playerPriceLevel, float $playerAttractiveness, SeededRng $rng): array
    {
        $capture = new CaptureRate($this->sheet);
        $evolution = new StateEvolution($this->sheet);
        $next = [];

        foreach ($competitors as $competitor) {
            $own = $rng->fork($competitor->id);
            $nearby = $competitor->distanceMetres <= $this->sheet->float('competitors.follow_radius_metres');

            $price = $competitor->priceLevel
                + ($nearby ? $this->sheet->float('competitors.price_follow_rate') * ($playerPriceLevel - $competitor->priceLevel) : 0.0)
                + $own->normal(0.0, $this->sheet->float('competitors.price_noise_sd'));
            $price = min($this->sheet->float('competitors.price_max'), max($this->sheet->float('competitors.price_min'), $price));

            $attractiveness = $capture->attractiveness($competitor->reputation, $competitor->priceLevel, $competitor->quality);
            $quality = $competitor->quality
                + $own->normal(0.0, $this->sheet->float('competitors.quality_noise_sd'))
                + ($nearby && $playerAttractiveness > $attractiveness ? $this->sheet->float('competitors.quality_response') : 0.0);
            $quality = $this->clamp($quality);

            $target = $evolution->reputationTarget($quality, $price, 50.0);
            $reputation = $competitor->reputation
                + $this->sheet->float('competitors.reputation_adjustment_rate') * ($target - $competitor->reputation);

            $next[] = $competitor->with(
                priceLevel: round($price, 3),
                quality: round($quality, 2),
                reputation: round($this->clamp($reputation), 2),
            );
        }

        return $next;
    }

    private function clamp(float $score): float
    {
        return min(100.0, max(0.0, $score));
    }
}
