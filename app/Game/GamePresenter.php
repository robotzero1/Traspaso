<?php

namespace App\Game;

use App\Enums\BusinessStatus;
use App\Generation\Geo\Geo;
use App\Models\Business;
use App\Models\FootfallPoint;
use App\Models\Game;
use App\Models\GameCompetitor;
use App\Models\GameEvent;
use App\Models\MonthResult;
use App\Models\Neighbourhood;
use App\Models\PointOfInterest;
use App\Simulation\Costs\MonthlyCosts;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\EventRecord;
use App\Simulation\Data\Modifier;

/**
 * Shapes a game into props for the Inertia pages. Money stays in cents;
 * the UI formats it.
 */
final class GamePresenter
{
    public function __construct(
        private readonly GameMapper $mapper,
        private readonly GameValuation $valuation,
    ) {}

    /** @return array<string, mixed> */
    public function summary(Game $game): array
    {
        return [
            'id' => $game->id,
            'status' => $game->status->value,
            'phase' => $this->phase($game),
            'current_month' => $game->current_month,
            'months' => (int) config("market.{$game->market}.game.months"),
            'calendar_month' => $game->current_month <= 12 ? $game->calendarMonth($game->current_month) : null,
            'start_date' => $game->start_date->toDateString(),
            'starting_capital_cents' => $game->starting_capital_cents,
            'cash_cents' => $game->cash_cents,
            'deposit_cents' => $game->deposit_cents,
            'net_worth_cents' => $this->valuation->netWorthCents($game),
            'sold_for_cents' => $game->sold_for_cents,
            'final_net_worth_cents' => $game->final_net_worth_cents,
            'business_name' => $game->business?->fictional_name,
        ];
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
        $props = ['game' => $this->summary($game), 'map' => $this->map()];

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
            'decisions' => $game->decisions,
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
                : $results->first()->cash_after_cents - $results->first()->profit_cents,
            // For the decisions screen's cost estimate; the engine's own numbers.
            'cost_hints' => [
                'staff_per_person_cents' => $costs->staff(1),
                'rent_cents' => $state->profile->rentMonthCents,
                'utilities_base_cents' => $sheet->int('utilities.base_month_cents'),
                'utilities_per_open_hour_cents' => $sheet->int('utilities.per_open_hour_cents'),
                'cogs_share' => $sheet->array('cogs.share_of_revenue'),
            ],
            'results' => $results->map(fn (MonthResult $r) => [
                ...$r->only([
                    'month', 'calendar_month', 'customers', 'revenue_cents', 'event_revenue_cents', 'cogs_cents',
                    'staff_cents', 'rent_cents', 'utilities_cents', 'marketing_cents', 'other_cents', 'taxes_cents',
                    'profit_cents', 'cash_after_cents', 'day_parts',
                ]),
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
     * What every map needs: tiles and their attribution, the neighbourhood
     * overlays and the points of interest.
     *
     * @return array<string, mixed>
     */
    public function map(): array
    {
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
        return [
            ...$business->only([
                'id', 'fictional_name', 'lat', 'lng', 'street_type', 'category', 'floor_area_m2', 'indoor_seats', 'terrace_seats',
                'rent_month_cents', 'traspaso_cents', 'licence', 'kitchen', 'condition', 'equipment_age_years',
                'footfall', 'footfall_by_day_part', 'base_reputation',
            ]),
            'neighbourhood' => $business->neighbourhood->name,
            'deposit_cents' => $business->rent_month_cents * (int) config("market.{$game->market}.purchase.deposit_months_of_rent"),
        ];
    }
}
