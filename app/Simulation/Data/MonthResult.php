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
     * @param  list<EventRecord>  $events  new this month (some await a choice)
     * @param  list<DayPartResult>  $dayParts
     * @param  list<EventRecord>  $resolvedEvents  earlier events whose choice took effect this month
     */
    public function __construct(
        public int $gameMonth,
        public int $customers,
        public int $revenueCents,
        public CostBreakdown $costs,
        public BusinessState $stateAfter,
        public array $competitorsAfter = [],
        public array $events = [],
        public array $dayParts = [],
        public array $resolvedEvents = [],
        /** One-off income from events, included in $revenueCents. */
        public int $eventRevenueCents = 0,
    ) {
        Guard::between('gameMonth', $gameMonth, 1, 12);
        Guard::nonNegative('customers', $customers);
        Guard::nonNegative('revenueCents', $revenueCents);
        Guard::listOf('competitorsAfter', $competitorsAfter, CompetitorState::class);
        Guard::listOf('events', $events, EventRecord::class);
        Guard::listOf('dayParts', $dayParts, DayPartResult::class);
        Guard::listOf('resolvedEvents', $resolvedEvents, EventRecord::class);
        Guard::nonNegative('eventRevenueCents', $eventRevenueCents);
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
