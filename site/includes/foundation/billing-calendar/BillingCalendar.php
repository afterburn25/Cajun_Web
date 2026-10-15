<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\BillingCalendar;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/** Calendar dates only: no invoice, clock, timezone or payment authorization. */
final readonly class BillingCalendar
{
    public const MAX_INDEX = 119999;
    public const MAX_WINDOW = 120;

    public string $anchor;
    public string $cadence;
    public string $alignment;
    private int $year;
    private int $month;
    private int $day;
    private int $months;

    public function __construct(mixed $anchor, mixed $cadence, mixed $alignment)
    {
        [$year, $month, $day] = self::date($anchor);
        if (!is_string($cadence) || !in_array($cadence, ['monthly', 'quarterly', 'annually'], true)) {
            throw new InvalidArgumentException('Choose a supported recurring cadence.');
        }
        if (!is_string($alignment) || !in_array($alignment, ['day-of-month', 'month-end'], true)) {
            throw new InvalidArgumentException('Choose an explicit billing date alignment.');
        }
        if ($alignment === 'month-end' && $day !== self::daysInMonth($year, $month)) {
            throw new InvalidArgumentException('Month-end billing must start on a month-end date.');
        }
        $this->anchor = $anchor;
        $this->cadence = $cadence;
        $this->alignment = $alignment;
        $this->year = $year;
        $this->month = $month;
        $this->day = $day;
        $this->months = match ($cadence) { 'monthly' => 1, 'quarterly' => 3, 'annually' => 12 };
    }

    /** @return array{index:int,start:string,end:string} End is exclusive. */
    public function period(mixed $index): array
    {
        self::index($index);
        return ['index' => $index, 'start' => $this->boundary($index), 'end' => $this->boundary($index + 1)];
    }

    /** @return array{index:int,start:string,end:string}|null */
    public function periodAt(mixed $date): ?array
    {
        [$year, $month] = self::date($date);
        if ($date < $this->anchor) {
            return null;
        }
        $elapsedMonths = ($year - $this->year) * 12 + $month - $this->month;
        $index = intdiv($elapsedMonths, $this->months);
        if ($date < $this->boundary($index)) {
            --$index;
        }
        return $this->period($index);
    }

    /** @return list<array{index:int,start:string,end:string}> */
    public function window(mixed $first, mixed $count): array
    {
        self::index($first);
        if (!is_int($count) || $count < 1 || $count > self::MAX_WINDOW) {
            throw new InvalidArgumentException('Choose between 1 and 120 billing periods.');
        }
        if ($count > self::MAX_INDEX - $first + 1) {
            throw new InvalidArgumentException('Billing period range is too large.');
        }
        // Check the whole range before constructing a result; never return a partial window.
        $this->boundary($first + $count);
        $periods = [];
        for ($index = $first; $index < $first + $count; ++$index) {
            $periods[] = $this->period($index);
        }
        return $periods;
    }

    private static function index(mixed $index): void
    {
        if (!is_int($index) || $index < 0 || $index > self::MAX_INDEX) {
            throw new InvalidArgumentException('Billing period index is outside the supported range.');
        }
    }

    private function boundary(int $index): string
    {
        // Always advance from the original month/day, never from a previously clamped date.
        $absoluteMonth = $this->year * 12 + $this->month - 1 + $index * $this->months;
        if ($absoluteMonth > 9999 * 12 + 11) {
            throw new InvalidArgumentException('Billing period exceeds the supported date range.');
        }
        $year = intdiv($absoluteMonth, 12);
        $month = $absoluteMonth % 12 + 1;
        $lastDay = self::daysInMonth($year, $month);
        $day = $this->alignment === 'month-end' ? $lastDay : min($this->day, $lastDay);
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /** @return array{int,int,int} */
    private static function date(mixed $date): array
    {
        if (!is_string($date) || !preg_match('/^([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})$/D', $date, $parts)) {
            throw new InvalidArgumentException('Use an exact supported calendar date.');
        }
        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $day = (int) $parts[3];
        if ($month < 1 || $month > 12 || $day < 1 || $day > self::daysInMonth($year, $month)) {
            throw new InvalidArgumentException('Use a real calendar date.');
        }
        return [$year, $month, $day];
    }

    private static function daysInMonth(int $year, int $month): int
    {
        return match ($month) {
            2 => $year % 400 === 0 || ($year % 4 === 0 && $year % 100 !== 0) ? 29 : 28,
            4, 6, 9, 11 => 30,
            default => 31,
        };
    }
}
