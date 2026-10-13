<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Scheduling;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Booking constraints supplied by the adapter's admin configuration: how
 * long a consultation lasts, the gap kept between bookings, and how far
 * ahead a request must be made. All values are seconds except slotMinutes.
 */
final readonly class SchedulePolicy
{
    public function __construct(
        public int $slotMinutes,
        public int $bufferSeconds = 0,
        public int $minNoticeSeconds = 0,
    ) {
        if ($slotMinutes <= 0 || $slotMinutes > 480) {
            throw new InvalidArgumentException('A consultation slot must be 1–480 minutes.');
        }
        if ($bufferSeconds < 0 || $minNoticeSeconds < 0) {
            throw new InvalidArgumentException('Buffers and notice periods cannot be negative.');
        }
    }

    public function slotSeconds(): int
    {
        return $this->slotMinutes * 60;
    }
}
