<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailTransport;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use RuntimeException;

/**
 * One classified transport failure. `kind` is a stable machine-readable
 * class the adapter maps to `ConnectionResult` errors; the message is a
 * sanitized operator-facing string — it never contains credentials or raw
 * server payloads beyond a bounded snippet.
 */
final class MailTransportException extends RuntimeException
{
    public const CONNECT = 'connect';
    public const TLS = 'tls';
    public const AUTH = 'auth';
    public const PROTOCOL = 'protocol';
    public const TIMEOUT = 'timeout';
    public const CLOSED = 'closed';
    public const REFUSED = 'refused';

    public function __construct(
        private string $kind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function kind(): string
    {
        return $this->kind;
    }
}
