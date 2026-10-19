<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Vps;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One entitlement/usage pair for display (e.g. "3 of 10 servers",
 * "124 of 500 GB bandwidth"). Values are supplied by the adapter from
 * real provider reads — never defaults or fabrications; an unknown
 * entitlement is represented as `null`, not zero.
 */
final readonly class QuotaUsage
{
    public function __construct(
        public string $metric,
        public int $used,
        public ?int $entitled,
        public string $unit = '',
    ) {
        if (trim($metric) === '') {
            throw new InvalidArgumentException('A quota needs a metric name.');
        }
        if ($used < 0 || ($entitled !== null && $entitled < 0)) {
            throw new InvalidArgumentException('Quota values cannot be negative.');
        }
    }

    /** Percent used, or null when the entitlement is unknown. */
    public function percentUsed(): ?float
    {
        if ($this->entitled === null || $this->entitled === 0) {
            return null;
        }
        return $this->used / $this->entitled * 100;
    }

    /** Whether the entitlement is known and exhausted. */
    public function exhausted(): bool
    {
        return $this->entitled !== null && $this->used >= $this->entitled;
    }
}
