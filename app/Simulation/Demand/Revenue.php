<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\ParameterSheet;

/**
 * Step 5: revenue from covers. Tickets in the parameter sheet include IVA;
 * revenue is reported net of IVA, since that part goes to the tax office.
 */
final readonly class Revenue
{
    public function __construct(private ParameterSheet $sheet) {}

    /** Average spend per customer in cents, including IVA, at this price level. */
    public function ticketCents(BusinessProfile $profile, DayPart $part, Decisions $decisions): float
    {
        $min = $this->sheet->float("average_ticket_cents.{$part->value}.min");
        $max = $this->sheet->float("average_ticket_cents.{$part->value}.max");

        $position = $this->sheet->float("ticket_position.tier.{$decisions->qualityTier->value}");

        if (in_array($part->value, $this->sheet->array('ticket_position.kitchen_day_parts'), true)) {
            $position += $this->sheet->float("ticket_position.kitchen_offset.{$profile->kitchen->value}");
        }

        $position = min(1.0, max(0.0, $position));

        return ($min + ($max - $min) * $position) * $decisions->priceLevel;
    }

    public function netRevenueCents(int $covers, float $ticketCents): int
    {
        return (int) round($covers * $ticketCents / (1 + $this->sheet->float('iva.rate')));
    }
}
