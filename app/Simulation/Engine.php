<?php

namespace App\Simulation;

use App\Simulation\Calendar\TradingCalendar;
use App\Simulation\Competitors\CompetitorBehaviour;
use App\Simulation\Costs\MonthlyCosts;
use App\Simulation\Data\BusinessState;
use App\Simulation\Data\DayPartResult;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\EventRecord;
use App\Simulation\Data\MarketContext;
use App\Simulation\Data\MonthResult;
use App\Simulation\Data\ParameterSheet;
use App\Simulation\Demand\CaptureRate;
use App\Simulation\Demand\Covers;
use App\Simulation\Demand\LocalTrend;
use App\Simulation\Demand\PotentialCustomers;
use App\Simulation\Demand\QualityScore;
use App\Simulation\Demand\Revenue;
use App\Simulation\Demand\Seasonality;
use App\Simulation\Demand\Staffing;
use App\Simulation\Events\ChoiceResolver;
use App\Simulation\Events\EffectApplier;
use App\Simulation\Events\EventConditions;
use App\Simulation\Events\EventOccurrence;
use App\Simulation\Events\EventRoller;
use App\Simulation\Events\EventSignals;
use App\Simulation\Exceptions\DecisionNotAllowed;
use App\Simulation\Rng\SeededRng;
use App\Simulation\State\StateEvolution;

/**
 * Simulates one month of trading (SPEC §6). Each step lives in its own
 * class; this only wires them together.
 *
 * Pass a generator dedicated to this month, e.g.
 * (new SeededRng($game->seed))->fork("month-{$n}"), so replaying a month
 * gives the same result.
 *
 * Events: choices on last month's events take effect first, so their
 * modifiers count this month. Events rolled this month (step 7) book
 * their one-off costs now; their lasting modifiers start next month, and
 * any that offer a choice wait in the state's pendingEvents.
 */
final class Engine
{
    public function simulateMonth(BusinessState $state, Decisions $decisions, MarketContext $context, SeededRng $rng): MonthResult
    {
        $sheet = new ParameterSheet($context->parameters);
        self::guardLicence($state, $decisions, $sheet);

        $applier = new EffectApplier($sheet);
        $competitors = $context->competitors;
        $eventCostCents = 0;
        $eventRevenueCents = 0;

        // Choices made on last month's events
        $resolved = (new ChoiceResolver($sheet))->resolve($state->pendingEvents, $decisions);
        $state = $state->with(pendingEvents: []);

        foreach ($resolved as $occurrence) {
            $state = $applier->applyToState($state, $occurrence->effects, $occurrence->record->type);
            $competitors = $applier->applyToCompetitors($competitors, $occurrence->effects, $context->gameMonth, $rng->fork("resolve-{$occurrence->record->type}"));
            $eventCostCents += $occurrence->effects->costCents;
            $eventRevenueCents += $occurrence->effects->revenueCents;
        }

        $profile = $state->profile;
        $modifiers = $state->modifierSet();
        $potential = new PotentialCustomers($sheet);
        $covers = new Covers($sheet);
        $revenue = new Revenue($sheet);
        $captureRate = new CaptureRate($sheet);
        $calendar = new TradingCalendar($sheet);

        // 1. Seasonality & weather
        $season = (new Seasonality($sheet))->forMonth($context->calendarMonth, $decisions->openDaysPerWeek);

        // 3. Capture rate (needs quality, which doesn't depend on demand)
        $quality = max(0.0, (new QualityScore($sheet))->score($state, $decisions) - $modifiers->qualityPenalty());
        $capture = $captureRate->rate($state, $decisions, $quality, $competitors);

        // Staff missing because of events are still paid, but not on the floor.
        $onTheFloor = $decisions->with(staffCount: max(0, $decisions->staffCount - $modifiers->staffShortage()));
        $staffing = Staffing::for($onTheFloor, $sheet);
        $noise = $potential->noise($rng->fork('demand'));
        $dayParts = [];
        $totalDemand = 0.0;
        $totalServiceCapacity = 0.0;

        foreach ($decisions->openDayParts as $part) {
            // 2. Potential customers. A café that closes its quietest weekdays
            // trades on busier days than average (the daily engine's calendar).
            $partPotential = $potential->forDayPart($profile, $part, $season, $noise) * $modifiers->demand($part) * $state->localTrend
                * $calendar->weekdayFactor($decisions, $part);

            // 4. Covers
            $partDemand = $partPotential * $capture;
            $partCapacity = $covers->capacity($profile, $part, $season, $staffing) * $modifiers->capacity($part);
            $partCovers = $covers->covers($partDemand, $partCapacity);

            // 5. Revenue
            $partRevenue = $revenue->netRevenueCents($partCovers, $revenue->ticketCents($profile, $part, $decisions));

            $totalDemand += $partDemand;
            $totalServiceCapacity += $covers->serviceCapacity($part, $season, $staffing) * $modifiers->capacity($part);
            $dayParts[] = new DayPartResult(
                dayPart: $part,
                potentialCustomers: (int) round($partPotential),
                demand: (int) round($partDemand),
                capacity: (int) floor($partCapacity),
                covers: $partCovers,
                revenueCents: $partRevenue,
            );
        }

        $utilisation = $totalServiceCapacity > 0 ? $totalDemand / $totalServiceCapacity : 0.0;

        // 7. Events (rolled before costs so their one-off costs are booked this month)
        $rolled = (new EventRoller($sheet))->roll(
            EventSignals::from($state, $quality, $utilisation, $sheet->float('service.comfortable_utilisation')),
            EventConditions::from($state, $decisions, $context->calendarMonth, count($competitors)),
            $context->gameMonth,
            $rng->fork('events'),
        );

        foreach ($rolled as $occurrence) {
            $eventCostCents += $occurrence->effects->costCents;
            $eventRevenueCents += $occurrence->effects->revenueCents;
        }

        $revenueCents = array_sum(array_map(fn (DayPartResult $part) => $part->revenueCents, $dayParts)) + $eventRevenueCents;

        // 6. Costs
        $costs = (new MonthlyCosts($sheet))->calculate($state, $decisions, $season, $revenueCents, $modifiers, $eventCostCents, $context->gameMonth);
        // The owner's pay leaves the cash too, whatever the month brought in.
        $ownerPay = $sheet->int('owner.pay_month_cents');
        $cashAfter = $state->cashCents + $revenueCents - $costs->totalCents() - $ownerPay;

        // 8. State evolution, then this month's events on top
        $stateAfter = (new StateEvolution($sheet))->next($state, $decisions, $quality, $utilisation, $cashAfter);
        $stateAfter = $stateAfter->with(localTrend: (new LocalTrend($sheet))->next($state->localTrend, $rng->fork('trend')));

        // 9. Competitors
        $ownAttractiveness = $captureRate->attractiveness($state->reputation, $decisions->priceLevel, $quality);
        $competitors = (new CompetitorBehaviour($sheet))->next($competitors, $decisions->priceLevel, $ownAttractiveness, $rng->fork('competitors'));

        foreach ($rolled as $occurrence) {
            $stateAfter = $applier->applyToState($stateAfter, $occurrence->effects, $occurrence->record->type);
            $competitors = $applier->applyToCompetitors($competitors, $occurrence->effects, $context->gameMonth, $rng->fork("event-{$occurrence->record->type}"));
        }

        $stateAfter = $stateAfter->with(pendingEvents: array_values(array_filter(
            array_map(fn (EventOccurrence $o) => $o->record, $rolled),
            fn (EventRecord $event) => $event->awaitsChoice(),
        )));

        // 10. Result
        return new MonthResult(
            gameMonth: $context->gameMonth,
            customers: array_sum(array_map(fn (DayPartResult $part) => $part->covers, $dayParts)),
            revenueCents: $revenueCents,
            costs: $costs,
            stateAfter: $stateAfter,
            competitorsAfter: $competitors,
            events: array_map(fn (EventOccurrence $o) => $o->record, $rolled),
            dayParts: $dayParts,
            resolvedEvents: array_map(fn (EventOccurrence $o) => $o->record, $resolved),
            eventRevenueCents: $eventRevenueCents,
            ownerPayCents: $ownerPay,
        );
    }

    /** Which day parts a licence allows is a rule the engine enforces (SPEC §6). */
    public static function guardLicence(BusinessState $state, Decisions $decisions, ParameterSheet $sheet): void
    {
        $licence = $state->profile->licence->value;
        $allowed = $sheet->array("licence_day_parts.{$licence}");

        foreach ($decisions->openDayParts as $part) {
            if (! in_array($part->value, $allowed, true)) {
                throw new DecisionNotAllowed("A [{$licence}] licence doesn't allow opening for [{$part->value}].");
            }
        }
    }
}
