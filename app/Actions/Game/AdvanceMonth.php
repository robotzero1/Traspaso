<?php

namespace App\Actions\Game;

use App\Enums\GameStatus;
use App\Game\GameMapper;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\MonthResult;
use App\Simulation\Data\DayPartResult;
use App\Simulation\Data\EventRecord;
use App\Simulation\Data\MarketContext;
use App\Simulation\Engine;
use App\Simulation\Exceptions\DecisionNotAllowed;
use App\Simulation\Rng\SeededRng;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plays the current month with the saved decisions and stores the result.
 * Cash below zero ends the game in bankruptcy.
 */
final class AdvanceMonth
{
    public function __construct(
        private readonly GameMapper $mapper,
        private readonly Engine $engine,
    ) {}

    public function handle(Game $game): MonthResult
    {
        if (! $game->isActive() || $game->business_id === null || $game->isAwaitingEnd()) {
            throw ValidationException::withMessages(['game' => 'There is no month to play in this game.']);
        }

        $month = $game->current_month;
        $decisions = $this->mapper->decisionsFromArray($game->decisions);
        $context = new MarketContext(
            calendarMonth: $game->calendarMonth($month),
            gameMonth: $month,
            competitors: $this->mapper->competitors($game),
            parameters: $this->mapper->parameters($game),
        );

        try {
            $result = $this->engine->simulateMonth(
                $this->mapper->state($game),
                $decisions,
                $context,
                (new SeededRng($game->seed))->fork("month-{$month}"),
            );
        } catch (DecisionNotAllowed $e) {
            throw ValidationException::withMessages(['decisions' => $e->getMessage()]);
        }

        return DB::transaction(function () use ($game, $month, $decisions, $context, $result) {
            $row = $game->monthResults()->create([
                'month' => $month,
                'calendar_month' => $context->calendarMonth,
                'customers' => $result->customers,
                'revenue_cents' => $result->revenueCents,
                'event_revenue_cents' => $result->eventRevenueCents,
                'cogs_cents' => $result->costs->cogsCents,
                'staff_cents' => $result->costs->staffCents,
                'rent_cents' => $result->costs->rentCents,
                'utilities_cents' => $result->costs->utilitiesCents,
                'marketing_cents' => $result->costs->marketingCents,
                'other_cents' => $result->costs->otherCents,
                'taxes_cents' => $result->costs->taxesCents,
                'profit_cents' => $result->profitCents(),
                'cash_after_cents' => $result->cashAfterCents(),
                'day_parts' => array_map(fn (DayPartResult $p) => [
                    'day_part' => $p->dayPart->value,
                    'potential_customers' => $p->potentialCustomers,
                    'demand' => $p->demand,
                    'capacity' => $p->capacity,
                    'covers' => $p->covers,
                    'revenue_cents' => $p->revenueCents,
                ], $result->dayParts),
                'events' => array_map(fn (EventRecord $e) => $e->type, $result->events),
            ]);

            $game->states()->create([
                'month' => $month,
                ...$this->mapper->stateAttributes($result->stateAfter),
                'decisions' => $this->mapper->decisionsToArray($decisions),
            ]);

            foreach ($result->events as $event) {
                $game->events()->create([
                    'month' => $event->month,
                    'type' => $event->type,
                    'payload' => $event->payload,
                    'choices' => $event->choices,
                ]);
            }

            foreach ($result->resolvedEvents as $event) {
                GameEvent::query()
                    ->where(['game_id' => $game->id, 'month' => $event->month, 'type' => $event->type])
                    ->update(['choice' => $event->choice, 'resolved_month' => $month]);
            }

            // Rivals made from real cafés keep their own position.
            $locations = $game->competitors()->whereNotNull('lat')->get(['key', 'lat', 'lng'])->keyBy('key');
            $game->competitors()->delete();

            foreach ($result->competitorsAfter as $competitor) {
                $place = $locations[$competitor->id] ?? null;
                $game->competitors()->create($this->mapper->competitorAttributes($game, $competitor, $place ? [$place->lat, $place->lng] : null));
            }

            $bankrupt = $result->cashAfterCents() < 0;
            $nextDecisions = $this->mapper->decisionsToArray($decisions->with(eventChoices: []));

            $game->update([
                'cash_cents' => $result->cashAfterCents(),
                'current_month' => $month + 1,
                'decisions' => $nextDecisions,
                ...($bankrupt ? [
                    'status' => GameStatus::Bankrupt,
                    'final_net_worth_cents' => $result->cashAfterCents() + $game->deposit_cents,
                    'ended_at' => now(),
                ] : []),
            ]);

            return $row;
        });
    }
}
