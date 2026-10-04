<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;

/**
 * The outcome of simulating one month: the P&L breakdown for the UI and
 * the state the next month starts from.
 */
final readonly class MonthResult
{
    /**
     * @param  list<CompetitorState>  $competitorsAfter
     * @param  list<EventRecord>  $events
     */
    public function __construct(
        public int $gameMonth,
        public int $customers,
        public int $revenueCents,
        public CostBreakdown $costs,
        public BusinessState $stateAfter,
        public array $competitorsAfter = [],
        public array $events = [],
    ) {
        Guard::between('gameMonth', $gameMonth, 1, 12);
        Guard::nonNegative('customers', $customers);
        Guard::nonNegative('revenueCents', $revenueCents);
        Guard::listOf('competitorsAfter', $competitorsAfter, CompetitorState::class);
        Guard::listOf('events', $events, EventRecord::class);
    }

    public function profitCents(): int
    {
        return $this->revenueCents - $this->costs->totalCents();
    }

    public function cashAfterCents(): int
    {
        return $this->stateAfter->cashCents;
    }
}
