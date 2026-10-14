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
 *
 * Concrete implementations must: verify TLS certificates and hostnames
 * against the system trust store; resolve the destination to a public
 * address (rejecting private/link-local DNS results); never follow
 * redirects or honor proxy configuration; enforce bounded connect,
 * total and read deadlines and a capped response body; and never
 * surface request credential headers in the response or any thrown
 * exception. A fault thrown after the request may have been sent must
 * use a post-send kind (timeout/protocol/unknown), not connect.
 */
interface HttpTransport
{
    /**
     * @throws TransportException On a connection-level failure.
     */
    public function send(TransportRequest $request): TransportResponse;
}
