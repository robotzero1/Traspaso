<?php

use App\Simulation\Data\DayPart;
use App\Simulation\Data\Kitchen;
use App\Simulation\Data\QualityTier;
use App\Simulation\Demand\Revenue;
use Tests\Support\SimulationFixtures;

function ticket(DayPart $part, array $decisions = [], Kitchen $kitchen = Kitchen::Basic): float
{
    return (new Revenue(SimulationFixtures::sheet()))->ticketCents(
        SimulationFixtures::profile()->with(kitchen: $kitchen),
        $part,
        SimulationFixtures::decisions()->with(...$decisions),
    );
}

it('puts a standard business mid-range at the local average price', function () {
    $range = SimulationFixtures::parameters()['average_ticket_cents']['morning'];

    expect(ticket(DayPart::Morning))->toEqualWithDelta(($range['min'] + $range['max']) / 2, 1e-9);
});

it('scales the ticket with the price level', function () {
    expect(ticket(DayPart::Morning, ['priceLevel' => 1.2]))->toEqualWithDelta(ticket(DayPart::Morning) * 1.2, 1e-9);
});

it('charges more for premium and less for budget', function () {
    expect(ticket(DayPart::Afternoon, ['qualityTier' => QualityTier::Premium]))
        ->toBeGreaterThan(ticket(DayPart::Afternoon))
        ->and(ticket(DayPart::Afternoon, ['qualityTier' => QualityTier::Budget]))
        ->toBeLessThan(ticket(DayPart::Afternoon));
});

it('lets a kitchen lift lunch and evening tickets only', function () {
    expect(ticket(DayPart::Lunch, kitchen: Kitchen::Full))->toBeGreaterThan(ticket(DayPart::Lunch, kitchen: Kitchen::None))
        ->and(ticket(DayPart::Evening, kitchen: Kitchen::Full))->toBeGreaterThan(ticket(DayPart::Evening, kitchen: Kitchen::None))
        ->and(ticket(DayPart::Morning, kitchen: Kitchen::Full))->toBe(ticket(DayPart::Morning, kitchen: Kitchen::None));
});

it('stays within the configured range before the price level', function () {
    $range = SimulationFixtures::parameters()['average_ticket_cents']['lunch'];

    expect(ticket(DayPart::Lunch, ['qualityTier' => QualityTier::Premium], Kitchen::Full))->toBeLessThanOrEqual($range['max'])
        ->and(ticket(DayPart::Lunch, ['qualityTier' => QualityTier::Budget], Kitchen::None))->toBeGreaterThanOrEqual($range['min']);
});

it('reports revenue net of IVA, in whole cents', function () {
    $iva = SimulationFixtures::parameters()['iva']['rate'];

    expect((new Revenue(SimulationFixtures::sheet()))->netRevenueCents(100, 440.0))
        ->toBe((int) round(100 * 440 / (1 + $iva)));
});
