<?php

namespace App\Push;

use App\Enums\GameStatus;
use App\Models\DayResult;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\PushSubscription;
use Illuminate\Support\Str;

/**
 * After a café's nightly run: one notification to each of the owner's
 * devices with the latest day's results, any event waiting for an answer,
 * and the month's profit when a month closed. Players choose (in their
 * settings) whether they want the daily results, the events, or both.
 */
final class NightlyNotifier
{
    public function __construct(private readonly PushSender $sender) {}

    public function notify(Game $game): void
    {
        $user = $game->user;
        $day = $game->dayResults()->reorder()->latest('date')->first();

        if ($user === null || $day === null) {
            return;
        }

        $waiting = $game->events()->whereNull('choice')->whereNull('resolved_month')->get()
            ->filter(fn (GameEvent $e) => $e->choices !== [])->values();
        $bankrupt = $game->status === GameStatus::Bankrupt;

        // Bankruptcy always gets through; otherwise the player's choices apply.
        $wantsDay = $user->notify_daily_results || $bankrupt;
        $wantsEvents = $user->notify_events && $waiting->isNotEmpty();

        if (! $wantsDay && ! $wantsEvents) {
            return;
        }

        $message = $this->message($game, $day, $waiting->all(), $wantsDay, $bankrupt);

        foreach ($user->pushSubscriptions as $subscription) {
            /** @var PushSubscription $subscription */
            if (! $this->sender->send($subscription, $message)) {
                $subscription->delete();
            }
        }
    }

    /**
     * @param  list<GameEvent>  $waiting
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function message(Game $game, DayResult $day, array $waiting, bool $withResults = true, bool $bankrupt = false): array
    {
        $name = $game->business?->fictional_name ?? 'Your café';
        $lines = [];

        if ($withResults) {
            $lines[] = $day->open
                ? sprintf('%s customers, %s takings.', number_format($day->customers, 0, ',', '.'), self::euros($day->revenue_cents))
                : 'Closed today.';

            if ($day->open && $day->weather === 'rain') {
                $lines[] = $day->terrace_usable ? 'Rainy day.' : 'Rain kept the terrace shut.';
            }

            foreach ($day->events as $type) {
                $lines[] = Str::headline($type).'.';
            }

            $month = $game->monthResults()->latest('month')->first();

            if ($month !== null && $day->date->isLastOfMonth() && $month->month === $day->month) {
                $lines[] = sprintf('Month %d closed: %s %s.', $month->month, $month->profit_cents >= 0 ? 'profit' : 'loss', self::euros(abs($month->profit_cents)));
            }
        }

        if ($waiting !== []) {
            $lines[] = count($waiting) === 1
                ? Str::headline($waiting[0]->type).' needs your decision.'
                : count($waiting).' events need your decision.';
        }

        return [
            'title' => $bankrupt ? "{$name}: bankrupt" : $name.' · '.$day->date->format('D j M'),
            'body' => $bankrupt ? 'The cash ran out at the month end. '.implode(' ', $lines) : implode(' ', $lines),
            'url' => route('games.show', $game, absolute: false),
            'tag' => "game-{$game->id}",
        ];
    }

    /** "1.184 €", as the app shows money. */
    private static function euros(int $cents): string
    {
        return number_format($cents / 100, 0, ',', '.').' €';
    }
}
