<?php

namespace App\Generation\Geo;

use App\Generation\Distributions\Normal;

/**
 * Checks the footfall surface against manual pedestrian counts: does the
 * model rank the counted spots the way the counts do? And which component
 * weights would make it rank them best?
 */
final class Calibration
{
    /**
     * Pairs each count with the nearest surface point within $maxMetres.
     *
     * @param  list<array{lat: float, lng: float, day_part: string, count: float}>  $counts
     * @param  list<array<string, mixed>>  $points  footfall surface rows
     * @return list<array{count: array, point: array<string, mixed>, distance: float}>
     */
    public static function match(array $counts, array $points, float $maxMetres = 50.0): array
    {
        $grid = new SpatialGrid(100.0);

        foreach ($points as $point) {
            $grid->add($point['lat'], $point['lng'], $point);
        }

        $matched = [];

        foreach ($counts as $count) {
            $nearest = null;

            foreach ($grid->within($count['lat'], $count['lng'], $maxMetres) as $hit) {
                if ($nearest === null || $hit['distance'] < $nearest['distance']) {
                    $nearest = $hit;
                }
            }

            if ($nearest !== null) {
                $matched[] = ['count' => $count, 'point' => $nearest['item'], 'distance' => $nearest['distance']];
            }
        }

        return $matched;
    }

    /**
     * Spearman correlation between the model's score and the counts.
     *
     * @param  list<array{count: array, point: array<string, mixed>}>  $matched
     * @param  array<string, float>|null  $weights  component weights; null = the stored footfall
     */
    public static function spearman(array $matched, ?array $weights = null): float
    {
        if (count($matched) < 3) {
            return NAN;
        }

        $model = array_map(fn (array $m) => $weights === null
            ? (float) ($m['point']["footfall_{$m['count']['day_part']}"] ?? $m['point']['footfall'])
            : self::score($m['point'], $m['count']['day_part'], $weights), $matched);
        $observed = array_map(fn (array $m) => (float) $m['count']['count'], $matched);

        return self::pearson(Ranking::percentiles($model), Ranking::percentiles($observed));
    }

    /**
     * The component weights (steps of 0.1, adding up to 1) that rank the
     * counted spots most like the counts.
     *
     * @return array{weights: array<string, float>, spearman: float}
     */
    public static function fit(array $matched): array
    {
        $best = ['weights' => [], 'spearman' => -INF];

        for ($poi = 0; $poi <= 10; $poi++) {
            for ($centrality = 0; $centrality <= 10 - $poi; $centrality++) {
                for ($catchment = 0; $catchment <= 10 - $poi - $centrality; $catchment++) {
                    $weights = [
                        'poi' => $poi / 10.0,
                        'centrality' => $centrality / 10.0,
                        'catchment' => $catchment / 10.0,
                        'transport' => (10 - $poi - $centrality - $catchment) / 10.0,
                    ];
                    $rho = self::spearman($matched, $weights);

                    if ($rho > $best['spearman']) {
                        $best = ['weights' => $weights, 'spearman' => $rho];
                    }
                }
            }
        }

        return $best;
    }

    /** Rough two-sided p-value for a Spearman correlation (Fisher transform). */
    public static function pValue(float $rho, int $n): float
    {
        if ($n < 4 || abs($rho) >= 1) {
            return $n < 4 ? 1.0 : 0.0;
        }

        $z = abs(atanh($rho)) * sqrt(($n - 3) / 1.06);

        return 2 * (1 - Normal::cdf($z));
    }

    /** @param array<string, mixed> $point @param array<string, float> $weights */
    private static function score(array $point, string $dayPart, array $weights): float
    {
        return $weights['poi'] * (float) ($point["poi_{$dayPart}_score"] ?? $point['poi_score'])
            + $weights['centrality'] * (float) $point['centrality_score']
            + $weights['catchment'] * (float) $point['catchment_score']
            + $weights['transport'] * (float) $point['transport_score'];
    }

    /** @param array<array-key, float> $x @param array<array-key, float> $y */
    private static function pearson(array $x, array $y): float
    {
        $x = array_values($x);
        $y = array_values($y);
        $n = count($x);
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $cov = $vx = $vy = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $cov += ($x[$i] - $mx) * ($y[$i] - $my);
            $vx += ($x[$i] - $mx) ** 2;
            $vy += ($y[$i] - $my) ** 2;
        }

        return $vx > 0 && $vy > 0 ? $cov / sqrt($vx * $vy) : 0.0;
    }
}
