<?php

namespace App\Balance;

use App\Generation\BusinessGenerator;
use App\Generation\CompetitorCandidate;
use App\Generation\CompetitorPicker;
use App\Generation\GeneratedBusiness;
use App\Generation\Geo\Geo;
use App\Generation\Takeover;
use App\Generation\UnlistedRivals;
use App\Simulation\Data\CalendarDate;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\MarketContext;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\DayEngine;
use App\Simulation\Engine;
use App\Simulation\Rng\SeededRng;
use App\Simulation\Sale\Closure;
use App\Simulation\Sale\SaleCosts;
use App\Simulation\Valuation\BusinessValuation;

/**
 * Plays whole games without the database, the way the app does: generate
 * the market from the seed, buy, pick rivals, play for the given years,
 * value what's left (net worth = cash + deposit + what selling the café
 * privately would leave after costs and tax; cash
 * below zero ends the game with cash + deposit). Starting capital and the
 * starting month are drawn from the seed, across the range a player can
 * choose.
 *
 * A café closes, as the closure statistics count it, when its cash runs
 * out or at the end of the first year (from purchase) in which it didn't
 * earn enough to pay its owner: a real owner would close or sell up then.
 * A closed café is valued as it stands (sold on as a traspaso).
 *
 * With $daily, months are played day by day (DayEngine) on the real
 * calendar, starting in the drawn month of FIRST_YEAR.
 */
final readonly class BalanceRunner
{
    /** The calendar year daily games start in (weekdays, holidays and the Pilar fall as they do then). */
    public const FIRST_YEAR = 2027;

    private ParameterSheet $sheet;

    public function __construct(private BalanceMarket $market, private bool $daily = false)
    {
        $this->sheet = new ParameterSheet($market->parameters);
    }

    /** With $capital, starts with that instead of drawing it (a career's next café). */
    public function play(Strategy $strategy, int $seed, int $years = 1, ?int $capital = null): GameOutcome
    {
        $rng = new SeededRng($seed);
        $capitalRange = $this->sheet->array('game.starting_capital_cents');
        // Whole thousands of euros, as a player would choose.
        $capital ??= $rng->fork('capital')->int(intdiv($capitalRange['min'], 100_000), intdiv($capitalRange['max'], 100_000)) * 100_000;
        $startMonth = $rng->fork('calendar')->int(1, 12);

        $market = (new BusinessGenerator($this->market->parameters))->generate(
            $rng->fork('market'),
            $this->market->neighbourhoods,
            locations: $this->market->commercialPoints(),
        );

        $takeover = new Takeover($this->sheet);
        $budget = $capital * (1 - $strategy->reserveShare());
        $affordable = array_values(array_filter(
            $market,
            fn (GeneratedBusiness $b) => $b->traspasoCents + $takeover->depositCents($b->profile) <= $budget,
        ));

        if ($affordable === []) {
            return new GameOutcome($strategy->key(), $seed, $capital, bought: false, netWorthCents: $capital, years: $years);
        }

        $business = $strategy->choose($affordable, $this->sheet, $rng->fork("choose-{$strategy->key()}"));
        $index = array_search($business, $market, true);

        return $this->run($strategy, $business, $this->competitors($market, $index, $seed), $capital, $seed, $years, $startMonth, $rng);
    }

    /**
     * A flipper's career (SPEC §12): run a café for a year, sell it (at
     * its value, less the usual negotiation: buyers open sale.opening_
     * discount below their limit and a counter wins half of it back),
     * live off savings for changing_cafe.months_between, pay the buying
     * costs, and buy another in a fresh market with everything; and so on
     * for $years. Stops at a closure or bankruptcy. The café keeps trading
     * while it's for sale, so selling costs no trading time. Net worth is
     * against the first starting capital.
     */
    public function playFlipping(Strategy $strategy, int $seed, int $years): GameOutcome
    {
        $first = $this->play($strategy, $seed, 1);
        $outcome = $first;
        $profits = $first->profitsByMonth;

        for ($year = 2; $year <= $years && $outcome->bought && $outcome->closedInYear === null; $year++) {
            $last = $outcome->valueByYear[1] ?? 0;
            $haggle = (int) round($last * $this->sheet->float('sale.opening_discount') / 2);
            $capital = $outcome->netWorthCents - $haggle
                - $this->sheet->int('changing_cafe.months_between') * $this->sheet->int('owner.pay_month_cents')
                - $this->sheet->int('changing_cafe.buying_costs_cents');

            if ($capital <= 0) {
                break;
            }

            $outcome = $this->play($strategy, $seed * 100 + $year, 1, capital: $capital);
            $profits = [...$profits, ...$outcome->profitsByMonth];
        }

        return new GameOutcome(
            strategy: $strategy->key().'-flipping',
            seed: $seed,
            startingCapitalCents: $first->startingCapitalCents,
            bought: $first->bought,
            netWorthCents: $outcome->netWorthCents,
            years: $years,
            closedInYear: $outcome->closedInYear,
            bankruptInMonth: $outcome->bankruptInMonth,
            profitsByMonth: $profits,
        );
    }

    /**
     * Plays one given café from purchase for up to $years: the loop behind
     * play(), and what the viability check runs for a café the user
     * describes (SPEC §11).
     *
     * @param  list<CompetitorState>  $competitors
     */
    public function run(Strategy $strategy, GeneratedBusiness $business, array $competitors, int $capital, int $seed, int $years, int $startMonth, SeededRng $rng): GameOutcome
    {
        $takeover = new Takeover($this->sheet);
        $deposit = $takeover->depositCents($business->profile);
        $state = $takeover->initialState($business->profile, $business->baseReputation, $business->equipmentAgeYears, $capital - $business->traspasoCents - $deposit);
        $decisions = $strategy->openingDecisions($business, $takeover->defaultDecisions(), $this->sheet);
        $rivals = count($competitors);
        $revenues = [];

        $engine = new Engine;
        $dayEngine = new DayEngine;
        $profits = [];
        $values = [];
        $valuation = new BusinessValuation($this->sheet);
        // Net worth counts the café at what a private sale would leave the
        // owner, after its costs and the tax on the gain (SPEC §12).
        $costs = new SaleCosts($this->sheet);
        $closure = new Closure($this->sheet);
        $sales = [];
        $ownerPaid = 0;
        $yearProfit = 0;
        $yearPay = 0;
        $months = $years * 12;

        for ($month = 1; $month <= $months; $month++) {
            $decisions = $decisions->with(eventChoices: $strategy->eventChoices($state, $this->sheet));
            $context = new MarketContext(($startMonth + $month - 2) % 12 + 1, $month, $competitors, $this->market->parameters);
            $result = $this->daily
                ? $dayEngine->simulateMonth($state, $decisions, $context, (new CalendarDate(self::FIRST_YEAR, $startMonth, 1))->addMonths($month - 1), $rng->fork("month-{$month}"))->month
                : $engine->simulateMonth($state, $decisions, $context, $rng->fork("month-{$month}"));

            $profits[] = $result->profitCents();
            $revenues[] = $result->revenueCents;
            $ownerPaid += $result->ownerPayCents;
            $yearProfit += $result->profitCents();
            $yearPay += $result->ownerPayCents;
            $state = $result->stateAfter;
            $competitors = $result->competitorsAfter;
            $year = intdiv($month - 1, 12) + 1;

            if ($state->cashCents < 0) {
                return $this->outcome($strategy, $seed, $capital, $business, $rivals, $month, $month, $year, $profits, $state->cashCents + $deposit, $ownerPaid, $years, $revenues, $values, $sales);
            }

            if ($month % 12 === 0) {
                $values[$year] = $valuation->valueCents($state, $business->traspasoCents, $profits);
                $sales[$year] = $costs->netCents($values[$year], $business->traspasoCents, agency: false);

                // A café that didn't pay its owner closes: the owner takes
                // the better of a quick sale and closing down (milestone 19).
                if ($yearProfit < $yearPay) {
                    $quick = $costs->netCents($closure->quickSalePriceCents($state, $business->traspasoCents, $values[$year]), $business->traspasoCents, agency: false);
                    $exit = max($quick, $closure->breakdown($state, $business->traspasoCents, $month)['net_cents']);

                    return $this->outcome($strategy, $seed, $capital, $business, $rivals, $month, null, $year, $profits, $state->cashCents + $deposit + $exit, $ownerPaid, $years, $revenues, $values, $sales);
                }

                [$yearProfit, $yearPay] = [0, 0];
            }

            $decisions = $strategy->adjust($decisions->with(eventChoices: []), $result, $business, $this->sheet);
        }

        return $this->outcome($strategy, $seed, $capital, $business, $rivals, $months, null, null, $profits, $state->cashCents + $deposit + $sales[$years], $ownerPaid, $years, $revenues, $values, $sales);
    }

    /**
     * Rivals as PurchaseBusiness picks them: the nearest other listings,
     * topped up from real cafés and bars nearby.
     *
     * @param  list<GeneratedBusiness>  $market
     * @return list<CompetitorState>
     */
    private function competitors(array $market, int $ownIndex, int $seed): array
    {
        $own = $market[$ownIndex]->location;
        $candidates = [];

        foreach ($market as $i => $other) {
            if ($i === $ownIndex) {
                continue;
            }

            $candidates[] = new CompetitorCandidate(
                id: 'market-'.($i + 1),
                name: $other->name,
                seats: $other->profile->indoorSeats + $other->profile->terraceSeats,
                condition: $other->profile->condition,
                reputation: $other->baseReputation,
                distanceMetres: $own !== null && $other->location !== null
                    ? Geo::distanceMetres($own->lat, $own->lng, $other->location->lat, $other->location->lng)
                    : null,
            );
        }

        $rng = (new SeededRng($seed))->fork('competitors');
        $places = [];

        if ($own !== null) {
            $max = $this->sheet->float('competitors.distance_metres.max');

            foreach ($this->market->rivalPlaces as $place) {
                $distance = Geo::distanceMetres($own->lat, $own->lng, $place['lat'], $place['lng']);

                if ($distance <= $max) {
                    $places[] = ['key' => $place['key'], 'distance_metres' => $distance];
                }
            }

            usort($places, fn (array $a, array $b) => [$a['distance_metres'], $a['key']] <=> [$b['distance_metres'], $b['key']]);
        }

        $fill = (new UnlistedRivals($this->sheet))->candidates($places, $rng->fork('unlisted'), array_map(fn (GeneratedBusiness $b) => $b->name, $market));

        return (new CompetitorPicker($this->sheet))->pick($candidates, $rng, $fill);
    }

    /**
     * @param  list<int>  $profits
     * @param  list<int>  $revenues
     * @param  array<int, int>  $values
     * @param  array<int, int>  $sales
     */
    private function outcome(Strategy $strategy, int $seed, int $capital, GeneratedBusiness $business, int $rivals, int $monthsPlayed, ?int $bankruptIn, ?int $closedInYear, array $profits, int $netWorth, int $ownerPaid, int $years, array $revenues, array $values, array $sales): GameOutcome
    {
        return new GameOutcome(
            strategy: $strategy->key(),
            seed: $seed,
            startingCapitalCents: $capital,
            bought: true,
            neighbourhood: $business->profile->neighbourhood->name,
            footfall: $business->profile->footfall,
            traspasoCents: $business->traspasoCents,
            rentMonthCents: $business->profile->rentMonthCents,
            rivals: $rivals,
            monthsPlayed: $monthsPlayed,
            bankruptInMonth: $bankruptIn,
            totalProfitCents: array_sum($profits),
            netWorthCents: $netWorth,
            ownerPaidCents: $ownerPaid,
            years: $years,
            closedInYear: $closedInYear,
            profitsByMonth: $profits,
            revenueByMonth: $revenues,
            valueByYear: $values,
            saleNetByYear: $sales,
        );
    }
}
