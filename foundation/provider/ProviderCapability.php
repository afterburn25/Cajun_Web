<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One provider operation's support state. HC-07 requires an explicit
 * support matrix: a capability is UNVERIFIED until inspected against the
 * real provider account, and nothing unverified may be treated as
 * supported.
 */
final readonly class ProviderCapability
{
    public const SUPPORTED = 'supported';
    public const UNSUPPORTED = 'unsupported';
    public const UNVERIFIED = 'unverified';

    private const STATES = [self::SUPPORTED, self::UNSUPPORTED, self::UNVERIFIED];

    /**
     * @param bool $idempotent True only when re-executing the operation
     *        provably cannot apply a second remote side effect (read-only
     *        queries, or writes the provider documents as idempotent).
     *        Defaults false — non-idempotent operations are never
     *        automatically retried, since a timed-out write may already
     *        have landed.
     */
    public function __construct(
        public string $operation,
        public string $state,
        public string $note = '',
        public bool $idempotent = false,
    ) {
        if (trim($operation) === '') {
            throw new InvalidArgumentException('A capability needs an operation name.');
        }
        if (!in_array($state, self::STATES, true)) {
            throw new InvalidArgumentException('Unknown capability state: ' . $state);
        }
    }

    public static function supported(string $operation, string $note = '', bool $idempotent = false): self
    {
        return new self($operation, self::SUPPORTED, $note, $idempotent);
    }

    public static function unsupported(string $operation, string $note = ''): self
    {
        return new self($operation, self::UNSUPPORTED, $note);
    }

    public static function unverified(string $operation, string $note = ''): self
    {
        return new self($operation, self::UNVERIFIED, $note);
    }
}
