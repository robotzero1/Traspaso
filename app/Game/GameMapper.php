<?php

namespace App\Game;

use App\Models\Business;
use App\Models\Game;
use App\Models\GameBusinessState;
use App\Models\GameCompetitor;
use App\Models\Neighbourhood;
use App\Simulation\Data\BusinessCategory;
use App\Simulation\Data\BusinessProfile;
use App\Simulation\Data\BusinessState;
use App\Simulation\Data\CompetitorState;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\EventRecord;
use App\Simulation\Data\Kitchen;
use App\Simulation\Data\Licence;
use App\Simulation\Data\Modifier;
use App\Simulation\Data\ModifierEffect;
use App\Simulation\Data\NeighbourhoodProfile;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Data\QualityTier;

/**
 * Maps between Eloquent models (and their JSON columns) and the
 * simulation's DTOs. The only place that knows both shapes.
 */
final class GameMapper
{
    /** @return array<string, mixed> */
    public function parameters(Game $game): array
    {
        return config("market.{$game->market}");
    }

    public function sheet(Game $game): ParameterSheet
    {
        return new ParameterSheet($this->parameters($game));
    }

    public function neighbourhood(Neighbourhood $neighbourhood): NeighbourhoodProfile
    {
        return new NeighbourhoodProfile(
            name: $neighbourhood->name,
            population: $neighbourhood->population,
            studentIndex: $neighbourhood->student_index,
            touristIndex: $neighbourhood->tourist_index,
            officeIndex: $neighbourhood->office_index,
            transportIndex: $neighbourhood->transport_index,
            competitionDensity: $neighbourhood->competition_density,
        );
    }

    public function profile(Business $business): BusinessProfile
    {
        return new BusinessProfile(
            category: BusinessCategory::from($business->category),
            licence: Licence::from($business->licence),
            kitchen: Kitchen::from($business->kitchen),
            neighbourhood: $this->neighbourhood($business->neighbourhood),
            floorAreaM2: $business->floor_area_m2,
            indoorSeats: $business->indoor_seats,
            terraceSeats: $business->terrace_seats,
            rentMonthCents: $business->rent_month_cents,
            footfall: $business->footfall,
            condition: $business->condition,
            footfallByDayPart: array_map('floatval', $business->footfall_by_day_part ?? []),
        );
    }

    public function state(Game $game): BusinessState
    {
        /** @var GameBusinessState $row */
        $row = $game->latestState()->firstOrFail();

        return new BusinessState(
            profile: $this->profile($game->business),
            cashCents: $game->cash_cents,
            reputation: $row->reputation,
            staffCount: $row->staff_count,
            staffMorale: $row->staff_morale,
            equipmentHealth: $row->equipment_health,
            equipmentAgeMonths: $row->equipment_age_months,
            stockQuality: $row->stock_quality,
            modifiers: array_map($this->modifierFromArray(...), $row->modifiers),
            pendingEvents: array_map($this->eventFromArray(...), $row->pending_events),
        );
    }

    /** @return array<string, mixed> attributes for a GameBusinessState row */
    public function stateAttributes(BusinessState $state): array
    {
        return [
            'reputation' => round($state->reputation, 2),
            'staff_count' => $state->staffCount,
            'staff_morale' => round($state->staffMorale, 2),
            'equipment_health' => round($state->equipmentHealth, 2),
            'equipment_age_months' => $state->equipmentAgeMonths,
            'stock_quality' => round($state->stockQuality, 2),
            'modifiers' => array_map($this->modifierToArray(...), $state->modifiers),
            'pending_events' => array_map($this->eventToArray(...), $state->pendingEvents),
        ];
    }

    /** @param array<string, mixed> $data */
    public function decisionsFromArray(array $data): Decisions
    {
        return new Decisions(
            priceLevel: (float) $data['price_level'],
            openDayParts: array_map(fn (string $p) => DayPart::from($p), array_values($data['open_day_parts'])),
            openDaysPerWeek: (int) $data['open_days_per_week'],
            staffCount: (int) $data['staff_count'],
            marketingSpendCents: (int) $data['marketing_spend_cents'],
            qualityTier: QualityTier::from($data['quality_tier']),
            eventChoices: $data['event_choices'] ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function decisionsToArray(Decisions $decisions): array
    {
        return [
            'price_level' => $decisions->priceLevel,
            'open_day_parts' => array_map(fn (DayPart $p) => $p->value, $decisions->openDayParts),
            'open_days_per_week' => $decisions->openDaysPerWeek,
            'staff_count' => $decisions->staffCount,
            'marketing_spend_cents' => $decisions->marketingSpendCents,
            'quality_tier' => $decisions->qualityTier->value,
            'event_choices' => $decisions->eventChoices,
        ];
    }

    /** @return list<CompetitorState> */
    public function competitors(Game $game): array
    {
        return $game->competitors()->get()->map(fn (GameCompetitor $c) => new CompetitorState(
            id: $c->key,
            name: $c->name,
            distanceMetres: $c->distance_metres,
            priceLevel: $c->price_level,
            quality: $c->quality,
            reputation: $c->reputation,
            seats: $c->seats,
        ))->values()->all();
    }

    /**
     * The competitor id for a business in the market. Built from its place
     * in the generated market, not its database id, so the engine's random
     * streams (forked per competitor) are the same for the same seed.
     */
    public function competitorKey(Business $business): string
    {
        return "market-{$business->market_index}";
    }

    /** A rival made from a real café or bar: "osm-node-123". */
    public function unlistedKey(string $osmId): string
    {
        return 'osm-'.str_replace('/', '-', $osmId);
    }

    /**
     * @param  array{0: float, 1: float}|null  $location  for rivals that aren't listings
     * @return array<string, mixed> attributes for a GameCompetitor row
     */
    public function competitorAttributes(Game $game, CompetitorState $competitor, ?array $location = null): array
    {
        $businessId = str_starts_with($competitor->id, 'market-')
            ? $game->businesses()->where('market_index', (int) substr($competitor->id, 7))->value('id')
            : null;

        return [
            'key' => $competitor->id,
            'business_id' => $businessId,
            'name' => $competitor->name,
            'distance_metres' => $competitor->distanceMetres,
            'price_level' => round($competitor->priceLevel, 3),
            'quality' => round($competitor->quality, 2),
            'reputation' => round($competitor->reputation, 2),
            'seats' => $competitor->seats,
            'lat' => $location[0] ?? null,
            'lng' => $location[1] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public function modifierToArray(Modifier $modifier): array
    {
        return [
            'source' => $modifier->source,
            'effect' => $modifier->effect->value,
            'value' => $modifier->value,
            'months_remaining' => $modifier->monthsRemaining,
            'day_parts' => array_map(fn (DayPart $p) => $p->value, $modifier->dayParts),
        ];
    }

    /** @param array<string, mixed> $data */
    public function modifierFromArray(array $data): Modifier
    {
        return new Modifier(
            source: $data['source'],
            effect: ModifierEffect::from($data['effect']),
            value: (float) $data['value'],
            monthsRemaining: $data['months_remaining'],
            dayParts: array_map(fn (string $p) => DayPart::from($p), $data['day_parts']),
        );
    }

    /** @return array<string, mixed> */
    public function eventToArray(EventRecord $event): array
    {
        return [
            'type' => $event->type,
            'month' => $event->month,
            'payload' => $event->payload,
            'choices' => $event->choices,
            'choice' => $event->choice,
        ];
    }

    /** @param array<string, mixed> $data */
    public function eventFromArray(array $data): EventRecord
    {
        return new EventRecord($data['type'], $data['month'], $data['payload'], $data['choices'], $data['choice']);
    }
}
