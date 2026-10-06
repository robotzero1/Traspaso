<?php

namespace App\Balance;

use App\Generation\BusinessGenerator;
use App\Generation\CompetitorCandidate;
use App\Generation\CompetitorPicker;
use App\Generation\GeneratedBusiness;
use App\Generation\Geo\Geo;
use App\Generation\Takeover;
use App\Generation\UnlistedRivals;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\MarketContext;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Engine;
use App\Simulation\Rng\SeededRng;
use App\Simulation\Valuation\BusinessValuation;

/**
 * Plays whole games without the database, the way the app does: generate
 * the market from the seed, buy, pick rivals, play 12 months, value what's
 * left (net worth = cash + deposit + business value; cash below zero ends
 * the game with cash + deposit). Starting capital and the starting month
 * are drawn from the seed, across the range a player can choose.
 */
final readonly class BalanceRunner
{
    private ParameterSheet $sheet;

    public function __construct(private BalanceMarket $market)
    {
        $this->sheet = new ParameterSheet($market->parameters);
    }

    public function play(Strategy $strategy, int $seed): GameOutcome
    {
        $rng = new SeededRng($seed);
        $capitalRange = $this->sheet->array('game.starting_capital_cents');
        // Whole thousands of euros, as a player would choose.
        $capital = $rng->fork('capital')->int(intdiv($capitalRange['min'], 100_000), intdiv($capitalRange['max'], 100_000)) * 100_000;
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
            return new GameOutcome($strategy->key(), $seed, $capital, bought: false, netWorthCents: $capital);
        }

        $business = $strategy->choose($affordable, $this->sheet, $rng->fork("choose-{$strategy->key()}"));
        $index = array_search($business, $market, true);
        $deposit = $takeover->depositCents($business->profile);
        $state = $takeover->initialState($business->profile, $business->baseReputation, $business->equipmentAgeYears, $capital - $business->traspasoCents - $deposit);
        $decisions = $strategy->openingDecisions($business, $takeover->defaultDecisions(), $this->sheet);
        $competitors = $this->competitors($market, $index, $seed);
        $rivals = count($competitors);

        $engine = new Engine;
        $profits = [];
        $ownerPaid = 0;
        $months = $this->sheet->int('game.months');

        for ($month = 1; $month <= $months; $month++) {
            $result = $engine->simulateMonth(
                $state,
                $decisions,
                new MarketContext(($startMonth + $month - 2) % 12 + 1, $month, $competitors, $this->market->parameters),
                $rng->fork("month-{$month}"),
            );

            $profits[] = $result->profitCents();
            $ownerPaid += $result->ownerPayCents;
            $state = $result->stateAfter;
            $competitors = $result->competitorsAfter;

            if ($state->cashCents < 0) {
                return $this->outcome($strategy, $seed, $capital, $business, $rivals, $month, $month, $profits, $state->cashCents + $deposit, $ownerPaid);
            }

            $decisions = $strategy->adjust($decisions->with(eventChoices: []), $result, $business, $this->sheet);
        }

        $value = (new BusinessValuation($this->sheet))->valueCents($state, $business->traspasoCents, $profits);

        return $this->outcome($strategy, $seed, $capital, $business, $rivals, $months, null, $profits, $state->cashCents + $deposit + $value, $ownerPaid);
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

    /** @param list<int> $profits */
    private function outcome(Strategy $strategy, int $seed, int $capital, GeneratedBusiness $business, int $rivals, int $monthsPlayed, ?int $bankruptIn, array $profits, int $netWorth, int $ownerPaid): GameOutcome
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
        );
    }
}
