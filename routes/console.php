<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The nightly run (SPEC §11): every active café's day, at 23:00 Madrid.
Schedule::command('game:nightly')
    ->dailyAt(config('game.nightly_at'))
    ->timezone(config('game.timezone'))
    ->withoutOverlapping();
