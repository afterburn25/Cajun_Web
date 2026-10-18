<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyI\Operations;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * Exact additions/removals for the documented ns / old-ns interface.
 * Input sets must come from a separately authorized current observation and
 * confirmed desired configuration. This object is not ownership or consent.
 */
final readonly class NameserverChange
{
    public const MAX_NAMES = 64; // Local structural bound, not a registry allowance.

    private function __construct(
        public array $current,
        public array $desired,
        public array $added,
        public array $removed,
    ) {}

    public static function between(mixed $current, mixed $desired): self
    {
        $current = self::names($current);
        $desired = self::names($desired);
        if ($desired === []) {
            throw new InvalidArgumentException('Choose at least one nameserver. Removing all nameservers requires a separate supported workflow.');
        }
        $added = array_values(array_diff($desired, $current));
        $removed = array_values(array_diff($current, $desired));
        if ($added === [] && $removed === []) {
            throw new InvalidArgumentException('The nameserver configuration is unchanged.');
        }
        return new self($current, $desired, $added, $removed);
    }

    /** Only actual differences are serialized; unchanged names never appear. */
    public function fields(): array
    {
        return ['ns' => $this->added, 'old-ns' => $this->removed];
    }

    /** Validate direct builder input as a delta, never as two replacement sets. */
    public static function delta(mixed $added, mixed $removed): array
    {
        $added = self::names($added);
        $removed = self::names($removed);
        if (($added === [] && $removed === []) || array_intersect($added, $removed)) {
            throw new InvalidArgumentException('Nameserver additions and removals must be distinct and include an actual change.');
        }
        return ['ns' => $added, 'old-ns' => $removed];
    }

    private static function names(mixed $values): array
    {
        if (!is_array($values) || !array_is_list($values) || count($values) > self::MAX_NAMES) {
            throw new InvalidArgumentException('Use a bounded list of nameserver hostnames.');
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value) || strlen($value) > 254) {
                throw new InvalidArgumentException('Use a valid ASCII nameserver hostname.');
            }
            // DNS names are case-insensitive; one absolute-name dot is harmless.
            $name = strtolower(str_ends_with($value, '.') ? substr($value, 0, -1) : $value);
            if (strlen($name) > 253 || !str_contains($name, '.')
                || preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+\z/', $name) !== 1
                || filter_var($name, FILTER_VALIDATE_IP) !== false) {
                throw new InvalidArgumentException('Use a valid ASCII nameserver hostname, without a URL, address, port or path.');
            }
            if (in_array($name, $out, true)) {
                throw new InvalidArgumentException('Duplicate nameservers are not allowed.');
            }
            $out[] = $name;
        }
        sort($out, SORT_STRING);
        return $out;
    }
}
