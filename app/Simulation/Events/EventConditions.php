<?php

namespace App\Simulation\Events;

use App\Simulation\Data\BusinessState;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Decisions;
use InvalidArgumentException;

/**
 * The facts an event's `requires` block can check.
 */
final readonly class EventConditions
{
    /**
     * @param  list<DayPart>  $openDayParts
     */
    public function __construct(
        public int $calendarMonth,
        public array $openDayParts,
        public bool $hasTerrace,
        public int $staffCount,
        public int $competitorCount,
    ) {}

    public static function from(BusinessState $state, Decisions $decisions, int $calendarMonth, int $competitorCount): self
    {
        return new self(
            calendarMonth: $calendarMonth,
            openDayParts: $decisions->openDayParts,
            hasTerrace: $state->profile->terraceSeats > 0,
            staffCount: $decisions->staffCount,
            competitorCount: $competitorCount,
        );
    }

    /**
     * @param  array<string, mixed>  $requires
     */
    public function allow(array $requires): bool
    {
        foreach ($requires as $rule => $value) {
            $met = match ($rule) {
                'months' => in_array($this->calendarMonth, $value, true),
                'open_any' => array_intersect($value, array_map(fn (DayPart $p) => $p->value, $this->openDayParts)) !== [],
                'terrace' => $this->hasTerrace === $value,
                'min_staff' => $this->staffCount >= $value,
                'has_competitors' => ($this->competitorCount > 0) === $value,
                default => throw new InvalidArgumentException("Unknown event requirement [{$rule}]."),
            };

            if (! $met) {
                return false;
            }
        }

        return true;
    }
}
