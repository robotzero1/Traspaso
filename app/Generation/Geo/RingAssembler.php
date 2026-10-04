<?php

namespace App\Generation\Geo;

/**
 * Joins the way segments of an OSM multipolygon relation into closed rings.
 * Segments are lists of [lng, lat]; they may need reversing to join.
 */
final class RingAssembler
{
    /**
     * @param  list<list<array{0: float, 1: float}>>  $segments
     * @return list<list<array{0: float, 1: float}>> closed rings (first point repeated last)
     */
    public static function assemble(array $segments): array
    {
        $segments = array_values(array_filter($segments, fn (array $s) => count($s) >= 2));
        $rings = [];

        while ($segments !== []) {
            $ring = array_shift($segments);

            while (! self::closed($ring)) {
                $end = end($ring);
                $joined = false;

                foreach ($segments as $i => $segment) {
                    if (self::same($segment[0], $end)) {
                        $ring = [...$ring, ...array_slice($segment, 1)];
                    } elseif (self::same(end($segment), $end)) {
                        $ring = [...$ring, ...array_slice(array_reverse($segment), 1)];
                    } else {
                        continue;
                    }

                    unset($segments[$i]);
                    $segments = array_values($segments);
                    $joined = true;
                    break;
                }

                if (! $joined) {
                    // An unclosable gap in the data: close it straight across.
                    $ring[] = $ring[0];
                }
            }

            if (count($ring) >= 4) {
                $rings[] = $ring;
            }
        }

        return $rings;
    }

    /** @param list<array{0: float, 1: float}> $ring */
    private static function closed(array $ring): bool
    {
        return count($ring) >= 4 && self::same($ring[0], end($ring));
    }

    /** @param array{0: float, 1: float} $a @param array{0: float, 1: float} $b */
    private static function same(array $a, array $b): bool
    {
        return abs($a[0] - $b[0]) < 1e-9 && abs($a[1] - $b[1]) < 1e-9;
    }
}
