<?php

use App\Generation\BusinessGenerator;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Licence;
use App\Simulation\Rng\SeededRng;
use Tests\Support\SimulationFixtures;

it('loads the Zaragoza café sheet through Laravel config', function () {
    expect(config('market.zaragoza_cafe.city'))->toBe('Zaragoza');
});

it('gives every section a source', function () {
    foreach (config('market.zaragoza_cafe') as $section => $values) {
        if (! is_array($values)) {
            continue;
        }

        expect($values)->toHaveKey('source')
            ->and($values['source'])->toBeString()->not->toBeEmpty();
    }
});

it('stores money as integer cents', function () {
    $walk = function (array $values, string $path) use (&$walk) {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $walk($value, "{$path}.{$key}");
            } elseif ($key !== 'source' && str_contains("{$path}.{$key}", '_cents') && $value !== null) {
                expect($value)->toBeInt("{$path}.{$key} should be integer cents");
            }
        }
    };

    $walk(config('market.zaragoza_cafe'), 'zaragoza_cafe');
});

it('keeps day parts in line with the engine and licences', function () {
    $sheet = config('market.zaragoza_cafe');
    $dayParts = array_map(fn ($part) => $part->value, DayPart::cases());

    expect(array_keys(array_diff_key($sheet['day_parts'], ['source' => true])))->toEqualCanonicalizing($dayParts)
        ->and(array_keys(array_diff_key($sheet['average_ticket_cents'], ['source' => true])))->toEqualCanonicalizing($dayParts);

    foreach (Licence::cases() as $licence) {
        expect($sheet['licence_day_parts'])->toHaveKey($licence->value)
            ->and(array_diff($sheet['licence_day_parts'][$licence->value], $dayParts))->toBe([]);
    }

    foreach (array_diff_key($sheet['day_parts'], ['source' => true]) as $part => $config) {
        expect(array_sum($config['demand_mix']))->toEqualWithDelta(1.0, 1e-9, "{$part} demand mix")
            ->and($config['end_hour'])->toBeGreaterThan($config['start_hour']);
    }
});

it('covers all twelve months', function () {
    expect(array_keys(config('market.zaragoza_cafe.seasonality.multipliers')))->toBe(range(1, 12))
        ->and(array_keys(config('market.zaragoza_cafe.terrace_usable_days.days')))->toBe(range(1, 12));
});

it('resolves a generator configured for Zaragoza from the container', function () {
    $market = app(BusinessGenerator::class)->generate(new SeededRng(1), SimulationFixtures::neighbourhoods());

    expect(count($market))->toBeGreaterThanOrEqual(100)->toBeLessThanOrEqual(200);
});

it('lists sections that are still placeholders', function () {
    $this->artisan('market:placeholders')
        ->expectsOutputToContain('still hold placeholder values')
        ->expectsOutputToContain('manual sample of ~100 listings')
        ->assertSuccessful();
});

it('reports when every section is verified', function () {
    config(['market.verified_test' => ['rent' => ['source' => 'Sample of 112 listings, May 2026']]]);

    $this->artisan('market:placeholders verified_test')
        ->expectsOutputToContain('has a verified source')
        ->assertSuccessful();
});

it('fails for an unknown market', function () {
    $this->artisan('market:placeholders atlantis')->assertFailed();
});
