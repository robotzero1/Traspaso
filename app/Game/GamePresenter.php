<?php

namespace App\Game;

use App\Enums\BusinessStatus;
use App\Enums\GameStatus;
use App\Generation\Geo\Geo;
use App\Models\Business;
use App\Models\DayResult;
use App\Models\FootfallPoint;
use App\Models\Game;
use App\Models\GameCompetitor;
use App\Models\GameEvent;
use App\Models\MonthResult;
use App\Models\Neighbourhood;
use App\Models\PointOfInterest;
use App\Models\SaleOffer;
use App\Simulation\Costs\MonthlyCosts;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\EventRecord;
use App\Simulation\Data\Modifier;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Sale\BuyingCosts;
use App\Simulation\Sale\SaleCosts;

/**
 * Shapes a game into props for the Inertia pages. Money stays in cents;
 * the UI formats it.
 */
final class GamePresenter
{
    public function __construct(
        private readonly GameMapper $mapper,
        private readonly GameValuation $valuation,
        private readonly Sales $sales,
    ) {}

    /** @return array<string, mixed> */
    public function summary(Game $game): array
    {
        return [
            'id' => $game->id,
            'status' => $game->status->value,
            'phase' => $this->phase($game),
            'current_month' => $game->current_month,
            'months' => config("market.{$game->market}.game.months"),
            'calendar_month' => $game->isAwaitingEnd() ? null : $game->calendarMonth($game->current_month),
            'started_on' => $game->started_on?->toDateString(),
            'last_simulated_on' => $game->last_simulated_on?->toDateString(),
            'fast_forward' => (bool) config('game.fast_forward'),
            'start_date' => $game->start_date->toDateString(),
            'starting_capital_cents' => $game->starting_capital_cents,
            'cash_cents' => $game->cash_cents,
            'deposit_cents' => $game->deposit_cents,
            'net_worth_cents' => $this->valuation->netWorthCents($game),
            'sold_for_cents' => $game->sold_for_cents,
            'final_net_worth_cents' => $game->final_net_worth_cents,
            'closure' => $game->closure,
            'can_buy_again' => $game->canBuyAgain(),
            'next_game_id' => $game->nextGame?->id,
            'business_name' => $game->business?->fictional_name,
        ];
    }

    /**
     * Every café of the career this game is part of (SPEC §12), first one
     * first; empty for a career of one.
     *
     * @return list<array<string, mixed>>
     */
    public function career(Game $game): array
    {
        $chain = $game->career();

        if (count($chain) === 1) {
            return [];
        }

        return array_map(fn (Game $g) => [
            'game_id' => $g->id,
            'current' => $g->id === $game->id,
            'name' => $g->business?->fictional_name,
            'neighbourhood' => $g->business?->neighbourhood?->name,
            'started_on' => $g->started_on?->toDateString(),
            'ended_on' => $g->ended_at?->toDateString(),
            'traspaso_cents' => $g->business?->traspaso_cents,
            'months' => $g->monthResults()->count(),
            'profit_cents' => (int) $g->monthResults()->sum('profit_cents'),
            'owner_pay_cents' => (int) $g->monthResults()->sum('owner_pay_cents'),
            'outcome' => match (true) {
                $g->status === GameStatus::Bankrupt => 'bankrupt',
                $g->sold_for_cents !== null => 'sold',
                $g->closure !== null => 'closed',
                $g->business_id === null => 'choosing',
                default => 'running',
            },
            'sold_for_cents' => $g->sold_for_cents,
            'starting_capital_cents' => $g->starting_capital_cents,
            'net_worth_cents' => $this->valuation->netWorthCents($g),
        ], $chain);
    }

    /** browsing → playing → ending → over */
    public function phase(Game $game): string
    {
        return match (true) {
            ! $game->isActive() => 'over',
            $game->business_id === null => 'browsing',
            $game->isAwaitingEnd() => 'ending',
            default => 'playing',
        };
    }

    /** @return array<string, mixed> */
    public function show(Game $game): array
    {
        $props = ['game' => $this->summary($game), 'map' => $this->map($game), 'career' => $this->career($game)];

        if ($game->business_id === null) {
            if ($game->isActive()) {
                $props['businesses'] = $game->businesses()
                    ->where('status', BusinessStatus::ForSale)
                    ->with('neighbourhood')
                    ->orderBy('traspaso_cents')
                    ->get()
                    ->map(fn (Business $b) => $this->business($b, $game))
                    ->all();
            }

            return $props;
        }

        $sheet = $this->mapper->sheet($game);
        $state = $this->mapper->state($game);
        $results = $game->monthResults()->get();
        $costs = new MonthlyCosts($sheet);

        return [
            ...$props,
            'business' => $this->business($game->business, $game),
            'state' => [
                'reputation' => $state->reputation,
                'staff_count' => $state->staffCount,
                'staff_morale' => $state->staffMorale,
                'equipment_health' => $state->equipmentHealth,
                'equipment_age_months' => $state->equipmentAgeMonths,
                'stock_quality' => $state->stockQuality,
                'modifiers' => array_map(fn (Modifier $m) => $this->mapper->modifierToArray($m), $state->modifiers),
            ],
            'business_value_cents' => $this->valuation->businessValueCents($game),
            'sale' => $this->sale($game, $sheet),
            // What the player has chosen, including changes not yet in effect.
            'decisions' => array_merge($game->decisions, ...array_column($game->scheduled_decisions ?? [], 'changes')),
            'scheduled_decisions' => $game->scheduled_decisions ?? [],
            'decision_limits' => $sheet->array('decision_limits'),
            'allowed_day_parts' => $sheet->array("licence_day_parts.{$state->profile->licence->value}"),
            'day_parts' => array_map(fn (DayPart $p) => [
                'value' => $p->value,
                'start_hour' => $sheet->int("day_parts.{$p->value}.start_hour"),
                'end_hour' => $sheet->int("day_parts.{$p->value}.end_hour"),
            ], DayPart::cases()),
            'pending_events' => array_map(fn (EventRecord $e) => [
                'key' => $e->key(),
                'type' => $e->type,
                'month' => $e->month,
                'choices' => $e->choices,
                'payload' => $e->payload,
            ], $state->pendingEvents),
            // Cash on the day the business was bought, where the cash chart starts.
            'opening_cash_cents' => $results->isEmpty()
                ? $game->cash_cents
                : $results->first()->cash_after_cents - $results->first()->profit_cents + $results->first()->owner_pay_cents,
            // For the decisions screen's cost estimate; the engine's own numbers.
            'cost_hints' => [
                'staff_per_person_cents' => $costs->staff(1),
                // For the part-time cover estimate (see Staffing).
                'full_time_hours_per_week' => $sheet->float('staff.full_time_hours_per_week'),
                'owner_hours_per_week' => $sheet->float('service.owner_hours_per_week'),
                'min_on_shift' => $sheet->float('staff.min_on_shift'),
                'owner_pay_cents' => $sheet->int('owner.pay_month_cents'),
                'rent_cents' => $state->profile->rentMonthCents,
                'utilities_base_cents' => $sheet->int('utilities.base_month_cents'),
                'utilities_per_open_hour_cents' => $sheet->int('utilities.per_open_hour_cents'),
                'cogs_share' => $sheet->array('cogs.share_of_revenue'),
            ],
            'results' => $results->map(fn (MonthResult $r) => [
                ...$r->only([
                    'month', 'calendar_month', 'customers', 'revenue_cents', 'event_revenue_cents', 'cogs_cents',
                    'staff_cents', 'rent_cents', 'utilities_cents', 'marketing_cents', 'other_cents', 'taxes_cents',
                    'profit_cents', 'owner_pay_cents', 'cash_after_cents', 'day_parts',
                ]),
            ])->all(),
            // The app opens on this (SPEC §11): the last day traded, and the
            // month so far.
            ...$this->latestDay($game),
            // The last month played, day by day.
            'days' => $results->isEmpty() ? [] : $game->dayResults()->where('month', $results->last()->month)->get()
                ->map(fn (DayResult $d) => [
                    'date' => $d->date->toDateString(),
                    ...$d->only(['open', 'weather', 'terrace_usable', 'customers', 'revenue_cents', 'events']),
                ])->all(),
            'events' => $game->events()->get()->map(fn (GameEvent $e) => $e->only([
                'month', 'type', 'payload', 'choices', 'choice', 'resolved_month',
            ]))->all(),
            'competitors' => $game->competitors()->with('business')->get()->map(fn (GameCompetitor $c) => [
                ...$c->only(['key', 'name', 'distance_metres', 'price_level', 'quality', 'reputation', 'seats']),
                ...$this->competitorLocation($c, $game->business),
            ])->all(),
        ];
    }

    /**
     * Selling (SPEC §12): the latest listing and its offers (never the
     * buyers' limits), and what a sale at the café's value would leave.
     *
     * @return array<string, mixed>
     */
    private function sale(Game $game, ParameterSheet $sheet): array
    {
        $listing = $game->saleListings()->reorder()->latest('id')->first();
        $value = $this->valuation->businessValueCents($game);
        $costs = new SaleCosts($sheet);

        return [
            'listing' => $listing === null || $listing->withdrawn_on !== null ? null : [
                ...$listing->only(['id', 'asking_cents', 'agency', 'price_cents', 'costs']),
                ...collect(['listed_on', 'accepted_on', 'completes_on', 'completed_on'])->mapWithKeys(fn (string $k) => [$k => $listing->{$k}?->toDateString()])->all(),
                'offers' => $listing->offers->map(fn (SaleOffer $o) => [
                    ...$o->only(['id', 'buyer', 'amount_cents', 'status', 'counter_cents']),
                    'made_on' => $o->made_on->toDateString(),
                    'expires_on' => $o->expires_on->toDateString(),
                ])->all(),
            ],
            'value_cents' => $value,
            'private' => $costs->breakdown($value, $game->business->traspaso_cents, agency: false),
            'agency' => $costs->breakdown($value, $game->business->traspaso_cents, agency: true),
            // Getting out fast (milestone 19).
            'closes_on' => $game->closes_on?->toDateString(),
            'exit_on' => $game->nextDay()->lastOfMonth()->toString(),
            'quick_sale' => ($quick = $this->sales->quickSalePriceCents($game)) > 0 ? [
                'price_cents' => $quick,
                ...$costs->breakdown($quick, $game->business->traspaso_cents, agency: false),
            ] : null,
            'closure' => $this->sales->closureCosts($game),
            'offer_days' => $sheet->int('sale.offer_days'),
            'handover_days' => $sheet->int('sale.handover_days'),
        ];
    }

    /** @return array{latest_day: array<string, mixed>|null, month_to_date: array<string, int>|null} */
    private function latestDay(Game $game): array
    {
        $day = $game->dayResults()->reorder()->latest('date')->first();

        if ($day === null) {
            return ['latest_day' => null, 'month_to_date' => null];
        }

        $month = $game->dayResults()->where('month', $day->month);

        return [
            'latest_day' => [
                'date' => $day->date->toDateString(),
                'month' => $day->month,
                ...$day->only(['open', 'weather', 'terrace_usable', 'customers', 'revenue_cents', 'events', 'cash_after_cents']),
            ],
            'month_to_date' => [
                'days_open' => (clone $month)->where('open', true)->count(),
                'customers' => (int) (clone $month)->sum('customers'),
                'revenue_cents' => (int) (clone $month)->sum('revenue_cents'),
            ],
        ];
    }

    /**
     * What every map needs: tiles and their attribution, the neighbourhood
     * overlays and the points of interest.
     *
     * @return array<string, mixed>
     */
    public function map(Game $game): array
    {
        $market = "market.{$game->market}";

        return [
            'tile_url' => config('map.tile_url'),
            'attribution' => config('map.attribution'),
            'max_zoom' => config('map.max_zoom'),
            'centre' => config('map.centre'),
            'zoom' => config('map.zoom'),
            'neighbourhoods' => Neighbourhood::query()->whereNotNull('centre_lat')->orderBy('name')->get()
                ->map(fn (Neighbourhood $n) => [
                    'name' => $n->name,
                    'lat' => $n->centre_lat,
                    'lng' => $n->centre_lng,
                    'radius_m' => $n->radius_m,
                    'boundary' => $n->boundary,
                ])->all(),
            'points_of_interest' => PointOfInterest::query()
                ->when($fromOsm = PointOfInterest::query()->whereNotNull('osm_id')->exists(), fn ($q) => $q->whereIn('type', config('geo.map_poi_types')))
                ->orderBy('type')->orderBy('name')->get()
                ->map(fn (PointOfInterest $p) => $p->only(['type', 'name', 'lat', 'lng']))->all(),
            'placeholder' => ! $fromOsm,
            'has_footfall' => FootfallPoint::query()->exists(),
            // How busy the streets are in each day part: the footfall layer
            // scales each period's values by it, as the demand model does.
            'footfall_exponent' => (float) config("{$market}.demand.footfall_exponent"),
            'day_part_intensity' => collect(DayPart::cases())
                ->mapWithKeys(fn (DayPart $p) => [$p->value => (float) config("{$market}.day_parts.{$p->value}.intensity")])->all(),
        ];
    }

    /**
     * A rival's position: its listing's, or for rivals that opened during
     * the game, a point at its recorded distance from the player on a
     * bearing fixed by its id.
     *
     * @return array{lat: float|null, lng: float|null}
     */
    private function competitorLocation(GameCompetitor $competitor, Business $own): array
    {
        if ($competitor->lat !== null) {
            return ['lat' => $competitor->lat, 'lng' => $competitor->lng];
        }

        if ($competitor->business?->lat !== null) {
            return ['lat' => $competitor->business->lat, 'lng' => $competitor->business->lng];
        }

        if ($own->lat === null) {
            return ['lat' => null, 'lng' => null];
        }

        [$lat, $lng] = Geo::offset($own->lat, $own->lng, $competitor->distance_metres, crc32($competitor->key) % 360);

        return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
    }

    /** @return array<string, mixed> */
    private function business(Business $business, Game $game): array
    {
        // What buying it takes (SPEC §13): "cash needed" on the card.
        $costs = (new BuyingCosts($this->mapper->sheet($game)))->breakdown($business->traspaso_cents, $business->rent_month_cents);

        return [
            ...$business->only([
                'id', 'fictional_name', 'lat', 'lng', 'street_type', 'category', 'floor_area_m2', 'indoor_seats', 'terrace_seats',
                'rent_month_cents', 'traspaso_cents', 'licence', 'kitchen', 'condition', 'equipment_age_years',
                'footfall', 'footfall_by_day_part', 'base_reputation',
            ]),
            'neighbourhood' => $business->neighbourhood->name,
            'buying_costs' => $costs,
            'deposit_cents' => $costs['held_cents'],
            'cash_needed_cents' => $costs['cash_needed_cents'],
        ];
    }
}
