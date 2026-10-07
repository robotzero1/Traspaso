<?php

/*
| The real-time clock (SPEC §11). Economic values stay in config/market.
*/

return [

    // The day's results are simulated and stored every night at this time.
    'timezone' => 'Europe/Madrid',
    'nightly_at' => '23:00',

    // The "fast-forward to month end" button, for trying the game out
    // without waiting for real days. Off in production.
    'fast_forward' => (bool) env('GAME_FAST_FORWARD', env('APP_ENV', 'production') !== 'production'),

];
