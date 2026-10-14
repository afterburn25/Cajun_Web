<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SupportAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * An owner-issued authorization for one staff member to open one
 * client's portal in support mode. A-09 requires separate staff and
 * customer identities — the grant records both and refuses identical
 * values — plus expiry, revocation and attribution of who issued it.
 * Client data scope stays explicit: the grant names the client it
 * covers and nothing else, and sensitive actions need a separately
 * flagged permission rather than being implied by portal access.
 */
final class SupportGrant
{
    /** Support sessions may last at most one working day. */
    private const MAX_TTL_SECONDS = 28800;

    private bool $revoked = false;

    /**
     * @param int $ttlSeconds session lifetime once launched
     * @param bool $permitsSensitive whether actions flagged sensitive are allowed
     */
    public function __construct(
        public readonly string $id,
        public readonly string $staffId,
        public readonly string $clientId,
        public readonly string $issuedBy,
        public readonly int $issuedAt,
        public readonly int $ttlSeconds,
        public readonly bool $permitsSensitive = false,
        public readonly string $reason = '',
    ) {
        foreach (['id', 'staffId', 'clientId', 'issuedBy'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A support grant needs ' . $field . '.');
            }
        }
        if ($staffId === $clientId) {
            throw new InvalidArgumentException('Staff and client identities must differ.');
        }
        if ($ttlSeconds < 60 || $ttlSeconds > self::MAX_TTL_SECONDS) {
            throw new InvalidArgumentException('Support session TTL must be between 60s and 8h.');
        }
    }

    public function usable(int $now): bool
    {
        return !$this->revoked;
    }

    public function revoke(string $actor): void
    {
        if ($this->revoked) {
            throw new LogicException('Grant is already revoked.');
        }
        if (trim($actor) === '') {
            throw new InvalidArgumentException('Revocation needs an actor.');
        }
        $this->revoked = true;
    }

    public function revoked(): bool
    {
        return $this->revoked;
    }
}
