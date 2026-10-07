<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;

/**
 * One day of trading. Takings come in every day, and the stock sold is
 * paid for with them (COGS), as are one-off event costs; rent, wages and
 * the other monthly bills are settled at month end (DayEngine::closeMonth).
 */
final readonly class DayResult
{
    /**
     * @param  list<DayPartResult>  $dayParts
     * @param  list<EventRecord>  $events  new today (some await a choice)
     * @param  list<EventRecord>  $resolvedEvents  earlier events whose choice took effect today
     * @param  list<CompetitorState>  $competitorsAfter
     */
    public function __construct(
        public CalendarDate $date,
        public int $gameMonth,
        public bool $open,
        public DayWeather $weather,
        public int $customers,
        /** Net of IVA, including event income. */
        public int $revenueCents,
        public int $cogsCents,
        /** One-off costs of today's events and of choices that took effect today. */
        public int $eventCostCents,
        /** Today's share of monthly-cost modifiers (e.g. air conditioning in a heatwave). */
        public int $modifierCostCents,
        public BusinessState $stateAfter,
        public array $competitorsAfter = [],
        public array $dayParts = [],
        public array $events = [],
        public array $resolvedEvents = [],
        public int $eventRevenueCents = 0,
        /** Demand ÷ what the people on shift could serve. */
        public float $utilisation = 0.0,
    ) {
        Guard::positive('gameMonth', $gameMonth);
        Guard::nonNegative('customers', $customers);
        Guard::nonNegative('revenueCents', $revenueCents);
        Guard::nonNegative('cogsCents', $cogsCents);
        Guard::nonNegative('eventCostCents', $eventCostCents);
        Guard::nonNegative('modifierCostCents', $modifierCostCents);
        Guard::nonNegative('eventRevenueCents', $eventRevenueCents);
        Guard::listOf('dayParts', $dayParts, DayPartResult::class);
        Guard::listOf('events', $events, EventRecord::class);
        Guard::listOf('resolvedEvents', $resolvedEvents, EventRecord::class);
        Guard::listOf('competitorsAfter', $competitorsAfter, CompetitorState::class);
    }

    /** What the day added to the till: takings less the stock and one-off costs paid from them. */
    public function cashFlowCents(): int
    {
        return $this->revenueCents - $this->cogsCents - $this->eventCostCents - $this->modifierCostCents;
    }
}
