<?php

use App\Simulation\Data\CalendarDate;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\DayWeather;
use App\Simulation\Data\WeatherKind;
use App\Simulation\Demand\Weather;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

function weather(): Weather
{
    return new Weather(SimulationFixtures::sheet());
}

/** @return array{rain: float, hot: float, terrace: float} average days per month */
function climate(int $year, int $month, int $years = 400): array
{
    $count = ['rain' => 0, 'hot' => 0, 'terrace' => 0];
    $rng = new SeededRng(7);

    for ($y = 0; $y < $years; $y++) {
        $first = new CalendarDate($year, $month, 1);

        for ($d = 0; $d < $first->daysInMonth(); $d++) {
            $day = weather()->draw($first->addDays($d), $rng->fork("{$y}-{$d}"));
            $count['rain'] += $day->kind === WeatherKind::Rain ? 1 : 0;
            $count['hot'] += $day->kind === WeatherKind::Hot ? 1 : 0;
            $count['terrace'] += $day->terraceUsable ? 1 : 0;
        }
    }

    return array_map(fn (int $n) => $n / $years, $count);
}

it('rains, swelters and opens the terrace as often as the climate normals say', function (int $month) {
    $sheet = SimulationFixtures::parameters();
    $climate = climate(2027, $month);

    expect($climate['rain'])->toEqualWithDelta($sheet['weather']['rain_days'][$month], 0.4)
        ->and($climate['hot'])->toEqualWithDelta($sheet['weather']['hot_days'][$month] ?? 0, 0.4)
        ->and($climate['terrace'])->toEqualWithDelta($sheet['terrace_usable_days']['days'][$month], 0.8);
})->with([1, 5, 7, 10]);

it('never opens the terrace in the rain', function () {
    $rng = new SeededRng(3);

    for ($i = 0; $i < 500; $i++) {
        $day = weather()->draw(CalendarDate::parse('2027-05-10'), $rng->fork("d{$i}"));

        if ($day->kind === WeatherKind::Rain) {
            expect($day->terraceUsable)->toBeFalse();
        }
    }
});

it('moves trade between days but averages out over the month', function (DayPart $part, int $month) {
    $sheet = SimulationFixtures::parameters();
    $date = new CalendarDate(2027, $month, 1);
    $days = $date->daysInMonth();
    $rain = $sheet['weather']['rain_days'][$month] / $days;
    $hot = ($sheet['weather']['hot_days'][$month] ?? 0) / $days;
    $factor = fn (WeatherKind $kind) => weather()->demandFactor(new DayWeather($kind, true), $part, $date);

    expect($rain * $factor(WeatherKind::Rain) + $hot * $factor(WeatherKind::Hot) + (1 - $rain - $hot) * $factor(WeatherKind::Fair))
        ->toEqualWithDelta(1.0, 1e-9)
        ->and($factor(WeatherKind::Rain))->toBeLessThan($factor(WeatherKind::Fair));
})->with([DayPart::Morning, DayPart::Afternoon, DayPart::Evening])->with([3, 7]);

it('empties the afternoon and fills the evening on a hot day', function () {
    $date = CalendarDate::parse('2027-07-15');
    $hot = new DayWeather(WeatherKind::Hot, true);
    $fair = new DayWeather(WeatherKind::Fair, true);

    expect(weather()->demandFactor($hot, DayPart::Afternoon, $date))->toBeLessThan(weather()->demandFactor($fair, DayPart::Afternoon, $date))
        ->and(weather()->demandFactor($hot, DayPart::Evening, $date))->toBeGreaterThan(weather()->demandFactor($fair, DayPart::Evening, $date));
});
