<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;

/**
 * A lasting effect of an event, e.g. "capacity × 0.75 for 2 months" or
 * "rent × 1.05 from now on".
 *
 * The daily engine counts the modifiers of events that happen during the
 * month in days ($daysRemaining), starting the next day; the month's ticks
 * leave those alone. Modifiers counted in months tick at month end.
 */
final readonly class Modifier
{
    use Concerns\Immutable;

    /**
     * @param  list<DayPart>  $dayParts  the day parts it applies to; empty means all
     */
    public function __construct(
        public string $source,
        public ModifierEffect $effect,
        public float $value,
        /** Months left, including the coming one. Null means permanent. */
        public ?int $monthsRemaining,
        public array $dayParts = [],
        /** Days left, including the coming one, for modifiers counted in days. */
        public ?int $daysRemaining = null,
    ) {
        Guard::notBlank('source', $source);
        Guard::listOf('dayParts', $dayParts, DayPart::class);

        if ($monthsRemaining !== null) {
            Guard::positive('monthsRemaining', $monthsRemaining);
        }

        if ($daysRemaining !== null) {
            Guard::positive('daysRemaining', $daysRemaining);
        }

        if (in_array($effect, [ModifierEffect::Demand, ModifierEffect::Capacity, ModifierEffect::Rent], true)) {
            Guard::nonNegative('value', $value);
        }
    }

    public function appliesTo(DayPart $part): bool
    {
        return $this->dayParts === [] || in_array($part, $this->dayParts, true);
    }

    /** The modifier after one more month has passed, or null once it has run out. */
    public function tick(): ?self
    {
        if ($this->monthsRemaining === null || $this->daysRemaining !== null) {
            return $this;
        }

        return $this->monthsRemaining > 1 ? $this->with(monthsRemaining: $this->monthsRemaining - 1) : null;
    }

    /** The modifier after one more day has passed: only modifiers counted in days run down. */
    public function tickDay(): ?self
    {
        if ($this->daysRemaining === null) {
            return $this;
        }

        return $this->daysRemaining > 1 ? $this->with(daysRemaining: $this->daysRemaining - 1) : null;
    }

    /**
     * The same modifier counted in days, for an event that happened during
     * the month: its months become average-length months of days.
     */
    public function inDays(): self
    {
        return $this->monthsRemaining === null
            ? $this
            : $this->with(daysRemaining: (int) round($this->monthsRemaining * 365.25 / 12));
    }
}
