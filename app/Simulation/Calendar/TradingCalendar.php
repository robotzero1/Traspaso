<?php

namespace App\Simulation\Calendar;

use App\Simulation\Data\CalendarDate;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\Decisions;
use App\Simulation\Data\ParameterSheet;

/**
 * The real calendar as trade sees it: weekdays, public holidays (which
 * trade like a Sunday) and the Fiestas del Pilar. Gives each day its
 * share of the month's trade, per day part, and says which days a café
 * open fewer than seven days a week closes (its quietest).
 *
 * A day's weight is its weekday weight (times the Pilar weight in fiesta
 * days) divided by the average over the whole month, so the weights of
 * every day in a month add up to the number of days in it: a café open
 * every day sells what the monthly engine says.
 */
final class TradingCalendar
{
    /** ISO-8601 weekday number → config key. */
    public const WEEKDAYS = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];

    /** @var array<string, float> monthly average weight, keyed "Y-m:part" */
    private array $monthMeans = [];

    /** @var array<int, list<string>> holidays per year, as "m-d" */
    private array $holidays = [];

    /** @var array<int, array{0: int, 1: int}> the Pilar's first and last day per year, as days since 1970 */
    private array $pilar = [];

    /** @var array<string, list<string>> closed weekdays, keyed by day parts and days open */
    private array $closed = [];

    /** @var array<string, int> */
    private array $openDays = [];

    public function __construct(private readonly ParameterSheet $sheet) {}

    public function isHoliday(CalendarDate $date): bool
    {
        $this->holidays[$date->year] ??= $this->holidaysIn($date->year);

        return in_array($date->monthDay(), $this->holidays[$date->year], true);
    }

    /** The weekday the date trades as: a public holiday trades like a Sunday. */
    public function tradesAs(CalendarDate $date): string
    {
        return $this->isHoliday($date) ? 'sun' : self::WEEKDAYS[$date->weekday()];
    }

    public function isPilar(CalendarDate $date): bool
    {
        if (! isset($this->pilar[$date->year])) {
            $anchor = CalendarDate::parse("{$date->year}-".$this->sheet->get('pilar.anchor_month_day'));
            // The fiestas end on the first Sunday on or after the anchor.
            $end = $anchor->addDays((7 - $anchor->weekday()) % 7)->toDays();
            $this->pilar[$date->year] = [$end - $this->sheet->int('pilar.days') + 1, $end];
        }

        [$start, $end] = $this->pilar[$date->year];
        $day = $date->toDays();

        return $day >= $start && $day <= $end;
    }

    /** The day's trade relative to an average day of its month, for a day part. */
    public function dayWeight(CalendarDate $date, DayPart $part): float
    {
        $key = "{$date->year}-{$date->month}:{$part->value}";

        if (! isset($this->monthMeans[$key])) {
            $first = $date->firstOfMonth();
            $days = $date->daysInMonth();
            $sum = 0.0;

            for ($d = 0; $d < $days; $d++) {
                $sum += $this->rawWeight($first->addDays($d), $part);
            }

            $this->monthMeans[$key] = $sum / $days;
        }

        return $this->rawWeight($date, $part) / $this->monthMeans[$key];
    }

    /**
     * The weekdays a café open fewer than seven days a week closes: the
     * quietest for the day parts it opens (ties: earlier in the week).
     *
     * @return list<string>
     */
    public function closedWeekdays(Decisions $decisions): array
    {
        $key = $this->decisionsKey($decisions);

        if (isset($this->closed[$key])) {
            return $this->closed[$key];
        }

        $totals = [];

        foreach (self::WEEKDAYS as $weekday) {
            $totals[$weekday] = array_sum(array_map(fn (DayPart $p) => $this->weekdayWeight($weekday, $p), $decisions->openDayParts));
        }

        // asort is stable, so ties keep the week's order.
        asort($totals);

        return $this->closed[$key] = array_slice(array_keys($totals), 0, 7 - $decisions->openDaysPerWeek);
    }

    public function isOpen(CalendarDate $date, Decisions $decisions): bool
    {
        return ! in_array($this->tradesAs($date), $this->closedWeekdays($decisions), true);
    }

    public function openDaysInMonth(int $year, int $month, Decisions $decisions): int
    {
        $key = "{$year}-{$month}:".$this->decisionsKey($decisions);

        if (isset($this->openDays[$key])) {
            return $this->openDays[$key];
        }

        $first = new CalendarDate($year, $month, 1);
        $open = 0;

        for ($d = 0; $d < $first->daysInMonth(); $d++) {
            $open += $this->isOpen($first->addDays($d), $decisions) ? 1 : 0;
        }

        return $this->openDays[$key] = $open;
    }

    /**
     * For the monthly engine: an open day's trade relative to an average
     * day of the week, given which weekdays the café closes. Closing the
     * quietest day leaves open days a little busier than average.
     */
    public function weekdayFactor(Decisions $decisions, DayPart $part): float
    {
        $closed = $this->closedWeekdays($decisions);
        $all = 0.0;
        $open = 0.0;

        foreach (self::WEEKDAYS as $weekday) {
            $weight = $this->weekdayWeight($weekday, $part);
            $all += $weight;
            $open += in_array($weekday, $closed, true) ? 0.0 : $weight;
        }

        return ($open / $decisions->openDaysPerWeek) / ($all / 7);
    }

    private function decisionsKey(Decisions $decisions): string
    {
        return implode(',', array_map(fn (DayPart $p) => $p->value, $decisions->openDayParts)).'/'.$decisions->openDaysPerWeek;
    }

    private function rawWeight(CalendarDate $date, DayPart $part): float
    {
        return $this->weekdayWeight($this->tradesAs($date), $part)
            * ($this->isPilar($date) ? $this->sheet->float("pilar.weights.{$part->value}") : 1.0);
    }

    private function weekdayWeight(string $weekday, DayPart $part): float
    {
        return $this->sheet->float("day_of_week.weights.{$part->value}.{$weekday}");
    }

    /** @return list<string> */
    private function holidaysIn(int $year): array
    {
        $holidays = $this->sheet->array('holidays.fixed');
        $easter = self::easterSunday($year);
        $moving = ['holy_thursday' => -3, 'good_friday' => -2, 'easter_monday' => 1];

        foreach ($this->sheet->array('holidays.easter') as $name) {
            $holidays[] = $easter->addDays($moving[$name])->monthDay();
        }

        return $holidays;
    }

    /** Easter Sunday in the Gregorian calendar (anonymous Gregorian algorithm). */
    public static function easterSunday(int $year): CalendarDate
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = ($h + $l - 7 * $m + 114) % 31 + 1;

        return new CalendarDate($year, $month, $day);
    }
}
