<?php

/*
|--------------------------------------------------------------------------
| Geo data pipeline (SPEC §8 "Footfall estimate", milestone 8)
|--------------------------------------------------------------------------
|
| Settings for the offline pipeline that turns open data into the files
| committed under database/seeders/geo/zaragoza/:
|
|   php artisan geo:fetch       download OSM data for the city (needs network)
|   php artisan geo:build       derive neighbourhoods, POIs and the footfall surface
|   php artisan geo:calibrate   compare the surface with manual pedestrian counts
|
| The app never runs these at request time and never fetches geo data at
| runtime: it reads the committed files through the seeders.
|
| ⚠️ The footfall weights, radii and timing profiles are PLACEHOLDERS until
| calibrated against manual pedestrian counts (geo:calibrate --fit).
|
*/

return [

    'city' => 'zaragoza',

    // Relative to the project root: where downloads go (not committed:
    // they're large), the hand-collected sources, and the derived files the
    // seeders read.
    'raw_path' => 'storage/app/geo/zaragoza',
    'sources_path' => 'database/seeders/geo/zaragoza/sources',
    'output_path' => 'database/seeders/geo/zaragoza',

    /*
    |--------------------------------------------------------------------------
    | Fetching (geo:fetch)
    |--------------------------------------------------------------------------
    */

    'overpass_url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
    // Overpass turns away requests with a generic HTTP-library User-Agent
    // (406 Not Acceptable): identify the tool, with a way to reach you.
    'user_agent' => env('GEO_USER_AGENT', 'Traspaso/1.0 (business simulator; https://github.com/robotzero1/Traspaso)'),
    // PHP memory for geo:build; a city's raw street data is large.
    'build_memory_limit' => '2G',
    'overpass_timeout_seconds' => 300,

    // The public Overpass servers time out (504) on big queries and push
    // back when busy (429). Streets and POIs are fetched in fetch_tiles ×
    // fetch_tiles pieces, each retried up to `retries` times with a growing
    // pause; finished tiles are kept, so a re-run resumes.
    'fetch_tiles' => 3,
    'retries' => 4,
    'retry_delay_ms' => 20_000,

    // The urban area: south, west, north, east.
    'bbox' => [41.600, -0.960, 41.700, -0.820],

    // Districts as OSM administrative boundaries inside the city. If OSM
    // doesn't have them at this level, put a GeoJSON FeatureCollection with
    // a "name" property per district (e.g. the juntas municipales from
    // Zaragoza's open data portal) at sources/neighbourhoods.geojson and it
    // is used instead.
    'boundaries' => [
        'city_relation_name' => 'Zaragoza',
        'city_admin_level' => 8,
        'district_admin_level' => 9,
        // Districts left out of the game: Zaragoza's Distrito Rural is the
        // outlying villages and countryside around the city.
        'exclude' => ['Distrito Rural'],
    ],

    // The built-up part of each district: grid cells of cell_metres with
    // at least min_street_metres of street in them. Densities (indices,
    // competition, residents for footfall) are per built-up km², so
    // farmland inside a boundary doesn't dilute them. A dense city block
    // has 15–25 km of street per km² (600–1,000 m per 200 m cell); a
    // country road crossing a cell is under 300 m.
    'built_up' => ['cell_metres' => 200, 'min_street_metres' => 400],

    // Streets that people walk along. Footways and service roads are left
    // out: sidewalks mapped separately would double the network.
    'street_highway_types' => [
        'primary', 'primary_link', 'secondary', 'secondary_link', 'tertiary', 'tertiary_link',
        'unclassified', 'residential', 'living_street', 'pedestrian',
    ],

    // Street type for the game, from the OSM highway class.
    'street_types' => [
        'primary' => 'main_street', 'primary_link' => 'main_street', 'secondary' => 'main_street',
        'secondary_link' => 'main_street', 'tertiary' => 'secondary_street', 'tertiary_link' => 'secondary_street',
        'unclassified' => 'secondary_street', 'residential' => 'side_street', 'living_street' => 'side_street',
        'pedestrian' => 'square',
    ],

    /*
    |--------------------------------------------------------------------------
    | Points of interest
    |--------------------------------------------------------------------------
    |
    | OSM tags → the game's POI types. First match wins.
    |
    */

    'poi_types' => [
        // Hospitality in three types with different hours (see timing).
        ['type' => 'nightlife', 'tags' => ['amenity' => ['bar', 'pub', 'nightclub']]],
        ['type' => 'cafe', 'tags' => ['amenity' => ['cafe', 'ice_cream']]],
        ['type' => 'restaurant', 'tags' => ['amenity' => ['restaurant', 'fast_food']]],
        ['type' => 'university', 'tags' => ['amenity' => ['university', 'college']]],
        ['type' => 'school', 'tags' => ['amenity' => ['school']]],
        ['type' => 'market', 'tags' => ['amenity' => ['marketplace']]],
        ['type' => 'station', 'tags' => ['railway' => ['station', 'halt', 'tram_stop']]],
        ['type' => 'bus_stop', 'tags' => ['highway' => ['bus_stop']]],
        ['type' => 'tourism', 'tags' => ['tourism' => ['attraction', 'museum', 'gallery', 'viewpoint', 'hotel']]],
        ['type' => 'park', 'tags' => ['leisure' => ['park']]],
        ['type' => 'office', 'tags' => ['office' => '*']],
        ['type' => 'shop', 'tags' => ['shop' => '*']],
    ],

    // Cafés and bars counted for competition_density.
    'competitor_tags' => ['amenity' => ['cafe', 'bar', 'pub']],

    // Share of real premises that are mapped in OSM. Zaragoza has about
    // 3,000–3,500 hospitality establishments (IAEST / Asociación Café Bares
    // de Zaragoza); OSM had 2,152 cafés, bars and restaurants in October
    // 2026, so about 65%. competition_density is scaled up by 1 / this.
    'osm_coverage' => ['source' => 'IAEST, Asociación Café Bares de Zaragoza (user, 2026) vs OSM count', 'hospitality' => 0.65],

    // POI types shown on the map's landmark layer (the rest feed the model).
    'map_poi_types' => ['university', 'station', 'tourism', 'park', 'market'],

    /*
    |--------------------------------------------------------------------------
    | Footfall surface (geo:build)
    |--------------------------------------------------------------------------
    */

    'footfall' => [
        'source' => 'PLACEHOLDER: to calibrate against manual pedestrian counts',

        // Sample a point along the streets every this many metres.
        'sample_spacing_metres' => 25,

        // A point is commercial (businesses can be placed there) with at
        // least min_pois shops or hospitality venues within radius_metres.
        'commercial' => ['radius_metres' => 30, 'min_pois' => 1, 'types' => ['shop', 'nightlife', 'cafe', 'restaurant']],

        // How the four components combine (they're each ranked 0–1 first),
        // for the all-day footfall.
        'component_weights' => ['poi' => 0.4, 'centrality' => 0.3, 'catchment' => 0.2, 'transport' => 0.1],

        // The same per day part (missing = component_weights). What's open
        // nearby matters more as the day goes on: at night a busy junction
        // with nothing open isn't busy. Commuters make transport count in
        // the morning.
        'component_weights_by_day_part' => [
            'morning' => ['poi' => 0.35, 'centrality' => 0.25, 'catchment' => 0.15, 'transport' => 0.25],
            'lunch' => ['poi' => 0.45, 'centrality' => 0.25, 'catchment' => 0.15, 'transport' => 0.15],
            'afternoon' => ['poi' => 0.4, 'centrality' => 0.3, 'catchment' => 0.2, 'transport' => 0.1],
            'evening' => ['poi' => 0.5, 'centrality' => 0.25, 'catchment' => 0.2, 'transport' => 0.05],
            'night' => ['poi' => 0.7, 'centrality' => 0.2, 'catchment' => 0.1, 'transport' => 0.0],
        ],

        // poi: Σ weight × timing[day part] × e^(−distance / decay).
        'poi' => [
            'decay_metres' => 150,
            'max_radius_metres' => 450,
            'weights' => [
                'shop' => 1.0, 'nightlife' => 1.0, 'cafe' => 1.0, 'restaurant' => 1.0, 'office' => 1.5, 'university' => 8.0, 'school' => 3.0,
                'market' => 5.0, 'station' => 6.0, 'bus_stop' => 0.5, 'tourism' => 4.0, 'park' => 2.0,
            ],
            // When each type draws people, by day part (missing = 1.0).
            'timing' => [
                'office' => ['morning' => 1.5, 'lunch' => 1.5, 'afternoon' => 0.7, 'evening' => 0.2, 'night' => 0.0],
                'school' => ['morning' => 1.5, 'lunch' => 0.8, 'afternoon' => 1.0, 'evening' => 0.0, 'night' => 0.0],
                'university' => ['morning' => 1.2, 'lunch' => 1.2, 'afternoon' => 1.2, 'evening' => 0.6, 'night' => 0.3],
                // Small shops close about 14:00–17:00 and by 20:30.
                'shop' => ['morning' => 1.2, 'lunch' => 0.5, 'afternoon' => 1.3, 'evening' => 0.4, 'night' => 0.0],
                'nightlife' => ['morning' => 0.1, 'lunch' => 0.4, 'afternoon' => 0.6, 'evening' => 1.5, 'night' => 2.5],
                'cafe' => ['morning' => 1.6, 'lunch' => 1.0, 'afternoon' => 1.2, 'evening' => 0.6, 'night' => 0.1],
                'restaurant' => ['morning' => 0.3, 'lunch' => 1.8, 'afternoon' => 0.4, 'evening' => 1.6, 'night' => 0.5],
                'market' => ['morning' => 1.5, 'lunch' => 1.0, 'afternoon' => 0.5, 'evening' => 0.0, 'night' => 0.0],
                'tourism' => ['morning' => 0.8, 'lunch' => 1.2, 'afternoon' => 1.2, 'evening' => 1.0, 'night' => 0.5],
                'park' => ['morning' => 0.8, 'lunch' => 0.8, 'afternoon' => 1.4, 'evening' => 0.8, 'night' => 0.1],
            ],
        ],

        // centrality: local edge betweenness on the street network, counting
        // shortest walks up to this length. sample_sources < 1 speeds up a
        // big city at some cost in precision.
        'centrality' => ['radius_metres' => 800, 'sample_sources' => 1.0],

        // catchment: residents (neighbourhood density × area) plus workers
        // (offices × workers_per_office) within the radius.
        'catchment' => ['radius_metres' => 600, 'workers_per_office' => 15],

        // transport: Σ weight × e^(−distance / decay) over stops.
        'transport' => [
            'decay_metres' => 150,
            'max_radius_metres' => 300,
            'weights' => ['station' => 3.0, 'bus_stop' => 1.0],
        ],

        // The combined score's rank is mapped onto this 0–10 scale, per day
        // part. It matches the spread the generator used before real data,
        // so the game's balance carries over.
        'percentiles' => [0 => 0.5, 10 => 2.2, 25 => 3.5, 50 => 5.1, 75 => 7.0, 90 => 8.6, 100 => 10.0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Neighbourhood indices (geo:build)
    |--------------------------------------------------------------------------
    |
    | Each index is a density per km² of the listed POI types (weighted),
    | ranked across neighbourhoods onto 0–10.
    |
    */

    'indices' => [
        'source' => 'PLACEHOLDER: weights to review',
        'student' => ['university' => 1.0, 'school' => 0.3],
        'tourist' => ['tourism' => 1.0],
        'office' => ['office' => 1.0],
        'transport' => ['station' => 3.0, 'bus_stop' => 1.0],
    ],

];
