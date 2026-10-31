<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SupportAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use LogicException;
use Throwable;

/**
 * Honest refusal of a support-session launch. The reason string is a
 * stable machine-readable code (`grant-revoked`, `client-session-limit`,
 * ...) the adapter can map to a user-facing message — never a silent
 * failure and never a simulated session.
 */
final class SupportDenied extends LogicException
{
    public function __construct(
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Support access denied: ' . $reason, 0, $previous);
    }
}
