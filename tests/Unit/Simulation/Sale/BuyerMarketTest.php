<?php

use App\Simulation\Rng\SeededRng;
use App\Simulation\Sale\BuyerMarket;
use App\Simulation\Sale\BuyerOffer;
use Tests\Support\SimulationFixtures;

/** Offers over a year of days, for one listing. */
function yearOfOffers(int $asking, int $value, bool $agency = false, int $seed = 1): array
{
    $market = new BuyerMarket(SimulationFixtures::sheet());
    $rng = new SeededRng($seed);
    $offers = [];

    for ($day = 1; $day <= 360; $day++) {
        $offers[] = $market->day($asking, $value, $agency, 4, 30, $rng->fork("day-{$day}"));
    }

    return array_values(array_filter($offers));
}

it('brings fewer buyers as the asking price rises above the value, and in August', function () {
    $market = new BuyerMarket(SimulationFixtures::sheet());

    expect($market->buyersPerMonth(5_000_000, 4_000_000, false, 4))->toBeLessThan($market->buyersPerMonth(4_000_000, 4_000_000, false, 4))
        ->and($market->buyersPerMonth(4_000_000, 4_000_000, false, 8))->toBeLessThan($market->buyersPerMonth(4_000_000, 4_000_000, false, 4))
        ->and($market->buyersPerMonth(4_000_000, 4_000_000, true, 4))->toBeGreaterThan($market->buyersPerMonth(4_000_000, 4_000_000, false, 4))
        // Asking far below value doesn't bring endless buyers.
        ->and($market->buyersPerMonth(1_000_000, 4_000_000, false, 4))->toBe(0.4 * 1.5);
});

it('makes offers below the buyer\'s limit and never above asking', function () {
    $offers = yearOfOffers(4_000_000, 4_000_000, agency: true);

    expect($offers)->not->toBeEmpty();

    foreach ($offers as $offer) {
        expect($offer)->toBeInstanceOf(BuyerOffer::class)
            ->and($offer->amountCents)->toBeLessThanOrEqual(4_000_000)
            ->and($offer->amountCents)->toBeLessThanOrEqual($offer->limitCents)
            ->and($offer->limitCents)->toBeGreaterThanOrEqual((int) (4_000_000 * 0.6));
    }
});

it('sells faster through an agency, and plays the same for the same seed', function () {
    expect(count(yearOfOffers(4_000_000, 4_000_000, agency: true)))->toBeGreaterThan(count(yearOfOffers(4_000_000, 4_000_000)))
        ->and(yearOfOffers(4_000_000, 4_000_000, seed: 9))->toEqual(yearOfOffers(4_000_000, 4_000_000, seed: 9));
});

it('takes a counter-offer within the buyer\'s limit', function () {
    $market = new BuyerMarket(SimulationFixtures::sheet());
    $offer = new BuyerOffer(3_500_000, 3_900_000);

    expect($market->acceptsCounter($offer, 3_900_000))->toBeTrue()
        ->and($market->acceptsCounter($offer, 3_950_000))->toBeFalse();
});
