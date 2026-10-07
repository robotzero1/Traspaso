<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;

/**
 * A month played day by day: each day's result, and the month's P&L and
 * end state once the month's bills were settled.
 */
final readonly class DailyMonthResult
{
    /** @param list<DayResult> $days */
    public function __construct(
        public array $days,
        public MonthResult $month,
    ) {
        Guard::listOf('days', $days, DayResult::class);
    }
}
