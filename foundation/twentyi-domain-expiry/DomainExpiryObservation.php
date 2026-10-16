<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIDomainExpiry;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/** A saved-value candidate, not proof of present expiry or renewal/payment status. */
final readonly class DomainExpiryObservation
{
    public const REASONS = [
        'authentication', 'rate_limited', 'remote_error', 'unexpected_status',
        'response_limit', 'malformed_body', 'timestamp_null', 'timestamp_type',
        'timestamp_unsupported',
    ];

    public string $scope;
    public int $domainId;
    public string $domainName;

    public function __construct(
        public DomainExpiryBinding $binding,
        public string $state,
        public ?string $upstreamExpiryDate,
        public string $reason,
        public int $observedAt,
    ) {
        if ($observedAt < 0 || !in_array($state, ['complete', 'unavailable'], true)) {
            throw new \InvalidArgumentException('Invalid registrar expiry observation.');
        }
        if ($state === 'complete') {
            if ($reason !== '' || !self::validTimestamp($upstreamExpiryDate)) {
                throw new \InvalidArgumentException('Invalid registrar expiry observation.');
            }
        } elseif ($upstreamExpiryDate !== null || !in_array($reason, self::REASONS, true)) {
            throw new \InvalidArgumentException('Invalid registrar expiry observation.');
        }
        $this->scope = $binding->scope;
        $this->domainId = $binding->registrar->data['domain_id'];
        $this->domainName = $binding->registrar->data['domain'];
    }

    /**
     * Defensive local subset, not a complete verified provider grammar:
     * Gregorian calendar, 00:00:00..23:59:59, optional 1..6 fractional digits,
     * uppercase T and Z or an explicit offset through +/-14:00. -00:00 is
     * excluded because it can express an unknown offset. No normalization.
     */
    public static function validTimestamp(mixed $value): bool
    {
        if (!is_string($value) || strlen($value) < 20 || strlen($value) > 32
            || preg_match(
                '/\A(?<year>[0-9]{4})-(?<month>[0-9]{2})-(?<day>[0-9]{2})T'
                . '(?<hour>[0-9]{2}):(?<minute>[0-9]{2}):(?<second>[0-9]{2})'
                . '(?:\.[0-9]{1,6})?(?<zone>Z|[+-](?<offset_hour>[0-9]{2}):(?<offset_minute>[0-9]{2}))\z/',
                $value,
                $parts,
            ) !== 1) {
            return false;
        }
        if (!checkdate((int)$parts['month'], (int)$parts['day'], (int)$parts['year'])
            || (int)$parts['hour'] > 23 || (int)$parts['minute'] > 59 || (int)$parts['second'] > 59) {
            return false;
        }
        if ($parts['zone'] === 'Z') return true;
        $hour = (int)$parts['offset_hour'];
        $minute = (int)$parts['offset_minute'];
        return $parts['zone'] !== '-00:00' && $hour <= 14 && $minute <= 59
            && ($hour !== 14 || $minute === 0);
    }
}
