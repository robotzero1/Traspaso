<?php

namespace App\Simulation\Sale;

use App\Simulation\Data\ParameterSheet;

/**
 * What buying a café costs on top of the traspaso (SPEC §13): the
 * landlord's deposit and extra guarantee, held and paid back when the
 * business is sold or closed, and the fees, which are spent: the buyer's
 * lawyer or gestoría and the licence's change of holder.
 */
final readonly class BuyingCosts
{
    public function __construct(private ParameterSheet $sheet) {}

    /**
     * @return array{deposit_cents: int, guarantee_cents: int, held_cents: int, legal_cents: int, licence_cents: int, fees_cents: int, cash_needed_cents: int}
     */
    public function breakdown(int $traspasoCents, int $rentMonthCents): array
    {
        $deposit = $rentMonthCents * $this->sheet->int('purchase.deposit_months_of_rent');
        $guarantee = $rentMonthCents * $this->sheet->int('purchase.guarantee_months_of_rent');
        $legal = $this->sheet->int('purchase.legal_fees.base_cents')
            + (int) round($traspasoCents * $this->sheet->float('purchase.legal_fees.share_of_traspaso'));
        $licence = $this->sheet->int('purchase.licence_change.fee_cents')
            + $this->sheet->int('purchase.licence_change.technical_report_cents')
            + $this->sheet->int('purchase.licence_change.paperwork_cents');

        return [
            'deposit_cents' => $deposit,
            'guarantee_cents' => $guarantee,
            'held_cents' => $deposit + $guarantee,
            'legal_cents' => $legal,
            'licence_cents' => $licence,
            'fees_cents' => $legal + $licence,
            'cash_needed_cents' => $traspasoCents + $deposit + $guarantee + $legal + $licence,
        ];
    }

    /** What the landlord holds: the deposit and the extra guarantee. */
    public function heldCents(int $rentMonthCents): int
    {
        return $this->breakdown(0, $rentMonthCents)['held_cents'];
    }

    /** The fees, spent for good. */
    public function feesCents(int $traspasoCents): int
    {
        return $this->breakdown($traspasoCents, 0)['fees_cents'];
    }

    /** Everything the buyer pays on the day: the traspaso, what the landlord holds and the fees. */
    public function cashNeededCents(int $traspasoCents, int $rentMonthCents): int
    {
        return $this->breakdown($traspasoCents, $rentMonthCents)['cash_needed_cents'];
    }
}
