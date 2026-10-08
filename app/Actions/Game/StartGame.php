<?php

namespace App\Actions\Game;

use App\Enums\BusinessStatus;
use App\Enums\GameStatus;
use App\Game\GameMapper;
use App\Game\MarketData;
use App\Generation\BusinessGenerator;
use App\Generation\CommercialPoints;
use App\Generation\GeneratedBusiness;
use App\Generation\Geo\LocationPlacer;
use App\Models\Game;
use App\Models\Neighbourhood;
use App\Models\User;
use App\Simulation\Rng\SeededRng;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates a game and generates its market of businesses for sale from the
 * game's seed.
 */
final class StartGame
{
    public function __construct(
        private readonly GameMapper $mapper,
        private readonly MarketData $data,
    ) {}

    /** With $previous, the next café of a career: today's market, the last café's cash. */
    public function handle(User $user, int $startingCapitalCents, string $market = 'zaragoza_cafe', ?int $seed = null, ?Game $previous = null): Game
    {
        $neighbourhoods = Neighbourhood::query()->orderBy('id')->get();

        if ($neighbourhoods->isEmpty()) {
            throw new RuntimeException('No neighbourhoods. Run the NeighbourhoodSeeder.');
        }

        return DB::transaction(function () use ($user, $startingCapitalCents, $market, $seed, $neighbourhoods, $previous) {
            $game = Game::query()->create([
                'user_id' => $user->id,
                'market' => $market,
                'seed' => $seed ?? random_int(1, PHP_INT_MAX),
                'start_date' => now()->startOfMonth(),
                'starting_capital_cents' => $startingCapitalCents,
                'cash_cents' => $startingCapitalCents,
                'status' => GameStatus::Active,
                'previous_game_id' => $previous?->id,
                'market_refreshed_on' => Game::today()->toString(),
            ]);

            $generated = (new BusinessGenerator($this->mapper->parameters($game)))->generate(
                (new SeededRng($game->seed))->fork('market'),
                $this->neighbourhoodData($neighbourhoods),
                locations: $this->commercialPoints(),
            );
            $this->insertListings($game, $generated, 1, (new SeededRng($game->seed))->fork('locations'));

            // The player's last café, sold on, still trades: a possible rival.
            if ($previous?->sold_for_cents !== null && $previous->business !== null) {
                $copy = $previous->business->replicate(['game_id', 'market_index', 'status']);
                $copy->fill(['game_id' => $game->id, 'market_index' => count($generated) + 1, 'status' => BusinessStatus::Taken])->save();
            }

            return $game;
        });
    }

    /**
     * Stores generated businesses as listings, numbered from $firstIndex.
     *
     * @param  list<GeneratedBusiness>  $generated
     */
    public function insertListings(Game $game, array $generated, int $firstIndex, SeededRng $locations): void
    {
        $byName = Neighbourhood::query()->get()->keyBy('name');
        $now = now();
        $rows = [];

        foreach ($generated as $i => $business) {
            $index = $firstIndex + $i;
            $profile = $business->profile;
            $neighbourhood = $byName[$profile->neighbourhood->name];
            [$lat, $lng] = match (true) {
                $business->location !== null => [$business->location->lat, $business->location->lng],
                $neighbourhood->centre_lat !== null => LocationPlacer::inCircle($neighbourhood->centre_lat, $neighbourhood->centre_lng, $neighbourhood->radius_m, $locations->fork((string) ($index - 1))),
                default => [null, null],
            };

            $rows[] = [
                'game_id' => $game->id,
                'market_index' => $index,
                'neighbourhood_id' => $neighbourhood->id,
                'lat' => $lat,
                'lng' => $lng,
                'footfall_point_id' => $business->location?->pointId,
                'fictional_name' => $business->name,
                'street_type' => $business->streetType,
                'category' => $profile->category->value,
                'floor_area_m2' => $profile->floorAreaM2,
                'indoor_seats' => $profile->indoorSeats,
                'terrace_seats' => $profile->terraceSeats,
                'rent_month_cents' => $profile->rentMonthCents,
                'traspaso_cents' => $business->traspasoCents,
                'licence' => $profile->licence->value,
                'kitchen' => $profile->kitchen->value,
                'condition' => $profile->condition,
                'equipment_age_years' => $business->equipmentAgeYears,
                'footfall' => $profile->footfall,
                'footfall_by_day_part' => $profile->footfallByDayPart === [] ? null : json_encode($profile->footfallByDayPart, JSON_PRESERVE_ZERO_FRACTION),
                'base_reputation' => $business->baseReputation,
                'status' => BusinessStatus::ForSale->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('businesses')->insert($chunk);
        }
    }

    /**
     * @param  iterable<Neighbourhood>  $neighbourhoods
     * @return list<\App\Simulation\Data\Neighbourhood>
     */
    public function neighbourhoodData(iterable $neighbourhoods): array
    {
        return collect($neighbourhoods)->map(fn (Neighbourhood $n) => $this->mapper->neighbourhood($n))->values()->all();
    }

    /**
     * The footfall surface's commercial points, or null before geo:build
     * has produced one (businesses then get generated footfall and a
     * position in their neighbourhood's circle).
     */
    public function commercialPoints(): ?CommercialPoints
    {
        $points = $this->data->commercialPoints();

        return $points === null ? null : new CommercialPoints($points);
    }
}
