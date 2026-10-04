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
            cashCents: self::STARTING_CAPITAL_CENTS - self::traspaso($rentPercentile) - self::deposit($rentPercentile),
            reputation: 50.0,
            staffCount: self::parameters()['takeover']['staff_count'],
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

    /** The landlord's deposit for the rent at this percentile. */
    public static function deposit(int $percentile): int
    {
        return self::parameters()['rent']['percentiles_cents'][$percentile] * self::parameters()['purchase']['deposit_months_of_rent'];
    }

    /**
     * Five rivals at the given distances, like the five nearest real cafés
     * and bars the game picks.
     *
     * @param  list<float>  $metres
     * @return list<CompetitorState>
     */
    private static function rivalsAt(array $metres): array
    {
        $traits = [[1.0, 55.0, 30], [0.95, 50.0, 40], [1.05, 60.0, 25], [1.0, 50.0, 30], [1.0, 55.0, 35]];

        return array_map(
            fn (int $i) => new CompetitorState('c'.($i + 1), 'Competitor '.($i + 1), $metres[$i], $traits[$i][0], $traits[$i][1], $traits[$i][1], $traits[$i][2]),
            array_keys($metres),
        );
    }

    /**
     * The rivals of a typical spot in the real game: the median distances
     * of the nearest five cafés and bars to footfall-5 points on the
     * Zaragoza surface (measured in the balancing pass).
     *
     * @return list<CompetitorState>
     */
    public static function typicalCompetitors(): array
    {
        return self::rivalsAt([42.0, 76.0, 104.0, 120.0, 134.0]);
    }

    /** @return list<CompetitorState> rivals of a quiet spot (footfall 3–4) */
    public static function quietCompetitors(): array
    {
        return self::rivalsAt([50.0, 118.0, 156.0, 191.0, 222.0]);
    }

    /** @return list<CompetitorState> rivals of a busy spot (footfall 8.5–10) */
    public static function busyCompetitors(): array
    {
        return self::rivalsAt([40.0, 49.0, 65.0, 77.0, 91.0]);
    }

    /** The game's default decisions (the parameter sheet's default_decisions). */
    public static function averageDecisions(): Decisions
    {
        return new Decisions(
            priceLevel: 1.0,
            openDayParts: [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon],
            openDaysPerWeek: 6,
            staffCount: self::parameters()['default_decisions']['staff_count'],
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

    /**
     * Lean staffing, fair prices, little marketing. Open through the day:
     * one employee costs the same however long the café opens, so cutting
     * hours only cuts revenue (seen in the balancing pass).
     */
    public static function goodDecisions(): Decisions
    {
        return new Decisions(
            priceLevel: 1.0,
            openDayParts: [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon],
            openDaysPerWeek: 6,
            staffCount: 1,
            marketingSpendCents: 5_000,
            qualityTier: QualityTier::Standard,
        );
    }

    public static function average(int $seed = 1): BalanceGame
    {
        return self::game(self::business(self::averageNeighbourhood(), 5.0, 50), 50, $seed, self::typicalCompetitors());
    }

    public static function greatLocation(int $seed = 1): BalanceGame
    {
        return self::game(self::business(self::busyNeighbourhood(), 9.0, 90, 8), 90, $seed, self::busyCompetitors());
    }

    public static function mediocreLocation(int $seed = 1): BalanceGame
    {
        return self::game(self::business(self::quietNeighbourhood(), 3.5, 25), 25, $seed, self::quietCompetitors());
    }

    /** @param list<CompetitorState> $competitors */
    private static function game(BusinessState $start, int $percentile, int $seed, array $competitors): BalanceGame
    {
        return new BalanceGame(
            start: $start,
            startingCapitalCents: self::STARTING_CAPITAL_CENTS,
            traspasoCents: self::traspaso($percentile),
            depositCents: self::deposit($percentile),
            competitors: $competitors,
            parameters: self::parameters(),
            seed: $seed,
        );
    }
}
