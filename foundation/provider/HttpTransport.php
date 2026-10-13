<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Thin outbound HTTP boundary. The deployment adapter implements this
 * over cURL with verified TLS; tests implement it over scripted
 * fixtures. Connection-level failures (DNS, TLS, connect timeout) are
 * signalled by throwing TransportException — HTTP error statuses are
 * returned as TransportResponse, not thrown.
 */
interface HttpTransport
{
    /**
     * @throws TransportException On a connection-level failure.
     */
    public function send(TransportRequest $request): TransportResponse;
}
