<?php

namespace App\Actions\Game;

use App\Enums\BusinessStatus;
use App\Game\GameMapper;
use App\Generation\BusinessGenerator;
use App\Models\Game;
use App\Models\Neighbourhood;
use App\Simulation\Data\CalendarDate;
use App\Simulation\Rng\SeededRng;
use Illuminate\Support\Facades\DB;

/**
 * The living market (SPEC §12): for each week since it was last looked
 * at, some listings sell to someone else (they stay on the map, trading,
 * as possible rivals) and new ones appear. Only while the player is
 * choosing a café: once bought, the rivals are set.
 */
final class RefreshMarket
{
    public function __construct(
        private readonly GameMapper $mapper,
        private readonly StartGame $start,
    ) {}

    /** @return int weeks played */
    public function handle(Game $game): int
    {
        if (! $game->isActive() || $game->business_id !== null || $game->market_refreshed_on === null) {
            return 0;
        }

        $sheet = $this->mapper->sheet($game);
        $from = CalendarDate::parse($game->market_refreshed_on->toDateString());
        $weeks = min(intdiv($from->daysUntil(Game::today()), 7), $sheet->int('market_churn.max_weeks'));

        if ($weeks <= 0) {
            return 0;
        }

        DB::transaction(function () use ($game, $sheet, $from, $weeks) {
            $neighbourhoods = $this->start->neighbourhoodData(Neighbourhood::query()->orderBy('id')->get());
            $generator = new BusinessGenerator($this->mapper->parameters($game));

            for ($w = 1; $w <= $weeks; $w++) {
                $week = $from->addDays(7 * $w)->toString();
                $rng = (new SeededRng($game->seed))->fork("market-week-{$week}");

                $taken = $game->businesses()->where('status', BusinessStatus::ForSale)->orderBy('market_index')->pluck('id')
                    ->filter(fn (int $id) => $rng->fork("taken-{$id}")->chance($sheet->float('market_churn.taken_per_week')));
                $game->businesses()->whereKey($taken)->update(['status' => BusinessStatus::Taken]);

                $count = $this->poisson($sheet->float('market_churn.new_per_week'), $rng->fork('new'));
                $new = $generator->generate($rng->fork('market'), $neighbourhoods, $count, $this->start->commercialPoints());
                $this->start->insertListings($game, $new, (int) $game->businesses()->max('market_index') + 1, $rng->fork('locations'));
            }

            $game->update(['market_refreshed_on' => $from->addDays(7 * $weeks)->toString()]);
        });

        return $weeks;
    }

    private function poisson(float $mean, SeededRng $rng): int
    {
        $limit = exp(-$mean);
        $product = $rng->float();
        $count = 0;

        while ($product > $limit) {
            $count++;
            $product *= $rng->float();
        }

        return $count;
    }
}
