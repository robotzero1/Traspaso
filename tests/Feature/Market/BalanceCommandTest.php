<?php

use Database\Seeders\FootfallPointSeeder;
use Database\Seeders\NeighbourhoodSeeder;
use Database\Seeders\PointOfInterestSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->seed([NeighbourhoodSeeder::class, PointOfInterestSeeder::class, FootfallPointSeeder::class]);
});

it('plays games for each strategy and reports the distributions and targets', function () {
    $this->artisan('market:balance', ['--games' => 3])
        ->expectsOutputToContain('3 games × 5 strategies')
        ->expectsOutputToContain('Thoughtful player by district')
        ->expectsOutputToContain('Typical new owner (default settings): 20–25% fail in year 1')
        ->assertSuccessful();
});

it('runs only the strategies asked for and writes every outcome as JSON', function () {
    $path = storage_path('framework/testing/balance-'.uniqid().'.json');

    $this->artisan('market:balance', ['--games' => 2, '--strategy' => ['careless'], '--json' => $path])->assertSuccessful();

    $outcomes = json_decode(File::get($path), true);
    File::delete($path);

    expect($outcomes)->toHaveCount(2)
        ->and(array_unique(array_column($outcomes, 'strategy')))->toBe(['careless'])
        ->and($outcomes[0])->toHaveKeys(['seed', 'neighbourhood', 'footfall', 'netWorthCents', 'change']);
});

it('refuses an unknown market', function () {
    $this->artisan('market:balance', ['--market' => 'atlantis'])->assertFailed();
});
