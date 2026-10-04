<?php

namespace App\Actions\Game;

use App\Enums\GameStatus;
use App\Game\GameValuation;
use App\Models\Game;
use Illuminate\Validation\ValidationException;

/**
 * After the twelfth month: sell the business (its value and the deposit
 * become cash) or keep it (net worth counts its value).
 */
final class EndGame
{
    public function __construct(private readonly GameValuation $valuation) {}

    public function sell(Game $game): void
    {
        $this->guard($game);

        $price = $this->valuation->businessValueCents($game);
        $cash = $game->cash_cents + $price + $game->deposit_cents;

        $game->update([
            'cash_cents' => $cash,
            'sold_for_cents' => $price,
            'deposit_cents' => 0,
            'status' => GameStatus::Finished,
            'final_net_worth_cents' => $cash,
            'ended_at' => now(),
        ]);
    }

    public function keep(Game $game): void
    {
        $this->guard($game);

        $game->update([
            'status' => GameStatus::Finished,
            'final_net_worth_cents' => $this->valuation->netWorthCents($game),
            'ended_at' => now(),
        ]);
    }

    private function guard(Game $game): void
    {
        if (! $game->isAwaitingEnd()) {
            throw ValidationException::withMessages(['game' => 'The game can only end after its last month.']);
        }
    }
}
