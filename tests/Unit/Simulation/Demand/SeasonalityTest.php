<?php

use App\Simulation\Data\ParameterSheet;
use App\Simulation\Demand\Seasonality;
use Tests\Support\SimulationFixtures;

it('reads the demand multiplier for the calendar month', function (int $month) {
    $factors = (new Seasonality(SimulationFixtures::sheet()))->forMonth($month, 6);

    expect($factors->demandMultiplier)->toBe((float) SimulationFixtures::parameters()['seasonality']['multipliers'][$month]);
})->with(range(1, 12));

it('counts open days from days per week', function (int $month, int $daysPerWeek, int $days, int $openDays) {
    $factors = (new Seasonality(SimulationFixtures::sheet()))->forMonth($month, $daysPerWeek);

    expect($factors->daysInMonth)->toBe($days)
        ->and($factors->openDays)->toBe($openDays);
})->with([
    'January, every day' => [1, 7, 31, 31],
    'January, 6 days' => [1, 6, 31, 27],
    'February, 5 days' => [2, 5, 28, 20],
    'April, 1 day' => [4, 1, 30, 4],
]);

it('turns terrace days into a usable share', function () {
    $parameters = SimulationFixtures::parameters();
    $parameters['terrace_usable_days']['days'][6] = 15;
    $parameters['terrace_usable_days']['days'][7] = 40;

    $seasonality = new Seasonality(new ParameterSheet($parameters));

    expect($seasonality->forMonth(6, 7)->terraceUsableShare)->toBe(0.5)
        ->and($seasonality->forMonth(7, 7)->terraceUsableShare)->toBe(1.0);
});

it('makes October busier than August', function () {
    $seasonality = new Seasonality(SimulationFixtures::sheet());

    expect($seasonality->forMonth(10, 6)->demandMultiplier)
        ->toBeGreaterThan($seasonality->forMonth(8, 6)->demandMultiplier);
});
