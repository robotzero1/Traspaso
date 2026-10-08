<?php

namespace App\Simulation\Sale;

use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/**
 * Buyers for a listed café (SPEC §12): one day at a time, a buyer may turn
 * up. How often depends on the asking price against the café's value, on
 * the season and on whether an agency is selling it. Each buyer's limit is
 * the valuation give or take; they open below it and never above asking.
 */
final readonly class BuyerMarket
{
    public function __construct(private ParameterSheet $sheet) {}

    public function day(int $askingCents, int $valueCents, bool $agency, int $calendarMonth, int $daysInMonth, SeededRng $rng): ?BuyerOffer
    {
        if ($valueCents <= 0 || ! $rng->chance(min(1.0, $this->buyersPerMonth($askingCents, $valueCents, $agency, $calendarMonth) / $daysInMonth))) {
            return null;
        }

        $limit = (int) round($valueCents * max(0.3, $rng->normal(1.0, $this->sheet->float('sale.buyer_value_sd'))));

        if ($limit < $askingCents * $this->sheet->float('sale.min_offer_share')) {
            return null;
        }

        $amount = min($askingCents, (int) round($limit * (1 - $this->sheet->float('sale.opening_discount')) / 50_000) * 50_000);

        return new BuyerOffer(max(50_000, $amount), $limit);
    }

    /** Expected serious buyers a month. */
    public function buyersPerMonth(int $askingCents, int $valueCents, bool $agency, int $calendarMonth): float
    {
        $base = $this->sheet->float('sale.buyers_per_month.'.($agency ? 'agency' : 'private'));
        $interest = min(
            $this->sheet->float('sale.max_interest'),
            exp(-$this->sheet->float('sale.price_sensitivity') * ($askingCents / $valueCents - 1)),
        );
        $season = $this->sheet->array('sale.season')[$calendarMonth] ?? 1.0;

        return $base * $interest * $season;
    }

    /** A counter-offer is taken if it's within the buyer's limit; otherwise the buyer walks away. */
    public function acceptsCounter(BuyerOffer $offer, int $counterCents): bool
    {
        return $counterCents <= $offer->limitCents;
    }
}
