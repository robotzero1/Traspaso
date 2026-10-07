<?php

/*
| The standalone viability check (SPEC §11, milestone 14).
*/

return [
    'market' => 'zaragoza_cafe',
    // How many futures, and how long each runs.
    'runs' => (int) env('VIABILITY_RUNS', 1000),
    'years' => 5,
    // The pin must be this close to a commercial street point.
    'max_point_metres' => 150,
    // Show the full report without payment (payments come in milestone 15).
    // Never on in production.
    'unlock_all' => (bool) env('VIABILITY_UNLOCK_ALL', false),
];
