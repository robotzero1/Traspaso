<?php

use App\Simulation\Data\ParameterSheet;
use App\Simulation\Sale\BuyingCosts;
use Tests\Support\SimulationFixtures;

function buyingCosts(array $purchase = []): BuyingCosts
{
    $parameters = SimulationFixtures::parameters();
    $parameters['purchase'] = array_replace_recursive($parameters['purchase'], $purchase);

    return new BuyingCosts(new ParameterSheet($parameters));
}

it('adds the deposit, the guarantee and the fees to the traspaso', function () {
    $costs = buyingCosts([
        'deposit_months_of_rent' => 2,
        'guarantee_months_of_rent' => 1,
        'legal_fees' => ['base_cents' => 50_000, 'share_of_traspaso' => 0.02],
        'licence_change' => ['fee_cents' => 10_000, 'technical_report_cents' => 30_000, 'paperwork_cents' => 5_000],
    ]);

    expect($costs->breakdown(4_000_000, 150_000))->toBe([
        'deposit_cents' => 300_000,
        'guarantee_cents' => 150_000,
        'held_cents' => 450_000,
        'legal_cents' => 50_000 + 80_000,
        'licence_cents' => 45_000,
        'fees_cents' => 175_000,
        'cash_needed_cents' => 4_000_000 + 450_000 + 175_000,
    ]);
});

it('holds rent-based amounts and spends price-based fees', function () {
    $costs = buyingCosts();
    $all = $costs->breakdown(3_000_000, 120_000);

    expect($costs->heldCents(120_000))->toBe($all['held_cents'])
        ->and($costs->feesCents(3_000_000))->toBe($all['fees_cents'])
        ->and($costs->cashNeededCents(3_000_000, 120_000))->toBe($all['cash_needed_cents'])
        // A dearer traspaso costs more in legal fees, not in deposits.
        ->and($costs->feesCents(6_000_000))->toBeGreaterThan($all['fees_cents'])
        ->and($costs->heldCents(240_000))->toBe(2 * $all['held_cents']);
});
