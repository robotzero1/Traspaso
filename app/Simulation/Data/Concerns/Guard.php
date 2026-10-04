<?php

namespace App\Simulation\Data\Concerns;

use InvalidArgumentException;

/**
 * Constructor checks shared by the DTOs. They reject impossible values
 * (negative seats, a 13th month); they are not game-balance rules.
 */
final class Guard
{
    public static function between(string $field, int|float $value, int|float $min, int|float $max): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("{$field} must be between {$min} and {$max}, got {$value}.");
        }
    }

    public static function nonNegative(string $field, int|float $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException("{$field} must not be negative, got {$value}.");
        }
    }

    public static function positive(string $field, int|float $value): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("{$field} must be positive, got {$value}.");
        }
    }

    public static function notBlank(string $field, string $value): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("{$field} must not be blank.");
        }
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @param  class-string  $class
     */
    public static function listOf(string $field, array $items, string $class): void
    {
        if (! array_is_list($items)) {
            throw new InvalidArgumentException("{$field} must be a list.");
        }

        foreach ($items as $item) {
            if (! $item instanceof $class) {
                throw new InvalidArgumentException("{$field} must only contain {$class} instances.");
            }
        }
    }
}
