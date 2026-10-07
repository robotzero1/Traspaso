<?php

namespace App\Simulation\State;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\ParameterSheet;

/**
 * Step 8: how the business changes over the month. Reputation and staff
 * morale move part of the way towards a target each month; equipment
 * wears and ages; event modifiers count down a month.
 *
 * The daily engine takes the same steps a day at a time (day(), then
 * monthEnd()): reputation and morale move on open days, at the daily rate
 * that adds up to the monthly one over the month's open days; equipment
 * wears a share each day.
 */
final readonly class StateEvolution
{
    public function __construct(private ParameterSheet $sheet) {}

    /**
     * @param  float  $quality  quality served this month, 0–100
     * @param  float  $utilisation  demand ÷ what the people on shift could serve
     */
    public function next(BusinessState $state, Decisions $decisions, float $quality, float $utilisation, int $cashAfterCents): BusinessState
    {
        $service = $this->serviceScore($state->staffMorale, $utilisation);

        return $state->with(
            cashCents: $cashAfterCents,
            reputation: $this->approach($state->reputation, $this->reputationTarget($quality, $decisions->priceLevel, $service), $this->sheet->float('reputation.adjustment_rate')),
            staffCount: $decisions->staffCount,
            staffMorale: $this->approach($state->staffMorale, $this->moraleTarget($utilisation), $this->sheet->float('morale.adjustment_rate')),
            equipmentHealth: max(0.0, $state->equipmentHealth - $this->wear($state->equipmentAgeMonths)),
            equipmentAgeMonths: $state->equipmentAgeMonths + 1,
            stockQuality: $quality,
            modifiers: $state->modifierSet()->tick(),
        );
    }

    /**
     * One day. On a closed day reputation and morale stay where they are.
     *
     * @param  int  $openDaysInMonth  the steps reputation and morale take this month
     */
    public function day(BusinessState $state, Decisions $decisions, float $quality, float $utilisation, int $cashAfterCents, bool $open, int $daysInMonth, int $openDaysInMonth): BusinessState
    {
        $state = $state->with(
            cashCents: $cashAfterCents,
            staffCount: $decisions->staffCount,
            equipmentHealth: max(0.0, $state->equipmentHealth - $this->wear($state->equipmentAgeMonths) / $daysInMonth),
            modifiers: $state->modifierSet()->tickDay(),
        );

        if (! $open) {
            return $state;
        }

        $service = $this->serviceScore($state->staffMorale, $utilisation);
        $steps = max(1, $openDaysInMonth);

        return $state->with(
            reputation: $this->approach($state->reputation, $this->reputationTarget($quality, $decisions->priceLevel, $service), $this->dailyRate('reputation', $steps)),
            staffMorale: $this->approach($state->staffMorale, $this->moraleTarget($utilisation), $this->dailyRate('morale', $steps)),
            stockQuality: $quality,
        );
    }

    /** What changes once a month in the daily engine: equipment ages, monthly modifiers count down. */
    public function monthEnd(BusinessState $state): BusinessState
    {
        return $state->with(
            equipmentAgeMonths: $state->equipmentAgeMonths + 1,
            modifiers: $state->modifierSet()->tick(),
        );
    }

    /** The daily rate that, taken $steps times, moves as far as the monthly rate. */
    public function dailyRate(string $section, int $steps): float
    {
        return 1 - (1 - $this->sheet->float("{$section}.adjustment_rate")) ** (1 / $steps);
    }

    /** 0–100: how well customers are looked after. */
    public function serviceScore(float $morale, float $utilisation): float
    {
        $overload = max(0.0, $utilisation - $this->sheet->float('service.comfortable_utilisation'));

        return $this->clamp($this->sheet->float('service.morale_weight') * $morale
            + $this->sheet->float('service.base')
            - $this->sheet->float('service.overload_penalty') * $overload);
    }

    public function reputationTarget(float $quality, float $priceLevel, float $service): float
    {
        $priceEffect = $priceLevel >= 1.0
            ? -$this->sheet->float('reputation.price_premium_penalty') * ($priceLevel - 1.0)
            : $this->sheet->float('reputation.price_discount_bonus') * (1.0 - $priceLevel);

        return $this->clamp($this->sheet->float('reputation.target_base')
            + $this->sheet->float('reputation.quality_weight') * ($quality - 50)
            + $priceEffect
            + $this->sheet->float('reputation.service_weight') * ($service - 50));
    }

    public function moraleTarget(float $utilisation): float
    {
        $overload = max(0.0, $utilisation - $this->sheet->float('service.comfortable_utilisation'));

        return $this->clamp($this->sheet->float('morale.base') - $this->sheet->float('morale.overwork_penalty') * $overload);
    }

    public function wear(int $equipmentAgeMonths): float
    {
        return $this->sheet->float('equipment.wear_per_month')
            + $this->sheet->float('equipment.wear_per_age_year') * $equipmentAgeMonths / 12;
    }

    private function approach(float $current, float $target, float $rate): float
    {
        return $this->clamp($current + $rate * ($target - $current));
    }

    private function clamp(float $score): float
    {
        return min(100.0, max(0.0, $score));
    }
}
