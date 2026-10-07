<?php

namespace App\Simulation\Demand;

use App\Simulation\Data\CalendarDate;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\DayWeather;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Data\WeatherKind;
use App\Simulation\Rng\SeededRng;

/**
 * Day-to-day weather, drawn from the month's climate: rain days and hot
 * days from the parameter sheet, and a terrace that opens on enough dry
 * days to give the month's terrace_usable_days.
 *
 * Seasonality already holds a month's typical weather, so a day's weather
 * only moves trade between days: each effect is divided by the month's
 * expected effect, and over the month demand averages out to 1.
 */
final readonly class Weather
{
    public function __construct(private ParameterSheet $sheet) {}

    public function draw(CalendarDate $date, SeededRng $rng): DayWeather
    {
        $days = $date->daysInMonth();
        [$rain, $hot] = $this->probabilities($date);
        $roll = $rng->float();
        $kind = match (true) {
            $roll < $rain => WeatherKind::Rain,
            $roll < $rain + $hot => WeatherKind::Hot,
            default => WeatherKind::Fair,
        };

        $dryDays = $days * (1 - $rain);
        $terraceChance = $dryDays > 0
            ? min(1.0, $this->sheet->float("terrace_usable_days.days.{$date->month}") / $dryDays)
            : 0.0;

        return new DayWeather($kind, $kind !== WeatherKind::Rain && $rng->chance($terraceChance));
    }

    /** The weather's effect on a day part's demand, relative to the month's average weather. */
    public function demandFactor(DayWeather $weather, DayPart $part, CalendarDate $date): float
    {
        [$rain, $hot] = $this->probabilities($date);
        $rainEffect = $this->sheet->float("weather.effects.rain.{$part->value}");
        $hotEffect = $this->sheet->float("weather.effects.hot.{$part->value}");
        $expected = $rain * $rainEffect + $hot * $hotEffect + (1 - $rain - $hot);

        return match ($weather->kind) {
            WeatherKind::Rain => $rainEffect,
            WeatherKind::Hot => $hotEffect,
            WeatherKind::Fair => 1.0,
        } / $expected;
    }

    /** @return array{0: float, 1: float} chance of a rain day and of a hot day */
    private function probabilities(CalendarDate $date): array
    {
        $days = $date->daysInMonth();
        $rain = min(1.0, $this->sheet->float("weather.rain_days.{$date->month}") / $days);
        $hotDays = $this->sheet->array('weather.hot_days')[$date->month] ?? 0;

        return [$rain, min(1.0 - $rain, $hotDays / $days)];
    }
}
