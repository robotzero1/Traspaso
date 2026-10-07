<?php

namespace App\Actions\Game;

use App\Game\GameMapper;
use App\Models\Game;
use App\Simulation\Data\CalendarDate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Saves the player's decisions, after checking the rules the request
 * can't see: the licence's opening hours and the choices on pending
 * events.
 *
 * Real time (SPEC §11): answers to events count at the next run; other
 * changes take effect from the next day played; staff changes wait their
 * lead time (staff.hire_lead_days to hire, staff.notice_days to let
 * someone go). Before the café's first day, everything applies at once.
 */
final class UpdateDecisions
{
    public function __construct(private readonly GameMapper $mapper) {}

    /** @param array<string, mixed> $data validated input */
    public function handle(Game $game, array $data): void
    {
        if (! $game->isActive() || $game->business_id === null || $game->isAwaitingEnd()) {
            throw ValidationException::withMessages(['decisions' => 'This game is not taking decisions.']);
        }

        try {
            $decisions = $this->mapper->decisionsFromArray($data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['decisions' => $e->getMessage()]);
        }

        $state = $this->mapper->state($game);
        $allowed = $this->mapper->sheet($game)->array("licence_day_parts.{$state->profile->licence->value}");

        foreach ($decisions->openDayParts as $part) {
            if (! in_array($part->value, $allowed, true)) {
                throw ValidationException::withMessages([
                    'open_day_parts' => "Your licence doesn't allow opening for the {$part->value} day part.",
                ]);
            }
        }

        $pending = [];

        foreach ($state->pendingEvents as $event) {
            $pending[$event->key()] = $event->choices;
        }

        foreach ($decisions->eventChoices as $key => $choice) {
            if (! isset($pending[$key]) || ! in_array($choice, $pending[$key], true)) {
                throw ValidationException::withMessages(['event_choices' => "[{$choice}] isn't a choice for event {$key}."]);
            }
        }

        $new = $this->mapper->decisionsToArray($decisions);

        if ($game->last_simulated_on === null || $game->started_on === null || $game->nextDay()->daysUntil(CalendarDate::parse($game->started_on->toDateString())) >= 0) {
            $game->update(['decisions' => $new, 'scheduled_decisions' => null]);

            return;
        }

        $game->update([
            'decisions' => [...$game->decisions, 'event_choices' => $new['event_choices']],
            'scheduled_decisions' => $this->schedule($game, $new) ?: null,
        ]);
    }

    /**
     * The changes to make, and from when. A new save replaces changes not
     * yet in effect; a staff change already under way keeps its date.
     *
     * @param  array<string, mixed>  $new
     * @return list<array{from: string, changes: array<string, mixed>}>
     */
    private function schedule(Game $game, array $new): array
    {
        $current = $game->decisions;
        $tomorrow = Game::today()->addDays(1);
        // A fast-forwarded game is ahead of the real clock.
        $from = $tomorrow->daysUntil($game->nextDay()) > 0 ? $game->nextDay() : $tomorrow;
        $changes = [];

        foreach ($new as $key => $value) {
            if (! in_array($key, ['event_choices', 'staff_count'], true) && ($current[$key] ?? null) !== $value) {
                $changes[$key] = $value;
            }
        }

        $schedule = $changes === [] ? [] : [['from' => $from->toString(), 'changes' => $changes]];

        if ($new['staff_count'] !== $current['staff_count']) {
            $under = collect($game->scheduled_decisions ?? [])->first(fn (array $e) => ($e['changes']['staff_count'] ?? null) === $new['staff_count']);
            $lead = $this->mapper->sheet($game)->int($new['staff_count'] > $current['staff_count'] ? 'staff.hire_lead_days' : 'staff.notice_days');
            $schedule[] = ['from' => $under['from'] ?? $from->addDays($lead)->toString(), 'changes' => ['staff_count' => $new['staff_count']]];
        }

        return $schedule;
    }
}
