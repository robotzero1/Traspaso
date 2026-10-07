<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The nightly run (SPEC §11): every active café's day, at 23:00 Madrid.
Schedule::command('game:nightly')
    ->dailyAt(config('game.nightly_at'))
    ->timezone(config('game.timezone'))
    ->withoutOverlapping()
    // Optional heartbeat: the monitor alerts when a night is missed.
    ->pingOnSuccessIf(filled(config('ops.nightly_ping_url')), (string) config('ops.nightly_ping_url'));

// Backups and data retention (milestone 16).
Schedule::command('app:backup')
    ->dailyAt(config('ops.backup.at'))
    ->timezone(config('game.timezone'))
    ->when(fn () => DB::connection(config('ops.backup.connection'))->getDriverName() === 'sqlite');
Schedule::command('model:prune')->daily();
Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])->daily();
