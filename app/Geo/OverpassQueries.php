<?php

namespace App\Geo;

/**
 * The Overpass QL queries geo:fetch sends, built from config('geo').
 */
final class OverpassQueries
{
    /** @param array<string, mixed> $config config('geo') */
    public function __construct(private readonly array $config) {}

    public function streets(): string
    {
        $types = implode('|', array_map(fn ($t) => preg_quote($t, '/'), $this->config['street_highway_types']));

        return $this->header()."way[\"highway\"~\"^({$types})$\"];\nout body;\n>;\nout skel qt;";
    }

    public function pointsOfInterest(): string
    {
        $clauses = [];

        foreach ($this->config['poi_types'] as $definition) {
            foreach ($definition['tags'] as $key => $values) {
                $clauses[] = $values === '*'
                    ? "nwr[\"{$key}\"];"
                    : "nwr[\"{$key}\"~\"^(".implode('|', $values).')$"];';
            }
        }

        return $this->header().'('.implode("\n", array_unique($clauses)).");\nout center tags;";
    }

    public function boundaries(): string
    {
        $b = $this->config['boundaries'];
        $timeout = $this->config['overpass_timeout_seconds'];

        return "[out:json][timeout:{$timeout}];\n"
            ."area[\"boundary\"=\"administrative\"][\"admin_level\"=\"{$b['city_admin_level']}\"][\"name\"=\"{$b['city_relation_name']}\"]->.city;\n"
            ."rel[\"boundary\"=\"administrative\"][\"admin_level\"=\"{$b['district_admin_level']}\"](area.city);\nout geom;";
    }

    private function header(): string
    {
        [$south, $west, $north, $east] = $this->config['bbox'];
        $timeout = $this->config['overpass_timeout_seconds'];

        return "[out:json][timeout:{$timeout}][bbox:{$south},{$west},{$north},{$east}];\n";
    }
}
