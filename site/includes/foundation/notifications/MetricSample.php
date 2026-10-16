<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Notifications;

use InvalidArgumentException;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * P-07 — a timestamped hosting-usage/health metric with honest freshness.
 *
 * The roadmap requires actual progress and verified health/usage timestamps,
 * marking stale or absent metrics honestly rather than showing invented
 * "current" numbers. This sample carries its `checkedAt` and reports one of
 * three states — fresh / stale / absent — never a fabricated value.
 *
 * @internal Roadmap P-07; additively inserted.
 */
final readonly class MetricSample
{
    public const STATUS_FRESH = 'fresh';
    public const STATUS_STALE = 'stale';
    public const STATUS_ABSENT = 'absent';

    /**
     * @param ?string $value     Observed value ('82%', 'warn'), or null when no
     *                           sample exists — absent is representable.
     * @param ?int    $checkedAt When the value was actually verified; null when
     *                           nothing has ever been measured.
     */
    public function __construct(
        public string $kind,
        public ?string $value,
        public ?int $checkedAt,
    ) {
        if (trim($kind) === '') {
            throw new InvalidArgumentException('Metric kind must not be empty.');
        }
        if ($checkedAt === null && $value !== null) {
            throw new InvalidArgumentException('A metric value requires its checkedAt timestamp.');
        }
    }

    public function status(int $now, int $maxAgeSeconds): string
    {
        if ($maxAgeSeconds < 1) {
            throw new InvalidArgumentException('maxAgeSeconds must be >= 1.');
        }
        if ($this->checkedAt === null) {
            return self::STATUS_ABSENT;
        }
        return ($now - $this->checkedAt) <= $maxAgeSeconds ? self::STATUS_FRESH : self::STATUS_STALE;
    }

    /**
     * The displayable value only when fresh; null for stale/absent so a
     * renderer must show the honest marker, not a number.
     */
    public function currentValue(int $now, int $maxAgeSeconds): ?string
    {
        return $this->status($now, $maxAgeSeconds) === self::STATUS_FRESH ? $this->value : null;
    }
}
