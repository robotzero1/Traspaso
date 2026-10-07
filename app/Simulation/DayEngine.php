<?php

namespace App\Simulation;

use App\Simulation\Calendar\TradingCalendar;
use App\Simulation\Competitors\CompetitorBehaviour;
use App\Simulation\Costs\MonthlyCosts;
use App\Simulation\Data\BusinessState;
use App\Simulation\Data\CalendarDate;
use App\Simulation\Data\DailyMonthResult;
use App\Simulation\Data\DayContext;
use App\Simulation\Data\DayPartResult;
use App\Simulation\Data\DayResult;
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
use App\Simulation\Demand\SeasonalFactors;
use App\Simulation\Demand\Staffing;
use App\Simulation\Demand\Weather;
use App\Simulation\Events\ChoiceResolver;
use App\Simulation\Events\EffectApplier;
use App\Simulation\Events\EventConditions;
use App\Simulation\Events\EventOccurrence;
use App\Simulation\Events\EventRoller;
use App\Simulation\Events\EventSignals;
use App\Simulation\Rng\SeededRng;
use App\Simulation\State\StateEvolution;
use InvalidArgumentException;

/**
 * Simulates trading one day at a time (SPEC §11, Daily simulation), with
 * the monthly engine's steps on each day's share of the month:
 *
 * - the day's trade is the month's (seasonality, the month's demand noise,
 *   the spot's custom) shared out by weekday, public holidays and the
 *   Pilar (TradingCalendar), moved by the day's weather (Weather) and a
 *   little day-to-day noise. Over a month these average out, so the days
 *   add up to what the monthly engine gives: stage one's calibration
 *   holds. Each day is capped by that day's seats and staff, so a busy
 *   Saturday can turn people away even when the month as a whole wouldn't;
 * - takings and the stock sold come in daily; rent, wages, the owner's
 *   pay and the other monthly bills go out at month end (closeMonth);
 * - events roll daily with their monthly odds spread over the days, and
 *   their lasting effects start the next day, counted in days;
 * - reputation, morale and equipment move daily in smaller steps; rivals,
 *   the spot's long-run custom and equipment age move at month end.
 *
 * Pass the month's generator, (new SeededRng($seed))->fork("month-{$n}"),
 * the same one the monthly engine takes: each day forks its own stream
 * from it by date, so a day is the same however often it is replayed.
 */
final class DayEngine
{
    private ?TradingCalendar $calendar = null;

    /** @var array<string, mixed>|null the parameters was built from */
    private ?array $calendarParameters = null;

    public function simulateDay(BusinessState $state, Decisions $decisions, DayContext $context, SeededRng $monthRng): DayResult
    {
        $sheet = new ParameterSheet($context->parameters);
        Engine::guardLicence($state, $decisions, $sheet);

        $date = $context->date;
        $rng = $monthRng->fork("day-{$date}");
        $calendar = $this->calendar($context->parameters, $sheet);
        $weather = new Weather($sheet);
        $applier = new EffectApplier($sheet);
        $competitors = $context->competitors;
        $eventCostCents = 0;
        $eventRevenueCents = 0;
        $resolved = [];

        // Choices on events waiting for one: all of them when settling, else
        // those the player has answered and those past their deadline.
        $due = match (true) {
            $context->settleChoices => $state->pendingEvents,
            ! $context->deadlines => [],
            default => array_values(array_filter($state->pendingEvents, fn (EventRecord $e) => $decisions->choiceFor($e) !== null || $this->overdue($e, $date, $sheet))),
        };

        if ($due !== []) {
            $resolved = (new ChoiceResolver($sheet))->resolve($due, $decisions);
            $state = $state->with(pendingEvents: array_values(array_filter($state->pendingEvents, fn (EventRecord $e) => ! in_array($e, $due, true))));

            foreach ($resolved as $occurrence) {
                $state = $applier->applyToState($state, $occurrence->effects, $occurrence->record->type);
                $competitors = $applier->applyToCompetitors($competitors, $occurrence->effects, $context->gameMonth, $rng->fork("resolve-{$occurrence->record->type}"));
                $eventCostCents += $occurrence->effects->costCents;
                $eventRevenueCents += $occurrence->effects->revenueCents;
            }
        }

        $profile = $state->profile;
        $modifiers = $state->modifierSet();
        $daysInMonth = $date->daysInMonth();
        $open = $calendar->isOpen($date, $decisions);
        $todaysWeather = $weather->draw($date, $rng->fork('weather'));

        $quality = max(0.0, (new QualityScore($sheet))->score($state, $decisions) - $modifiers->qualityPenalty());
        $captureRate = new CaptureRate($sheet);
        $dayParts = [];
        $utilisation = 0.0;

        if ($open) {
            $potential = new PotentialCustomers($sheet);
            $covers = new Covers($sheet);
            $revenue = new Revenue($sheet);
            $capture = $captureRate->rate($state, $decisions, $quality, $competitors);
            $onTheFloor = $decisions->with(staffCount: max(0, $decisions->staffCount - $modifiers->staffShortage()));
            $staffing = Staffing::for($onTheFloor, $sheet);
            // The month's noise is the monthly engine's own draw; the day's comes on top.
            $noise = $potential->noise($monthRng->fork('demand')) * $this->dayNoise($sheet, $rng->fork('demand'));
            $seasonality = $sheet->float("seasonality.multipliers.{$date->month}");
            $totalDemand = 0.0;
            $totalServiceCapacity = 0.0;

            foreach ($decisions->openDayParts as $part) {
                $day = new SeasonalFactors(
                    demandMultiplier: $seasonality * $calendar->dayWeight($date, $part) * $weather->demandFactor($todaysWeather, $part, $date),
                    daysInMonth: $daysInMonth,
                    openDays: 1,
                    terraceUsableShare: $todaysWeather->terraceUsable ? 1.0 : 0.0,
                );

                $partPotential = $potential->forDayPart($profile, $part, $day, $noise) * $modifiers->demand($part) * $state->localTrend;
                $partDemand = $partPotential * $capture;
                $partCapacity = $covers->capacity($profile, $part, $day, $staffing) * $modifiers->capacity($part);
                $partCovers = $covers->covers($partDemand, $partCapacity);

                $totalDemand += $partDemand;
                $totalServiceCapacity += $covers->serviceCapacity($part, $day, $staffing) * $modifiers->capacity($part);
                $dayParts[] = new DayPartResult(
                    dayPart: $part,
                    potentialCustomers: (int) round($partPotential),
                    demand: (int) round($partDemand),
                    capacity: (int) floor($partCapacity),
                    covers: $partCovers,
                    revenueCents: $revenue->netRevenueCents($partCovers, $revenue->ticketCents($profile, $part, $decisions)),
                );
            }

            $utilisation = $totalServiceCapacity > 0 ? $totalDemand / $totalServiceCapacity : 0.0;
        }

        // Events, with the month's odds spread over its days
        $rolled = (new EventRoller($sheet))->rollDay(
            EventSignals::from($state, $quality, $utilisation, $sheet->float('service.comfortable_utilisation')),
            EventConditions::from($state, $decisions, $date->month, count($competitors)),
            $context->gameMonth,
            $daysInMonth,
            $context->eventsThisMonth,
            $rng->fork('events'),
            ['date' => $date->toString()],
        );

        foreach ($rolled as $occurrence) {
            $eventCostCents += $occurrence->effects->costCents;
            $eventRevenueCents += $occurrence->effects->revenueCents;
        }

        $costs = new MonthlyCosts($sheet);
        $revenueCents = array_sum(array_map(fn (DayPartResult $p) => $p->revenueCents, $dayParts)) + $eventRevenueCents;
        $cogsCents = (int) round($revenueCents * $costs->cogsShare($decisions, $modifiers));
        $modifierCostCents = (int) round($modifiers->monthlyCostCents() / $daysInMonth);
        $cashAfter = $state->cashCents + $revenueCents - $cogsCents - $eventCostCents - $modifierCostCents;

        $stateAfter = (new StateEvolution($sheet))->day(
            $state, $decisions, $quality, $utilisation, $cashAfter, $open, $daysInMonth,
            $calendar->openDaysInMonth($date->year, $date->month, $decisions),
        );

        // Today's events take effect from tomorrow, counted in days
        foreach ($rolled as $occurrence) {
            $stateAfter = $applier->applyToState($stateAfter, $occurrence->effects, $occurrence->record->type, inDays: true);
            $competitors = $applier->applyToCompetitors($competitors, $occurrence->effects, $context->gameMonth, $rng->fork("event-{$occurrence->record->type}"));
        }

        $stateAfter = $stateAfter->with(pendingEvents: [
            ...$stateAfter->pendingEvents,
            ...array_values(array_filter(
                array_map(fn (EventOccurrence $o) => $o->record, $rolled),
                fn (EventRecord $event) => $event->awaitsChoice(),
            )),
        ]);

        return new DayResult(
            date: $date,
            gameMonth: $context->gameMonth,
            open: $open,
            weather: $todaysWeather,
            customers: array_sum(array_map(fn (DayPartResult $p) => $p->covers, $dayParts)),
            revenueCents: $revenueCents,
            cogsCents: $cogsCents,
            eventCostCents: $eventCostCents,
            modifierCostCents: $modifierCostCents,
            stateAfter: $stateAfter,
            competitorsAfter: $competitors,
            dayParts: $dayParts,
            events: array_map(fn (EventOccurrence $o) => $o->record, $rolled),
            resolvedEvents: array_map(fn (EventOccurrence $o) => $o->record, $resolved),
            eventRevenueCents: $eventRevenueCents,
            utilisation: $utilisation,
        );
    }

    /**
     * Month end (or the end of a month's first traded days): rent, wages, utilities, marketing, the cuota and the other
     * monthly costs are settled, the owner takes their pay, and the month's
     * P&L is drawn up from its days. Then equipment ages, monthly modifiers
     * count down, the spot's custom drifts and rivals respond, as in the
     * monthly engine (with the same random streams).
     *
     * @param  BusinessState  $state  as the month's last day left it
     * @param  list<DayResult>  $days  the month's days, in order
     */
    public function closeMonth(BusinessState $state, Decisions $decisions, MarketContext $context, array $days, SeededRng $monthRng): MonthResult
    {
        if ($days === []) {
            throw new InvalidArgumentException('A month needs at least one day to close.');
        }

        $sheet = new ParameterSheet($context->parameters);
        $costs = new MonthlyCosts($sheet);
        $sum = fn (callable $field) => array_sum(array_map($field, $days));
        $revenueCents = $sum(fn (DayResult $d) => $d->revenueCents);
        $cogsCents = $sum(fn (DayResult $d) => $d->cogsCents);
        $paidCents = $sum(fn (DayResult $d) => $d->cogsCents + $d->eventCostCents + $d->modifierCostCents);
        $openDays = count(array_filter($days, fn (DayResult $d) => $d->open));
        $daysInMonth = $days[0]->date->daysInMonth();
        $season = new SeasonalFactors(1.0, $daysInMonth, $openDays, 0.0);
        // A month traded only in part (the first, from the day the café was
        // taken over) pays its fixed bills and the owner's pay pro rata.
        $share = min(1.0, count($days) / $daysInMonth);

        $breakdown = $costs->settleMonth(
            $state,
            $decisions,
            $season,
            $revenueCents,
            $cogsCents,
            $sum(fn (DayResult $d) => $d->eventCostCents + $d->modifierCostCents),
            $share,
        );

        $ownerPay = (int) round($sheet->int('owner.pay_month_cents') * $share);
        // The days already paid for their stock and one-off costs.
        $cashAfter = $state->cashCents - ($breakdown->totalCents() - $paidCents) - $ownerPay;

        $stateAfter = (new StateEvolution($sheet))->monthEnd($state)->with(
            cashCents: $cashAfter,
            localTrend: (new LocalTrend($sheet))->next($state->localTrend, $monthRng->fork('trend')),
        );

        $last = $days[array_key_last($days)];
        $quality = max(0.0, (new QualityScore($sheet))->score($state, $decisions) - $state->modifierSet()->qualityPenalty());
        $ownAttractiveness = (new CaptureRate($sheet))->attractiveness($state->reputation, $decisions->priceLevel, $quality);
        $competitors = (new CompetitorBehaviour($sheet))->next($last->competitorsAfter, $decisions->priceLevel, $ownAttractiveness, $monthRng->fork('competitors'));

        return new MonthResult(
            gameMonth: $context->gameMonth,
            customers: $sum(fn (DayResult $d) => $d->customers),
            revenueCents: $revenueCents,
            costs: $breakdown,
            stateAfter: $stateAfter,
            competitorsAfter: $competitors,
            events: array_merge(...array_map(fn (DayResult $d) => $d->events, $days)),
            dayParts: $this->monthDayParts($days, $decisions),
            resolvedEvents: array_merge(...array_map(fn (DayResult $d) => $d->resolvedEvents, $days)),
            eventRevenueCents: $sum(fn (DayResult $d) => $d->eventRevenueCents),
            ownerPayCents: $ownerPay,
        );
    }

    /**
     * A whole calendar month, day by day, with the same decisions
     * throughout, closed at month end. Events waiting for a choice are
     * settled on the first day, as in the monthly engine (no deadlines).
     */
    public function simulateMonth(BusinessState $state, Decisions $decisions, MarketContext $context, CalendarDate $month, SeededRng $monthRng): DailyMonthResult
    {
        if ($month->month !== $context->calendarMonth) {
            throw new InvalidArgumentException("{$month} is not in calendar month {$context->calendarMonth}.");
        }

        $competitors = $context->competitors;
        $eventsThisMonth = [];
        $days = [];

        for ($date = $month->firstOfMonth(); $date->month === $context->calendarMonth && $date->year === $month->year; $date = $date->addDays(1)) {
            $day = $this->simulateDay(
                $state,
                $decisions,
                new DayContext($date, $context->gameMonth, $competitors, $context->parameters, $eventsThisMonth, settleChoices: $days === [], deadlines: false),
                $monthRng,
            );

            $days[] = $day;
            $state = $day->stateAfter;
            $competitors = $day->competitorsAfter;
            $eventsThisMonth = [...$eventsThisMonth, ...array_map(fn (EventRecord $e) => $e->type, $day->events)];
        }

        return new DailyMonthResult($days, $this->closeMonth($state, $decisions, $context, $days, $monthRng));
    }

    /**
     * The calendar caches holidays, weights and open days, so it is kept
     * while the parameters stay the same.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function calendar(array $parameters, ParameterSheet $sheet): TradingCalendar
    {
        if ($this->calendar === null || $this->calendarParameters !== $parameters) {
            $this->calendar = new TradingCalendar($sheet);
            $this->calendarParameters = $parameters;
        }

        return $this->calendar;
    }

    /**
     * Whether an event has waited past its deadline (events.deadline_days,
     * or its own), so its default choice is taken. Events without a date
     * (from the monthly engine) are settled at once.
     */
    private function overdue(EventRecord $event, CalendarDate $today, ParameterSheet $sheet): bool
    {
        $date = $event->payload['date'] ?? null;

        if (! is_string($date)) {
            return true;
        }

        $deadline = $sheet->array("events.library.{$event->type}")['deadline_days'] ?? $sheet->int('events.deadline_days');

        return CalendarDate::parse($date)->daysUntil($today) >= $deadline;
    }

    private function dayNoise(ParameterSheet $sheet, SeededRng $rng): float
    {
        $noise = $rng->normal(1.0, $sheet->float('daily.noise_sd'));

        return min($sheet->float('daily.noise_max'), max($sheet->float('daily.noise_min'), $noise));
    }

    /**
     * The month's results per day part, added up over its days.
     *
     * @param  list<DayResult>  $days
     * @return list<DayPartResult>
     */
    private function monthDayParts(array $days, Decisions $decisions): array
    {
        $totals = [];

        foreach ($decisions->openDayParts as $part) {
            $totals[$part->value] = ['part' => $part, 'potential' => 0, 'demand' => 0, 'capacity' => 0, 'covers' => 0, 'revenue' => 0];
        }

        foreach ($days as $day) {
            foreach ($day->dayParts as $result) {
                $totals[$result->dayPart->value] ??= ['part' => $result->dayPart, 'potential' => 0, 'demand' => 0, 'capacity' => 0, 'covers' => 0, 'revenue' => 0];
                $t = &$totals[$result->dayPart->value];
                $t['potential'] += $result->potentialCustomers;
                $t['demand'] += $result->demand;
                $t['capacity'] += $result->capacity;
                $t['covers'] += $result->covers;
                $t['revenue'] += $result->revenueCents;
                unset($t);
            }
        }

        return array_values(array_map(fn (array $t) => new DayPartResult($t['part'], $t['potential'], $t['demand'], $t['capacity'], $t['covers'], $t['revenue']), $totals));
    }
}
