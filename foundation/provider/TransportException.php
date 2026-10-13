<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use RuntimeException;

/**
 * A connection-level transport failure (DNS, TLS handshake, connect or
 * read timeout). Always treated as retryable by the gateway; the
 * message must describe the failure class without echoing request
 * credentials.
 */
final class TransportException extends RuntimeException
{
}
