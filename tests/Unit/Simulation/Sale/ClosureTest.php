<?php

use App\Simulation\Sale\Closure;
use Tests\Support\SimulationFixtures;

it('costs two months\' rent and the staff\'s severance, and the equipment sells for scrap', function () {
    $state = SimulationFixtures::state();
    $closure = new Closure(SimulationFixtures::sheet());
    $out = $closure->breakdown($state, 4_000_000, monthsOwned: 12);

    // 20 days a year on 14 payments of €1,300, for 1 year owned + 2 inherited.
    $severance = (int) round($state->staffCount * 130_000 * 14 / 365 * 20 * 3);

    expect($out['notice_cents'])->toBe($state->profile->rentMonthCents * 2)
        ->and($out['severance_cents'])->toBe($severance)
        ->and($out['scrap_cents'])->toBeGreaterThan(0)
        ->and($out['net_cents'])->toBe($out['scrap_cents'] - $out['notice_cents'] - $out['severance_cents'])
        // Longer service costs more.
        ->and($closure->breakdown($state, 4_000_000, monthsOwned: 36)['severance_cents'])->toBeGreaterThan($severance);
});

it('pays a quick sale at half the value, but never below scrap', function () {
    $state = SimulationFixtures::state();
    $closure = new Closure(SimulationFixtures::sheet());

    expect($closure->quickSalePriceCents($state, 4_000_000, 3_000_000))->toBe(1_500_000)
        ->and($closure->quickSalePriceCents($state, 4_000_000, 0))->toBe((int) (round($closure->scrapCents($state, 4_000_000) / 50_000) * 50_000));
});
