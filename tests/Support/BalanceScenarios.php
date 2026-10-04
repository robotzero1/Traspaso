<?php

namespace Tests\Support;

use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\BusinessState;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\Kitchen;
use App\Simulation\Data\Licence;
use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Data\QualityTier;

/**
 * The businesses, decisions and markets the SPEC §6 balance tests use.
 * Rents and traspasos come from the parameter sheet's percentiles, so the
 * scenarios follow the sheet when it's updated.
 */
final class BalanceScenarios
{
    public const STARTING_CAPITAL_CENTS = 5_000_000;

    /** @return array<string, mixed> */
    public static function parameters(): array
    {
        return require dirname(__DIR__, 2).'/config/market/zaragoza_cafe.php';
    }

    public static function averageNeighbourhood(): NeighbourhoodProfile
    {
        return new NeighbourhoodProfile('Average', 60_000, 5.0, 5.0, 5.0, 5.0, 50.0);
    }

    public static function busyNeighbourhood(): NeighbourhoodProfile
    {
        return new NeighbourhoodProfile('Busy', 80_000, 7.0, 9.0, 8.0, 9.0, 120.0);
    }

    public static function quietNeighbourhood(): NeighbourhoodProfile
    {
        return new NeighbourhoodProfile('Quiet', 45_000, 3.0, 2.0, 3.0, 4.0, 30.0);
    }

    public static function business(NeighbourhoodProfile $neighbourhood, float $footfall, int $rentPercentile, int $condition = 6): BusinessState
    {
        return new BusinessState(
            profile: new BusinessProfile(
                category: BusinessCategory::Cafe,
                licence: Licence::Cafe,
                kitchen: Kitchen::Basic,
                neighbourhood: $neighbourhood,
                floorAreaM2: 55,
                indoorSeats: 28,
                terraceSeats: 12,
                rentMonthCents: self::parameters()['rent']['percentiles_cents'][$rentPercentile],
                footfall: $footfall,
                condition: $condition,
            ),
            cashCents: self::STARTING_CAPITAL_CENTS - self::traspaso($rentPercentile),
            reputation: 50.0,
            staffCount: 2,
            staffMorale: 70.0,
            equipmentHealth: 80.0,
            equipmentAgeMonths: 60,
            stockQuality: 55.0,
        );
    }

    /** The traspaso at the same percentile as the rent. */
    public static function traspaso(int $percentile): int
    {
        return self::parameters()['traspaso']['percentiles_cents'][$percentile];
    }

    /** @return list<CompetitorState> */
    public static function typicalCompetitors(): array
    {
        return [
            new CompetitorState('c1', 'Competitor 1', 120.0, 1.0, 55.0, 55.0, 30),
            new CompetitorState('c2', 'Competitor 2', 200.0, 0.95, 50.0, 50.0, 40),
            new CompetitorState('c3', 'Competitor 3', 300.0, 1.05, 60.0, 60.0, 25),
        ];
    }

    public static function averageDecisions(): Decisions
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

    /** Overpriced, cheap stock, overstaffed, open all hours, big ad spend. */
    public static function badDecisions(): Decisions
    {
        return new Decisions(
            priceLevel: 1.5,
            openDayParts: [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon, DayPart::Evening],
            openDaysPerWeek: 7,
            staffCount: 6,
            marketingSpendCents: 200_000,
            qualityTier: QualityTier::Budget,
        );
    }

    /** Lean staffing, the hours that suit a quiet area, fair prices. */
    public static function goodDecisions(): Decisions
    {
        return new Decisions(
            priceLevel: 1.0,
            openDayParts: [DayPart::Morning, DayPart::Lunch],
            openDaysPerWeek: 6,
            staffCount: 1,
            marketingSpendCents: 5_000,
            qualityTier: QualityTier::Standard,
        );
    }

    public static function average(int $seed = 1): BalanceGame
    {
        return self::game(self::business(self::averageNeighbourhood(), 5.0, 50), 50, $seed);
    }

    public static function greatLocation(int $seed = 1): BalanceGame
    {
        return self::game(self::business(self::busyNeighbourhood(), 9.0, 90, 8), 90, $seed);
    }

    public static function mediocreLocation(int $seed = 1): BalanceGame
    {
        return self::game(self::business(self::quietNeighbourhood(), 3.5, 25), 25, $seed);
    }

    private static function game(BusinessState $start, int $percentile, int $seed): BalanceGame
    {
        return new BalanceGame(
            start: $start,
            startingCapitalCents: self::STARTING_CAPITAL_CENTS,
            traspasoCents: self::traspaso($percentile),
            competitors: self::typicalCompetitors(),
            parameters: self::parameters(),
            seed: $seed,
        );
    }
}
