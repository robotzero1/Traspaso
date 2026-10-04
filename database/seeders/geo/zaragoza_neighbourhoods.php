<?php

/*
|--------------------------------------------------------------------------
| Zaragoza's urban districts (juntas municipales)
|--------------------------------------------------------------------------
|
| ⚠️ PLACEHOLDER DATA. The district names are real; every number is a
| rough estimate, to be replaced in milestone 8 with population from the
| INE / municipal padrón and indices derived from OSM extracts (SPEC §5).
| Indices run 0–10; competition_density is cafés and bars per km².
|
*/

return [
    'source' => 'PLACEHOLDER: estimates, to be replaced from INE/padrón and OSM in milestone 8',
    'neighbourhoods' => [
        ['name' => 'Casco Histórico', 'population' => 46_000, 'student' => 5, 'tourist' => 10, 'office' => 6, 'transport' => 8, 'competition_density' => 160],
        ['name' => 'Centro', 'population' => 52_000, 'student' => 5, 'tourist' => 7, 'office' => 9, 'transport' => 10, 'competition_density' => 140],
        ['name' => 'Delicias', 'population' => 105_000, 'student' => 4, 'tourist' => 2, 'office' => 4, 'transport' => 7, 'competition_density' => 70],
        ['name' => 'Universidad', 'population' => 50_000, 'student' => 10, 'tourist' => 3, 'office' => 5, 'transport' => 8, 'competition_density' => 90],
        ['name' => 'San José', 'population' => 65_000, 'student' => 3, 'tourist' => 1, 'office' => 3, 'transport' => 6, 'competition_density' => 55],
        ['name' => 'Las Fuentes', 'population' => 43_000, 'student' => 3, 'tourist' => 1, 'office' => 2, 'transport' => 5, 'competition_density' => 45],
        ['name' => 'La Almozara', 'population' => 25_000, 'student' => 3, 'tourist' => 2, 'office' => 3, 'transport' => 6, 'competition_density' => 40],
        ['name' => 'Oliver-Valdefierro', 'population' => 31_000, 'student' => 2, 'tourist' => 0, 'office' => 2, 'transport' => 4, 'competition_density' => 25],
        ['name' => 'Torrero-La Paz', 'population' => 40_000, 'student' => 3, 'tourist' => 1, 'office' => 2, 'transport' => 5, 'competition_density' => 40],
        ['name' => 'Actur-Rey Fernando', 'population' => 58_000, 'student' => 7, 'tourist' => 2, 'office' => 6, 'transport' => 8, 'competition_density' => 50],
        ['name' => 'El Rabal', 'population' => 79_000, 'student' => 3, 'tourist' => 2, 'office' => 3, 'transport' => 6, 'competition_density' => 50],
        ['name' => 'Casablanca', 'population' => 27_000, 'student' => 4, 'tourist' => 1, 'office' => 3, 'transport' => 5, 'competition_density' => 35],
        ['name' => 'Santa Isabel', 'population' => 13_000, 'student' => 2, 'tourist' => 0, 'office' => 1, 'transport' => 3, 'competition_density' => 20],
        ['name' => 'Miralbueno', 'population' => 13_000, 'student' => 2, 'tourist' => 0, 'office' => 2, 'transport' => 3, 'competition_density' => 20],
        ['name' => 'Sur', 'population' => 38_000, 'student' => 3, 'tourist' => 1, 'office' => 3, 'transport' => 5, 'competition_density' => 30],
    ],
];
