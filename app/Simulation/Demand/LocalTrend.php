<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

/**
 * How a spot's custom drifts over the years: offices move in or out, a
 * market opens, an area goes up or down. A random walk on the log scale,
 * one step a month, so changes last; sd_per_year sets how far a typical
 * year moves it, and it stays between min and max.
 */
final readonly class LocalTrend
{
    public function __construct(private ParameterSheet $sheet) {}

    public function next(float $trend, SeededRng $rng): float
    {
        $sdMonth = $this->sheet->float('demand.local_trend.sd_per_year') / sqrt(12);
        $next = $trend * exp($rng->normal(0.0, $sdMonth));

        return min($this->sheet->float('demand.local_trend.max'), max($this->sheet->float('demand.local_trend.min'), $next));
    }
}
