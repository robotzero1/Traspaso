<?php

/*
 * The simulation engine must stay pure PHP so it's deterministic and
 * testable without the framework (see SPEC.md §4).
 */

arch('the simulation does not depend on Laravel or the app layers')
    ->expect('App\Simulation')
    ->not->toUse([
        'Illuminate',
        'Laravel',
        'App\Models',
        'App\Http',
        'App\Generation',
        'DB',
        'Carbon',
    ]);

arch('the simulation takes randomness only from SeededRng and no clock')
    ->expect('App\Simulation')
    ->not->toUse([
        'rand', 'mt_rand', 'random_int', 'random_bytes', 'lcg_value', 'uniqid',
        'array_rand', 'shuffle', 'str_shuffle', 'srand', 'mt_srand',
        'now', 'today', 'time', 'microtime', 'hrtime', 'date', 'getdate', 'mktime',
        'config', 'env', 'app', 'resolve',
        'DateTime', 'DateTimeImmutable',
    ]);

arch('only SeededRng touches PHP\'s random engines')
    ->expect('Random')
    ->toOnlyBeUsedIn('App\Simulation\Rng');

arch('simulation data objects are immutable')
    ->expect('App\Simulation\Data')
    ->classes()
    ->toBeReadonly()
    ->ignoring('App\Simulation\Data\Concerns');

arch('simulation data objects are final')
    ->expect('App\Simulation\Data')
    ->classes()
    ->toBeFinal()
    ->ignoring('App\Simulation\Data\Concerns');
