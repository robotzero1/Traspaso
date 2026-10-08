<?php

namespace App\Simulation\Sale;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Valuation\BusinessValuation;

/**
 * Getting out without a buyer (SPEC §12): closing down costs the lease's
 * notice and the staff's severance, the equipment sells for scrap, and
 * the traspaso is lost. The landlord returns the deposit (handled by the
 * caller). A quick sale to a buyer of last resort is the alternative.
 */
final readonly class Closure
{
    public function __construct(private ParameterSheet $sheet) {}

    /** @return array{notice_cents: int, severance_cents: int, scrap_cents: int, net_cents: int} */
    public function breakdown(BusinessState $state, int $traspasoPaidCents, int $monthsOwned): array
    {
        $notice = (int) round($state->profile->rentMonthCents * $this->sheet->float('closure.notice_months_of_rent'));
        $years = $monthsOwned / 12 + $this->sheet->float('closure.inherited_tenure_years');
        $dailyPay = $this->sheet->float('staff.gross_per_payment_cents') * $this->sheet->float('staff.payments_per_year') / 365;
        $severance = (int) round($state->staffCount * $dailyPay * $this->sheet->float('closure.severance_days_per_year') * $years);
        $scrap = $this->scrapCents($state, $traspasoPaidCents);

        return [
            'notice_cents' => $notice,
            'severance_cents' => $severance,
            'scrap_cents' => $scrap,
            'net_cents' => $scrap - $notice - $severance,
        ];
    }

    public function scrapCents(BusinessState $state, int $traspasoPaidCents): int
    {
        $fixtures = (new BusinessValuation($this->sheet))->fixturesCents($state, $traspasoPaidCents);

        return (int) round($fixtures * $this->sheet->float('closure.scrap_share_of_fixtures'));
    }

    /** What a buyer of last resort pays: a share of the value, never less than the scrap value, in €500 steps. */
    public function quickSalePriceCents(BusinessState $state, int $traspasoPaidCents, int $valueCents): int
    {
        $price = max($valueCents * $this->sheet->float('quick_sale.share_of_value'), $this->scrapCents($state, $traspasoPaidCents));

        return (int) (round($price / 50_000) * 50_000);
    }
}
