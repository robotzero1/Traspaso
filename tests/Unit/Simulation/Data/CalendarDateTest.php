<?php

use App\Simulation\Data\CalendarDate;

it('parses and prints dates', function () {
    $date = CalendarDate::parse('2026-10-07');

    expect([$date->year, $date->month, $date->day])->toBe([2026, 10, 7])
        ->and($date->toString())->toBe('2026-10-07')
        ->and((string) $date)->toBe('2026-10-07')
        ->and($date->monthDay())->toBe('10-07');
});

it('rejects dates that do not exist', function (string $date) {
    CalendarDate::parse($date);
})->throws(InvalidArgumentException::class)->with(['2026-02-29', '2026-13-01', '2026-04-31', '7 Oct 2026']);

it('knows leap years and month lengths', function () {
    expect(CalendarDate::daysIn(2028, 2))->toBe(29)
        ->and(CalendarDate::daysIn(2100, 2))->toBe(28)
        ->and(CalendarDate::daysIn(2000, 2))->toBe(29)
        ->and(CalendarDate::daysIn(2026, 4))->toBe(30)
        ->and(CalendarDate::parse('2026-12-31')->isLastOfMonth())->toBeTrue();
});

it('works out weekdays', function () {
    // 7 October 2026 is a Wednesday; 1 January 1970 a Thursday; 29 February 2000 a Tuesday.
    expect(CalendarDate::parse('2026-10-07')->weekday())->toBe(3)
        ->and(CalendarDate::parse('1970-01-01')->weekday())->toBe(4)
        ->and(CalendarDate::parse('2000-02-29')->weekday())->toBe(2)
        ->and(CalendarDate::parse('1969-12-28')->weekday())->toBe(7);
});

it('adds days and months across month and year ends', function () {
    expect(CalendarDate::parse('2026-12-31')->addDays(1)->toString())->toBe('2027-01-01')
        ->and(CalendarDate::parse('2028-02-28')->addDays(1)->toString())->toBe('2028-02-29')
        ->and(CalendarDate::parse('2026-03-01')->addDays(-1)->toString())->toBe('2026-02-28')
        ->and(CalendarDate::parse('2026-10-15')->addMonths(3)->toString())->toBe('2027-01-01')
        ->and(CalendarDate::parse('2026-01-01')->daysUntil(CalendarDate::parse('2027-01-01')))->toBe(365);
});

it('round-trips every day over four centuries', function () {
    $date = CalendarDate::parse('1900-01-01');

    for ($days = $date->toDays(), $end = CalendarDate::parse('2300-01-01')->toDays(); $days < $end; $days += 97) {
        expect(CalendarDate::fromDays($days)->toDays())->toBe($days);
    }
});
