<?php

namespace App\Ops;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

/**
 * What /up and `app:health` check on the live service: the database
 * answers, a queue worker is taking jobs, no job failed lately, and the
 * nightly run (scheduler + worker) has kept every café up to date.
 */
class HealthCheck
{
    /** @return list<string> one line per problem; empty when healthy */
    public function problems(): array
    {
        $problems = [];

        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            return ['Database unreachable: '.$e->getMessage()];
        }

        $staleBefore = now()->subMinutes(config('ops.health.queue_stale_minutes'))->getTimestamp();
        $waiting = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<', $staleBefore)->count();

        if ($waiting > 0) {
            $problems[] = "{$waiting} queued job(s) waiting over ".config('ops.health.queue_stale_minutes').' minutes: is the queue worker running?';
        }

        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHours(config('ops.health.failed_jobs_hours')))->count();

        if ($failed > 0) {
            $problems[] = "{$failed} job(s) failed in the last ".config('ops.health.failed_jobs_hours').' hours (php artisan queue:failed).';
        }

        // Before the night's run a café is a day behind; two days behind
        // means a night was missed.
        $behind = Game::query()
            ->where('status', GameStatus::Active)
            ->whereNotNull('business_id')
            ->whereDate('last_simulated_on', '<', Game::today()->addDays(-1)->toString())
            ->count();

        if ($behind > 0) {
            $problems[] = "{$behind} café(s) missed a nightly run: is the scheduler running?";
        }

        return $problems;
    }
}
