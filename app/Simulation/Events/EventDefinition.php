<?php

namespace App\Simulation\Events;

use InvalidArgumentException;

/**
 * One event in the library: when it can happen, how likely it is, and
 * what it does — directly, through a weighted outcome, or through the
 * player's choice.
 */
final readonly class EventDefinition
{
    /**
     * @param  array<string, int|float>  $probability
     * @param  array<string, mixed>  $requires
     * @param  array<string, EventEffects>  $choices
     * @param  array<string, array{weight: array<string, int|float>, effects: EventEffects}>  $outcomes
     */
    public function __construct(
        public string $type,
        public array $probability,
        public array $requires,
        public EventEffects $effects,
        public array $choices = [],
        public ?string $defaultChoice = null,
        public array $outcomes = [],
    ) {
        if ($choices !== [] && $outcomes !== []) {
            throw new InvalidArgumentException("Event [{$type}] can have choices or outcomes, not both.");
        }

        if ($choices !== [] && ! isset($choices[$defaultChoice])) {
            throw new InvalidArgumentException("Event [{$type}] needs a default_choice that is one of its choices.");
        }

        if ($choices === [] && $defaultChoice !== null) {
            throw new InvalidArgumentException("Event [{$type}] has a default_choice but no choices.");
        }
    }

    /** @param array<string, mixed> $config */
    public static function fromConfig(string $type, array $config): self
    {
        return new self(
            type: $type,
            probability: $config['probability'] ?? throw new InvalidArgumentException("Event [{$type}] needs a probability."),
            requires: $config['requires'] ?? [],
            effects: EventEffects::fromConfig($config['effects'] ?? []),
            choices: array_map(fn (array $c) => EventEffects::fromConfig($c), $config['choices'] ?? []),
            defaultChoice: $config['default_choice'] ?? null,
            outcomes: array_map(fn (array $o) => [
                'weight' => $o['weight'],
                'effects' => EventEffects::fromConfig($o['effects'] ?? []),
            ], $config['outcomes'] ?? []),
        );
    }

    /** @return list<string> */
    public function choiceNames(): array
    {
        return array_keys($this->choices);
    }
}
