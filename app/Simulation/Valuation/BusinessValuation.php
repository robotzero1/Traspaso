<?php

namespace App\Simulation\Valuation;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\ParameterSheet;

/**
 * What a buyer would pay for the business: the location and licence part
 * of what it was bought for, its fixtures worn down with the equipment,
 * plus goodwill from recent profits and reputation. Used for net worth
 * and for selling at the end of the game.
 */
final readonly class BusinessValuation
{
    public function __construct(private ParameterSheet $sheet) {}

    /**
     * @param  list<int>  $monthlyProfitsCents  oldest first; only the most recent profit_months count
     */
    public function valueCents(BusinessState $state, int $traspasoPaidCents, array $monthlyProfitsCents): int
    {
        $location = $traspasoPaidCents * $this->sheet->float('valuation.location_share');
        $fixtures = $this->fixturesCents($state, $traspasoPaidCents);
        $goodwill = $this->goodwillCents($state, $monthlyProfitsCents);
        $step = $this->sheet->int('valuation.rounding_cents');

        return (int) (round(($location + $fixtures + $goodwill) / $step) * $step);
    }

    public function fixturesCents(BusinessState $state, int $traspasoPaidCents): float
    {
        $equipmentBase = $this->sheet->float('valuation.equipment_base');

        return $traspasoPaidCents
            * $this->sheet->float('valuation.fixtures_share')
            * ($equipmentBase + (1 - $equipmentBase) * $state->equipmentHealth / 100);
    }

    /**
     * @param  list<int>  $monthlyProfitsCents
     */
    public function goodwillCents(BusinessState $state, array $monthlyProfitsCents): float
    {
        $recent = array_slice($monthlyProfitsCents, -$this->sheet->int('valuation.profit_months'));

        if ($recent === []) {
            return 0.0;
        }

        $annualProfit = max(0.0, array_sum($recent) / count($recent) * 12);
        $reputationFactor = $this->sheet->float('valuation.reputation_base')
            + $this->sheet->float('valuation.reputation_per_point') * $state->reputation;

        return $this->sheet->float('valuation.profit_multiple_years') * $annualProfit * $reputationFactor;
    }
}
