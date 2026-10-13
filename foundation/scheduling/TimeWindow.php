<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Scheduling;

use InvalidArgumentException;

/**
 * A half-open UTC interval [startsAt, endsAt) as unix timestamps. Windows are
 * the currency of the calendar: availability, buffers and bookings are all
 * expressed the same way so overlap checks stay exact.
 */
final readonly class TimeWindow
{
    public function __construct(
        public int $startsAt,
        public int $endsAt,
    ) {
        if ($startsAt < 0 || $endsAt <= $startsAt) {
            throw new InvalidArgumentException('A time window needs a nonnegative start before its end.');
        }
    }

    public function contains(TimeWindow $inner): bool
    {
        return $this->startsAt <= $inner->startsAt && $inner->endsAt <= $this->endsAt;
    }

    public function overlaps(TimeWindow $other): bool
    {
        return $this->startsAt < $other->endsAt && $other->startsAt < $this->endsAt;
    }

    /** Extend both ends outward for buffer checks. */
    public function withMargin(int $seconds): self
    {
        return new self(max(0, $this->startsAt - $seconds), $this->endsAt + $seconds);
    }
}
