<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Scheduling;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * A recurring weekly availability window in a named IANA timezone, e.g.
 * "Tuesdays 09:00–12:00 America/Chicago". Expansion to concrete UTC windows
 * uses real timezone rules, so DST transitions shift the UTC times while the
 * local time the staff member configured stays put.
 */
final readonly class WeeklyRule
{
    public string $timeZone;

    /**
     * @param int $dayOfWeek 0 (Sunday) through 6 (Saturday), in the rule's timezone.
     * @param int $startMinute Minutes after local midnight, 0–1439.
     * @param int $endMinute Minutes after local midnight; may exceed 1439 only to mean end-of-day 1440.
     */
    public function __construct(
        public int $dayOfWeek,
        public int $startMinute,
        public int $endMinute,
        string $timeZone,
    ) {
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            throw new InvalidArgumentException('A weekly rule needs a day of week 0–6.');
        }
        if ($startMinute < 0 || $startMinute > 1439) {
            throw new InvalidArgumentException('A weekly rule start must be a minute of day 0–1439.');
        }
        if ($endMinute <= $startMinute || $endMinute > 1440) {
            throw new InvalidArgumentException('A weekly rule end must be after its start within the same day.');
        }
        $zone = self::zone($timeZone);
        $this->timeZone = $zone->getName();
    }

    /**
     * Every concrete UTC window this rule produces inside [rangeStart, rangeEnd).
     * Occurrences whose start OR end local minute does not exist (spring-forward
     * gaps) are skipped — normalizing an endpoint inside the gap would silently
     * lengthen or move the offered availability. Ambiguous fall-back times
     * resolve to the first occurrence.
     *
     * @return list<TimeWindow>
     */
    public function expand(int $rangeStart, int $rangeEnd): array
    {
        if ($rangeEnd <= $rangeStart || $rangeStart < 0) {
            throw new InvalidArgumentException('Expansion needs a nonnegative range start before its end.');
        }
        $zone = new DateTimeZone($this->timeZone);
        $cursor = (new DateTimeImmutable('@' . $rangeStart))->setTimezone($zone)->setTime(0, 0);
        $endLocal = (new DateTimeImmutable('@' . $rangeEnd))->setTimezone($zone);

        $windows = [];
        $localMinute = static fn (DateTimeImmutable $t): int => (int) $t->format('H') * 60 + (int) $t->format('i');
        // Walk local days; each matching day yields at most one window.
        while ($cursor->getTimestamp() <= $endLocal->getTimestamp() + 86400) {
            if ((int) $cursor->format('w') === $this->dayOfWeek) {
                $dayStart = $cursor;
                $start = $dayStart->setTime(0, 0)->modify("+{$this->startMinute} minutes");
                $end = $dayStart->setTime(0, 0)->modify("+{$this->endMinute} minutes");
                // Skip occurrences swallowed by a DST gap at either endpoint:
                // after a spring forward the requested local time may not
                // exist, so the resolved instant lands on a different local
                // minute. Checking only the start would let an end inside the
                // gap silently stretch the window.
                $startOk = $localMinute($start) === $this->startMinute;
                $endOk = $localMinute($end) === $this->endMinute % 1440;
                if ($startOk && $endOk && $end->getTimestamp() > $start->getTimestamp()) {
                    $window = new TimeWindow($start->getTimestamp(), $end->getTimestamp());
                    if ($window->overlaps(new TimeWindow($rangeStart, $rangeEnd))) {
                        $windows[] = $window;
                    }
                }
            }
            $cursor = $cursor->modify('+1 day');
        }
        return $windows;
    }

    /** Validate an IANA identifier; unknown names are rejected, never guessed. */
    public static function zone(string $name): DateTimeZone
    {
        $name = trim($name);
        if ($name === '' || !in_array($name, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('A scheduling timezone must be a real IANA identifier.');
        }
        return new DateTimeZone($name);
    }
}
