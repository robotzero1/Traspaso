<?php

namespace App\Simulation\Sale;

use App\Simulation\Data\ParameterSheet;

/**
 * What the seller keeps (SPEC §12): the price less an agency's commission
 * (if one sold it), the gestoría, and IRPF on the gain at the savings
 * scale. Selling a whole going business isn't subject to IVA (LIVA 7.1).
 */
final readonly class SaleCosts
{
    public function __construct(private ParameterSheet $sheet) {}

    /** @return array{commission_cents: int, gestoria_cents: int, gain_cents: int, tax_cents: int, net_cents: int} */
    public function breakdown(int $priceCents, int $traspasoPaidCents, bool $agency): array
    {
        $commission = $agency
            ? max($this->sheet->int('sale.agency_commission_min_cents'), (int) round($priceCents * $this->sheet->float('sale.agency_commission_share')))
            : 0;
        $gestoria = $this->sheet->int('sale.gestoria_cents');
        $gain = $priceCents - $traspasoPaidCents - $commission - $gestoria;
        $tax = $this->tax($gain);

        return [
            'commission_cents' => $commission,
            'gestoria_cents' => $gestoria,
            'gain_cents' => $gain,
            'tax_cents' => $tax,
            'net_cents' => $priceCents - $commission - $gestoria - $tax,
        ];
    }

    public function netCents(int $priceCents, int $traspasoPaidCents, bool $agency): int
    {
        return $this->breakdown($priceCents, $traspasoPaidCents, $agency)['net_cents'];
    }

    /** IRPF on a gain at the savings scale; a loss pays nothing. */
    public function tax(int $gainCents): int
    {
        $tax = 0.0;
        $from = 0;

        foreach ($this->sheet->array('sale.tax_brackets') as $bracket) {
            $to = $bracket['up_to_cents'] ?? PHP_INT_MAX;

            if ($gainCents <= $from) {
                break;
            }

            $tax += (min($gainCents, $to) - $from) * $bracket['rate'];
            $from = $to;
        }

        return (int) round($tax);
    }
}
