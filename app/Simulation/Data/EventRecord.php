<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;
use App\Simulation\Data\Concerns\Immutable;
use InvalidArgumentException;

/**
 * A random event that happened during a month. Some events offer the player
 * a choice, e.g. "repair" or "limp_on"; $choice is null until one is made.
 * An event type happens at most once a month, so month + type identify it.
 */
final readonly class EventRecord
{
    use Immutable;

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $choices
     */
    public function __construct(
        public string $type,
        public int $month,
        public array $payload = [],
        public array $choices = [],
        public ?string $choice = null,
    ) {
        Guard::notBlank('type', $type);
        Guard::between('month', $month, 1, 12);

        if (! array_is_list($choices) || count(array_unique($choices)) !== count($choices)) {
            throw new InvalidArgumentException('choices must be a list of unique strings.');
        }

        if ($choice !== null && ! in_array($choice, $choices, true)) {
            throw new InvalidArgumentException("choice [{$choice}] is not one of this event's choices.");
        }
    }

    /** Identifies the event within a game, e.g. "3:equipment_failure". */
    public function key(): string
    {
        return "{$this->month}:{$this->type}";
    }

    public function awaitsChoice(): bool
    {
        return $this->choices !== [] && $this->choice === null;
    }
}
