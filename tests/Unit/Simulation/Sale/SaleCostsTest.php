<?php

use App\Simulation\Sale\SaleCosts;
use Tests\Support\SimulationFixtures;

it('taxes the gain at the savings scale, bracket by bracket', function () {
    $costs = new SaleCosts(SimulationFixtures::sheet());

    expect($costs->tax(-500_000))->toBe(0)
        ->and($costs->tax(0))->toBe(0)
        ->and($costs->tax(600_000))->toBe(114_000)                       // 19% of €6,000
        ->and($costs->tax(1_000_000))->toBe(114_000 + 84_000)            // + 21% of €4,000
        ->and($costs->tax(40_000_000))->toBe((int) round(114_000 + 4_400_000 * 0.21 + 15_000_000 * 0.23 + 10_000_000 * 0.27 + 10_000_000 * 0.30));
});

it('takes commission, the gestoría and the tax from the price', function () {
    $costs = new SaleCosts(SimulationFixtures::sheet());

    $private = $costs->breakdown(5_000_000, 4_000_000, agency: false);
    expect($private)->toMatchArray(['commission_cents' => 0, 'gestoria_cents' => 80_000, 'gain_cents' => 920_000])
        ->and($private['tax_cents'])->toBe($costs->tax(920_000))
        ->and($private['net_cents'])->toBe(5_000_000 - 80_000 - $costs->tax(920_000));

    $agency = $costs->breakdown(5_000_000, 4_000_000, agency: true);
    expect($agency['commission_cents'])->toBe(400_000)
        ->and($agency['net_cents'])->toBeLessThan($private['net_cents']);

    // The minimum commission, and a sale at a loss pays no tax.
    $loss = $costs->breakdown(2_000_000, 4_000_000, agency: true);
    expect($loss['commission_cents'])->toBe(300_000)
        ->and($loss['tax_cents'])->toBe(0)
        ->and($loss['net_cents'])->toBe(2_000_000 - 300_000 - 80_000);
});
