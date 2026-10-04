<?php

use App\Generation\NameDrawer;
use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Rng\SeededRng;

it('combines a category prefix with a name', function () {
    $drawer = new NameDrawer(new ParameterSheet(['names' => [
        'prefixes' => ['cafe' => ['Café'], 'cafe_bar' => ['Bar']],
        'names' => ['El Cierzo'],
    ]]), new SeededRng(1));

    expect($drawer->draw(BusinessCategory::Cafe))->toBe('Café El Cierzo')
        ->and($drawer->draw(BusinessCategory::CafeBar))->toBe('Bar El Cierzo');
});

it('numbers names once every combination is used', function () {
    $drawer = new NameDrawer(new ParameterSheet(['names' => [
        'prefixes' => ['cafe' => ['Café'], 'cafe_bar' => ['Bar']],
        'names' => ['El Cierzo'],
    ]]), new SeededRng(1));

    $names = array_map(fn () => $drawer->draw(BusinessCategory::Cafe), range(1, 4));

    expect($names)->toBe(['Café El Cierzo', 'Café El Cierzo 2', 'Café El Cierzo 3', 'Café El Cierzo 4']);
});
