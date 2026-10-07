<?php

namespace Tests\Support;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\CalendarDate;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\MarketContext;
use App\Simulation\Data\MonthResult;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\DayEngine;
use App\Simulation\Engine;
use App\Simulation\Rng\SeededRng;
use App\Simulation\Valuation\BusinessValuation;

/**
 * Plays a business for up to 12 months with fixed decisions, for balance
 * tests. Net worth = cash + deposit + the business's value (SPEC §1).
 */
final class BalanceGame
{
    /** @var list<MonthResult> */
    public array $months = [];

    /**
     * @param  list<CompetitorState>  $competitors
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public readonly BusinessState $start,
        public readonly int $startingCapitalCents,
        public readonly int $traspasoCents,
        public readonly int $depositCents,
        private readonly array $competitors,
        private readonly array $parameters,
        private readonly int $seed = 1,
        private readonly int $startCalendarMonth = 1,
    ) {}

    /**
     * @param  Decisions|callable(int, BusinessState): Decisions  $decisions  fixed, or chosen per month
     * @param  bool  $daily  play day by day (DayEngine), on the calendar of 2027
     */
    public function play(Decisions|callable $decisions, int $months = 12, bool $daily = false): self
    {
        $engine = new Engine;
        $dayEngine = new DayEngine;
        $state = $this->start;

        for ($month = 1; $month <= $months; $month++) {
            $context = new MarketContext(
                calendarMonth: ($this->startCalendarMonth + $month - 2) % 12 + 1,
                gameMonth: $month,
                competitors: $this->competitors,
                parameters: $this->parameters,
            );
            $choice = $decisions instanceof Decisions ? $decisions : $decisions($month, $state);
            $rng = (new SeededRng($this->seed))->fork("month-{$month}");
            $result = $daily
                ? $dayEngine->simulateMonth($state, $choice, $context, (new CalendarDate(2027, $this->startCalendarMonth, 1))->addMonths($month - 1), $rng)->month
                : $engine->simulateMonth($state, $choice, $context, $rng);

            $this->months[] = $result;
            $state = $result->stateAfter;
        }

        return $this;
    }

    public function finalCashCents(): int
    {
        return end($this->months)->cashAfterCents();
    }

    public function lowestCashCents(): int
    {
        return min(array_map(fn (MonthResult $m) => $m->cashAfterCents(), $this->months));
    }

    public function totalProfitCents(): int
    {
        return array_sum(array_map(fn (MonthResult $m) => $m->profitCents(), $this->months));
    }

    public function businessValueCents(): int
    {
        return (new BusinessValuation(new ParameterSheet($this->parameters)))->valueCents(
            end($this->months)->stateAfter,
            $this->traspasoCents,
            array_map(fn (MonthResult $m) => $m->profitCents(), $this->months),
        );
    }

    public function netWorthCents(): int
    {
        return $this->finalCashCents() + $this->depositCents + $this->businessValueCents();
    }

    /** Change in net worth over the game, as a share of starting capital. */
    public function netWorthChange(): float
    {
        return ($this->netWorthCents() - $this->startingCapitalCents) / $this->startingCapitalCents;
    }

    /** @return list<int> */
    public function revenueByMonth(): array
    {
        return array_map(fn (MonthResult $m) => $m->revenueCents, $this->months);
    }
}
