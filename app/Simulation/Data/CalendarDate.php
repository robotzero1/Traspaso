<?php

namespace App\Simulation\Data;

use InvalidArgumentException;

/**
 * A day in the Gregorian calendar, with no time or time zone. The engine
 * can't use PHP's DateTime classes (they can read the clock), so dates are
 * passed in as these and all the arithmetic is done here.
 */
final readonly class CalendarDate
{
    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
        if ($month < 1 || $month > 12 || $day < 1 || $day > self::daysIn($year, $month)) {
            throw new InvalidArgumentException(sprintf('%04d-%02d-%02d is not a date.', $year, $month, $day));
        }
    }

    /** From "YYYY-MM-DD". */
    public static function parse(string $date): self
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) !== 1) {
            throw new InvalidArgumentException("[{$date}] is not a YYYY-MM-DD date.");
        }

        return new self((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    public static function daysIn(int $year, int $month): int
    {
        return match ($month) {
            2 => ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0 ? 29 : 28,
            4, 6, 9, 11 => 30,
            default => 31,
        };
    }

    public function daysInMonth(): int
    {
        return self::daysIn($this->year, $this->month);
    }

    public function firstOfMonth(): self
    {
        return new self($this->year, $this->month, 1);
    }

    public function lastOfMonth(): self
    {
        return new self($this->year, $this->month, $this->daysInMonth());
    }

    public function isLastOfMonth(): bool
    {
        return $this->day === $this->daysInMonth();
    }

    /** ISO-8601 weekday: 1 = Monday … 7 = Sunday. */
    public function weekday(): int
    {
        // 1970-01-01 was a Thursday (4).
        return (($this->toDays() % 7) + 7 + 3) % 7 + 1;
    }

    public function addDays(int $days): self
    {
        return self::fromDays($this->toDays() + $days);
    }

    /** The first day of the month $months after this one's. */
    public function addMonths(int $months): self
    {
        $index = $this->year * 12 + $this->month - 1 + $months;

        return new self(intdiv($index, 12), $index % 12 + 1, 1);
    }

    public function daysUntil(self $other): int
    {
        return $other->toDays() - $this->toDays();
    }

    /** "MM-DD", for matching yearly dates such as holidays. */
    public function monthDay(): string
    {
        return sprintf('%02d-%02d', $this->month, $this->day);
    }

    public function toString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /** Days since 1970-01-01 (H. Hinnant's days_from_civil). */
    public function toDays(): int
    {
        $y = $this->month <= 2 ? $this->year - 1 : $this->year;
        $era = intdiv($y >= 0 ? $y : $y - 399, 400);
        $yoe = $y - $era * 400;
        $doy = intdiv(153 * ($this->month + ($this->month > 2 ? -3 : 9)) + 2, 5) + $this->day - 1;
        $doe = $yoe * 365 + intdiv($yoe, 4) - intdiv($yoe, 100) + $doy;

        return $era * 146097 + $doe - 719468;
    }

    public static function fromDays(int $days): self
    {
        $z = $days + 719468;
        $era = intdiv($z >= 0 ? $z : $z - 146096, 146097);
        $doe = $z - $era * 146097;
        $yoe = intdiv($doe - intdiv($doe, 1460) + intdiv($doe, 36524) - intdiv($doe, 146096), 365);
        $doy = $doe - (365 * $yoe + intdiv($yoe, 4) - intdiv($yoe, 100));
        $mp = intdiv(5 * $doy + 2, 153);
        $day = $doy - intdiv(153 * $mp + 2, 5) + 1;
        $month = $mp < 10 ? $mp + 3 : $mp - 9;

        return new self($yoe + $era * 400 + ($month <= 2 ? 1 : 0), $month, $day);
    }
}
