<?php

namespace App\Jobs;

use App\Actions\Game\SimulateDays;
use App\Models\Game;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One café's nightly run: plays every day up to today that hasn't been
 * played yet, so a missed night is caught up and a repeated one does
 * nothing.
 */
class SimulateGameDays implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $gameId) {}

    public function uniqueId(): string
    {
        return (string) $this->gameId;
    }

    public function handle(SimulateDays $days): void
    {
        $game = Game::query()->find($this->gameId);

        if ($game !== null) {
            $days->handle($game, Game::today());
        }
    }
}
