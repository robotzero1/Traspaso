<?php

namespace App\Actions\Game;

use App\Game\GameMapper;
use App\Models\Game;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Saves the decisions for the coming month, after checking the rules the
 * request can't see: the licence's opening hours and the choices on
 * pending events.
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

        $game->update(['decisions' => $this->mapper->decisionsToArray($decisions)]);
    }
}
