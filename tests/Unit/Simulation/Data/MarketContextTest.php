<?php

use App\Simulation\Data\MarketContext;
use Tests\Support\SimulationFixtures;

function marketContext(array $overrides = []): MarketContext
{
    return new MarketContext(...array_merge([
        'calendarMonth' => 10,
        'gameMonth' => 1,
        'competitors' => [SimulationFixtures::competitor()],
        'parameters' => ['rent' => ['median' => 72_500], 'iva' => 0.10, 'empty' => null],
    ], $overrides));
}

it('looks up parameters with dot notation', function () {
    $context = marketContext();

    expect($context->parameter('rent.median'))->toBe(72_500)
        ->and($context->parameter('iva'))->toBe(0.10)
        ->and($context->parameter('rent'))->toBe(['median' => 72_500])
        ->and($context->parameter('empty'))->toBeNull();
});

it('throws for a missing parameter', function (string $key) {
    expect(fn () => marketContext()->parameter($key))
        ->toThrow(InvalidArgumentException::class, "Market parameter [{$key}] is not defined.");
})->with(['rent.p90', 'missing', 'iva.rate']);

it('rejects invalid months', function (array $overrides) {
    expect(fn () => marketContext($overrides))->toThrow(InvalidArgumentException::class);
})->with([
    'calendar month 0' => [['calendarMonth' => 0]],
    'calendar month 13' => [['calendarMonth' => 13]],
    'game month 0' => [['gameMonth' => 0]],
    'game month 13' => [['gameMonth' => 13]],
]);

it('only accepts a list of competitors', function (array $competitors) {
    expect(fn () => marketContext(['competitors' => $competitors]))->toThrow(InvalidArgumentException::class);
})->with([
    'wrong type' => [['not a competitor']],
    'keyed array' => [['a' => SimulationFixtures::competitor()]],
]);
