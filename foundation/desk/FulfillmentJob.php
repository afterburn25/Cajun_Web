<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Desk;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

require_once __DIR__ . '/TraceLink.php';
require_once __DIR__ . '/ApprovalRequest.php';

/**
 * HC-12 — one unit of provisioning work with honest, recoverable
 * execution state.
 *
 * queued → running → succeeded | failed → (retry back to queued while
 * attempts remain, else dead_letter). A running job whose lease expires
 * becomes `stalled` — recoverable, never silently re-run. Every `run()`
 * claim mints an immutable attempt token that `succeed()`/`fail()` must
 * echo exactly, so a worker from an expired claim can never settle a
 * replacement attempt. `idempotencyKey` is required so the adapter can
 * detect replays instead of double-executing.  Irreversible kinds
 * require an `ApprovalRequest` id before `run()`.
 *
 * A stalled attempt is *uncertain*: the expired worker may already have
 * executed the external operation. `requeue()` therefore cannot release
 * a stalled job on its own — `resolveStalled()` must first record an
 * explicit, attempt-bound verified resolution carrying evidence:
 * `completed` settles the job terminally with no resend, `not_executed`
 * releases a separately fenced retry, and `unknown` keeps the job
 * blocked. Resolutions are immutable and retained per attempt token —
 * absent, mismatched or `unknown` evidence never releases a retry.
 *
 * Backoff is exponential and bounded: `nextAttemptAt` grows by the
 * configured base each attempt. A job that exhausts `maxAttempts`
 * reaches the terminal `dead_letter` state for manual fulfillment —
 * honest failure, never a fabricated success.
 *
 * @internal Roadmap HC-12; additively inserted.
 */
final class FulfillmentJob
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const STALLED = 'stalled';
    public const DEAD_LETTER = 'dead_letter';

    /** Job kinds that permanently destroy or move money — approval-gated. */
    public const IRREVERSIBLE = ['deprovision', 'refund', 'purge'];

    /** Verified outcomes for a stalled attempt. */
    public const RESOLUTION_COMPLETED = 'completed';
    public const RESOLUTION_NOT_EXECUTED = 'not_executed';
    public const RESOLUTION_UNKNOWN = 'unknown';

    private string $status = self::QUEUED;
    private int $attempts = 0;
    private ?string $attemptToken = null;
    private ?string $stalledToken = null;
    private ?int $leaseUntil = null;
    private ?int $nextAttemptAt = null;
    private ?string $lastError = null;
    /** @var array<string,array{outcome:string,evidence:string,actor:string,at:int}> conclusive resolutions, keyed by attempt token */
    private array $resolutions = [];
    /** @var array<string,list<array{evidence:string,actor:string,at:int}>> inconclusive observations, append-only per token */
    private array $observations = [];
    /** @var list<array{status:string,at:int,actor:string,note:string}> */
    private array $history = [];

    /**
     * @param list<TraceLink> $trace order/invoice/service links for staff
     *        traceability — at least one is required.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly string $idempotencyKey,
        public readonly array $trace,
        public readonly int $maxAttempts = 3,
        public readonly int $backoffBase = 60,
    ) {
        foreach (['id' => $id, 'kind' => $kind, 'idempotencyKey' => $idempotencyKey] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Job {$f} must not be empty.");
            }
        }
        if ($trace === [] || !self::allLinks($trace)) {
            throw new InvalidArgumentException('A job needs at least one traceable record link.');
        }
        if ($maxAttempts < 1 || $backoffBase < 1) {
            throw new InvalidArgumentException('Attempts and backoff must be positive.');
        }
        $this->history[] = ['status' => self::QUEUED, 'at' => 0, 'actor' => '', 'note' => 'queued'];
    }

    /** Whether this kind is irreversible and needs prior approval. */
    public function needsApproval(): bool
    {
        return in_array($this->kind, self::IRREVERSIBLE, true);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function nextAttemptAt(): ?int
    {
        return $this->nextAttemptAt;
    }

    /** The exact token minted by the latest run() claim, or null when not running. */
    public function attemptToken(): ?string
    {
        return $this->attemptToken;
    }

    /** The token of the attempt that stalled, or null when not stalled. */
    public function stalledToken(): ?string
    {
        return $this->stalledToken;
    }

    /** The recorded conclusive resolution for an attempt token, or null. */
    public function resolution(string $token): ?array
    {
        return $this->resolutions[$token] ?? null;
    }

    /** All inconclusive observations recorded for an attempt token. */
    public function observations(string $token): array
    {
        return $this->observations[$token] ?? [];
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function history(): array
    {
        return $this->history;
    }

    /**
     * Claim for execution. Irreversible jobs require a live approved
     * `ApprovalRequest` for the same operation kind — the gate verifies
     * the approval's state and subject, so a bare id or a stale/denied
     * request cannot release it.
     */
    public function run(string $actor, int $at, int $leaseSeconds, ?ApprovalRequest $approval = null): string
    {
        $this->requireStatus(self::QUEUED);
        if ($this->needsApproval()) {
            if ($approval === null || !$approval->approved($at)) {
                throw new LogicException("Job kind '{$this->kind}' is irreversible and needs a live approved request first.");
            }
            if ($approval->operationKind !== $this->kind) {
                throw new LogicException("Approval is for '{$approval->operationKind}', not '{$this->kind}'.");
            }
            $traced = false;
            foreach ($this->trace as $l) {
                if ($l->key() === $approval->subject->key()) {
                    $traced = true;
                }
            }
            if (!$traced) {
                throw new LogicException('Approval subject is not one of this job\'s trace links.');
            }
        }
        if ($this->nextAttemptAt !== null && $at < $this->nextAttemptAt) {
            throw new LogicException('Retry is not due yet — backoff is still cooling.');
        }
        if ($leaseSeconds < 1) {
            throw new InvalidArgumentException('A run needs a positive lease.');
        }
        $this->attempts++;
        $this->leaseUntil = $at + $leaseSeconds;
        // A fresh token per claim: an older worker can never settle a
        // replacement attempt that was requeued after its lease expired.
        $this->attemptToken = $this->id . '#' . $this->attempts;
        $this->move(self::RUNNING, $actor, $at, 'attempt ' . $this->attempts);
        return $this->attemptToken;
    }

    /**
     * The caller must name the exact token returned by the run() claim it
     * is reporting — a stale worker cannot settle a replacement attempt.
     */
    public function succeed(string $actor, int $at, string $token, string $note = ''): void
    {
        $this->requireAttempt($token);
        $this->move(self::SUCCEEDED, $actor, $at, $note !== '' ? $note : 'completed');
    }

    /** Honest failure: retryable while attempts remain, else dead-letter. */
    public function fail(string $error, string $actor, int $at, string $token): void
    {
        $this->requireAttempt($token);
        if (trim($error) === '') {
            throw new InvalidArgumentException('A failed attempt must record an error.');
        }
        $this->lastError = $error;
        if ($this->attempts < $this->maxAttempts) {
            $this->nextAttemptAt = $at + $this->backoffBase * (1 << ($this->attempts - 1));
            $this->move(self::QUEUED, $actor, $at, 'failed: ' . $error);
        } else {
            $this->move(self::DEAD_LETTER, $actor, $at, 'exhausted attempts: ' . $error);
        }
    }

    /** A running job past its lease is stalled, not lost. */
    public function expireLease(int $now): bool
    {
        if ($this->status !== self::RUNNING || $this->leaseUntil === null || $now < $this->leaseUntil) {
            return false;
        }
        // The expired token is released for settlement but retained for
        // resolution binding: the stalled attempt's outcome is uncertain
        // until resolveStalled() records verified evidence against it.
        $this->stalledToken = $this->attemptToken;
        $this->attemptToken = null;
        $this->move(self::STALLED, 'system', $now, 'lease expired without completion');
        return true;
    }

    /**
     * Record a verified resolution for the stalled attempt. The token
     * must name the exact attempt that stalled and the evidence must be
     * non-empty — an absent or mismatched claim is refused. Resolutions
     * are immutable: an identical replay returns quietly, a conflicting
     * outcome for the same attempt is refused.
     *
     * `completed` settles the job terminally — the operation already ran
     * and must never be resent. `not_executed` keeps the job stalled but
     * eligible for a separately fenced `requeue()`. `unknown` leaves the
     * job blocked. The adapter performs the actual provider verification;
     * this model requires and represents that evidence.
     */
    public function resolveStalled(string $token, string $outcome, string $evidence, string $actor, int $at): void
    {
        // Idempotent replay first: an identical repeat of the recorded
        // conclusive resolution returns quietly even after the job has
        // left `stalled` (e.g. a completed verdict already settled it or
        // a not_executed verdict was consumed by requeue).
        $recorded = $this->resolutions[$token] ?? null;
        if ($recorded !== null) {
            if ($recorded['outcome'] === $outcome && $recorded['evidence'] === $evidence) {
                return;
            }
            throw new LogicException('A conflicting resolution for this attempt is already recorded.');
        }
        $this->requireStatus(self::STALLED);
        if ($token === '' || $token !== $this->stalledToken) {
            throw new LogicException('Resolution must name the exact token of the stalled attempt.');
        }
        if (!in_array($outcome, [self::RESOLUTION_COMPLETED, self::RESOLUTION_NOT_EXECUTED, self::RESOLUTION_UNKNOWN], true)) {
            throw new InvalidArgumentException("Unknown resolution outcome '{$outcome}'.");
        }
        if (trim($evidence) === '') {
            throw new InvalidArgumentException('A verified resolution requires evidence.');
        }
        if ($outcome === self::RESOLUTION_UNKNOWN) {
            // An inconclusive observation is retained but never final:
            // a later conclusive, attempt-bound resolution may still
            // complete the stall — the unknown evidence stays recorded.
            foreach ($this->observations[$token] ?? [] as $obs) {
                if ($obs['evidence'] === $evidence && $obs['actor'] === $actor) {
                    return;
                }
            }
            $this->observations[$token][] = ['evidence' => $evidence, 'actor' => $actor, 'at' => $at];
            $this->move(self::STALLED, $actor, $at, 'stalled attempt ' . $token . ' observed unknown');
            return;
        }
        $this->resolutions[$token] = ['outcome' => $outcome, 'evidence' => $evidence, 'actor' => $actor, 'at' => $at];
        if ($outcome === self::RESOLUTION_COMPLETED) {
            $this->stalledToken = null;
            $this->move(self::SUCCEEDED, $actor, $at, 'stalled attempt ' . $token . ' verified completed');
            return;
        }
        $this->move(self::STALLED, $actor, $at, 'stalled attempt ' . $token . ' resolved ' . $outcome);
    }

    /**
     * Recover a stalled job for another try. A stalled attempt erases
     * certainty about the external operation, so retry is fenced: only a
     * recorded `not_executed` resolution for the stalled attempt can
     * release it — missing, conflicting or `unknown` evidence leaves the
     * job blocked. Manual recovery also clears the leftover backoff.
     */
    public function requeue(string $actor, int $at): void
    {
        $this->requireStatus(self::STALLED);
        if ($this->attempts >= $this->maxAttempts) {
            $this->move(self::DEAD_LETTER, $actor, $at, 'stalled beyond max attempts');
            return;
        }
        $resolution = $this->stalledToken !== null ? ($this->resolutions[$this->stalledToken] ?? null) : null;
        if ($resolution === null || $resolution['outcome'] !== self::RESOLUTION_NOT_EXECUTED) {
            throw new LogicException('A stalled attempt needs a verified not_executed resolution before retry.');
        }
        $this->stalledToken = null;
        $this->nextAttemptAt = null;
        $this->move(self::QUEUED, $actor, $at, 'verified not_executed; stalled job requeued for recovery');
    }

    private static function allLinks(array $trace): bool
    {
        foreach ($trace as $l) {
            if (!$l instanceof TraceLink) {
                return false;
            }
        }
        return true;
    }

    private function requireStatus(string $status): void
    {
        if ($this->status !== $status) {
            throw new LogicException("Job is {$this->status}, not {$status}.");
        }
    }

    private function requireAttempt(string $token): void
    {
        $this->requireStatus(self::RUNNING);
        if ($token !== $this->attemptToken) {
            throw new LogicException('Completion must name the exact token of the running attempt.');
        }
    }

    private function move(string $status, string $actor, int $at, string $note): void
    {
        $this->status = $status;
        $this->history[] = ['status' => $status, 'at' => $at, 'actor' => $actor, 'note' => $note];
    }
}
