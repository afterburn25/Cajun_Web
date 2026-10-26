<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SshAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * A one-time, short-lived launch token bound to staff + client +
 * service + the current staff session. Only the SHA-256 hash is stored
 * on the object — the raw token is handed to the browser once and
 * presented back at connection time; a stolen record cannot be replayed
 * because consumption marks it used and a wrong hash never matches.
 */
final class LaunchToken
{
    private ?int $consumedAt = null;

    private function __construct(
        public readonly string $id,
        public readonly string $grantId,
        public readonly string $staffId,
        public readonly string $clientId,
        public readonly string $serviceId,
        public readonly string $staffSessionId,
        public readonly string $tokenHash,
        public readonly int $expiresAt,
    ) {
    }

    /**
     * Mint a token. Returns [token, rawSecret] — the raw secret goes to
     * the browser exactly once; only its hash is retained.
     */
    public static function mint(
        string $id,
        AccessGrant $grant,
        string $staffSessionId,
        int $now,
        int $ttlSeconds,
        ?callable $entropy = null,
    ): array {
        foreach (['id', 'staffSessionId'] as $field) {
            if (trim($$field) === '') {
                throw new InvalidArgumentException('A launch token needs ' . $field . '.');
            }
        }
        if ($ttlSeconds < 10 || $ttlSeconds > 600) {
            throw new InvalidArgumentException('Launch-token TTL must be between 10 and 600 seconds.');
        }
        $raw = $entropy ? $entropy() : bin2hex(random_bytes(32));
        if (!is_string($raw) || strlen($raw) < 16) {
            throw new InvalidArgumentException('Token entropy must produce at least 16 characters.');
        }
        $token = new self(
            $id,
            $grant->id,
            $grant->staffId,
            $grant->clientId,
            $grant->serviceId,
            $staffSessionId,
            hash('sha256', $raw),
            $now + $ttlSeconds,
        );
        return [$token, $raw];
    }

    public function usableAt(int $now): bool
    {
        return $this->consumedAt === null && $this->expiresAt > $now;
    }

    /** Whether the presented raw secret matches this token. */
    public function matches(string $rawSecret): bool
    {
        return hash_equals($this->tokenHash, hash('sha256', $rawSecret));
    }

    /**
     * Consume the token with the presented raw secret. Throws on
     * expired, already-consumed or mismatched secrets — a replay can
     * never open a second session.
     */
    public function consume(string $rawSecret, int $now): self
    {
        if ($this->consumedAt !== null) {
            throw new LogicException('Launch token already used.');
        }
        if ($this->expiresAt <= $now) {
            throw new LogicException('Launch token expired.');
        }
        if (!$this->matches($rawSecret)) {
            throw new LogicException('Launch token secret mismatch.');
        }
        $this->consumedAt = $now;
        return $this;
    }

    public function consumed(): bool
    {
        return $this->consumedAt !== null;
    }
}
