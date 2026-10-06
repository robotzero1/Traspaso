<?php

namespace App\Generation;

use App\Generation\Distributions\Normal;
use App\Generation\Distributions\PercentileDistribution;
use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\Kitchen;
use App\Simulation\Data\Licence;
use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;
use InvalidArgumentException;

/**
 * Draws a market of fictional businesses for sale from the distributions
 * in a parameter sheet (config/market/*.php).
 *
 * Rent and traspaso are drawn with a Gaussian copula: each business gets
 * standard normal scores for location, floor area and condition, the
 * scores are blended using the configured correlations, and the blend is
 * turned back into a percentile. So the market as a whole matches the
 * percentile table exactly, while bigger, busier and better-kept places
 * get the higher draws.
 *
 * Each attribute draws from its own forked RNG stream, so adding an
 * attribute later doesn't change the ones already generated for a seed.
 */
final class BusinessGenerator
{
    private const INDEX_FIELDS = [
        'student' => 'studentIndex',
        'tourist' => 'touristIndex',
        'office' => 'officeIndex',
        'transport' => 'transportIndex',
    ];

    private readonly ParameterSheet $sheet;

    private readonly PercentileDistribution $floorArea;

    private readonly PercentileDistribution $condition;

    private readonly PercentileDistribution $equipmentAge;

    private readonly PercentileDistribution $rent;

    private readonly PercentileDistribution $traspaso;

    /**
     * @param  ParameterSheet|array<string, mixed>  $parameters
     */
    public function __construct(ParameterSheet|array $parameters)
    {
        $this->sheet = $parameters instanceof ParameterSheet ? $parameters : new ParameterSheet($parameters);

        $this->floorArea = new PercentileDistribution($this->sheet->array('floor_area_m2.percentiles'));
        $this->condition = new PercentileDistribution($this->sheet->array('condition.percentiles'));
        $this->equipmentAge = new PercentileDistribution($this->sheet->array('equipment_age_years.percentiles'));
        $this->rent = new PercentileDistribution($this->sheet->array('rent.percentiles_cents'));
        $this->traspaso = new PercentileDistribution($this->sheet->array('traspaso.percentiles_cents'));

        foreach (['rent', 'traspaso', 'equipment_age_years', 'base_reputation'] as $section) {
            $this->noiseWeight($section);
        }
    }

    /**
     * @param  list<NeighbourhoodProfile>  $neighbourhoods
     * @param  int|null  $count  defaults to a draw from business_count
     * @param  LocationSource|null  $locations  real locations with footfall; without, footfall comes from the indices
     * @return list<GeneratedBusiness>
     */
    public function generate(SeededRng $rng, array $neighbourhoods, ?int $count = null, ?LocationSource $locations = null): array
    {
        if ($neighbourhoods === [] || ! array_is_list($neighbourhoods)) {
            throw new InvalidArgumentException('generate() needs a non-empty list of neighbourhoods.');
        }

        $count ??= $rng->fork('count')->int(
            $this->sheet->int('business_count.min'),
            $this->sheet->int('business_count.max'),
        );

        if ($count < 0) {
            throw new InvalidArgumentException("generate(): count ({$count}) must not be negative.");
        }

        $streams = [];

        foreach (['neighbourhood', 'category', 'licence', 'kitchen', 'street', 'footfall', 'area', 'condition',
            'seats', 'terrace', 'age', 'reputation', 'rent', 'traspaso', 'name', 'location'] as $label) {
            $streams[$label] = $rng->fork($label);
        }

        $neighbourhoodWeights = $this->neighbourhoodWeights($neighbourhoods);

        // First pass: everything that doesn't depend on the rest of the market.
        $drafts = [];

        for ($i = 0; $i < $count; $i++) {
            $neighbourhood = $neighbourhoods[$streams['neighbourhood']->weightedKey($neighbourhoodWeights)];
            $category = BusinessCategory::from($streams['category']->weightedKey($this->sheet->array('categories.weights')));
            $location = $locations?->draw($neighbourhood, $streams['location']);
            $streetType = $location?->streetType ?? (string) $streams['street']->weightedKey($this->streetTypeWeights());

            $zArea = $streams['area']->normal();
            $zCondition = $streams['condition']->normal();
            $floorArea = (int) round($this->floorArea->quantile(Normal::cdf($zArea)));

            $drafts[] = [
                'neighbourhood' => $neighbourhood,
                'category' => $category,
                'licence' => Licence::from($streams['licence']->weightedKey(
                    $this->sheet->array("licences.weights.{$category->value}"),
                )),
                'kitchen' => Kitchen::from($streams['kitchen']->weightedKey(
                    $this->sheet->array("kitchens.weights.{$category->value}"),
                )),
                'streetType' => $streetType,
                'location' => $location,
                'footfall' => $location?->footfall ?? $this->footfall($neighbourhood, $streetType, $streams['footfall']),
                'floorArea' => $floorArea,
                'zArea' => $zArea,
                'zCondition' => $zCondition,
                'condition' => (int) min(10, max(1, round($this->condition->quantile(Normal::cdf($zCondition))))),
                'indoorSeats' => $this->indoorSeats($floorArea, $streams['seats']),
                'terraceSeats' => $this->terraceSeats($streams['terrace']),
            ];
        }

        // Second pass: location only means something relative to the rest
        // of the market, so score footfall by rank.
        $zLocation = $this->rankScores(array_column($drafts, 'footfall'));
        // Features buyers pay extra for: a kitchen with a smoke outlet (hard
        // to add in a residential building) and a terrace permit.
        $zKitchen = $this->standardised(array_map(fn (array $d) => $d['kitchen'] === Kitchen::Full ? 1.0 : 0.0, $drafts));
        $zTerrace = $this->standardised(array_map(fn (array $d) => $d['terraceSeats'] > 0 ? 1.0 : 0.0, $drafts));
        $names = new NameDrawer($this->sheet, $streams['name']);
        $businesses = [];

        foreach ($drafts as $i => $draft) {
            $scores = [
                'location' => $zLocation[$i], 'floor_area' => $draft['zArea'], 'condition' => $draft['zCondition'],
                'kitchen' => $zKitchen[$i], 'terrace' => $zTerrace[$i],
            ];

            $profile = new BusinessProfile(
                category: $draft['category'],
                licence: $draft['licence'],
                kitchen: $draft['kitchen'],
                neighbourhood: $draft['neighbourhood'],
                floorAreaM2: $draft['floorArea'],
                indoorSeats: $draft['indoorSeats'],
                terraceSeats: $draft['terraceSeats'],
                rentMonthCents: $this->money($this->rent, 'rent', $scores, $streams['rent']),
                footfall: $draft['footfall'],
                condition: $draft['condition'],
                footfallByDayPart: $draft['location']?->footfallByDayPart ?? [],
            );

            $businesses[] = new GeneratedBusiness(
                name: $names->draw($draft['category']),
                streetType: $draft['streetType'],
                profile: $profile,
                traspasoCents: $this->money($this->traspaso, 'traspaso', $scores, $streams['traspaso']),
                equipmentAgeYears: (int) round($this->equipmentAge->quantile(
                    Normal::cdf($this->blend('equipment_age_years', $scores, $streams['age'])),
                )),
                baseReputation: $this->baseReputation($scores, $streams['reputation']),
                location: $draft['location'],
            );
        }

        return $businesses;
    }

    /**
     * @param  list<NeighbourhoodProfile>  $neighbourhoods
     * @return list<float>
     */
    private function neighbourhoodWeights(array $neighbourhoods): array
    {
        $boost = $this->sheet->float('neighbourhood_weighting.commercial_boost');

        return array_map(
            fn (NeighbourhoodProfile $n) => $n->population * (1 + $boost * ($n->touristIndex + $n->officeIndex) / 20),
            $neighbourhoods,
        );
    }

    /**
     * @return array<string, float>
     */
    private function streetTypeWeights(): array
    {
        return array_map(
            fn (array $type) => (float) $type['weight'],
            $this->sheet->array('footfall.street_types'),
        );
    }

    private function footfall(NeighbourhoodProfile $neighbourhood, string $streetType, SeededRng $rng): float
    {
        $base = 0.0;

        foreach ($this->sheet->array('footfall.index_weights') as $index => $weight) {
            $field = self::INDEX_FIELDS[$index] ?? throw new InvalidArgumentException("Unknown footfall index [{$index}].");
            $base += $weight * $neighbourhood->{$field};
        }

        $footfall = $base
            + $this->sheet->float("footfall.street_types.{$streetType}.footfall_bonus")
            + $rng->normal(0.0, $this->sheet->float('footfall.noise_sd'));

        return round(min(10.0, max(0.0, $footfall)), 1);
    }

    private function indoorSeats(int $floorArea, SeededRng $rng): int
    {
        $expected = $floorArea * $this->sheet->float('seating.indoor_seats_per_m2');
        $seats = (int) round($expected * (1 + $rng->normal(0.0, $this->sheet->float('seating.indoor_seats_sd'))));

        return max($this->sheet->int('seating.min_indoor_seats'), $seats);
    }

    private function terraceSeats(SeededRng $rng): int
    {
        if (! $rng->chance($this->sheet->float('seating.terrace_probability'))) {
            return 0;
        }

        $tables = $rng->int($this->sheet->int('seating.terrace_tables.min'), $this->sheet->int('seating.terrace_tables.max'));

        return $tables * $this->sheet->int('seating.seats_per_terrace_table');
    }

    /**
     * @param  array<string, float>  $scores
     */
    private function money(PercentileDistribution $distribution, string $section, array $scores, SeededRng $rng): int
    {
        $value = $distribution->quantile(Normal::cdf($this->blend($section, $scores, $rng)));
        $step = $this->sheet->int("{$section}.rounding_cents");
        $rounded = $step > 0 ? (int) (round($value / $step) * $step) : (int) round($value);

        return (int) min($distribution->max(), max($distribution->min(), $rounded));
    }

    /**
     * @param  array<string, float>  $scores
     */
    private function baseReputation(array $scores, SeededRng $rng): float
    {
        $reputation = $this->sheet->float('base_reputation.mean')
            + $this->sheet->float('base_reputation.sd') * $this->blend('base_reputation', $scores, $rng);

        return round(min(
            $this->sheet->float('base_reputation.max'),
            max($this->sheet->float('base_reputation.min'), $reputation),
        ), 1);
    }

    /**
     * A standard normal score: Σ correlation × score + the remaining
     * variance as independent noise.
     *
     * @param  array<string, float>  $scores
     */
    private function blend(string $section, array $scores, SeededRng $rng): float
    {
        $z = $this->noiseWeight($section) * $rng->normal();

        foreach ($this->sheet->array("{$section}.correlation") as $factor => $correlation) {
            $z += $correlation * ($scores[$factor] ?? throw new InvalidArgumentException(
                "Unknown correlation factor [{$factor}] in [{$section}]."
            ));
        }

        return $z;
    }

    private function noiseWeight(string $section): float
    {
        $explained = 0.0;

        foreach ($this->sheet->array("{$section}.correlation") as $correlation) {
            $explained += $correlation ** 2;
        }

        if ($explained > 1.0) {
            throw new InvalidArgumentException("Correlations in [{$section}] must have squares adding up to at most 1.");
        }

        return sqrt(1.0 - $explained);
    }

    /**
     * A yes/no feature as a score with mean 0 and variance 1 across the
     * market, so it can sit beside the normal scores in a blend.
     *
     * @param  list<float>  $values
     * @return list<float>
     */
    private function standardised(array $values): array
    {
        $n = count($values);
        $mean = $n ? array_sum($values) / $n : 0.0;
        $sd = $n ? sqrt(array_sum(array_map(fn (float $v) => ($v - $mean) ** 2, $values)) / $n) : 0.0;

        return array_map(fn (float $v) => $sd > 0 ? ($v - $mean) / $sd : 0.0, $values);
    }

    /**
     * Turn values into standard normal scores by rank (ties keep input order).
     *
     * @param  list<float>  $values
     * @return list<float>
     */
    private function rankScores(array $values): array
    {
        $order = array_keys($values);
        usort($order, fn (int $a, int $b) => [$values[$a], $a] <=> [$values[$b], $b]);

        $scores = array_fill(0, count($values), 0.0);
        $n = count($values);

        foreach ($order as $rank => $index) {
            $scores[$index] = Normal::inverseCdf(($rank + 0.5) / $n);
        }

        return $scores;
    }
}
