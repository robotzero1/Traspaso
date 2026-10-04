<?php

use App\Simulation\Data\ParameterSheet;

function sheet(): ParameterSheet
{
    return new ParameterSheet(['rent' => ['median' => 72_500, 'share' => 0.5, 'table' => [0 => 1]], 'name' => 'x']);
}

it('reads typed values', function () {
    expect(sheet()->int('rent.median'))->toBe(72_500)
        ->and(sheet()->float('rent.median'))->toBe(72_500.0)
        ->and(sheet()->float('rent.share'))->toBe(0.5)
        ->and(sheet()->array('rent.table'))->toBe([0 => 1])
        ->and(sheet()->get('name'))->toBe('x');
});

it('rejects missing keys and wrong types', function (Closure $read) {
    expect($read)->toThrow(InvalidArgumentException::class);
})->with([
    'missing' => fn () => sheet()->get('rent.p90'),
    'int from float' => fn () => sheet()->int('rent.share'),
    'float from string' => fn () => sheet()->float('name'),
    'array from int' => fn () => sheet()->array('rent.median'),
]);
