<?php

namespace App\Generation\Distributions;

use InvalidArgumentException;

/**
 * A distribution described by a table of percentiles, e.g.
 * [0 => 35, 10 => 40, 50 => 55, 90 => 80, 100 => 90]. Percentile 0 and 100
 * are the minimum and maximum; between knots the value is interpolated
 * linearly, so drawing quantile(u) for uniform u reproduces the table.
 */
final readonly class PercentileDistribution
{
    /** @var list<float> */
    private array $percents;

    /** @var list<float> */
    private array $values;

    /**
     * @param  array<int|string, int|float>  $knots  percent => value
     */
    public function __construct(array $knots)
    {
        if (! isset($knots[0], $knots[100])) {
            throw new InvalidArgumentException('Percentile table must include percentiles 0 and 100.');
        }

        $sorted = [];

        foreach ($knots as $percent => $value) {
            if (! is_numeric($percent) || $percent < 0 || $percent > 100) {
                throw new InvalidArgumentException("Percentile [{$percent}] must be between 0 and 100.");
            }

            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException("Value for percentile [{$percent}] must be a number.");
            }

            $sorted[(string) (float) $percent] = [(float) $percent, (float) $value];
        }

        usort($sorted, fn (array $a, array $b) => $a[0] <=> $b[0]);

        for ($i = 1; $i < count($sorted); $i++) {
            if ($sorted[$i][1] < $sorted[$i - 1][1]) {
                throw new InvalidArgumentException('Percentile values must not decrease as the percentile rises.');
            }
        }

        $this->percents = array_column($sorted, 0);
        $this->values = array_column($sorted, 1);
    }

    public function min(): float
    {
        return $this->values[0];
    }

    public function max(): float
    {
        return $this->values[count($this->values) - 1];
    }

    /** The value at cumulative probability $u (0–1). */
    public function quantile(float $u): float
    {
        if ($u < 0.0 || $u > 1.0) {
            throw new InvalidArgumentException("quantile(): u ({$u}) must be between 0 and 1.");
        }

        $percent = $u * 100;

        for ($i = 1; $i < count($this->percents); $i++) {
            if ($percent <= $this->percents[$i]) {
                $span = $this->percents[$i] - $this->percents[$i - 1];
                $t = ($percent - $this->percents[$i - 1]) / $span;

                return $this->values[$i - 1] + $t * ($this->values[$i] - $this->values[$i - 1]);
            }
        }

        return $this->max();
    }

    /** The share of the distribution at or below $value (0–1). */
    public function cdf(float $value): float
    {
        if ($value < $this->min()) {
            return 0.0;
        }

        // Walk from the top so a flat stretch reports its highest percentile.
        for ($i = count($this->values) - 1; $i > 0; $i--) {
            if ($value >= $this->values[$i]) {
                return $this->percents[$i] / 100;
            }

            if ($value >= $this->values[$i - 1]) {
                $t = ($value - $this->values[$i - 1]) / ($this->values[$i] - $this->values[$i - 1]);

                return ($this->percents[$i - 1] + $t * ($this->percents[$i] - $this->percents[$i - 1])) / 100;
            }
        }

        return $this->percents[0] / 100;
    }
}
