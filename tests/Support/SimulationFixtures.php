<?php

namespace Tests\Support;

use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\BusinessState;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\CostBreakdown;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\Kitchen;
use App\Simulation\Data\Licence;
use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Data\QualityTier;

/**
 * Valid, unremarkable DTOs for tests. Override what a test cares about
 * with ->with(...).
 */
final class SimulationFixtures
{
    public static function neighbourhood(): NeighbourhoodProfile
    {
        return new NeighbourhoodProfile(
            name: 'Delicias',
            population: 100_000,
            studentIndex: 4.0,
            touristIndex: 2.0,
            officeIndex: 3.0,
            transportIndex: 6.0,
            competitionDensity: 40.0,
        );
    }

    public static function profile(): BusinessProfile
    {
        return new BusinessProfile(
            category: BusinessCategory::Cafe,
            licence: Licence::Cafe,
            kitchen: Kitchen::Basic,
            neighbourhood: self::neighbourhood(),
            floorAreaM2: 55,
            indoorSeats: 28,
            terraceSeats: 12,
            rentMonthCents: 72_500,
            footfall: 5.0,
            condition: 6,
        );
    }

    public static function state(): BusinessState
    {
        return new BusinessState(
            profile: self::profile(),
            cashCents: 2_000_000,
            reputation: 50.0,
            staffCount: 2,
            staffMorale: 70.0,
            equipmentHealth: 80.0,
            equipmentAgeMonths: 48,
            stockQuality: 60.0,
        );
    }

    public static function decisions(): Decisions
    {
        return new Decisions(
            priceLevel: 1.0,
            openingHoursPerDay: 12,
            openDaysPerWeek: 6,
            staffCount: 2,
            marketingSpendCents: 10_000,
            qualityTier: QualityTier::Standard,
        );
    }

    public static function competitor(string $id = 'c1'): CompetitorState
    {
        return new CompetitorState(
            id: $id,
            name: 'Café Ficticio',
            distanceMetres: 150.0,
            priceLevel: 1.0,
            quality: 55.0,
            reputation: 60.0,
            seats: 30,
        );
    }

    public static function costs(): CostBreakdown
    {
        return new CostBreakdown(
            cogsCents: 300_000,
            staffCents: 450_000,
            rentCents: 72_500,
            utilitiesCents: 35_000,
            marketingCents: 10_000,
            otherCents: 40_000,
            taxesCents: 0,
        );
    }
}
