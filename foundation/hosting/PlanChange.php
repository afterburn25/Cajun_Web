<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Hosting;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

require_once __DIR__ . '/HostingPackage.php';
require_once __DIR__ . '/OperationResult.php';

/**
 * HC-08 — a queued upgrade, downgrade or renewal for one hosting account.
 *
 * Changes are queued, approved, dispatched and only then recorded — a plan
 * never silently retargets. `approve()` runs **before** the provider call:
 * it enforces the effective time, validates the current usage observation,
 * and refuses a downgrade that cannot fit so the local record can never say
 * "refused" after a remote change already landed. `dispatch()` then mints
 * an immutable attempt identity (`id#n`) — once a provider call may be in
 * flight the change can no longer be cancelled, and `recordResult()`
 * accepts only a result bound to the expected operation, this account and
 * that exact attempt. A stale receipt from an earlier change or attempt is
 * rejected without mutation. A timeout leaves the change `uncertain`:
 * unresolved, never silently failed or re-dispatched until `resolve()`
 * records reconciliation evidence — an unapplied resolution returns the
 * change to `approved` so a fresh `dispatch()` arms a new attempt.
 *
 * @internal Roadmap HC-08; additively inserted.
 */
final class PlanChange
{
    public const UPGRADE = 'upgrade';
    public const DOWNGRADE = 'downgrade';
    public const RENEWAL = 'renewal';

    public const QUEUED = 'queued';
    public const APPROVED = 'approved';
    public const DISPATCHED = 'dispatched';
    public const APPLIED = 'applied';
    public const REFUSED = 'refused';
    public const FAILED = 'failed';
    public const UNCERTAIN = 'uncertain';
    public const CANCELLED = 'cancelled';

    /** Provider operation names results are bound to, per change kind. */
    private const OPERATIONS = [
        self::UPGRADE => 'hosting.upgrade',
        self::DOWNGRADE => 'hosting.downgrade',
        self::RENEWAL => 'hosting.renew',
    ];

    private string $status = self::QUEUED;
    private ?string $reason = null;
    private int $attempts = 0;
    private ?string $dispatchedAttempt = null;

    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly string $kind,
        public readonly HostingPackage $target,
        public readonly string $requestedBy,
        public readonly int $requestedAt,
        public readonly int $effectiveAt,
    ) {
        foreach (['id' => $id, 'accountId' => $accountId, 'requestedBy' => $requestedBy] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Plan change {$f} must not be empty.");
            }
        }
        if (!in_array($kind, [self::UPGRADE, self::DOWNGRADE, self::RENEWAL], true)) {
            throw new InvalidArgumentException("Unknown plan change kind '{$kind}'.");
        }
        if ($kind === self::RENEWAL && $effectiveAt <= $requestedAt) {
            throw new InvalidArgumentException('A renewal must take effect after it is requested.');
        }
    }

    public function status(): string
    {
        return $this->status;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    /** Whether the target package can provably hold the observed usage. */
    public function targetFits(array $usage): bool
    {
        return $this->target->fits($usage);
    }

    /**
     * Immutable identity of the attempt currently dispatched to the
     * provider — `id#n`, minted by `dispatch()`. The adapter must echo it
     * as `attemptRef` on the result. Null before dispatch and again after
     * an unapplied resolution returns the change to `approved`.
     */
    public function dispatchedAttempt(): ?string
    {
        return $this->dispatchedAttempt;
    }

    /**
     * Pre-dispatch decision. Enforces the effective time and, for a
     * downgrade, requires a complete correctly-typed usage observation —
     * every metered target limit must have an observed reading — that
     * provably fits the target. A partial observation cannot prove the
     * unobserved quotas fit, so it is rejected outright: the change stays
     * queued rather than being dispatched or mislabeled "over limit".
     * Refused here means the provider call is never made, never a
     * post-hoc label on a landed change.
     */
    public function approve(array $currentUsage, int $at): void
    {
        if ($this->status !== self::QUEUED) {
            throw new InvalidArgumentException('Only a queued change can be decided.');
        }
        if ($at < $this->effectiveAt) {
            throw new InvalidArgumentException('The change cannot be dispatched before its effective time.');
        }
        if ($this->kind === self::DOWNGRADE) {
            if ($currentUsage === []) {
                throw new InvalidArgumentException('A downgrade needs the current usage observation.');
            }
            foreach ($this->target->limits as $name => $cap) {
                if ($cap !== null && !array_key_exists($name, $currentUsage)) {
                    throw new InvalidArgumentException("A downgrade needs an observed reading for metered limit '{$name}'.");
                }
            }
            if (!$this->targetFits($currentUsage)) {
                $this->status = self::REFUSED;
                $this->reason = 'Current usage exceeds the target package limits.';
                return;
            }
        }
        $this->status = self::APPROVED;
        $this->reason = null;
    }

    /**
     * Mark the provider call in flight and mint its immutable attempt
     * identity. Dispatch closes cancellation — the requester may only
     * withdraw while no provider call may have begun — and arms the exact
     * `attemptRef` the recorded result must carry.
     */
    public function dispatch(): string
    {
        if ($this->status !== self::APPROVED) {
            throw new InvalidArgumentException('Only an approved change can be dispatched.');
        }
        $this->attempts++;
        $this->dispatchedAttempt = $this->id . '#' . $this->attempts;
        $this->status = self::DISPATCHED;
        return $this->dispatchedAttempt;
    }

    /**
     * Record the provider result for the dispatched change. The result
     * must name this change's operation, this account and the exact
     * dispatched attempt; anything else — including a receipt minted by
     * an earlier change for the same account and operation — is evidence
     * of an adapter bug and mutates nothing.
     */
    public function recordResult(OperationResult $result, int $at): void
    {
        if ($this->status !== self::DISPATCHED) {
            throw new InvalidArgumentException('Only a dispatched change can record a provider result.');
        }
        if ($result->operation !== self::OPERATIONS[$this->kind] || $result->subjectRef !== $this->accountId) {
            throw new InvalidArgumentException('The provider result is not bound to this change.');
        }
        if ($result->attemptRef === null || !hash_equals($this->dispatchedAttempt ?? '', $result->attemptRef)) {
            throw new InvalidArgumentException('The provider result is not bound to the dispatched attempt.');
        }
        if ($result->outcome === OperationResult::TIMEOUT) {
            $this->status = self::UNCERTAIN;
            $this->reason = 'Provider outcome unknown — reconcile before deciding.';
        } elseif ($result->ok()) {
            $this->status = self::APPLIED;
            $this->reason = $result->receipt;
        } else {
            $this->status = self::FAILED;
            $this->reason = $result->error;
        }
    }

    /**
     * Close an uncertain change with reconciliation evidence. When the
     * provider confirms the change landed it is `applied`; when it
     * confirms nothing happened the change returns to `approved` and the
     * stale attempt is discarded, so the caller can `dispatch()` a fresh
     * attempt or cancel deliberately.
     */
    public function resolve(bool $remoteApplied, string $evidence, int $at): void
    {
        if ($this->status !== self::UNCERTAIN) {
            throw new InvalidArgumentException('Only an uncertain change can be resolved.');
        }
        if (trim($evidence) === '') {
            throw new InvalidArgumentException('Resolving an uncertain change needs reconciliation evidence.');
        }
        $this->dispatchedAttempt = null;
        $this->status = $remoteApplied ? self::APPLIED : self::APPROVED;
        $this->reason = 'Reconciled: ' . $evidence;
    }

    /** The requester may withdraw before a provider call may have begun. */
    public function cancel(string $actor, int $at): void
    {
        if (!in_array($this->status, [self::QUEUED, self::APPROVED], true)) {
            throw new InvalidArgumentException('Only an undispatched change can be cancelled.');
        }
        if ($actor !== $this->requestedBy) {
            throw new InvalidArgumentException('Only the requester can cancel a queued change.');
        }
        $this->status = self::CANCELLED;
    }
}
