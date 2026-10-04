<?php

namespace Tests\Support;

use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\BusinessState;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\CostBreakdown;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\Kitchen;
use App\Simulation\Data\Licence;
use App\Simulation\Data\MarketContext;
use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Data\ParameterSheet;
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

    /**
     * A spread of made-up neighbourhoods, from a quiet residential area to
     * a busy old town.
     *
     * @return list<NeighbourhoodProfile>
     */
    public static function neighbourhoods(): array
    {
        return [
            new NeighbourhoodProfile('Residential', 90_000, 2.0, 1.0, 1.0, 4.0, 20.0),
            new NeighbourhoodProfile('Campus', 50_000, 9.0, 2.0, 3.0, 7.0, 60.0),
            new NeighbourhoodProfile('Old town', 40_000, 4.0, 9.0, 6.0, 8.0, 150.0),
            new NeighbourhoodProfile('Business district', 30_000, 2.0, 3.0, 9.0, 9.0, 90.0),
        ];
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
            openDayParts: [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon],
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

    /** The real parameter sheet, read without booting Laravel. */
    public static function parameters(): array
    {
        return require dirname(__DIR__, 2).'/config/market/zaragoza_cafe.php';
    }

    public static function sheet(): ParameterSheet
    {
        return new ParameterSheet(self::parameters());
    }

    public static function context(int $calendarMonth = 4, array $competitors = []): MarketContext
    {
        return new MarketContext($calendarMonth, 1, $competitors, self::parameters());
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
