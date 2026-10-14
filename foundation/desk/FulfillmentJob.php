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
 * becomes `stalled` — recoverable via `requeue()`, never silently
 * re-run. Every `run()` claim mints an immutable attempt token that
 * `succeed()`/`fail()` must echo exactly, so a worker from an expired
 * claim can never settle a replacement attempt. `idempotencyKey` is
 * required so the adapter can detect replays instead of double-executing.
 * Irreversible kinds require an `ApprovalRequest` id before `run()`.
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

    private string $status = self::QUEUED;
    private int $attempts = 0;
    private ?string $attemptToken = null;
    private ?int $leaseUntil = null;
    private ?int $nextAttemptAt = null;
    private ?string $lastError = null;
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
        // The expired token is released here: it stays recorded in history
        // but can never settle a later attempt after recovery.
        $this->attemptToken = null;
        $this->move(self::STALLED, 'system', $now, 'lease expired without completion');
        return true;
    }

    /**
     * Recover a stalled job for another try. Manual recovery clears the
     * leftover backoff — an operator's requeue is deliberate, so a stale
     * `nextAttemptAt` from an earlier failure must not block it.
     */
    public function requeue(string $actor, int $at): void
    {
        $this->requireStatus(self::STALLED);
        if ($this->attempts >= $this->maxAttempts) {
            $this->move(self::DEAD_LETTER, $actor, $at, 'stalled beyond max attempts');
            return;
        }
        $this->nextAttemptAt = null;
        $this->move(self::QUEUED, $actor, $at, 'stalled job requeued for recovery');
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
