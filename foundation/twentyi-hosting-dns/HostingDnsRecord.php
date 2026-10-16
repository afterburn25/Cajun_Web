<?php
declare(strict_types=1);
namespace CajunWebPro\Foundation\TwentyIHostingDns;
if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Local review values use evidenced field names. This is neither a DNS response nor a wire record. */
final readonly class HostingDnsRecord
{
    public array $fields;

    public function __construct(public string $type, array $fields)
    {
        $keys = match ($type) {
            'A'=>['host','ip'], 'AAAA'=>['host','ipv6'], 'CNAME'=>['reuseId','host','target'],
            'MX'=>['host','target','pri'], 'TXT'=>['host','txt'], 'SRV'=>['host','pri','weight','port','target'],
            default=>throw new \InvalidArgumentException('This DNS proposal type is not supported.'),
        };
        $actual = array_keys($fields); sort($actual); $expected = $keys; sort($expected);
        if ($actual !== $expected) throw new \InvalidArgumentException('Unsupported DNS proposal fields.');
        foreach ($fields as $value) if (!is_string($value)) throw new \InvalidArgumentException('DNS proposal fields must be strings.');
        self::host($fields['host']);
        if ($type === 'A' && filter_var($fields['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) throw new \InvalidArgumentException('Use one IPv4 address.');
        if ($type === 'AAAA' && filter_var($fields['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) throw new \InvalidArgumentException('Use one IPv6 address.');
        if (isset($fields['target'])) HostingDnsContext::zoneName($fields['target']);
        foreach (['pri','weight','port'] as $key) if (isset($fields[$key])) self::uint16($fields[$key]);
        if ($type === 'CNAME') {
            HostingDnsContext::opaqueId($fields['reuseId']);
            if ($fields['host'] === '@') throw new \InvalidArgumentException('A CNAME proposal requires a subdomain.');
        }
        if ($type === 'TXT' && ($fields['txt'] === '' || strlen($fields['txt']) > 4096 || preg_match('//u', $fields['txt']) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $fields['txt']))) throw new \InvalidArgumentException('Use bounded DNS text without control characters.');
        // Canonical local field order, preserving the exact accepted values.
        $canonical = [];
        foreach ($keys as $key) $canonical[$key] = $fields[$key];
        $this->fields = $canonical;
    }

    /** Local relative-name subset supports service labels; no wildcard, absolute name or escaping. */
    public static function host(mixed $value): string
    {
        if ($value === '@') return '@';
        if (!is_string($value) || $value === '' || strlen($value) > 253) throw new \InvalidArgumentException('Use a bounded relative DNS name.');
        foreach (explode('.', $value) as $label) {
            if (!preg_match('/\A[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?\z/', $label)) throw new \InvalidArgumentException('Use a bounded relative DNS name.');
        }
        return $value;
    }

    /** Application review policy, not a documented provider numeric range. */
    private static function uint16(string $value): void
    {
        if (!preg_match('/\A(?:0|[1-9][0-9]{0,4})\z/', $value) || (int)$value > 65535) throw new \InvalidArgumentException('Use a canonical decimal DNS value from 0 to 65535.');
    }
}
