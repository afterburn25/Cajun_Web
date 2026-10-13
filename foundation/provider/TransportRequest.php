<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One outbound provider HTTP request. Provider traffic must be TLS:
 * non-HTTPS URLs and URLs carrying userinfo are rejected before any
 * transport sees them.
 */
final readonly class TransportRequest
{
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'];

    public string $method;

    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        string $method,
        public string $url,
        public array $headers = [],
        public ?string $body = null,
        public float $timeoutSeconds = 15.0,
    ) {
        $method = strtoupper(trim($method));
        if (!in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException('Unsupported provider request method: ' . $method);
        }
        $this->method = $method;
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new InvalidArgumentException('Provider requests require an https:// URL.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Provider request URLs must not embed credentials.');
        }
        if (!is_finite($timeoutSeconds) || $timeoutSeconds <= 0 || $timeoutSeconds > 120) {
            throw new InvalidArgumentException('Provider request timeout must be a finite number between 0 and 120 seconds.');
        }
    }
}
