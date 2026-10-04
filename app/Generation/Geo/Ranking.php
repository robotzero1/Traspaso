<?php

namespace App\Generation\Geo;

/**
 * Percentile ranks: each value's position in the set, from 0 (lowest) to
 * 1 (highest), with ties sharing the average rank.
 */
final class Ranking
{
    /**
     * @param  array<array-key, float>  $values
     * @return array<array-key, float> same keys
     */
    public static function percentiles(array $values): array
    {
        $n = count($values);

        if ($n <= 1) {
            return array_map(fn () => 0.5, $values);
        }

        asort($values);
        $keys = array_keys($values);
        $sorted = array_values($values);
        $ranks = [];

        for ($i = 0; $i < $n;) {
            $j = $i;

            while ($j + 1 < $n && $sorted[$j + 1] == $sorted[$i]) {
                $j++;
            }

            for ($k = $i; $k <= $j; $k++) {
                $ranks[$keys[$k]] = (float) (($i + $j) / 2) / ($n - 1);
            }

            $i = $j + 1;
        }

        return $ranks;
    }
}
