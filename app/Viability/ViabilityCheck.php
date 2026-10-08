<?php

namespace App\Viability;

use App\Balance\BalanceMarket;
use App\Balance\BalanceRunner;
use App\Balance\GameOutcome;
use App\Generation\CompetitorPicker;
use App\Generation\GeneratedBusiness;
use App\Generation\Geo\Geo;
use App\Generation\Location;
use App\Generation\Takeover;
use App\Generation\UnlistedRivals;
use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\Licence;
use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/**
 * The viability check (SPEC §11): plays many futures of one café the user
 * describes, on the calibrated engine, and sums up how they went. No
 * database: the job hands it the spot's footfall point, its neighbourhood
 * and the real cafés and bars nearby.
 */
final readonly class ViabilityCheck
{
    private ParameterSheet $sheet;

    /**
     * @param  array<string, mixed>  $parameters  the market's parameter sheet
     * @param  list<array{key: string, lat: float, lng: float}>  $rivalPlaces  real cafés and bars
     */
    public function __construct(private array $parameters, private array $rivalPlaces)
    {
        $this->sheet = new ParameterSheet($parameters);
    }

    /**
     * @param  float  $footfallPercentile  the spot's footfall rank in the city, 0–1
     * @return array<string, mixed>
     */
    public function run(ViabilityInput $input, Location $point, NeighbourhoodProfile $neighbourhood, float $footfallPercentile, int $runs, int $years, int $startMonth, int $seed): array
    {
        $business = $this->business($input, $point, $neighbourhood);
        $competitors = $this->competitors($input, $seed);
        $runner = new BalanceRunner(new BalanceMarket($this->parameters, [$neighbourhood]));
        $outcomes = [];

        for ($n = 0; $n < $runs; $n++) {
            $outcomes[] = $runner->run(new FixedPlan($input), $business, $competitors, $input->capitalCents, $seed + $n, $years, $startMonth, new SeededRng($seed + $n));
        }

        $open = array_map(fn (int $y) => $this->share($outcomes, fn (GameOutcome $o) => ! $o->closedBy($y)), array_combine(range(1, $years), range(1, $years)));
        $deposit = (new Takeover($this->sheet))->depositCents($business->profile);
        $salesYear1 = $this->median(array_map(fn (GameOutcome $o) => array_sum(array_slice($o->revenueByMonth, 0, 12)) * 12 / max(1, min(12, count($o->revenueByMonth))), $outcomes));

        return [
            'runs' => $runs,
            'years' => $years,
            'spot' => [
                'neighbourhood' => $neighbourhood->name,
                'street_type' => $point->streetType,
                'footfall' => $point->footfall,
                'footfall_by_day_part' => $point->footfallByDayPart,
                'footfall_percentile' => round($footfallPercentile, 3),
                'rivals_within_150m' => $this->placesWithin($input, 150),
                'rivals_within_500m' => $this->placesWithin($input, 500),
            ],
            'open' => $open,
            'profit_by_year' => $this->profitByYear($outcomes, $years),
            'sales_year_1_cents' => (int) round($salesYear1),
            'payback' => $this->payback($outcomes, $input->traspasoCents),
            'resale' => $this->resale($outcomes, $years, $input->capitalCents),
            'cash_after_purchase_cents' => $input->capitalCents - $input->traspasoCents - $deposit,
            'owner_pay_month_cents' => $this->sheet->int('owner.pay_month_cents'),
            'risks' => $this->risks($input, $point, $open, $salesYear1, $deposit),
        ];
    }

    private function business(ViabilityInput $input, Location $point, NeighbourhoodProfile $neighbourhood): GeneratedBusiness
    {
        return new GeneratedBusiness(
            name: 'Your café',
            streetType: $point->streetType,
            profile: new BusinessProfile(
                category: $input->licence === Licence::Cafe ? BusinessCategory::Cafe : BusinessCategory::CafeBar,
                licence: $input->licence,
                kitchen: $input->kitchen,
                neighbourhood: $neighbourhood,
                floorAreaM2: $input->floorAreaM2,
                indoorSeats: $input->indoorSeats,
                terraceSeats: $input->terraceSeats,
                rentMonthCents: $input->rentMonthCents,
                footfall: $point->footfall,
                condition: $input->condition,
                footfallByDayPart: $point->footfallByDayPart,
            ),
            traspasoCents: $input->traspasoCents,
            // Older premises have older equipment: about a year less per condition point.
            equipmentAgeYears: max(0, 11 - $input->condition),
            baseReputation: $this->sheet->float('base_reputation.mean'),
            location: $point,
        );
    }

    /**
     * The nearest real cafés and bars, as the game picks rivals. A place
     * right at the pin is taken to be the café itself.
     *
     * @return list<CompetitorState>
     */
    private function competitors(ViabilityInput $input, int $seed): array
    {
        $places = [];
        $own = $this->sheet->float('viability.own_place_metres');

        foreach ($this->rivalPlaces as $place) {
            $distance = Geo::distanceMetres($input->lat, $input->lng, $place['lat'], $place['lng']);

            if ($distance > $own && $distance <= $this->sheet->float('competitors.distance_metres.max')) {
                $places[] = ['key' => $place['key'], 'distance_metres' => $distance];
            }
        }

        usort($places, fn (array $a, array $b) => [$a['distance_metres'], $a['key']] <=> [$b['distance_metres'], $b['key']]);
        $rng = (new SeededRng($seed))->fork('competitors');

        return (new CompetitorPicker($this->sheet))->pick([], $rng, (new UnlistedRivals($this->sheet))->candidates($places, $rng->fork('unlisted')));
    }

    private function placesWithin(ViabilityInput $input, float $metres): int
    {
        $own = $this->sheet->float('viability.own_place_metres');

        return count(array_filter($this->rivalPlaces, function (array $p) use ($input, $metres, $own) {
            $d = Geo::distanceMetres($input->lat, $input->lng, $p['lat'], $p['lng']);

            return $d > $own && $d <= $metres;
        }));
    }

    /**
     * Profit before the owner's pay, each year, for the cafés still open
     * all that year.
     *
     * @param  list<GameOutcome>  $outcomes
     * @return list<array{year: int, open: int, median: int|null, p10: int|null, p90: int|null}>
     */
    private function profitByYear(array $outcomes, int $years): array
    {
        $rows = [];

        for ($year = 1; $year <= $years; $year++) {
            $profits = [];

            foreach ($outcomes as $o) {
                $months = array_slice($o->profitsByMonth, ($year - 1) * 12, 12);

                if (count($months) === 12) {
                    $profits[] = array_sum($months);
                }
            }

            sort($profits);
            $rows[] = [
                'year' => $year,
                'open' => count($profits),
                'median' => $profits ? (int) round($this->median($profits)) : null,
                'p10' => $profits ? $profits[(int) floor(0.1 * (count($profits) - 1))] : null,
                'p90' => $profits ? $profits[(int) ceil(0.9 * (count($profits) - 1))] : null,
            ];
        }

        return $rows;
    }

    /**
     * What the café would sell for at the end (SPEC §12), for the futures
     * where it's still open then, before and after the costs of a private
     * sale and the tax; and the owner's total return over all futures:
     * net worth at the end (or at closing) against the money they put in.
     *
     * @param  list<GameOutcome>  $outcomes
     * @return array<string, mixed>
     */
    private function resale(array $outcomes, int $years, int $capitalCents): array
    {
        $open = array_values(array_filter($outcomes, fn (GameOutcome $o) => ! $o->closedBy($years) && isset($o->valueByYear[$years])));
        $spread = function (array $values): ?array {
            if ($values === []) {
                return null;
            }

            sort($values);

            return [
                'median' => (int) round($this->median($values)),
                'p10' => $values[(int) floor(0.1 * (count($values) - 1))],
                'p90' => $values[(int) ceil(0.9 * (count($values) - 1))],
            ];
        };
        $returns = array_map(fn (GameOutcome $o) => $o->netWorthCents - $capitalCents, $outcomes);

        return [
            'year' => $years,
            'value_cents' => $spread(array_map(fn (GameOutcome $o) => $o->valueByYear[$years], $open)),
            'net_cents' => $spread(array_map(fn (GameOutcome $o) => $o->saleNetByYear[$years], $open)),
            'total_return_cents' => $spread($returns),
            'ahead' => $this->share($outcomes, fn (GameOutcome $o) => $o->netWorthCents > $capitalCents),
        ];
    }

    /**
     * Months until what's left after the owner's pay adds up to the
     * traspaso.
     *
     * @param  list<GameOutcome>  $outcomes
     * @return array{median_months: int|null, share: float}
     */
    private function payback(array $outcomes, int $traspasoCents): array
    {
        $pay = $this->sheet->int('owner.pay_month_cents');
        $months = [];

        foreach ($outcomes as $o) {
            $sum = 0;

            foreach ($o->profitsByMonth as $i => $profit) {
                $sum += $profit - $pay;

                if ($sum >= $traspasoCents) {
                    $months[] = $i + 1;
                    break;
                }
            }
        }

        $share = count($outcomes) ? count($months) / count($outcomes) : 0.0;

        // The median future, counting those that never pay back as "later".
        sort($months);
        $mid = intdiv(count($outcomes), 2);

        return ['median_months' => $mid < count($months) ? $months[$mid] : null, 'share' => round($share, 3)];
    }

    /**
     * What stands out, as data for the report to word.
     *
     * @param  array<int, float>  $open
     * @return list<array{type: string, value: float|int}>
     */
    private function risks(ViabilityInput $input, Location $point, array $open, float $salesYear1, int $deposit): array
    {
        $risks = [];

        if ($salesYear1 > 0 && $input->rentMonthCents * 12 / $salesYear1 > 0.15) {
            $risks[] = ['type' => 'rent_share', 'value' => round($input->rentMonthCents * 12 / $salesYear1, 3)];
        }

        if ($point->footfall < 3.0) {
            $risks[] = ['type' => 'quiet_spot', 'value' => $point->footfall];
        }

        if (($crowd = $this->placesWithin($input, 150)) >= 4) {
            $risks[] = ['type' => 'crowded', 'value' => $crowd];
        }

        if (($open[1] ?? 1.0) < 0.75) {
            $risks[] = ['type' => 'first_year', 'value' => $open[1]];
        }

        $cushion = $input->capitalCents - $input->traspasoCents - $deposit;
        $fixed = $input->rentMonthCents + $this->sheet->int('owner.pay_month_cents');

        if ($cushion < 3 * $fixed) {
            $risks[] = ['type' => 'thin_cash', 'value' => round($cushion / max(1, $fixed), 1)];
        }

        return $risks;
    }

    /**
     * @param  list<GameOutcome>  $outcomes
     */
    private function share(array $outcomes, callable $test): float
    {
        return count($outcomes) ? round(count(array_filter($outcomes, $test)) / count($outcomes), 3) : 0.0;
    }

    /** @param list<int|float> $values */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $n = count($values);

        return $n % 2 ? (float) $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }
}
