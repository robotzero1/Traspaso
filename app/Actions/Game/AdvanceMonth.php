<?php

namespace App\Actions\Game;

use App\Models\Game;
use App\Models\MonthResult;
use Illuminate\Validation\ValidationException;

/**
 * Fast-forward: plays the rest of the current month at once, without
 * waiting for the nightly runs, for trying the game out (config
 * game.fast_forward; off in production). The game's clock moves ahead of
 * the real one, and the nightly run waits for it.
 */
final class AdvanceMonth
{
    public function __construct(private readonly SimulateDays $days) {}

    public function handle(Game $game): MonthResult
    {
        if (! config('game.fast_forward')) {
            throw ValidationException::withMessages(['game' => 'Fast-forward is switched off: the café trades in real time.']);
        }

        if (! $game->isActive() || $game->business_id === null || $game->isAwaitingEnd() || $game->last_simulated_on === null) {
            throw ValidationException::withMessages(['game' => 'There is no month to play in this game.']);
        }

        $this->days->handle($game, $game->nextDay()->lastOfMonth());

        return $game->monthResults()->latest('month')->firstOrFail();
    }
}
