<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\ReviewRequests;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A normalized address for the visitor's existing website. The component
 * validates and normalizes only; no network access happens here. Any later
 * adapter that fetches the site must additionally resolve DNS and re-check
 * the resolved addresses, because literal-address checks alone cannot stop
 * rebinding or redirect-based SSRF.
 */
final readonly class WebsiteAddress
{
    /** Loopback, private, link-local, multicast and unspecified ranges are never fetchable targets. */
    private const BLOCKED_HOSTS = ['localhost', 'localhost.'];
    private const BLOCKED_SUFFIXES = ['.localhost', '.local', '.internal', '.test', '.example', '.invalid'];

    public string $scheme;
    public string $host;
    public int $port;
    public string $path;
    public ?string $query;

    /**
     * @param string $address Raw visitor input; a bare host or host/path is accepted and normalized to https.
     */
    public function __construct(string $address)
    {
        $address = trim($address);
        if ($address === '') {
            throw new InvalidArgumentException('A review request needs the existing website address.');
        }
        // Byte-length limit via strlen() — the installer does not require
        // mbstring, and a byte cap is consistent with HTTP input limits.
        if (strlen($address) > 2048) {
            throw new InvalidArgumentException('The website address is too long.');
        }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $address)) {
            $address = 'https://' . $address;
        }

        $parts = parse_url($address);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('The website address is not a readable URL.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('The website address must not embed credentials.');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http or https website addresses can be reviewed.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === '' || !self::isPlausibleHost($host)) {
            throw new InvalidArgumentException('The website address needs a valid host name.');
        }
        if (isset($parts['fragment'])) {
            throw new InvalidArgumentException('The website address must not contain a fragment.');
        }

        $this->scheme = $scheme;
        $this->host = $host;
        $this->port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $this->path = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];
        $this->query = $parts['query'] ?? null;
    }

    /** Normalized absolute URL without default ports or fragments. */
    public function url(): string
    {
        $default = $this->scheme === 'https' ? 443 : 80;
        $port = $this->port === $default ? '' : ':' . $this->port;
        $url = "{$this->scheme}://{$this->host}{$port}{$this->path}";
        return $this->query === null ? $url : "{$url}?{$this->query}";
    }

    /**
     * Whether the host is even a candidate for server-side fetching. Literal
     * private/reserved addresses and well-known internal names are rejected
     * outright; a real adapter must still verify resolved IPs per request.
     */
    public function isFetchCandidate(): bool
    {
        if (in_array($this->host, self::BLOCKED_HOSTS, true)) {
            return false;
        }
        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if (str_ends_with($this->host, $suffix)) {
                return false;
            }
        }
        $ipv4 = filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $ipv6 = filter_var(trim($this->host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        if ($ipv4 !== false) {
            // PHP's NO_RES_RANGE does not cover 224.0.0.0/4 multicast.
            if (self::isMulticastPacked(inet_pton($this->host))) {
                return false;
            }
            return filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        if ($ipv6 !== false) {
            // ff00::/8 multicast is likewise outside the reserved-range flag.
            if (self::isMulticastPacked(inet_pton(trim($this->host, '[]')))) {
                return false;
            }
            return filter_var(trim($this->host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        return true;
    }

    /** IPv4 224.0.0.0/4 (first octet 224–239) or IPv6 ff00::/8 (first byte 0xff). */
    private static function isMulticastPacked(string $packed): bool
    {
        return match (strlen($packed)) {
            4 => ord($packed[0]) >= 224 && ord($packed[0]) <= 239,
            16 => ord($packed[0]) === 0xff,
            default => false,
        };
    }

    private static function isPlausibleHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return true;
        }
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return str_starts_with($host, '[') && str_ends_with($host, ']') ? true : str_contains($host, ':');
        }
        if (strlen($host) > 253) {
            return false;
        }
        foreach (explode('.', $host) as $label) {
            if ($label === '' || strlen($label) > 63 || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label)) {
                return false;
            }
        }
        return true;
    }
}
