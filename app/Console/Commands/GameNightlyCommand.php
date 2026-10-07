<?php

namespace App\Console\Commands;

use App\Enums\GameStatus;
use App\Jobs\SimulateGameDays;
use App\Models\Game;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The nightly run (SPEC §11): queues a job for every active café with
 * days to play. Scheduled at game.nightly_at, Europe/Madrid.
 */
#[Signature('game:nightly {--sync : Play the days now instead of queueing them}')]
#[Description('Simulate today (and any missed days) for every active café')]
class GameNightlyCommand extends Command
{
    public function handle(): int
    {
        $today = Game::today()->toString();
        $count = 0;

        Game::query()
            ->where('status', GameStatus::Active)
            ->whereNotNull('business_id')
            ->whereDate('last_simulated_on', '<', $today)
            ->pluck('id')
            ->each(function (int $id) use (&$count) {
                $this->option('sync') ? SimulateGameDays::dispatchSync($id) : SimulateGameDays::dispatch($id);
                $count++;
            });

        $this->components->info("{$count} café(s) ".($this->option('sync') ? 'played' : 'queued')." through {$today}.");

        return self::SUCCESS;
    }
}
