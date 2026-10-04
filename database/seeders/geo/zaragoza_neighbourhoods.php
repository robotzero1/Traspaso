<?php

/*
|--------------------------------------------------------------------------
| Zaragoza's urban districts (juntas municipales)
|--------------------------------------------------------------------------
|
| ⚠️ PLACEHOLDER DATA. The district names are real; every number is a
| rough estimate, to be replaced in milestone 8 with population from the
| INE / municipal padrón, indices derived from OSM extracts and real
| boundaries (SPEC §5). Indices run 0–10; competition_density is cafés and
| bars per km². centre and radius_m are approximate, hand-placed from
| memory: a circle standing in for the district on the map, not its shape.
|
*/

return [
    'source' => 'PLACEHOLDER: estimates, to be replaced from INE/padrón and OSM in milestone 8',
    'neighbourhoods' => [
        ['name' => 'Casco Histórico', 'population' => 46_000, 'student' => 5, 'tourist' => 10, 'office' => 6, 'transport' => 8, 'competition_density' => 160, 'centre' => [41.6545, -0.88], 'radius_m' => 700],
        ['name' => 'Centro', 'population' => 52_000, 'student' => 5, 'tourist' => 7, 'office' => 9, 'transport' => 10, 'competition_density' => 140, 'centre' => [41.648, -0.884], 'radius_m' => 700],
        ['name' => 'Delicias', 'population' => 105_000, 'student' => 4, 'tourist' => 2, 'office' => 4, 'transport' => 7, 'competition_density' => 70, 'centre' => [41.65, -0.91], 'radius_m' => 1000],
        ['name' => 'Universidad', 'population' => 50_000, 'student' => 10, 'tourist' => 3, 'office' => 5, 'transport' => 8, 'competition_density' => 90, 'centre' => [41.642, -0.899], 'radius_m' => 800],
        ['name' => 'San José', 'population' => 65_000, 'student' => 3, 'tourist' => 1, 'office' => 3, 'transport' => 6, 'competition_density' => 55, 'centre' => [41.644, -0.865], 'radius_m' => 900],
        ['name' => 'Las Fuentes', 'population' => 43_000, 'student' => 3, 'tourist' => 1, 'office' => 2, 'transport' => 5, 'competition_density' => 45, 'centre' => [41.652, -0.856], 'radius_m' => 800],
        ['name' => 'La Almozara', 'population' => 25_000, 'student' => 3, 'tourist' => 2, 'office' => 3, 'transport' => 6, 'competition_density' => 40, 'centre' => [41.662, -0.905], 'radius_m' => 700],
        ['name' => 'Oliver-Valdefierro', 'population' => 31_000, 'student' => 2, 'tourist' => 0, 'office' => 2, 'transport' => 4, 'competition_density' => 25, 'centre' => [41.64, -0.93], 'radius_m' => 1000],
        ['name' => 'Torrero-La Paz', 'population' => 40_000, 'student' => 3, 'tourist' => 1, 'office' => 2, 'transport' => 5, 'competition_density' => 40, 'centre' => [41.63, -0.88], 'radius_m' => 1000],
        ['name' => 'Actur-Rey Fernando', 'population' => 58_000, 'student' => 7, 'tourist' => 2, 'office' => 6, 'transport' => 8, 'competition_density' => 50, 'centre' => [41.673, -0.89], 'radius_m' => 1100],
        ['name' => 'El Rabal', 'population' => 79_000, 'student' => 3, 'tourist' => 2, 'office' => 3, 'transport' => 6, 'competition_density' => 50, 'centre' => [41.666, -0.865], 'radius_m' => 1000],
        ['name' => 'Casablanca', 'population' => 27_000, 'student' => 4, 'tourist' => 1, 'office' => 3, 'transport' => 5, 'competition_density' => 35, 'centre' => [41.625, -0.895], 'radius_m' => 900],
        ['name' => 'Santa Isabel', 'population' => 13_000, 'student' => 2, 'tourist' => 0, 'office' => 1, 'transport' => 3, 'competition_density' => 20, 'centre' => [41.672, -0.835], 'radius_m' => 900],
        ['name' => 'Miralbueno', 'population' => 13_000, 'student' => 2, 'tourist' => 0, 'office' => 2, 'transport' => 3, 'competition_density' => 20, 'centre' => [41.65, -0.94], 'radius_m' => 900],
        ['name' => 'Sur', 'population' => 38_000, 'student' => 3, 'tourist' => 1, 'office' => 3, 'transport' => 5, 'competition_density' => 30, 'centre' => [41.61, -0.92], 'radius_m' => 1300],
    ],
];
