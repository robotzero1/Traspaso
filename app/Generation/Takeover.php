<?php

namespace App\Generation;

use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\BusinessState;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Data\QualityTier;
use App\Simulation\Sale\BuyingCosts;

/**
 * The business as the player finds it on the day they take it over, and
 * the decisions it starts with.
 */
final readonly class Takeover
{
    public function __construct(private ParameterSheet $sheet) {}

    public function initialState(BusinessProfile $profile, float $baseReputation, int $equipmentAgeYears, int $cashCents): BusinessState
    {
        $health = $this->sheet->float('takeover.equipment_health.base')
            + $this->sheet->float('takeover.equipment_health.per_condition') * $profile->condition;

        return new BusinessState(
            profile: $profile,
            cashCents: $cashCents,
            reputation: $baseReputation,
            staffCount: $this->sheet->int('takeover.staff_count'),
            staffMorale: $this->sheet->float('takeover.staff_morale'),
            equipmentHealth: min(100.0, max(0.0, $health)),
            equipmentAgeMonths: $equipmentAgeYears * 12,
            stockQuality: $this->sheet->float('takeover.stock_quality'),
        );
    }

    public function defaultDecisions(): Decisions
    {
        return new Decisions(
            priceLevel: $this->sheet->float('default_decisions.price_level'),
            openDayParts: array_map(fn (string $p) => DayPart::from($p), $this->sheet->array('default_decisions.open_day_parts')),
            openDaysPerWeek: $this->sheet->int('default_decisions.open_days_per_week'),
            staffCount: $this->sheet->int('default_decisions.staff_count'),
            marketingSpendCents: $this->sheet->int('default_decisions.marketing_spend_cents'),
            qualityTier: QualityTier::from($this->sheet->get('default_decisions.quality_tier')),
        );
    }

    /**
     * What the landlord holds, paid on top of the traspaso and back when
     * the business is sold or closed: the deposit and the extra guarantee.
     */
    public function depositCents(BusinessProfile $profile): int
    {
        return (new BuyingCosts($this->sheet))->heldCents($profile->rentMonthCents);
    }

    /** The buying fees, spent for good (SPEC §13). */
    public function feesCents(int $traspasoCents): int
    {
        return (new BuyingCosts($this->sheet))->feesCents($traspasoCents);
    }

    /** Everything paid on the day: the traspaso, the deposits and the fees. */
    public function cashNeededCents(BusinessProfile $profile, int $traspasoCents): int
    {
        return (new BuyingCosts($this->sheet))->cashNeededCents($traspasoCents, $profile->rentMonthCents);
    }
}
