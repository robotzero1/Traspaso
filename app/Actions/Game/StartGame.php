<?php

namespace App\Actions\Game;

use App\Enums\BusinessStatus;
use App\Enums\GameStatus;
use App\Game\GameMapper;
use App\Generation\BusinessGenerator;
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
    public function __construct(private readonly GameMapper $mapper) {}

    public function handle(User $user, int $startingCapitalCents, string $market = 'zaragoza_cafe', ?int $seed = null): Game
    {
        $neighbourhoods = Neighbourhood::query()->orderBy('id')->get();

        if ($neighbourhoods->isEmpty()) {
            throw new RuntimeException('No neighbourhoods. Run the NeighbourhoodSeeder.');
        }

        return DB::transaction(function () use ($user, $startingCapitalCents, $market, $seed, $neighbourhoods) {
            $game = Game::query()->create([
                'user_id' => $user->id,
                'market' => $market,
                'seed' => $seed ?? random_int(1, PHP_INT_MAX),
                'start_date' => now()->startOfMonth(),
                'starting_capital_cents' => $startingCapitalCents,
                'cash_cents' => $startingCapitalCents,
                'status' => GameStatus::Active,
            ]);

            $idsByName = $neighbourhoods->pluck('id', 'name');
            $generated = (new BusinessGenerator($this->mapper->parameters($game)))->generate(
                (new SeededRng($game->seed))->fork('market'),
                $neighbourhoods->map(fn (Neighbourhood $n) => $this->mapper->neighbourhood($n))->values()->all(),
            );

            $now = now();
            $rows = [];

            foreach ($generated as $index => $business) {
                $profile = $business->profile;
                $rows[] = [
                    'game_id' => $game->id,
                    'market_index' => $index + 1,
                    'neighbourhood_id' => $idsByName[$profile->neighbourhood->name],
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
                    'base_reputation' => $business->baseReputation,
                    'status' => BusinessStatus::ForSale->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('businesses')->insert($chunk);
            }

            return $game;
        });
    }
}
