<?php

/*
|--------------------------------------------------------------------------
| Map display
|--------------------------------------------------------------------------
|
| Tiles are loaded by the player's browser for display only; no map data is
| fetched or stored by the app. OpenStreetMap's tile usage policy requires
| the attribution below on every map (CLAUDE.md, SPEC §8). If traffic grows,
| point MAP_TILE_URL at a tile provider or a self-hosted tile server.
|
*/

return [
    'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'attribution' => env(
        'MAP_ATTRIBUTION',
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    ),
    'max_zoom' => 19,
    'centre' => [41.6488, -0.8891],
    'zoom' => 13,
];
