<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Desk;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

require_once __DIR__ . '/TraceLink.php';

/**
 * HC-12 — one unit of provisioning work with honest, recoverable
 * execution state.
 *
 * queued → running → succeeded | failed → (retry back to queued while
 * attempts remain, else dead_letter). A running job whose lease expires
 * becomes `stalled` — recoverable via `requeue()`, never silently
 * re-run. `idempotencyKey` is required so the adapter can detect
 * replays instead of double-executing. Irreversible kinds require an
 * `ApprovalRequest` id before `run()`.
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

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function history(): array
    {
        return $this->history;
    }

    /** Claim for execution. Irreversible jobs require an approval id. */
    public function run(string $actor, int $at, int $leaseSeconds, ?string $approvalId = null): void
    {
        $this->requireStatus(self::QUEUED);
        if ($this->needsApproval() && ($approvalId === null || trim($approvalId) === '')) {
            throw new LogicException("Job kind '{$this->kind}' is irreversible and needs an approved request first.");
        }
        if ($this->nextAttemptAt !== null && $at < $this->nextAttemptAt) {
            throw new LogicException('Retry is not due yet — backoff is still cooling.');
        }
        if ($leaseSeconds < 1) {
            throw new InvalidArgumentException('A run needs a positive lease.');
        }
        $this->attempts++;
        $this->leaseUntil = $at + $leaseSeconds;
        $this->move(self::RUNNING, $actor, $at, 'attempt ' . $this->attempts);
    }

    public function succeed(string $actor, int $at, string $note = ''): void
    {
        $this->requireStatus(self::RUNNING);
        $this->move(self::SUCCEEDED, $actor, $at, $note !== '' ? $note : 'completed');
    }

    /** Honest failure: retryable while attempts remain, else dead-letter. */
    public function fail(string $error, string $actor, int $at): void
    {
        $this->requireStatus(self::RUNNING);
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
        $this->move(self::STALLED, 'system', $now, 'lease expired without completion');
        return true;
    }

    /** Recover a stalled job for another try. */
    public function requeue(string $actor, int $at): void
    {
        $this->requireStatus(self::STALLED);
        if ($this->attempts >= $this->maxAttempts) {
            $this->move(self::DEAD_LETTER, $actor, $at, 'stalled beyond max attempts');
            return;
        }
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

    private function move(string $status, string $actor, int $at, string $note): void
    {
        $this->status = $status;
        $this->history[] = ['status' => $status, 'at' => $at, 'actor' => $actor, 'note' => $note];
    }
}
