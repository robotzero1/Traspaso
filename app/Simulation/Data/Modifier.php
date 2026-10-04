<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;

/**
 * A lasting effect of an event, e.g. "capacity × 0.75 for 2 months" or
 * "rent × 1.05 from now on".
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
    ) {
        Guard::notBlank('source', $source);
        Guard::listOf('dayParts', $dayParts, DayPart::class);

        if ($monthsRemaining !== null) {
            Guard::positive('monthsRemaining', $monthsRemaining);
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
        if ($this->monthsRemaining === null) {
            return $this;
        }

        return $this->monthsRemaining > 1 ? $this->with(monthsRemaining: $this->monthsRemaining - 1) : null;
    }
}
