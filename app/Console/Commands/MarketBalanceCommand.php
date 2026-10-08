<?php

namespace App\Console\Commands;

use App\Balance\BalanceMarket;
use App\Balance\BalanceReport;
use App\Balance\BalanceRunner;
use App\Balance\GameOutcome;
use App\Balance\Strategies\Careless;
use App\Balance\Strategies\Cheapest;
use App\Balance\Strategies\DefaultSettings;
use App\Balance\Strategies\Premium;
use App\Balance\Strategies\Thoughtful;
use App\Balance\Strategy;
use App\Game\GameMapper;
use App\Game\MarketData;
use App\Models\Neighbourhood;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The balancing pass (SPEC §9, milestone 9): plays many whole games with
 * scripted strategies on the seeded geo data and reports how they end.
 */
#[Signature('market:balance
    {--games=200 : Games per strategy}
    {--years=1 : Years each game runs (5 checks five-year survival)}
    {--seed=1 : First seed; game n uses seed + n}
    {--daily : Play day by day (the stage-two engine) instead of a month at a time}
    {--market=zaragoza_cafe}
    {--strategy=* : Only these strategies (default: all)}
    {--json= : Also write every game\'s outcome to this file}')]
#[Description('Play many simulated games with scripted strategies and report the outcome distributions')]
class MarketBalanceCommand extends Command
{
    public function handle(GameMapper $mapper, MarketData $data): int
    {
        $parameters = config("market.{$this->option('market')}");

        if (! is_array($parameters)) {
            $this->components->error("Unknown market [{$this->option('market')}].");

            return self::FAILURE;
        }

        $neighbourhoods = Neighbourhood::query()->orderBy('id')->get();

        if ($neighbourhoods->isEmpty()) {
            $this->components->error('No neighbourhoods. Run the seeders first.');

            return self::FAILURE;
        }

        $market = new BalanceMarket(
            parameters: $parameters,
            neighbourhoods: $neighbourhoods->map($mapper->neighbourhood(...))->values()->all(),
            points: $data->commercialPoints(),
            rivalPlaces: $data->rivalPlaces($parameters['competitors']['unlisted']['poi_types'], $mapper),
        );
        $strategies = $this->strategies();
        $games = (int) $this->option('games');
        $years = max(1, (int) $this->option('years'));
        $seed = (int) $this->option('seed');
        $runner = new BalanceRunner($market, daily: (bool) $this->option('daily'));
        $outcomes = [];

        $this->components->info(sprintf(
            '%d games × %d strategies, %d %s each, %s, on %s (%s)',
            $games,
            count($strategies),
            $years,
            $years === 1 ? 'year' : 'years',
            $this->option('daily') ? 'day by day' : 'a month at a time',
            $this->option('market'),
            $market->points === null ? 'placeholder locations' : count($market->rivalPlaces).' real cafés and bars, footfall surface',
        ));

        $bar = $this->output->createProgressBar($games * count($strategies));

        foreach ($strategies as $strategy) {
            for ($n = 0; $n < $games; $n++) {
                $outcomes[] = $runner->play($strategy, $seed + $n, $years);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $report = new BalanceReport($outcomes);
        $this->strategyTable($report, $strategies);

        if ($years > 1) {
            $this->survivalTable($report, $strategies, $years);
        }

        $this->resaleTable($report, $strategies, $years);
        $this->groupTable('Thoughtful player by district', $report->grouped('thoughtful', fn (GameOutcome $o) => $o->neighbourhood));
        $this->groupTable('Thoughtful player by footfall at the spot', $report->grouped('thoughtful', BalanceReport::footfallBand(...)));

        $targets = $report->targets();
        $this->table(['Target', 'Actual', ''], array_map(fn (array $t) => [$t['target'], $t['actual'], $t['pass'] ? 'PASS' : 'FAIL'], $targets));

        if ($path = $this->option('json')) {
            File::put($path, json_encode(array_map(fn (GameOutcome $o) => $o->toArray(), $outcomes), JSON_PRETTY_PRINT));
            $this->components->info("Outcomes written to {$path}");
        }

        return self::SUCCESS;
    }

    /** @return list<Strategy> */
    private function strategies(): array
    {
        $all = [new Thoughtful, new DefaultSettings, new Cheapest, new Premium, new Careless];
        $only = $this->option('strategy');

        return $only === [] ? $all : array_values(array_filter($all, fn (Strategy $s) => in_array($s->key(), $only, true)));
    }

    /** @param list<Strategy> $strategies */
    private function strategyTable(BalanceReport $report, array $strategies): void
    {
        $by = $report->byStrategy();
        $rows = [];

        foreach ($strategies as $strategy) {
            $s = BalanceReport::summary($by[$strategy->key()] ?? []);
            $rows[] = [$strategy->key(), $s['bought'].'/'.$s['games'], ...$this->spread($s), sprintf('%.1f', $s['rivals'])];
        }

        $this->table(['Strategy', 'Bought', 'p10', 'p25', 'Median', 'p75', 'p90', 'Ahead', 'Bankrupt', 'Failed', 'Rivals'], $rows);
        $this->line('  Failed: closed in year 1 (ran out of cash, or didn\'t earn enough over the year to pay the owner). Net worth is at the end, or at closing.');

        foreach ($strategies as $strategy) {
            $this->line("  <comment>{$strategy->key()}</comment>: {$strategy->description()}");
        }

        $this->newLine();
    }

    /** @param list<Strategy> $strategies */
    private function survivalTable(BalanceReport $report, array $strategies, int $years): void
    {
        $by = $report->byStrategy();
        $this->line('<info>Still open at the end of each year</info>');
        $this->table(
            ['Strategy', ...array_map(fn (int $y) => "Year {$y}", range(1, $years))],
            array_map(fn (Strategy $s) => [
                $s->key(),
                ...array_map(fn (float $open) => sprintf('%.0f%%', $open * 100), BalanceReport::summary($by[$s->key()] ?? [])['open']),
            ], $strategies),
        );
    }

    /** @param list<Strategy> $strategies */
    private function resaleTable(BalanceReport $report, array $strategies, int $years): void
    {
        $by = $report->byStrategy();
        $shown = array_values(array_unique([1, min(3, $years), $years]));
        $this->line('<info>What the café would sell for, ÷ the traspaso paid: median (p10–p90), cafés that reached the year end</info>');
        $this->table(
            ['Strategy', ...array_map(fn (int $y) => "Year {$y}", $shown)],
            array_map(fn (Strategy $s) => [
                $s->key(),
                ...array_map(function (int $y) use ($by, $s) {
                    $r = BalanceReport::resale($by[$s->key()] ?? [], $y);

                    return $r['n'] === 0 ? '—' : sprintf('%.2f (%.2f–%.2f)', $r['median'], $r['p10'], $r['p90']);
                }, $shown),
            ], $strategies),
        );
    }

    /** @param array<string, array<string, float|int>> $groups */
    private function groupTable(string $title, array $groups): void
    {
        $this->line("<info>{$title}</info>");
        $this->table(['', 'Games', 'p10', 'p25', 'Median', 'p75', 'p90', 'Ahead', 'Bankrupt', 'Failed'], array_map(
            fn (string $name, array $s) => [$name, $s['bought'], ...$this->spread($s)],
            array_keys($groups),
            $groups,
        ));
    }

    /** @return list<string> */
    private function spread(array $s): array
    {
        $pct = fn (float $v) => sprintf('%+.0f%%', $v * 100);

        return [$pct($s['p10']), $pct($s['p25']), $pct($s['median']), $pct($s['p75']), $pct($s['p90']), sprintf('%.0f%%', $s['gained'] * 100), sprintf('%.1f%%', $s['bankrupt'] * 100), sprintf('%.0f%%', $s['failed'] * 100)];
    }
}
