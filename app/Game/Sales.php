<?php

namespace App\Game;

use App\Models\Game;
use App\Models\SaleListing;
use App\Models\SaleOffer;
use App\Simulation\Data\CalendarDate;
use App\Simulation\Rng\SeededRng;
use App\Simulation\Sale\BuyerMarket;
use App\Simulation\Sale\BuyerOffer;
use App\Simulation\Sale\SaleCosts;
use Illuminate\Validation\ValidationException;

/**
 * Selling the café (SPEC §12): the player lists it, buyers make offers
 * day by day in the nightly run, the player accepts, rejects or counters,
 * and an accepted sale completes at the end of the month after the
 * handover period. The café trades all the while.
 */
final class Sales
{
    /** Who turns up to buy. Names only: buyers differ in what they'd pay, not in who they are. */
    private const BUYERS = ['Carmen', 'Javier', 'Lucía', 'Andrés', 'Pilar', 'Sergio', 'Elena', 'Raúl', 'Marta', 'Óscar', 'Nuria', 'Iván', 'Rosa', 'Diego', 'Ana y Luis', 'Hermanos Gil'];

    public function __construct(
        private readonly GameMapper $mapper,
        private readonly GameValuation $valuation,
    ) {}

    public function list(Game $game, int $askingCents, bool $agency): SaleListing
    {
        if (! $game->isActive() || $game->business_id === null) {
            throw ValidationException::withMessages(['asking' => 'Only a café you run can be listed.']);
        }

        if ($game->liveListing() !== null) {
            throw ValidationException::withMessages(['asking' => 'The café is already listed.']);
        }

        return $game->saleListings()->create([
            'business_id' => $game->business_id,
            'asking_cents' => $askingCents,
            'agency' => $agency,
            'listed_on' => $game->nextDay()->toString(),
        ]);
    }

    public function withdraw(Game $game): void
    {
        $listing = $game->liveListing();

        if ($listing === null || $listing->accepted_on !== null) {
            throw ValidationException::withMessages(['listing' => 'There is no listing to withdraw, or the sale is already agreed.']);
        }

        $listing->update(['withdrawn_on' => $game->nextDay()->toString()]);
        $listing->offers()->whereIn('status', ['open', 'countered'])->update(['status' => 'lapsed']);
    }

    /** accept, reject, or counter with a price between the offer and the asking price. */
    public function answer(SaleOffer $offer, string $answer, ?int $counterCents = null): void
    {
        $listing = $offer->listing;
        $game = $listing->game;

        if ($offer->status !== 'open' || ! $listing->isLive() || $listing->accepted_on !== null) {
            throw ValidationException::withMessages(['offer' => 'This offer is no longer open.']);
        }

        match ($answer) {
            'accept' => $this->accept($game, $listing, $offer, $offer->amount_cents, $game->nextDay()),
            'reject' => $offer->update(['status' => 'rejected']),
            'counter' => $this->counter($offer, $listing, $counterCents),
        };
    }

    /**
     * The nightly run's part, for one day: offers past their deadline
     * lapse, buyers answer counter-offers, and a new buyer may turn up.
     */
    public function day(Game $game, CalendarDate $date): void
    {
        $listing = $game->liveListing();

        if ($listing === null || $listing->accepted_on !== null) {
            return;
        }

        $market = new BuyerMarket($this->mapper->sheet($game));
        $listing->offers()->where('status', 'open')->whereDate('expires_on', '<', $date->toString())->update(['status' => 'lapsed']);

        foreach ($listing->offers()->where('status', 'countered')->get() as $offer) {
            if ($market->acceptsCounter(new BuyerOffer($offer->amount_cents, $offer->limit_cents), $offer->counter_cents)) {
                $this->accept($game, $listing, $offer, $offer->counter_cents, $date);

                return;
            }

            $offer->update(['status' => 'walked']);
        }

        $rng = (new SeededRng($game->seed))->fork("sale-{$listing->id}-{$date->toString()}");
        $offer = $market->day($listing->asking_cents, $this->valuation->businessValueCents($game), $listing->agency, $date->month, $date->daysInMonth(), $rng);

        if ($offer !== null) {
            $listing->offers()->create([
                'buyer' => $rng->fork('buyer')->pick(self::BUYERS),
                'amount_cents' => $offer->amountCents,
                'limit_cents' => $offer->limitCents,
                'made_on' => $date->toString(),
                'expires_on' => $date->addDays($this->mapper->sheet($game)->int('sale.offer_days'))->toString(),
            ]);
        }
    }

    /** The agreed sale that completes on this day, if any. */
    public function completing(Game $game, CalendarDate $date): ?SaleListing
    {
        $listing = $game->liveListing();

        return $listing?->completes_on?->toDateString() === $date->toString() ? $listing : null;
    }

    private function counter(SaleOffer $offer, SaleListing $listing, ?int $counterCents): void
    {
        if ($counterCents === null || $counterCents <= $offer->amount_cents || $counterCents > $listing->asking_cents) {
            throw ValidationException::withMessages(['counter' => 'A counter-offer goes between their offer and your asking price.']);
        }

        // One counter per buyer: they answer in the nightly run.
        $offer->update(['status' => 'countered', 'counter_cents' => $counterCents]);
    }

    private function accept(Game $game, SaleListing $listing, SaleOffer $offer, int $priceCents, CalendarDate $on): void
    {
        $sheet = $this->mapper->sheet($game);
        $offer->update(['status' => 'accepted']);
        $listing->offers()->whereKeyNot($offer->id)->whereIn('status', ['open', 'countered'])->update(['status' => 'rejected']);
        $listing->update([
            'accepted_on' => $on->toString(),
            'completes_on' => $on->addDays($sheet->int('sale.handover_days'))->lastOfMonth()->toString(),
            'price_cents' => $priceCents,
            'costs' => (new SaleCosts($sheet))->breakdown($priceCents, $game->business->traspaso_cents, $listing->agency),
        ]);
    }
}
