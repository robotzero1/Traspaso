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
        $sold = $game->sold_for_cents !== null;
        $sale = $this->saleNews($game, $day->date->toDateString());

        // Bankruptcy and a completed sale always get through; otherwise the
        // player's choices apply (buyers count as events: they need answers).
        $wantsDay = $user->notify_daily_results || $bankrupt || $sold;
        $wantsEvents = $user->notify_events && ($waiting->isNotEmpty() || $sale !== []);

        if (! $wantsDay && ! $wantsEvents) {
            return;
        }

        $message = $this->message($game, $day, $waiting->all(), $wantsDay, $bankrupt, $sale);

        foreach ($user->pushSubscriptions as $subscription) {
            /** @var PushSubscription $subscription */
            if (! $this->sender->send($subscription, $message)) {
                $subscription->delete();
            }
        }
    }

    /**
     * @param  list<GameEvent>  $waiting
     * @param  list<string>  $sale
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function message(Game $game, DayResult $day, array $waiting, bool $withResults = true, bool $bankrupt = false, array $sale = []): array
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

        array_push($lines, ...$sale);

        if ($waiting !== []) {
            $lines[] = count($waiting) === 1
                ? Str::headline($waiting[0]->type).' needs your decision.'
                : count($waiting).' events need your decision.';
        }

        return [
            'title' => match (true) {
                $bankrupt => "{$name}: bankrupt",
                $game->sold_for_cents !== null => "{$name}: sold",
                default => $name.' · '.$day->date->format('D j M'),
            },
            'body' => $bankrupt ? 'The cash ran out at the month end. '.implode(' ', $lines) : implode(' ', $lines),
            'url' => route('games.show', $game, absolute: false),
            'tag' => "game-{$game->id}",
        ];
    }

    /**
     * The day's news from the sale: new offers, buyers' answers to counter
     * offers, and the completion.
     *
     * @return list<string>
     */
    private function saleNews(Game $game, string $date): array
    {
        $listing = $game->saleListings()->reorder()->latest('id')->first();

        if ($listing === null) {
            return [];
        }

        if ($listing->completed_on?->toDateString() === $date) {
            return [sprintf('Sold for %s; %s is yours after costs and tax.', self::euros($listing->price_cents), self::euros($listing->costs['net_cents']))];
        }

        $lines = [];

        foreach ($listing->offers()->whereDate('made_on', $date)->get() as $offer) {
            $lines[] = sprintf('%s offers %s for the café.', $offer->buyer, self::euros($offer->amount_cents));
        }

        // Buyers answer counters in the run that just finished.
        foreach ($listing->offers()->where('status', 'walked')->where('updated_at', '>=', now()->subDay())->get() as $offer) {
            $lines[] = "{$offer->buyer} turned down your counter-offer.";
        }

        if ($listing->accepted_on?->toDateString() === $date) {
            $lines[] = sprintf('Sale agreed at %s, completing %s.', self::euros($listing->price_cents), $listing->completes_on->format('j M'));
        }

        return $lines;
    }

    /** "1.184 €", as the app shows money. */
    private static function euros(int $cents): string
    {
        return number_format($cents / 100, 0, ',', '.').' €';
    }
}
