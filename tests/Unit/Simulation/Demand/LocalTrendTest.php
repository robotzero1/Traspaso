<?php

use App\Simulation\Data\ParameterSheet;
use App\Simulation\Demand\LocalTrend;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

function trendSheet(float $sd, float $min = 0.4, float $max = 1.6): ParameterSheet
{
    return new ParameterSheet(array_replace_recursive(SimulationFixtures::parameters(), [
        'demand' => ['local_trend' => ['sd_per_year' => $sd, 'min' => $min, 'max' => $max]],
    ]));
}

it('drifts by about sd_per_year in a year, in either direction', function () {
    $trend = new LocalTrend(trendSheet(0.2));
    $logs = [];

    foreach (range(1, 2000) as $seed) {
        $value = 1.0;

        for ($month = 1; $month <= 12; $month++) {
            $value = $trend->next($value, (new SeededRng($seed))->fork("m{$month}"));
        }

        $logs[] = log($value);
    }

    $mean = array_sum($logs) / count($logs);
    $sd = sqrt(array_sum(array_map(fn ($l) => ($l - $mean) ** 2, $logs)) / count($logs));

    expect($mean)->toEqualWithDelta(0.0, 0.02)
        ->and($sd)->toEqualWithDelta(0.2, 0.02);
});

it('stays where it is with no drift, and within its bounds', function () {
    expect((new LocalTrend(trendSheet(0.0)))->next(1.3, new SeededRng(1)))->toBe(1.3);

    $wild = new LocalTrend(trendSheet(5.0, 0.5, 1.5));

    foreach (range(1, 50) as $seed) {
        expect($wild->next(1.0, new SeededRng($seed)))->toBeGreaterThanOrEqual(0.5)->toBeLessThanOrEqual(1.5);
    }
});

it('drifts the same way for the same seed', function () {
    $trend = new LocalTrend(trendSheet(0.2));

    expect($trend->next(1.0, new SeededRng(9)))->toBe($trend->next(1.0, new SeededRng(9)));
});
