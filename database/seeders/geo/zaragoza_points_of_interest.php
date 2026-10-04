<?php

/*
|--------------------------------------------------------------------------
| Points of interest in Zaragoza
|--------------------------------------------------------------------------
|
| ⚠️ PLACEHOLDER DATA: a handful of well-known landmarks at approximate,
| hand-placed coordinates (not taken from OpenStreetMap), so the map has a
| POI layer before milestone 8 imports the real OSM extract. Types follow
| SPEC §5: university, school, station, park, office, tourism, market.
|
*/

return [
    'source' => 'PLACEHOLDER: approximate hand-placed coordinates, to be replaced by the OSM extract in milestone 8',
    'points' => [
        ['type' => 'tourism', 'name' => 'Basílica del Pilar', 'lat' => 41.6566, 'lng' => -0.8784],
        ['type' => 'tourism', 'name' => 'La Seo', 'lat' => 41.6544, 'lng' => -0.8758],
        ['type' => 'tourism', 'name' => 'Aljafería', 'lat' => 41.6565, 'lng' => -0.8968],
        ['type' => 'market', 'name' => 'Mercado Central', 'lat' => 41.6545, 'lng' => -0.8812],
        ['type' => 'office', 'name' => 'Plaza de España', 'lat' => 41.6509, 'lng' => -0.8794],
        ['type' => 'office', 'name' => 'Paseo de la Independencia', 'lat' => 41.6480, 'lng' => -0.8830],
        ['type' => 'station', 'name' => 'Estación Delicias', 'lat' => 41.6585, 'lng' => -0.9112],
        ['type' => 'station', 'name' => 'Estación Portillo', 'lat' => 41.6497, 'lng' => -0.8917],
        ['type' => 'university', 'name' => 'Campus San Francisco', 'lat' => 41.6423, 'lng' => -0.9006],
        ['type' => 'university', 'name' => 'Campus Río Ebro', 'lat' => 41.6835, 'lng' => -0.8873],
        ['type' => 'park', 'name' => 'Parque Grande José Antonio Labordeta', 'lat' => 41.6343, 'lng' => -0.8946],
        ['type' => 'park', 'name' => 'Parque del Agua', 'lat' => 41.6680, 'lng' => -0.9050],
        ['type' => 'office', 'name' => 'Recinto Expo', 'lat' => 41.6655, 'lng' => -0.9070],
    ],
];
