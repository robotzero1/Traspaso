<?php

namespace App\Geo;

/**
 * The Overpass QL queries geo:fetch sends, built from config('geo').
 */
final class OverpassQueries
{
    /** @param array<string, mixed> $config config('geo') */
    public function __construct(private readonly array $config) {}

    /**
     * The city's bounding box cut into n × n tiles (south, west, north, east).
     *
     * @return list<array{0: float, 1: float, 2: float, 3: float}>
     */
    public function tiles(int $n): array
    {
        [$south, $west, $north, $east] = $this->config['bbox'];
        $n = max(1, $n);
        $dLat = ($north - $south) / $n;
        $dLng = ($east - $west) / $n;
        $tiles = [];

        for ($row = 0; $row < $n; $row++) {
            for ($col = 0; $col < $n; $col++) {
                $tiles[] = [
                    round($south + $row * $dLat, 6),
                    round($west + $col * $dLng, 6),
                    round($south + ($row + 1) * $dLat, 6),
                    round($west + ($col + 1) * $dLng, 6),
                ];
            }
        }

        return $tiles;
    }

    /** @param array{0: float, 1: float, 2: float, 3: float}|null $bbox defaults to the whole city */
    public function streets(?array $bbox = null): string
    {
        $types = implode('|', array_map(fn ($t) => preg_quote($t, '/'), $this->config['street_highway_types']));

        return $this->header($bbox)."way[\"highway\"~\"^({$types})$\"];\nout body;\n>;\nout skel qt;";
    }

    /** @param array{0: float, 1: float, 2: float, 3: float}|null $bbox defaults to the whole city */
    public function pointsOfInterest(?array $bbox = null): string
    {
        $clauses = [];

        foreach ($this->config['poi_types'] as $definition) {
            foreach ($definition['tags'] as $key => $values) {
                $clauses[] = $values === '*'
                    ? "nwr[\"{$key}\"];"
                    : "nwr[\"{$key}\"~\"^(".implode('|', $values).')$"];';
            }
        }

        return $this->header($bbox).'('.implode("\n", array_unique($clauses)).");\nout center tags;";
    }

    public function boundaries(): string
    {
        $b = $this->config['boundaries'];
        $timeout = $this->config['overpass_timeout_seconds'];

        return "[out:json][timeout:{$timeout}];\n"
            ."area[\"boundary\"=\"administrative\"][\"admin_level\"=\"{$b['city_admin_level']}\"][\"name\"=\"{$b['city_relation_name']}\"]->.city;\n"
            ."rel[\"boundary\"=\"administrative\"][\"admin_level\"=\"{$b['district_admin_level']}\"](area.city);\nout geom;";
    }

    /** @param array{0: float, 1: float, 2: float, 3: float}|null $bbox */
    private function header(?array $bbox = null): string
    {
        [$south, $west, $north, $east] = $bbox ?? $this->config['bbox'];
        $timeout = $this->config['overpass_timeout_seconds'];

        return "[out:json][timeout:{$timeout}][bbox:{$south},{$west},{$north},{$east}];\n";
    }
}
