<?php

namespace App\Actions\Game;

use App\Enums\BusinessStatus;
use App\Game\GameMapper;
use App\Generation\CompetitorCandidate;
use App\Generation\CompetitorPicker;
use App\Generation\Geo\Geo;
use App\Generation\Takeover;
use App\Models\Business;
use App\Models\Game;
use App\Simulation\Rng\SeededRng;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buys a business: pays the traspaso and the deposit, sets up its first
 * state and default decisions, and picks the nearby rivals.
 */
final class PurchaseBusiness
{
    public function __construct(private readonly GameMapper $mapper) {}

    public function handle(Game $game, Business $business): void
    {
        if (! $game->isActive() || $game->business_id !== null) {
            throw ValidationException::withMessages(['business_id' => 'This game already has a business.']);
        }

        if ($business->game_id !== $game->id || $business->status !== BusinessStatus::ForSale) {
            throw ValidationException::withMessages(['business_id' => 'That business is not for sale in this game.']);
        }

        $takeover = new Takeover($this->mapper->sheet($game));
        $profile = $this->mapper->profile($business);
        $deposit = $takeover->depositCents($profile);
        $price = $business->traspaso_cents + $deposit;

        if ($price > $game->cash_cents) {
            throw ValidationException::withMessages(['business_id' => 'You can\'t afford the traspaso and the deposit.']);
        }

        DB::transaction(function () use ($game, $business, $takeover, $profile, $deposit, $price) {
            $state = $takeover->initialState($profile, $business->base_reputation, $business->equipment_age_years, $game->cash_cents - $price);

            $business->update(['status' => BusinessStatus::OwnedByPlayer]);
            $game->update([
                'business_id' => $business->id,
                'cash_cents' => $state->cashCents,
                'deposit_cents' => $deposit,
                'decisions' => $this->mapper->decisionsToArray($takeover->defaultDecisions()),
            ]);
            $game->states()->create(['month' => 0, ...$this->mapper->stateAttributes($state)]);

            $this->pickCompetitors($game, $business);
        });
    }

    /**
     * The other businesses near the one bought become its rivals. With map
     * locations that means the nearest ones, wherever the district line
     * falls; without, the others in the same neighbourhood.
     */
    private function pickCompetitors(Game $game, Business $bought): void
    {
        $located = $bought->lat !== null;
        $others = $game->businesses()
            ->whereKeyNot($bought->id)
            ->when(! $located, fn ($q) => $q->where('neighbourhood_id', $bought->neighbourhood_id))
            ->orderBy('market_index')
            ->get();

        $candidates = $others->map(fn (Business $b) => new CompetitorCandidate(
            id: $this->mapper->competitorKey($b),
            name: $b->fictional_name,
            seats: $b->indoor_seats + $b->terrace_seats,
            condition: $b->condition,
            reputation: $b->base_reputation,
            distanceMetres: $located && $b->lat !== null
                ? Geo::distanceMetres($bought->lat, $bought->lng, $b->lat, $b->lng)
                : null,
        ))->values()->all();

        $competitors = (new CompetitorPicker($this->mapper->sheet($game)))
            ->pick($candidates, (new SeededRng($game->seed))->fork('competitors'));

        foreach ($competitors as $competitor) {
            $row = $game->competitors()->create($this->mapper->competitorAttributes($game, $competitor));
            Business::query()->whereKey($row->business_id)->update(['status' => BusinessStatus::Competitor]);
        }
    }
}
