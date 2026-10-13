<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\MailTransport;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Conservative public-mail destinations. No network I/O or private-range override. */
final class EndpointPolicy
{
    public static function host(string $host): string
    {
        $host = strtolower($host);
        if (strlen($host) > 253 || !str_contains($host, '.') || str_ends_with($host, '.')
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new \InvalidArgumentException('host');
        }
        foreach (['localhost', 'local', 'internal', 'home', 'test', 'invalid', 'example', 'onion'] as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) throw new \InvalidArgumentException('host');
        }
        return $host;
    }

    public static function port(string $protocol, int $port, string $encryption): void
    {
        $expected = ['imap' => ['ssl' => 993, 'starttls' => 143], 'smtp' => ['ssl' => 465, 'starttls' => 587]];
        if (($expected[$protocol][$encryption] ?? null) !== $port) throw new \InvalidArgumentException('port');
    }

    private static function inRange(string $packed, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr); $network = inet_pton($network); $bits = (int)$bits;
        if (strlen($packed) !== strlen($network)) return false;
        $bytes = intdiv($bits, 8); $tail = $bits % 8;
        return substr($packed, 0, $bytes) === substr($network, 0, $bytes)
            && (!$tail || (ord($packed[$bytes]) & (0xff << (8 - $tail))) === (ord($network[$bytes]) & (0xff << (8 - $tail))));
    }

    public static function publicAddress(string $address): bool
    {
        if (!filter_var($address, FILTER_VALIDATE_IP)) return false;
        $packed = inet_pton($address);
        if (strlen($packed) === 4) {
            $blocked = ['0.0.0.0/8','10.0.0.0/8','100.64.0.0/10','127.0.0.0/8','169.254.0.0/16','172.16.0.0/12',
                '192.0.0.0/24','192.0.2.0/24','192.88.99.0/24','192.168.0.0/16','198.18.0.0/15','198.51.100.0/24','203.0.113.0/24','224.0.0.0/4','240.0.0.0/4'];
        } else {
            // Global-unicast only. Translation, mapped, local and multicast addresses are outside this range.
            if (!self::inRange($packed, '2000::/3')) return false;
            $blocked = ['2001::/23','2001:db8::/32','2002::/16','3fff::/20'];
        }
        foreach ($blocked as $cidr) if (self::inRange($packed, $cidr)) return false;
        return true;
    }

    /** Reject the entire answer set if even one destination is unsafe; no private/public fallback. */
    public static function addresses(array $addresses): array
    {
        if (!$addresses || count($addresses) > 32 || !array_is_list($addresses)) throw new \InvalidArgumentException('dns');
        $result = [];
        foreach ($addresses as $address) {
            if (!is_string($address) || !self::publicAddress($address)) throw new \InvalidArgumentException('destination');
            $normalized = inet_ntop(inet_pton($address)); $result[$normalized] = $normalized;
        }
        return array_values($result);
    }
}
