<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;
use InvalidArgumentException;

/**
 * Step 2: how many people near the business might come in during a day
 * part over the month, before reputation, price and competition. The day
 * part's stop factor counts how readily people stop: more for a quick
 * coffee than for a meal.
 *
 * With the footfall surface (SPEC §8), the business has a footfall for
 * each day part, which already says when people are about. Without it, the
 * overall footfall says how busy the spot is and the neighbourhood indices,
 * weighted by each day part's demand mix, say when that crowd turns up.
 */
final readonly class PotentialCustomers
{
    private const INDEX_FIELDS = [
        'student' => 'studentIndex',
        'tourist' => 'touristIndex',
        'office' => 'officeIndex',
        'transport' => 'transportIndex',
    ];

    public function __construct(private ParameterSheet $sheet) {}

    public function forDayPart(BusinessProfile $profile, DayPart $part, SeasonalFactors $season, float $noise = 1.0): float
    {
        $surface = $profile->footfallByDayPart[$part->value] ?? null;
        $footfall = $surface ?? $profile->footfall;

        $perHour = $this->sheet->float('demand.potential_per_hour_at_footfall_10')
            * ($footfall / 10) ** $this->sheet->float('demand.footfall_exponent');

        return $perHour
            * $this->sheet->float("day_parts.{$part->value}.intensity")
            * $this->sheet->float("day_parts.{$part->value}.stop_factor")
            * ($surface !== null ? 1.0 : $this->demandMix($profile->neighbourhood, $part))
            * $this->appeal($profile, $part)
            * (new DayPartSchedule($this->sheet))->hours($part)
            * $season->openDays
            * $season->demandMultiplier
            * $noise;
    }

    /** Month-to-month randomness in demand, shared by all day parts. */
    public function noise(SeededRng $rng): float
    {
        $noise = $rng->normal(1.0, $this->sheet->float('demand.noise_sd'));

        return min($this->sheet->float('demand.noise_max'), max($this->sheet->float('demand.noise_min'), $noise));
    }

    /**
     * How much this day part's crowd suits the neighbourhood, relative to
     * the neighbourhood as a whole: Σ demand_mix weight × driver, divided by
     * the neighbourhood's average driver. Footfall already carries how busy
     * a place is, so the mix only shifts demand between day parts; it
     * doesn't count location twice. An even neighbourhood scores 1 in every
     * day part.
     */
    public function demandMix(NeighbourhoodProfile $neighbourhood, DayPart $part): float
    {
        $drivers = [];

        foreach ([...array_keys(self::INDEX_FIELDS), 'population'] as $driver) {
            $drivers[$driver] = $this->driver($neighbourhood, $driver);
        }

        $average = array_sum($drivers) / count($drivers);

        if ($average <= 0.0) {
            return 0.0;
        }

        $mix = 0.0;

        foreach ($this->sheet->array("day_parts.{$part->value}.demand_mix") as $driver => $weight) {
            $mix += $weight * ($drivers[$driver] ?? throw new InvalidArgumentException("Unknown demand driver [{$driver}]."));
        }

        return $mix / $average;
    }

    private function driver(NeighbourhoodProfile $neighbourhood, string $driver): float
    {
        if ($driver === 'population') {
            return min(
                $this->sheet->float('demand.population_driver_cap'),
                $neighbourhood->population / $this->sheet->float('demand.population_reference'),
            );
        }

        $field = self::INDEX_FIELDS[$driver];

        return $neighbourhood->{$field} / $this->sheet->float('demand.index_reference');
    }

    private function appeal(BusinessProfile $profile, DayPart $part): float
    {
        $appeal = $this->sheet->float("appeal.category.{$profile->category->value}.{$part->value}");
        $kitchen = $this->sheet->array('appeal.kitchen');

        if (isset($kitchen[$part->value])) {
            $appeal *= $this->sheet->float("appeal.kitchen.{$part->value}.{$profile->kitchen->value}");
        }

        return $appeal;
    }
}
