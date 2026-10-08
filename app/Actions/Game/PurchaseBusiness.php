<?php

namespace App\Actions\Game;

use App\Enums\BusinessStatus;
use App\Game\GameMapper;
use App\Generation\CompetitorCandidate;
use App\Generation\CompetitorPicker;
use App\Generation\Geo\Geo;
use App\Generation\Takeover;
use App\Generation\UnlistedRivals;
use App\Models\Business;
use App\Models\Game;
use App\Models\PointOfInterest;
use App\Simulation\Rng\SeededRng;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buys a business: pays the traspaso, the landlord's deposit and
 * guarantee, and the buying fees (SPEC §13), sets up its first
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
        $price = $takeover->cashNeededCents($profile, $business->traspaso_cents);

        if ($price > $game->cash_cents) {
            throw ValidationException::withMessages(['business_id' => 'You can\'t afford the traspaso, the deposit and the buying costs.']);
        }

        DB::transaction(function () use ($game, $business, $takeover, $profile, $deposit, $price) {
            $state = $takeover->initialState($profile, $business->base_reputation, $business->equipment_age_years, $game->cash_cents - $price);

            // The keys are handed over today; the café trades from tomorrow.
            $today = Game::today();
            $firstDay = $today->addDays(1);

            $business->update(['status' => BusinessStatus::OwnedByPlayer]);
            $game->update([
                'start_date' => $firstDay->firstOfMonth()->toString(),
                'started_on' => $firstDay->toString(),
                'last_simulated_on' => $today->toString(),
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
     * falls, topped up from real cafés and bars nearby when too few
     * listings are close; without, the others in the same neighbourhood.
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

        $rng = (new SeededRng($game->seed))->fork('competitors');
        $places = $located ? $this->nearbyPlaces($game, $bought) : [];
        $fill = (new UnlistedRivals($this->mapper->sheet($game)))->candidates(
            array_values(array_map(fn (array $p) => ['key' => $p['key'], 'distance_metres' => $p['distance_metres']], $places)),
            $rng->fork('unlisted'),
            $game->businesses()->pluck('fictional_name')->all(),
        );

        $competitors = (new CompetitorPicker($this->mapper->sheet($game)))->pick($candidates, $rng, $fill);

        foreach ($competitors as $competitor) {
            $place = $places[$competitor->id] ?? null;
            $row = $game->competitors()->create($this->mapper->competitorAttributes($game, $competitor, $place ? [$place['lat'], $place['lng']] : null));
            Business::query()->whereKey($row->business_id)->update(['status' => BusinessStatus::Competitor]);
        }
    }

    /**
     * Real cafés and bars (OSM) within rival range of the business, nearest
     * first, keyed by their rival key.
     *
     * @return array<string, array{key: string, lat: float, lng: float, distance_metres: float}>
     */
    private function nearbyPlaces(Game $game, Business $bought): array
    {
        $sheet = $this->mapper->sheet($game);
        $max = $sheet->float('competitors.distance_metres.max');
        $dLat = $max / 111_195;
        $dLng = $dLat / cos(deg2rad($bought->lat));

        $places = PointOfInterest::query()
            ->whereIn('type', $sheet->array('competitors.unlisted.poi_types'))
            ->whereNotNull('osm_id')
            ->whereBetween('lat', [$bought->lat - $dLat, $bought->lat + $dLat])
            ->whereBetween('lng', [$bought->lng - $dLng, $bought->lng + $dLng])
            ->get()
            ->map(fn (PointOfInterest $p) => [
                'key' => $this->mapper->unlistedKey($p->osm_id),
                'lat' => $p->lat,
                'lng' => $p->lng,
                'distance_metres' => Geo::distanceMetres($bought->lat, $bought->lng, $p->lat, $p->lng),
            ])
            ->filter(fn (array $p) => $p['distance_metres'] <= $max)
            ->sortBy([['distance_metres', 'asc'], ['key', 'asc']])
            ->keyBy('key');

        return $places->all();
    }
}
