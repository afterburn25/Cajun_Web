<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Provider;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * The outcome of an atomic ledger claim on an idempotency key.
 *
 * - CLAIMED:     the caller now owns the key and may execute exactly
 *                once; it must call OperationLedger::record() with the
 *                terminal outcome, even when execution fails.
 * - REPLAY:      a terminal result already exists for the key and the
 *                request fingerprint matches — return it, do not
 *                execute.
 * - IN_PROGRESS: another execution currently holds the claim; the
 *                caller must not execute and may poll/retry later.
 * - UNCERTAIN:   a claim exists but it expired without a terminal
 *                record (the owner crashed or timed out). The remote
 *                side effect may or may not have happened — the caller
 *                must not execute and must route to reconciliation.
 * - CONFLICT:    the key is already bound to a different request
 *                fingerprint; reusing the key for a different request
 *                is a caller error and is refused.
 */
final readonly class LedgerClaim
{
    public const CLAIMED = 'claimed';
    public const REPLAY = 'replay';
    public const IN_PROGRESS = 'in_progress';
    public const UNCERTAIN = 'uncertain';
    public const CONFLICT = 'conflict';

    private function __construct(
        public string $state,
        public ?OperationResult $result = null,
    ) {
    }

    public static function claimed(): self
    {
        return new self(self::CLAIMED);
    }

    public static function replay(OperationResult $result): self
    {
        return new self(self::REPLAY, $result);
    }

    public static function inProgress(): self
    {
        return new self(self::IN_PROGRESS);
    }

    public static function uncertain(): self
    {
        return new self(self::UNCERTAIN);
    }

    public static function conflict(): self
    {
        return new self(self::CONFLICT);
    }
}
