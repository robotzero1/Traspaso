<?php

namespace App\Actions\Game;

use App\Enums\GameStatus;
use App\Game\GameMapper;
use App\Models\DayResult as DayResultRow;
use App\Models\Game;
use App\Models\GameEvent;
use App\Simulation\Data\CalendarDate;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\DayContext;
use App\Simulation\Data\DayPartResult;
use App\Simulation\Data\DayResult;
use App\Simulation\Data\EventRecord;
use App\Simulation\Data\MarketContext;
use App\Simulation\Data\MonthResult;
use App\Simulation\DayEngine;
use App\Simulation\Exceptions\DecisionNotAllowed;
use App\Simulation\Rng\SeededRng;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plays a café's days, in order, from the day after the last one played
 * through $through (SPEC §11: the nightly run, and catch-up after a missed
 * one). Each day is its own transaction with the game row locked, so a
 * day is simulated once however often this runs. On a month's last day
 * the month's bills are settled; cash below zero then ends the game.
 *
 * Decisions waiting in scheduled_decisions take effect on their day.
 */
final class SimulateDays
{
    public function __construct(
        private readonly GameMapper $mapper,
        private readonly DayEngine $engine,
    ) {}

    /** @return int the number of days played */
    public function handle(Game $game, CalendarDate $through): int
    {
        $played = 0;

        while (DB::transaction(function () use ($game, $through) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);

            if (! $locked->isActive() || $locked->business_id === null || $locked->last_simulated_on === null
                || $locked->nextDay()->daysUntil($through) < 0) {
                return false;
            }

            $this->playDay($locked, $locked->nextDay());

            return true;
        })) {
            $played++;
        }

        $game->refresh();

        return $played;
    }

    private function playDay(Game $game, CalendarDate $date): void
    {
        $month = $game->current_month;
        $this->applyScheduled($game, $date);
        $decisions = $this->mapper->decisionsFromArray($game->decisions);
        $parameters = $this->mapper->parameters($game);
        $monthRng = (new SeededRng($game->seed))->fork("month-{$month}");
        $earlier = $game->dayResults()->where('month', $month)->pluck('events')->flatten()->values()->all();

        try {
            $day = $this->engine->simulateDay(
                $this->mapper->state($game),
                $decisions,
                new DayContext($date, $month, $this->mapper->competitors($game), $parameters, $earlier),
                $monthRng,
            );
        } catch (DecisionNotAllowed $e) {
            throw ValidationException::withMessages(['decisions' => $e->getMessage()]);
        }

        $this->storeDay($game, $month, $day);

        // Answered choices are used once.
        $resolved = array_map(fn (EventRecord $e) => $e->key(), $day->resolvedEvents);
        $game->decisions = [...$game->decisions, 'event_choices' => array_diff_key($game->decisions['event_choices'] ?? [], array_flip($resolved))];
        $game->cash_cents = $day->stateAfter->cashCents;
        $game->last_simulated_on = $date->toString();
        $game->save();

        if ($date->isLastOfMonth()) {
            $this->closeMonth($game, $month, $date, $monthRng);
        }
    }

    private function storeDay(Game $game, int $month, DayResult $day): void
    {
        $game->dayResults()->create([
            'date' => $day->date->toString(),
            'month' => $month,
            'open' => $day->open,
            'weather' => $day->weather->kind->value,
            'terrace_usable' => $day->weather->terraceUsable,
            'customers' => $day->customers,
            'revenue_cents' => $day->revenueCents,
            'event_revenue_cents' => $day->eventRevenueCents,
            'cogs_cents' => $day->cogsCents,
            'event_cost_cents' => $day->eventCostCents + $day->modifierCostCents,
            'cash_after_cents' => $day->stateAfter->cashCents,
            'day_parts' => $this->dayParts($day->dayParts),
            'events' => array_map(fn (EventRecord $e) => $e->type, $day->events),
        ]);

        foreach ($day->events as $event) {
            $game->events()->create(['month' => $event->month, 'type' => $event->type, 'payload' => $event->payload, 'choices' => $event->choices]);
        }

        foreach ($day->resolvedEvents as $event) {
            GameEvent::query()
                ->where(['game_id' => $game->id, 'month' => $event->month, 'type' => $event->type])
                ->update(['choice' => $event->choice, 'resolved_month' => $month]);
        }

        $game->states()->updateOrCreate(['month' => $month], [
            ...$this->mapper->stateAttributes($day->stateAfter),
            'decisions' => $game->decisions,
        ]);
        $this->storeCompetitors($game, $day->competitorsAfter);
    }

    private function closeMonth(Game $game, int $month, CalendarDate $lastDay, SeededRng $monthRng): void
    {
        $state = $this->mapper->state($game);
        $competitors = $this->mapper->competitors($game);
        $rows = $game->dayResults()->where('month', $month)->orderBy('date')->get();
        $decisions = $this->mapper->decisionsFromArray($game->decisions);
        $result = $this->engine->closeMonth(
            $state,
            $decisions,
            new MarketContext($lastDay->month, $month, $competitors, $this->mapper->parameters($game)),
            $rows->map(fn (DayResultRow $row) => $this->mapper->dayResult($row, $state, $competitors))->all(),
            $monthRng,
        );

        $this->storeMonth($game, $month, $lastDay, $result, $rows->pluck('events')->flatten()->values()->all());
        $rows->last()->update(['cash_after_cents' => $result->cashAfterCents()]);
        $game->states()->updateOrCreate(['month' => $month], [
            ...$this->mapper->stateAttributes($result->stateAfter),
            'decisions' => $game->decisions,
        ]);
        $this->storeCompetitors($game, $result->competitorsAfter);

        $bankrupt = $result->cashAfterCents() < 0;
        $game->update([
            'cash_cents' => $result->cashAfterCents(),
            'current_month' => $month + 1,
            ...($bankrupt ? [
                'status' => GameStatus::Bankrupt,
                'final_net_worth_cents' => $result->cashAfterCents() + $game->deposit_cents,
                'ended_at' => now(),
            ] : []),
        ]);
    }

    /** @param list<string> $events */
    private function storeMonth(Game $game, int $month, CalendarDate $lastDay, MonthResult $result, array $events): void
    {
        $game->monthResults()->create([
            'month' => $month,
            'calendar_month' => $lastDay->month,
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
            'owner_pay_cents' => $result->ownerPayCents,
            'cash_after_cents' => $result->cashAfterCents(),
            'day_parts' => $this->dayParts($result->dayParts),
            'events' => $events,
        ]);
    }

    /** Scheduled decisions whose day has come take effect. */
    private function applyScheduled(Game $game, CalendarDate $date): void
    {
        $decisions = $game->decisions;
        $later = [];

        foreach ($game->scheduled_decisions ?? [] as $entry) {
            if (CalendarDate::parse($entry['from'])->daysUntil($date) >= 0) {
                $decisions = [...$decisions, ...$entry['changes']];
            } else {
                $later[] = $entry;
            }
        }

        $game->decisions = $decisions;
        $game->scheduled_decisions = $later === [] ? null : $later;
    }

    /** @param list<CompetitorState> $competitors */
    private function storeCompetitors(Game $game, array $competitors): void
    {
        if ($competitors == $this->mapper->competitors($game)) {
            return;
        }

        // Rivals made from real cafés keep their own position.
        $locations = $game->competitors()->whereNotNull('lat')->get(['key', 'lat', 'lng'])->keyBy('key');
        $game->competitors()->delete();

        foreach ($competitors as $competitor) {
            $place = $locations[$competitor->id] ?? null;
            $game->competitors()->create($this->mapper->competitorAttributes($game, $competitor, $place ? [$place->lat, $place->lng] : null));
        }
    }

    /**
     * @param  list<DayPartResult>  $parts
     * @return list<array<string, mixed>>
     */
    private function dayParts(array $parts): array
    {
        return array_map(fn (DayPartResult $p) => [
            'day_part' => $p->dayPart->value,
            'potential_customers' => $p->potentialCustomers,
            'demand' => $p->demand,
            'capacity' => $p->capacity,
            'covers' => $p->covers,
            'revenue_cents' => $p->revenueCents,
        ], $parts);
    }
}
