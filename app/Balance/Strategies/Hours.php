<?php

namespace App\Balance\Strategies;

use App\Generation\GeneratedBusiness;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\ParameterSheet;

/** Opening-hours helpers shared by the strategies. */
final class Hours
{
    /** @return list<DayPart> the day parts the business's licence allows */
    public static function licensed(GeneratedBusiness $business, ParameterSheet $sheet): array
    {
        $allowed = $sheet->array("licence_day_parts.{$business->profile->licence->value}");

        return array_values(array_filter(DayPart::cases(), fn (DayPart $p) => in_array($p->value, $allowed, true)));
    }

    /** The decisions with any day part the licence forbids taken out. */
    public static function allowed(Decisions $decisions, GeneratedBusiness $business, ParameterSheet $sheet): Decisions
    {
        $licensed = self::licensed($business, $sheet);

        return $decisions->with(openDayParts: array_values(array_filter(
            $decisions->openDayParts,
            fn (DayPart $p) => in_array($p, $licensed, true),
        )));
    }

    /**
     * Open for the $count licensed day parts with the most footfall at the
     * spot, as a player reading the "By time of day" row would.
     */
    public static function busiest(Decisions $decisions, GeneratedBusiness $business, ParameterSheet $sheet, int $count): Decisions
    {
        $byPart = $business->profile->footfallByDayPart;
        $licensed = self::licensed($business, $sheet);
        $intensity = fn (DayPart $p) => $sheet->float("day_parts.{$p->value}.intensity");
        // Footfall here × how busy the streets are then: what the player sees on the map.
        usort($licensed, fn (DayPart $a, DayPart $b) => (($byPart[$b->value] ?? $business->profile->footfall) * $intensity($b))
            <=> (($byPart[$a->value] ?? $business->profile->footfall) * $intensity($a)));
        $chosen = array_slice($licensed, 0, $count);

        // Keep the day's order.
        return $decisions->with(openDayParts: array_values(array_filter(DayPart::cases(), fn (DayPart $p) => in_array($p, $chosen, true))));
    }
}
