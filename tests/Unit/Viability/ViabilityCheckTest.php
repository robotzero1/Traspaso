<?php

use App\Generation\Location;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Kitchen;
use App\Simulation\Data\Licence;
use App\Simulation\Data\QualityTier;
use App\Viability\ViabilityCheck;
use App\Viability\ViabilityInput;
use Tests\Support\SimulationFixtures;

function viabilityInput(array $overrides = []): ViabilityInput
{
    return new ViabilityInput(...array_merge([
        'lat' => 41.65, 'lng' => -0.88, 'traspasoCents' => 3_000_000, 'rentMonthCents' => 90_000,
        'floorAreaM2' => 60, 'indoorSeats' => 30, 'terraceSeats' => 0, 'licence' => Licence::CafeBar,
        'kitchen' => Kitchen::Basic, 'condition' => 6, 'capitalCents' => 5_000_000,
        'openDayParts' => [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon], 'staffCount' => 1,
        'qualityTier' => QualityTier::Standard,
    ], $overrides));
}

function viabilityPoint(float $footfall = 5.0): Location
{
    return new Location(41.65, -0.88, $footfall, array_fill_keys(array_map(fn (DayPart $p) => $p->value, DayPart::cases()), $footfall), 'main_street');
}

/** Real cafés: one right at the pin (the café itself), two close by, one far. */
function viabilityPlaces(): array
{
    $at = fn (string $key, float $metres) => ['key' => $key, 'lat' => 41.65 + $metres / 111_195, 'lng' => -0.88];

    return [$at('self', 5), $at('near-1', 60), $at('near-2', 120), $at('far', 900)];
}

function runCheck(array $input = [], float $footfall = 5.0, int $runs = 20): array
{
    return (new ViabilityCheck(SimulationFixtures::parameters(), viabilityPlaces()))
        ->run(viabilityInput($input), viabilityPoint($footfall), SimulationFixtures::neighbourhood(), 0.5, $runs, 3, 4, 42);
}

it('plays the futures and sums them up, the same way for the same seed', function () {
    $results = runCheck();

    expect($results)->toBe(runCheck())
        ->and($results['runs'])->toBe(20)
        ->and(array_keys($results['open']))->toBe([1, 2, 3])
        ->and($results['profit_by_year'])->toHaveCount(3)
        ->and($results['cash_after_purchase_cents'])->toBe(5_000_000 - 3_000_000 - 2 * 90_000);

    expect($results['open'][2])->toBeLessThanOrEqual($results['open'][1])
        ->and($results['open'][3])->toBeLessThanOrEqual($results['open'][2]);
});

it('counts the real cafés nearby, but not the one at the pin', function () {
    $spot = runCheck(runs: 2)['spot'];

    expect($spot['rivals_within_150m'])->toBe(2)
        ->and($spot['rivals_within_500m'])->toBe(2)
        ->and($spot['footfall'])->toBe(5.0);
});

it('does better on a busier street', function () {
    expect(runCheck(footfall: 9.0)['sales_year_1_cents'])->toBeGreaterThan(runCheck(footfall: 2.0)['sales_year_1_cents']);
});

it('flags a quiet spot, dear rent and little cash left', function () {
    $risks = array_column(runCheck(['rentMonthCents' => 400_000, 'capitalCents' => 3_900_000], footfall: 2.0, runs: 5)['risks'], 'type');

    expect($risks)->toContain('quiet_spot', 'rent_share', 'thin_cash');
});

it('says when the traspaso is earned back', function () {
    $cheap = runCheck(['traspasoCents' => 0])['payback'];

    expect($cheap['share'])->toBeGreaterThan(0.0)
        ->and($cheap['median_months'] === null || $cheap['median_months'] >= 1)->toBeTrue();
});

it('reports what the café would sell for at the end, and the owner\'s total return', function () {
    $resale = runCheck(footfall: 8.0)['resale'];

    expect($resale['year'])->toBe(3)
        ->and($resale['value_cents']['p10'])->toBeLessThanOrEqual($resale['value_cents']['median'])
        ->and($resale['value_cents']['median'])->toBeLessThanOrEqual($resale['value_cents']['p90'])
        // After the gestoría and tax, never more than the price.
        ->and($resale['net_cents']['median'])->toBeLessThan($resale['value_cents']['median'])
        ->and($resale['total_return_cents']['p10'])->toBeLessThanOrEqual($resale['total_return_cents']['p90'])
        ->and($resale['ahead'])->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
});
