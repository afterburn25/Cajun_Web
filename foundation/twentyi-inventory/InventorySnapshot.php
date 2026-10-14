<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\TwentyIInventory;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One bounded observation of a provider list read. States are honest:
 * COMPLETE means every *returned item* projected, PARTIAL means some
 * items were skipped, UNAVAILABLE means the response gives no
 * trustworthy inventory. An empty COMPLETE list is a real empty
 * observation — a failed read never masquerades as one.
 *
 * `coverage` records what is known about account-wide completeness,
 * which is strictly weaker than response completeness: no pagination
 * contract is documented for these endpoints, so coverage is always
 * UNKNOWN until a documented terminal contract exists. `scope` is the
 * caller-supplied provider account/operation identity the observation
 * belongs to, so stored observations can never be confused between
 * accounts.
 */
final readonly class InventorySnapshot
{
    public const COMPLETE = 'complete';
    public const PARTIAL = 'partial';
    public const UNAVAILABLE = 'unavailable';

    /** No documented completeness/pagination contract — the only honest coverage claim today. */
    public const COVERAGE_UNKNOWN = 'unknown';
    /** Reserved for a future documented complete-collection contract. */
    public const COVERAGE_COMPLETE = 'complete';

    public const MAX_ITEMS = 8192;
    public const MAX_SKIP_REASONS = 8;
    private const MAX_REASON_BYTES = 64;
    private const MAX_SCOPE_BYTES = 190;

    private const UNAVAILABLE_REASONS = [
        'authentication', 'rate_limited', 'remote_error', 'unexpected_status',
        'response_limit', 'malformed_body', 'unexpected_shape',
        'transport_connect', 'transport_timeout', 'transport_tls',
        'transport_protocol', 'transport_unavailable', 'transport_unknown',
    ];

    /**
     * @param InventoryPackage[] $packages   Projected records for the read.
     * @param string[]           $skipReasons First few reasons items were skipped (bounded).
     * @param string             $reason      Unavailable kind; empty when packages were observed.
     * @param string             $scope       Caller-supplied provider account/operation identity.
     * @param string             $coverage    Account-wide coverage claim — always COVERAGE_UNKNOWN today.
     */
    public function __construct(
        public string $state,
        public array $packages,
        public int $observedAt,
        public int $skipped = 0,
        public array $skipReasons = [],
        public string $reason = '',
        public string $scope = '',
        public string $coverage = self::COVERAGE_UNKNOWN,
    ) {
        if (!in_array($state, [self::COMPLETE, self::PARTIAL, self::UNAVAILABLE], true)) {
            throw new InvalidArgumentException('Unknown inventory snapshot state.');
        }
        if ($observedAt < 0 || $skipped < 0) {
            throw new InvalidArgumentException('Observation time and skip count must be non-negative.');
        }
        if (count($packages) > self::MAX_ITEMS || count($skipReasons) > self::MAX_SKIP_REASONS) {
            throw new InvalidArgumentException('Snapshot contents exceed the supported bounds.');
        }
        foreach ($packages as $package) {
            if (!$package instanceof InventoryPackage) {
                throw new InvalidArgumentException('Snapshots carry projected package records only.');
            }
        }
        foreach ($skipReasons as $skipReason) {
            if (!is_string($skipReason) || strlen($skipReason) > 200
                || preg_match('//u', $skipReason) !== 1
                || preg_match('/[\x00-\x1f\x7f]/', $skipReason)) {
                throw new InvalidArgumentException('Skip reasons are bounded printable classifications.');
            }
        }
        if (!in_array($coverage, [self::COVERAGE_UNKNOWN, self::COVERAGE_COMPLETE], true)) {
            throw new InvalidArgumentException('Unknown coverage claim.');
        }
        if ($coverage === self::COVERAGE_COMPLETE && $state !== self::COMPLETE) {
            throw new InvalidArgumentException('Complete coverage requires a complete observation.');
        }
        if ($scope !== '' && (strlen($scope) > self::MAX_SCOPE_BYTES
            || preg_match('//u', $scope) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $scope))) {
            throw new InvalidArgumentException('Observation scope is bounded printable text.');
        }
        if ($state === self::UNAVAILABLE && ($packages !== [] || $reason === '')) {
            throw new InvalidArgumentException('An unavailable snapshot carries a reason and no packages.');
        }
        if ($state === self::UNAVAILABLE && !in_array($reason, self::UNAVAILABLE_REASONS, true)) {
            throw new InvalidArgumentException('Unavailable reasons come from the closed redacted set.');
        }
        if ($state !== self::UNAVAILABLE && $reason !== '') {
            throw new InvalidArgumentException('An observed snapshot has no unavailable reason.');
        }
        if ($state === self::COMPLETE && $skipped > 0) {
            throw new InvalidArgumentException('A complete snapshot cannot have skipped items.');
        }
        if ($skipped > 0 && $state === self::PARTIAL && $skipReasons === []) {
            throw new InvalidArgumentException('A partial snapshot names why items were skipped.');
        }
        if ($skipped === 0 && $skipReasons !== []) {
            throw new InvalidArgumentException('Skip reasons require skipped items.');
        }
        if ($state === self::PARTIAL && $skipped === 0) {
            throw new InvalidArgumentException('A partial snapshot must record skipped items.');
        }
    }

    public static function unavailable(string $reason, int $observedAt, string $scope = ''): self
    {
        return new self(self::UNAVAILABLE, [], $observedAt, 0, [], $reason, $scope);
    }

    public function ok(): bool
    {
        return $this->state !== self::UNAVAILABLE;
    }
}
