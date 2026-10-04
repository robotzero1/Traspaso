<?php

namespace App\Simulation\Rng;

use InvalidArgumentException;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Deterministic random number generator. Every game has a seed, and all
 * randomness in the simulation must come through an instance of this class
 * so that the same seed and decisions always produce the same results.
 *
 * Use fork() to give each step (or each month) its own independent stream.
 * That way, adding a draw in one step doesn't shift the numbers that every
 * later step sees.
 */
final class SeededRng
{
    private readonly Randomizer $randomizer;

    public function __construct(public readonly int $seed)
    {
        $this->randomizer = new Randomizer(new Xoshiro256StarStar(self::expandSeed($seed)));
    }

    /**
     * A new, independent generator whose seed is derived from this one's
     * seed and the given label. It doesn't advance this generator.
     */
    public function fork(string $label): self
    {
        return new self(self::deriveSeed($this->seed, $label));
    }

    /** Uniform integer in [min, max], both inclusive. */
    public function int(int $min, int $max): int
    {
        if ($min > $max) {
            throw new InvalidArgumentException("int(): min ({$min}) is greater than max ({$max}).");
        }

        return $this->randomizer->getInt($min, $max);
    }

    /** Uniform float in [0, 1). */
    public function float(): float
    {
        return $this->randomizer->nextFloat();
    }

    /** Uniform float in [min, max). */
    public function floatBetween(float $min, float $max): float
    {
        if ($min > $max) {
            throw new InvalidArgumentException("floatBetween(): min ({$min}) is greater than max ({$max}).");
        }

        if ($min === $max) {
            return $min;
        }

        return $min + ($max - $min) * $this->float();
    }

    /** True with the given probability (0–1). */
    public function chance(float $probability): bool
    {
        if ($probability < 0.0 || $probability > 1.0) {
            throw new InvalidArgumentException("chance(): probability ({$probability}) must be between 0 and 1.");
        }

        return $this->float() < $probability;
    }

    /** Normally distributed float (Box–Muller transform). */
    public function normal(float $mean = 0.0, float $stdDev = 1.0): float
    {
        if ($stdDev < 0.0) {
            throw new InvalidArgumentException("normal(): stdDev ({$stdDev}) must not be negative.");
        }

        // 1 - float() is in (0, 1], so log() never sees zero.
        $u1 = 1.0 - $this->float();
        $u2 = $this->float();

        return $mean + $stdDev * sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }

    /**
     * A uniformly chosen element of a non-empty list.
     *
     * @template T
     *
     * @param  array<array-key, T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        if ($items === []) {
            throw new InvalidArgumentException('pick(): items must not be empty.');
        }

        $values = array_values($items);

        return $values[$this->int(0, count($values) - 1)];
    }

    /**
     * A key chosen with probability proportional to its weight.
     *
     * @template K of array-key
     *
     * @param  array<K, int|float>  $weights
     * @return K
     */
    public function weightedKey(array $weights): int|string
    {
        $total = 0.0;

        foreach ($weights as $key => $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException("weightedKey(): weight for [{$key}] must not be negative.");
            }

            $total += $weight;
        }

        if ($total <= 0.0) {
            throw new InvalidArgumentException('weightedKey(): weights must contain at least one positive value.');
        }

        $target = $this->float() * $total;
        $lastPositive = null;

        foreach ($weights as $key => $weight) {
            if ($weight <= 0) {
                continue;
            }

            $lastPositive = $key;
            $target -= $weight;

            if ($target < 0.0) {
                return $key;
            }
        }

        // Only reachable through floating-point rounding.
        return $lastPositive;
    }

    /**
     * A shuffled copy of the list. Keys are not preserved.
     *
     * @template T
     *
     * @param  array<array-key, T>  $items
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        return $this->randomizer->shuffleArray(array_values($items));
    }

    /** Spread a 64-bit seed into the 32 bytes Xoshiro256** needs. */
    private static function expandSeed(int $seed): string
    {
        return hash('sha256', 'traspaso-rng:'.$seed, binary: true);
    }

    private static function deriveSeed(int $seed, string $label): int
    {
        $bytes = substr(hash('sha256', $seed.'/'.$label, binary: true), 0, 8);

        // 'J' is big-endian on every platform, so derived seeds are portable.
        return unpack('J', $bytes)[1];
    }
}
