<?php

use App\Simulation\Calendar\TradingCalendar;
use App\Simulation\Data\CalendarDate;
use App\Simulation\Data\DayPart;
use Tests\Support\SimulationFixtures;

function tradingCalendar(): TradingCalendar
{
    return new TradingCalendar(SimulationFixtures::sheet());
}

it('finds Easter', function () {
    expect(TradingCalendar::easterSunday(2026)->toString())->toBe('2026-04-05')
        ->and(TradingCalendar::easterSunday(2027)->toString())->toBe('2027-03-28')
        ->and(TradingCalendar::easterSunday(2025)->toString())->toBe('2025-04-20')
        ->and(TradingCalendar::easterSunday(2038)->toString())->toBe('2038-04-25');
});

it('knows Zaragoza\'s public holidays, which trade like a Sunday', function () {
    $calendar = tradingCalendar();

    foreach (['2026-01-29', '2026-03-05', '2026-04-02', '2026-04-03', '2026-04-23', '2026-10-12', '2026-12-08'] as $holiday) {
        expect($calendar->isHoliday(CalendarDate::parse($holiday)))->toBeTrue($holiday)
            ->and($calendar->tradesAs(CalendarDate::parse($holiday)))->toBe('sun');
    }

    expect($calendar->isHoliday(CalendarDate::parse('2026-04-06')))->toBeFalse()
        ->and($calendar->tradesAs(CalendarDate::parse('2026-10-07')))->toBe('wed');
});

it('puts the Pilar in the nine days ending on the first Sunday from 12 October', function (int $year, string $from, string $to) {
    $calendar = tradingCalendar();
    $first = CalendarDate::parse($from);
    $last = CalendarDate::parse($to);

    expect($calendar->isPilar($first))->toBeTrue()
        ->and($calendar->isPilar($last))->toBeTrue()
        ->and($calendar->isPilar($first->addDays(-1)))->toBeFalse()
        ->and($calendar->isPilar($last->addDays(1)))->toBeFalse()
        ->and($last->weekday())->toBe(7);
})->with([
    [2023, '2023-10-07', '2023-10-15'],
    [2024, '2024-10-05', '2024-10-13'],
    [2025, '2025-10-04', '2025-10-12'],
    [2026, '2026-10-10', '2026-10-18'],
]);

it('shares out a month so that its days add up to the days in it', function (int $year, int $month) {
    $calendar = tradingCalendar();
    $first = new CalendarDate($year, $month, 1);

    foreach (DayPart::cases() as $part) {
        $sum = 0.0;

        for ($d = 0; $d < $first->daysInMonth(); $d++) {
            $sum += $calendar->dayWeight($first->addDays($d), $part);
        }

        expect($sum)->toEqualWithDelta($first->daysInMonth(), 1e-9);
    }
})->with([[2026, 10], [2027, 2], [2027, 3], [2028, 8]]);

it('makes Saturday evenings busier than Monday evenings, and the Pilar busiest', function () {
    $calendar = tradingCalendar();
    $monday = CalendarDate::parse('2026-11-16');
    $saturday = CalendarDate::parse('2026-11-21');

    expect($calendar->dayWeight($saturday, DayPart::Evening))->toBeGreaterThan(1.5 * $calendar->dayWeight($monday, DayPart::Evening))
        ->and($calendar->dayWeight(CalendarDate::parse('2026-10-14'), DayPart::Evening))
        ->toBeGreaterThan($calendar->dayWeight(CalendarDate::parse('2026-10-28'), DayPart::Evening) * 1.5);
});

it('closes the quietest weekdays for the hours the café opens', function () {
    $calendar = tradingCalendar();
    $daytime = SimulationFixtures::decisions()->with(openDayParts: [DayPart::Morning, DayPart::Lunch, DayPart::Afternoon]);

    expect($calendar->closedWeekdays($daytime->with(openDaysPerWeek: 7)))->toBe([])
        ->and($calendar->closedWeekdays($daytime->with(openDaysPerWeek: 6)))->toBe(['mon'])
        ->and($calendar->closedWeekdays($daytime->with(openDaysPerWeek: 5)))->toBe(['mon', 'tue'])
        ->and($calendar->isOpen(CalendarDate::parse('2026-10-05'), $daytime->with(openDaysPerWeek: 6)))->toBeFalse()
        // A holiday trades as a Sunday, and the café is open on Sundays.
        ->and($calendar->isOpen(CalendarDate::parse('2026-10-12'), $daytime->with(openDaysPerWeek: 6)))->toBeTrue()
        ->and($calendar->openDaysInMonth(2026, 11, $daytime->with(openDaysPerWeek: 6)))->toBe(25);
});

it('counts the busier open days in the monthly engine', function () {
    $calendar = tradingCalendar();
    $decisions = SimulationFixtures::decisions();

    expect($calendar->weekdayFactor($decisions->with(openDaysPerWeek: 7), DayPart::Lunch))->toEqualWithDelta(1.0, 1e-9)
        ->and($calendar->weekdayFactor($decisions->with(openDaysPerWeek: 6), DayPart::Lunch))->toBeGreaterThan(1.0)
        ->and($calendar->weekdayFactor($decisions->with(openDaysPerWeek: 6), DayPart::Lunch))->toBeLessThan(1.05);
});
