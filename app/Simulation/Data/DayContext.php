<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;

/**
 * Everything outside the business that the daily engine needs for one
 * day: the date, nearby competitors, the parameter sheet, and the events
 * that already happened this month (an event type happens at most once a
 * month, and only events.max_per_month in all).
 */
final readonly class DayContext
{
    /**
     * @param  list<CompetitorState>  $competitors
     * @param  array<string, mixed>  $parameters
     * @param  list<string>  $eventsThisMonth  event types that already happened this month
     */
    public function __construct(
        public CalendarDate $date,
        /** Months since the business was bought, from 1. */
        public int $gameMonth,
        public array $competitors,
        public array $parameters,
        public array $eventsThisMonth = [],
        /**
         * Settle events waiting for a choice before trading: the player's
         * pick, or the default. The turn-based game does this on the first
         * day of each month, after the player has chosen.
         */
        public bool $settleChoices = false,
    ) {
        Guard::positive('gameMonth', $gameMonth);
        Guard::listOf('competitors', $competitors, CompetitorState::class);
    }
}
