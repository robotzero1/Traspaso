<?php

/*
 * The simulation engine and the market generator must stay pure PHP so
 * they're deterministic and testable without the framework (SPEC.md §4).
 *
 * Note: each rule names one namespace. Passing an array of namespaces to
 * expect() silently checks nothing.
 */

const PURE_NAMESPACES = ['App\Simulation', 'App\Generation'];

const FRAMEWORK_AND_APP_LAYERS = [
    'Illuminate',
    'Laravel',
    'App\Models',
    'App\Http',
    'App\Console',
    'DB',
    'Carbon',
];

const IMPURE_FUNCTIONS = [
    'rand', 'mt_rand', 'random_int', 'random_bytes', 'lcg_value', 'uniqid',
    'array_rand', 'shuffle', 'str_shuffle', 'srand', 'mt_srand',
    'now', 'today', 'time', 'microtime', 'hrtime', 'date', 'getdate', 'mktime',
    'config', 'env', 'app', 'resolve',
    'DateTime', 'DateTimeImmutable',
];

foreach (PURE_NAMESPACES as $namespace) {
    arch("{$namespace} does not depend on Laravel or the app layers")
        ->expect($namespace)
        ->not->toUse(FRAMEWORK_AND_APP_LAYERS);

    arch("{$namespace} takes randomness only from SeededRng and reads no clock or config")
        ->expect($namespace)
        ->not->toUse(IMPURE_FUNCTIONS);
}

arch('the engine does not depend on the generator')
    ->expect('App\Simulation')
    ->not->toUse('App\Generation');

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
