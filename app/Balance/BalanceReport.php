<?php

namespace App\Balance;

/**
 * Summaries of many balance games: the outcome distribution per strategy,
 * and how the thoughtful player fares by district and by footfall.
 */
final readonly class BalanceReport
{
    /** @param list<GameOutcome> $outcomes */
    public function __construct(public array $outcomes) {}

    /** @return array<string, list<GameOutcome>> */
    public function byStrategy(): array
    {
        $by = [];

        foreach ($this->outcomes as $outcome) {
            $by[$outcome->strategy][] = $outcome;
        }

        return $by;
    }

    /**
     * @param  list<GameOutcome>  $outcomes
     * @return array{games: int, bought: int, median: float, p10: float, p25: float, p75: float, p90: float, gained: float, bankrupt: float, rivals: float}
     */
    public static function summary(array $outcomes): array
    {
        $bought = array_values(array_filter($outcomes, fn (GameOutcome $o) => $o->bought));
        $changes = array_map(fn (GameOutcome $o) => $o->change(), $bought);
        sort($changes);
        $n = count($bought);

        return [
            'games' => count($outcomes),
            'bought' => $n,
            'median' => self::percentile($changes, 50),
            'p10' => self::percentile($changes, 10),
            'p25' => self::percentile($changes, 25),
            'p75' => self::percentile($changes, 75),
            'p90' => self::percentile($changes, 90),
            'gained' => $n ? count(array_filter($changes, fn (float $c) => $c > 0)) / $n : 0.0,
            'bankrupt' => $n ? count(array_filter($bought, fn (GameOutcome $o) => $o->bankrupt())) / $n : 0.0,
            'rivals' => $n ? array_sum(array_map(fn (GameOutcome $o) => $o->rivals, $bought)) / $n : 0.0,
        ];
    }

    /** @return array<string, array<string, float|int>> group → summary, for one strategy */
    public function grouped(string $strategy, callable $groupOf): array
    {
        $groups = [];

        foreach ($this->byStrategy()[$strategy] ?? [] as $outcome) {
            if ($outcome->bought) {
                $groups[$groupOf($outcome)][] = $outcome;
            }
        }

        ksort($groups);

        return array_map(self::summary(...), $groups);
    }

    /** Footfall band of the business bought: "0–2", "2–4", … */
    public static function footfallBand(GameOutcome $outcome): string
    {
        $low = min(8, 2 * (int) floor(($outcome->footfall ?? 0) / 2));

        return $low.'–'.($low + 2);
    }

    /**
     * The milestone 9 targets, each with whether it holds.
     *
     * @return list<array{target: string, pass: bool, actual: string}>
     */
    public function targets(): array
    {
        $s = array_map(self::summary(...), $this->byStrategy());
        $pct = fn (float $v) => sprintf('%+.0f%%', $v * 100);
        $checks = [];

        if (isset($s['thoughtful'])) {
            $t = $s['thoughtful'];
            $checks[] = ['target' => 'Thoughtful player: median year between +5% and +25%', 'pass' => $t['median'] >= 0.05 && $t['median'] <= 0.25, 'actual' => $pct($t['median'])];
            $checks[] = ['target' => 'Thoughtful player: at least 60% end up ahead', 'pass' => $t['gained'] >= 0.6, 'actual' => sprintf('%.0f%%', $t['gained'] * 100)];
            $checks[] = ['target' => 'Thoughtful player: under 5% go bankrupt', 'pass' => $t['bankrupt'] < 0.05, 'actual' => sprintf('%.1f%%', $t['bankrupt'] * 100)];

            $districts = array_filter($this->grouped('thoughtful', fn (GameOutcome $o) => $o->neighbourhood), fn (array $d) => $d['bought'] >= 10);
            $worst = $districts === [] ? null : min(array_column($districts, 'median'));
            $checks[] = ['target' => 'Thoughtful player: no district (10+ games) with a median below −10%', 'pass' => $worst === null || $worst >= -0.10, 'actual' => $worst === null ? 'n/a' : $pct($worst)];
        }

        if (isset($s['default'])) {
            $checks[] = ['target' => 'Default settings: median year between −10% and +10%', 'pass' => $s['default']['median'] >= -0.10 && $s['default']['median'] <= 0.10, 'actual' => $pct($s['default']['median'])];
        }

        if (isset($s['thoughtful'], $s['default'])) {
            $gap = $s['thoughtful']['median'] - $s['default']['median'];
            $checks[] = ['target' => 'Thoughtful beats default settings by 5+ points (median)', 'pass' => $gap >= 0.05, 'actual' => sprintf('%+.0f points', $gap * 100)];
        }

        if (isset($s['careless'])) {
            $checks[] = ['target' => 'Careless player: median year below −20%', 'pass' => $s['careless']['median'] < -0.20, 'actual' => $pct($s['careless']['median'])];
        }

        return $checks;
    }

    /** @param list<float> $sorted */
    private static function percentile(array $sorted, int $p): float
    {
        if ($sorted === []) {
            return 0.0;
        }

        $rank = ($p / 100) * (count($sorted) - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);

        return $sorted[$low] + ($sorted[$high] - $sorted[$low]) * ($rank - $low);
    }
}
