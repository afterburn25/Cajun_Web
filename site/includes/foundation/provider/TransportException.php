<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use RuntimeException;
use Throwable;

/**
 * A connection-level transport failure (DNS, TLS handshake, connect or
 * read timeout, HTTP/2 protocol fault).
 *
 * The exception carries only a finite classification kind — never raw
 * transport text. Transports keep their detailed diagnostics internal;
 * anything reaching this type is destined for durable results and
 * staff-visible output, so URLs, credential fragments, response text
 * and provider internals are deliberately unrepresentable.
 */
final class TransportException extends RuntimeException
{
    public const KIND_CONNECT = 'connect';
    public const KIND_TIMEOUT = 'timeout';
    public const KIND_TLS = 'tls';
    public const KIND_PROTOCOL = 'protocol';
    public const KIND_UNAVAILABLE = 'unavailable';
    public const KIND_UNKNOWN = 'unknown';

    private const KINDS = [
        self::KIND_CONNECT,
        self::KIND_TIMEOUT,
        self::KIND_TLS,
        self::KIND_PROTOCOL,
        self::KIND_UNAVAILABLE,
        self::KIND_UNKNOWN,
    ];

    public readonly string $kind;

    /**
     * @param string $kind One of the KIND_* constants; unrecognized
     *        values collapse to 'unknown' rather than leaking detail.
     */
    public function __construct(string $kind = self::KIND_UNKNOWN, int $code = 0, ?Throwable $previous = null)
    {
        $this->kind = in_array($kind, self::KINDS, true) ? $kind : self::KIND_UNKNOWN;
        parent::__construct('provider transport failure: ' . $this->kind, $code, $previous);
    }
}
