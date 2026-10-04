<?php

namespace App\Simulation\Data\Concerns;

use InvalidArgumentException;

/**
 * Adds with(), which returns a copy with some properties changed. The copy
 * goes through the constructor, so it is validated like any new instance:
 *
 *     $state->with(reputation: 62.5, staffMorale: 40.0)
 */
trait Immutable
{
    public function with(mixed ...$changes): static
    {
        $properties = get_object_vars($this);

        foreach (array_keys($changes) as $name) {
            if (! is_string($name) || ! array_key_exists($name, $properties)) {
                throw new InvalidArgumentException(static::class."::with() got an unknown property [{$name}].");
            }
        }

        return new static(...array_merge($properties, $changes));
    }
}
