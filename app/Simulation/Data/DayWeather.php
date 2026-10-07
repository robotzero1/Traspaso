<?php

namespace App\Simulation\Data;

/**
 * One day's weather: what kind of day it is, and whether the terrace
 * could be used (rain, cold or a strong cierzo close it).
 */
final readonly class DayWeather
{
    public function __construct(
        public WeatherKind $kind,
        public bool $terraceUsable,
    ) {}
}
