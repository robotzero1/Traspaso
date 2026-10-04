<?php

namespace App\Simulation\Data;

use InvalidArgumentException;

/**
 * Read-only access to a market parameter sheet (config/market/*.php),
 * passed in by the caller because pure code can't read config itself.
 */
final readonly class ParameterSheet
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(public array $values) {}

    /**
     * Look up a value by dot-notation key, e.g. "rent.percentiles_cents".
     * A missing key is a bug in the parameter sheet, so it throws.
     */
    public function get(string $key): mixed
    {
        $value = $this->values;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                throw new InvalidArgumentException("Market parameter [{$key}] is not defined.");
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function float(string $key): float
    {
        $value = $this->get($key);

        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("Market parameter [{$key}] must be a number.");
        }

        return (float) $value;
    }

    public function int(string $key): int
    {
        $value = $this->get($key);

        if (! is_int($value)) {
            throw new InvalidArgumentException("Market parameter [{$key}] must be an integer.");
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->get($key);

        if (! is_array($value)) {
            throw new InvalidArgumentException("Market parameter [{$key}] must be an array.");
        }

        return $value;
    }
}
