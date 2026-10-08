<?php

namespace App\Game;

use App\Models\Game;
use App\Simulation\Sale\SaleCosts;
use App\Simulation\Valuation\BusinessValuation;

/**
 * Business value and net worth for a game: cash + the landlord's deposit +
 * what the owner would walk away with from selling the business: its value
 * less the costs of a private sale and the tax on the gain, or the agreed
 * sale's proceeds once a buyer has signed (SPEC §12).
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

        return $game->cash_cents + $game->deposit_cents + $this->walkAwayCents($game);
    }

    public function walkAwayCents(Game $game): int
    {
        if ($game->business_id === null || $game->sold_for_cents !== null) {
            return 0;
        }

        $agreed = $game->liveListing();

        if ($agreed?->costs !== null) {
            return $agreed->costs['net_cents'];
        }

        return (new SaleCosts($this->mapper->sheet($game)))->netCents($this->businessValueCents($game), $game->business->traspaso_cents, agency: false);
    }
}
