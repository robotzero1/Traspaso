<?php

namespace App\Game;

use App\Models\Game;
use App\Simulation\Valuation\BusinessValuation;

/**
 * Business value and net worth for a game: cash + the landlord's deposit +
 * what the business would sell for (SPEC §1).
 */
final class GameValuation
{
    public function __construct(private readonly GameMapper $mapper) {}

    public function businessValueCents(Game $game): int
    {
        if ($game->business_id === null || $game->sold_for_cents !== null) {
            return 0;
        }

        $profits = $game->monthResults()->pluck('profit_cents')->map(fn ($p) => (int) $p)->all();

        return (new BusinessValuation($this->mapper->sheet($game)))
            ->valueCents($this->mapper->state($game), $game->business->traspaso_cents, $profits);
    }

    public function netWorthCents(Game $game): int
    {
        if ($game->final_net_worth_cents !== null) {
            return $game->final_net_worth_cents;
        }

        return $game->cash_cents + $game->deposit_cents + $this->businessValueCents($game);
    }
}
