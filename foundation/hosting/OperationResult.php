<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Hosting;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * HC-08 — the recorded outcome of one real provider operation.
 *
 * Produced by the adapter **after** attempting the operation. Three honest
 * outcomes: `applied` (provider confirmed, receipt required), `failed`
 * (named error + whether a retry is safe), and `timeout` (the provider did
 * not answer — outcome genuinely unknown, so the adapter must reconcile
 * before retrying rather than risk a duplicate package). Timeout is
 * deliberately distinct from failure: retrying a timed-out create is the
 * classic duplicate-provisioning bug.
 *
 * Every result names the operation it resolves, the subject it binds to
 * (the account id for account operations, the account id for plan
 * changes) and — for operations dispatched under an attempt identity —
 * the immutable `attemptRef` minted at dispatch. Consumers refuse a
 * result whose operation, subject or attempt does not match the
 * in-flight request, so a `delete-hosting` receipt can never activate an
 * account and a stale receipt from an earlier attempt can never satisfy
 * a later dispatch. `receipt` is the provider's operation reference —
 * evidence of the call; it is not a resource identity. `resourceRef` is
 * optional and carries the verified provider resource id only when the
 * provider actually reported one.
 *
 * @internal Roadmap HC-08; additively inserted.
 */
final readonly class OperationResult
{
    public const APPLIED = 'applied';
    public const FAILED = 'failed';
    public const TIMEOUT = 'timeout';

    private function __construct(
        public string $operation,
        public string $outcome,
        public int $at,
        public string $subjectRef,
        public ?string $receipt,
        public ?string $resourceRef,
        public ?string $error,
        public bool $retryable,
        public ?string $attemptRef,
    ) {
    }

    /**
     * Provider confirmed; `receipt` is the provider's operation reference
     * (not a resource id). `resourceRef` carries the verified provider
     * resource identifier only when one was actually reported.
     * `attemptRef` binds the result to the exact dispatched attempt the
     * consumer minted — a result naming an earlier or foreign attempt is
     * refused without mutation.
     */
    public static function applied(string $operation, string $receipt, int $at, string $subjectRef, ?string $resourceRef = null, ?string $attemptRef = null): self
    {
        if (trim($receipt) === '') {
            throw new InvalidArgumentException('An applied operation needs the provider receipt.');
        }
        if ($resourceRef !== null && trim($resourceRef) === '') {
            throw new InvalidArgumentException('A provider resource reference must not be empty.');
        }
        return new self(self::check($operation), self::APPLIED, $at, self::checkSubject($subjectRef), $receipt, $resourceRef, null, false, self::checkAttempt($attemptRef));
    }

    public static function failed(string $operation, string $error, bool $retryable, int $at, string $subjectRef, ?string $attemptRef = null): self
    {
        if (trim($error) === '') {
            throw new InvalidArgumentException('A failed operation must name the error.');
        }
        return new self(self::check($operation), self::FAILED, $at, self::checkSubject($subjectRef), null, null, $error, $retryable, self::checkAttempt($attemptRef));
    }

    /** No response — outcome unknown; reconcile before any retry. */
    public static function timeout(string $operation, int $at, string $subjectRef, ?string $attemptRef = null): self
    {
        return new self(self::check($operation), self::TIMEOUT, $at, self::checkSubject($subjectRef), null, null, null, false, self::checkAttempt($attemptRef));
    }

    public function ok(): bool
    {
        return $this->outcome === self::APPLIED;
    }

    private static function check(string $operation): string
    {
        if (trim($operation) === '') {
            throw new InvalidArgumentException('An operation result needs a name.');
        }
        return $operation;
    }

    private static function checkSubject(string $subjectRef): string
    {
        if (trim($subjectRef) === '') {
            throw new InvalidArgumentException('An operation result needs the subject it resolves.');
        }
        return $subjectRef;
    }

    private static function checkAttempt(?string $attemptRef): ?string
    {
        if ($attemptRef !== null && trim($attemptRef) === '') {
            throw new InvalidArgumentException('An attempt reference must not be empty.');
        }
        return $attemptRef;
    }
}
