<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A provider HTTP response as delivered by the transport. The transport
 * is responsible for never exposing the request's credential headers in
 * the body or headers it returns to callers.
 */
final readonly class TransportResponse
{
    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Provider response status out of range: ' . $status);
        }
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** Whether the failure class is worth retrying later (429/5xx). */
    public function retryable(): bool
    {
        return $this->status === 429 || $this->status >= 500;
    }
}
