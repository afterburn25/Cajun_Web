<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Retry/throttle policy for a gateway. Bounded attempts with a fixed
 * backoff — retries must stay short because requests run inside a
 * shared-host PHP request, never in a daemon.
 */
final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts = 3,
        public float $backoffSeconds = 0.5,
        public float $timeoutSeconds = 15.0,
    ) {
        if ($maxAttempts < 1 || $maxAttempts > 5) {
            throw new InvalidArgumentException('Retry attempts must be between 1 and 5.');
        }
        if (!is_finite($backoffSeconds) || $backoffSeconds < 0 || $backoffSeconds > 10) {
            throw new InvalidArgumentException('Retry backoff must be a finite number between 0 and 10 seconds.');
        }
        if (!is_finite($timeoutSeconds) || $timeoutSeconds <= 0 || $timeoutSeconds > 120) {
            throw new InvalidArgumentException('Provider timeout must be a finite number between 0 and 120 seconds.');
        }
    }
}
