<?php

namespace App\Simulation\Costs;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\CostBreakdown;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\ModifierSet;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Demand\DayPartSchedule;
use App\Simulation\Demand\SeasonalFactors;
use App\Simulation\Demand\Staffing;
use InvalidArgumentException;

/**
 * Step 6: the month's costs. "Other" holds the cuota de autónomo,
 * insurance, maintenance, the terrace fee and costs from events.
 */
final readonly class MonthlyCosts
{
    public function __construct(private ParameterSheet $sheet) {}

    /**
     * @param  int  $eventCostCents  one-off costs from events this month
     * @param  int|null  $gameMonth  months since purchase (1 = the first), for the new-owner flat rate; null: no flat rate
     */
    public function calculate(
        BusinessState $state,
        Decisions $decisions,
        SeasonalFactors $season,
        int $revenueCents,
        ModifierSet $modifiers = new ModifierSet,
        int $eventCostCents = 0,
        ?int $gameMonth = null,
    ): CostBreakdown {
        $cogs = $this->round($revenueCents * $this->cogsShare($decisions, $modifiers));

        return $this->breakdown($state, $decisions, $season, $revenueCents, $cogs, $modifiers->rent(), $modifiers->monthlyCostCents() + $eventCostCents, 1.0, $gameMonth);
    }

    /**
     * The month's costs in the daily engine, settled at month end. COGS
     * and the costs of events and monthly-cost modifiers were counted day
     * by day; the rest is worked out for the month as above.
     *
     * @param  int  $otherCostCents  events and monthly-cost modifiers over the month's days
     * @param  float  $share  the share of the month traded: fixed costs are pro rata
     */
    public function settleMonth(BusinessState $state, Decisions $decisions, SeasonalFactors $season, int $revenueCents, int $cogsCents, int $otherCostCents, float $share = 1.0, ?int $gameMonth = null): CostBreakdown
    {
        return $this->breakdown($state, $decisions, $season, $revenueCents, $cogsCents, $state->modifierSet()->rent(), $otherCostCents, $share, $gameMonth);
    }

    /** COGS as a share of revenue: the quality tier's, plus any event modifiers. */
    public function cogsShare(Decisions $decisions, ModifierSet $modifiers = new ModifierSet): float
    {
        return $this->sheet->float("cogs.share_of_revenue.{$decisions->qualityTier->value}") + $modifiers->cogsShare();
    }

    private function breakdown(BusinessState $state, Decisions $decisions, SeasonalFactors $season, int $revenueCents, int $cogs, float $rentFactor, int $extraOtherCents, float $share = 1.0, ?int $gameMonth = null): CostBreakdown
    {
        $staff = $this->round(($this->staff($decisions->staffCount) + $this->coverCents($decisions)) * $share);
        $rent = $this->round($state->profile->rentMonthCents * $rentFactor * $share);
        $utilities = $share < 1.0
            ? $this->round($this->sheet->float('utilities.base_month_cents') * $share
                + $this->sheet->float('utilities.per_open_hour_cents') * (new DayPartSchedule($this->sheet))->hoursPerDay($decisions) * $season->openDays)
            : $this->utilities($decisions, $season);
        $marketing = $this->round($decisions->marketingSpendCents * $share);
        $fixedOther = $this->round(($this->insurance() + $this->maintenance($state) + $this->terraceFee($state)) * $share) + $extraOtherCents;

        $beforeCuota = $revenueCents - ($cogs + $staff + $rent + $utilities + $marketing + $fixedOther);
        // The cuota's band is set by what a full month would earn.
        // A new self-employed owner pays the flat rate for their first year.
        $cuota = $gameMonth !== null && $gameMonth <= $this->sheet->int('cuota_autonomo.flat_rate_months')
            ? $this->round($this->sheet->int('cuota_autonomo.flat_rate_cents') * $share)
            : $this->round($this->cuotaAutonomo($this->round($beforeCuota / $share)) * $share);
        $taxes = $this->round(max(0, $beforeCuota - $cuota) * $this->sheet->float('income_tax.rate'));

        return new CostBreakdown(
            cogsCents: $cogs,
            staffCents: $staff,
            rentCents: $rent,
            utilitiesCents: $utilities,
            marketingCents: $marketing,
            otherCents: $fixedOther + $cuota,
            taxesCents: $taxes,
        );
    }

    /** Monthly employer cost of the staff: 14 payments spread over 12 months, plus social security. */
    public function staff(int $staffCount): int
    {
        return $this->round($this->staffUnrounded($staffCount));
    }

    private function staffUnrounded(int $staffCount): float
    {
        $grossPerMonth = $this->sheet->float('staff.gross_per_payment_cents')
            * $this->sheet->float('staff.payments_per_year') / 12;

        return $staffCount * $grossPerMonth * (1 + $this->sheet->float('staff.employer_social_security_rate'));
    }

    /**
     * Part-time cover for open hours the owner and staff can't fill (see
     * Staffing), at a full-timer's hourly employer cost.
     */
    public function coverCents(Decisions $decisions): int
    {
        $weeksPerMonth = 52 / 12;
        $hourly = $this->staffUnrounded(1) / ($this->sheet->float('staff.full_time_hours_per_week') * $weeksPerMonth);

        return $this->round(Staffing::for($decisions, $this->sheet)->coverHoursPerWeek * $weeksPerMonth * $hourly);
    }

    public function utilities(Decisions $decisions, SeasonalFactors $season): int
    {
        $openHours = (new DayPartSchedule($this->sheet))->hoursPerDay($decisions) * $season->openDays;

        return $this->round($this->sheet->float('utilities.base_month_cents')
            + $this->sheet->float('utilities.per_open_hour_cents') * $openHours);
    }

    /** The cuota for the band the owner's monthly income falls in. */
    public function cuotaAutonomo(int $incomeCents): int
    {
        foreach ($this->sheet->array('cuota_autonomo.bands') as $band) {
            if ($band['max_income_cents'] === null || $incomeCents <= $band['max_income_cents']) {
                return $band['cuota_cents'];
            }
        }

        throw new InvalidArgumentException('The last cuota_autonomo band must have max_income_cents => null.');
    }

    private function insurance(): int
    {
        return $this->sheet->int('insurance.month_cents');
    }

    private function maintenance(BusinessState $state): int
    {
        return $this->round($this->sheet->float('maintenance.base_month_cents')
            + $this->sheet->float('maintenance.per_equipment_year_cents') * $state->equipmentAgeMonths / 12);
    }

    private function terraceFee(BusinessState $state): int
    {
        $tables = (int) ceil($state->profile->terraceSeats / $this->sheet->float('seating.seats_per_terrace_table'));

        return $this->round($tables * $this->sheet->float('terrace_fee.per_table_per_year_cents') / 12);
    }

    private function round(float $cents): int
    {
        return (int) round($cents);
    }
}
