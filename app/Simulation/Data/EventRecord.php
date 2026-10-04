<?php

namespace App\Simulation\Data;

use App\Simulation\Data\Concerns\Guard;
use InvalidArgumentException;

/**
 * A random event that happened during a month. Some events offer the player
 * a choice, e.g. "repair" or "limp_on"; $choice is null until one is made.
 */
final readonly class EventRecord
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $choices
     */
    public function __construct(
        public string $type,
        public array $payload = [],
        public array $choices = [],
        public ?string $choice = null,
    ) {
        Guard::notBlank('type', $type);

        if (! array_is_list($choices) || count(array_unique($choices)) !== count($choices)) {
            throw new InvalidArgumentException('choices must be a list of unique strings.');
        }

        if ($choice !== null && ! in_array($choice, $choices, true)) {
            throw new InvalidArgumentException("choice [{$choice}] is not one of this event's choices.");
        }
    }

    public function awaitsChoice(): bool
    {
        return $this->choices !== [] && $this->choice === null;
    }
}
