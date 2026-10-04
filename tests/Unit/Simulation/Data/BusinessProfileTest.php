<?php

use Tests\Support\SimulationFixtures;

it('adds indoor and terrace seats', function () {
    expect(SimulationFixtures::profile()->totalSeats())->toBe(40);
});

it('rejects impossible premises', function (array $changes) {
    expect(fn () => SimulationFixtures::profile()->with(...$changes))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'zero floor area' => [['floorAreaM2' => 0]],
    'negative indoor seats' => [['indoorSeats' => -1]],
    'negative terrace seats' => [['terraceSeats' => -1]],
    'negative rent' => [['rentMonthCents' => -1]],
    'footfall above 10' => [['footfall' => 10.1]],
    'condition 0' => [['condition' => 0]],
    'condition 11' => [['condition' => 11]],
]);

it('rejects neighbourhood indices outside 0–10', function (array $changes) {
    expect(fn () => SimulationFixtures::neighbourhood()->with(...$changes))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'blank name' => [['name' => ' ']],
    'negative population' => [['population' => -1]],
    'student index' => [['studentIndex' => 11.0]],
    'tourist index' => [['touristIndex' => -1.0]],
    'office index' => [['officeIndex' => 10.5]],
    'transport index' => [['transportIndex' => -0.5]],
    'competition density' => [['competitionDensity' => -1.0]],
]);
