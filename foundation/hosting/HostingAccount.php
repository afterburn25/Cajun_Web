<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Hosting;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

require_once __DIR__ . '/HostingPackage.php';
require_once __DIR__ . '/OperationResult.php';

/**
 * HC-08 — lifecycle state machine for one shared-hosting account.
 *
 * pending → provisioning → active ⇄ suspending/suspended ⇄ resuming.
 * pending/provisioning → failed → retry() → pending (never auto-retries).
 * active/suspended → cancellation_requested → deprovisioning → deprovisioned;
 * deprovisioning → deprovision_failed → retryDeprovision() → deprovisioning.
 * pending → cancelled (pre-provisioning withdrawal only).
 *
 * Protections:
 *  - Every provider result is bound: the operation name must match the
 *    in-flight call and `subjectRef` must be this account's id. A result
 *    for another operation or account mutates nothing — a `deprovision`
 *    receipt can never activate an account, and an operation receipt is
 *    never mistaken for a verified provider resource id.
 *  - Suspension and resumption are *local intent* followed by a confirmed
 *    provider result: `suspend()`/`beginResume()` enter `suspending` /
 *    `resuming`; only an applied `suspendResult`/`resumeResult` confirms
 *    `suspended`/`active`.
 *  - A `timeout` result leaves the operation unresolved: the account keeps
 *    its pre-call status (provisioning reports `failed`) and records
 *    `awaitingReconciliation`. While unresolved, the order cannot be
 *    cancelled as "never-provisioned", cannot be retried, and no further
 *    result is accepted — a delayed response for the timed-out attempt
 *    can never rewrite later or terminal state. Only `reconcileTimeout()`
 *    with evidence decides whether an existing remote resource is adopted
 *    or the call provably never landed — and the callback itself must
 *    name the exact unresolved attempt, so a replayed reconcile for an
 *    earlier attempt can never clear a newer dispatch.
 *  - A failed deprovision is a recorded outcome, not an armed request:
 *    it lands in `deprovision_failed` with no attempt in flight. Only the
 *    explicit `retryDeprovision()` decision arms a fresh attempt — and
 *    only when the provider marked the failure retryable.
 *  - Every dispatch mints an immutable attempt identity (`acct#n`),
 *    exposed through `pendingAttempt()`. A recorded result must carry
 *    that exact `attemptRef`, so a stale receipt from an earlier attempt
 *    of the same operation can never satisfy a later dispatch.
 *  - An applied provisioning result must carry the provider resource id.
 *    A confirmation without one is not `active` — it is held unresolved
 *    until reconciliation supplies the identity.
 *  - Suspension for payment lapse is refused before `graceUntil` and
 *    while a billing dispute is open — access and backups are preserved
 *    during disputes per policy.
 *  - Deprovisioning is a deliberate two-actor action: `requestCancellation`
 *    records one non-empty actor; `approveDeprovision` requires a
 *    *different* non-empty actor AND an explicit `retentionConfirmed`
 *    flag — a single account, an empty identity, or an approval without
 *    confirmed data retention cannot destroy hosting.
 *  - Every transition records an attributed history entry; provider
 *    operation results are stored verbatim (including honest timeouts).
 *
 * @internal Roadmap HC-08; additively inserted.
 */
final class HostingAccount
{
    public const PENDING = 'pending';
    public const PROVISIONING = 'provisioning';
    public const ACTIVE = 'active';
    public const SUSPENDING = 'suspending';
    public const SUSPENDED = 'suspended';
    public const RESUMING = 'resuming';
    public const FAILED = 'failed';
    public const CANCELLATION_REQUESTED = 'cancellation_requested';
    public const DEPROVISIONING = 'deprovisioning';
    public const DEPROVISION_FAILED = 'deprovision_failed';
    public const DEPROVISIONED = 'deprovisioned';
    public const CANCELLED = 'cancelled';

    public const OP_PROVISION = 'hosting.provision';
    public const OP_SUSPEND = 'hosting.suspend';
    public const OP_RESUME = 'hosting.resume';
    public const OP_DEPROVISION = 'hosting.deprovision';

    private const TERMINAL = [self::DEPROVISIONED, self::CANCELLED];

    private string $status = self::PENDING;
    private ?string $providerRef = null;
    private ?string $primaryDomain = null;
    private ?string $cancellationRequester = null;
    private ?string $unresolvedOperation = null;
    private ?string $unresolvedAttempt = null;
    private ?bool $deprovisionRetryable = null;
    private int $attempts = 0;
    private ?string $pendingAttempt = null;
    private bool $disputeOpen = false;
    private ?int $graceUntil = null;
    /** @var list<array{status:string,at:int,actor:string,note:string}> */
    private array $history = [];
    /** @var list<OperationResult> */
    private array $operations = [];

    public function __construct(
        public readonly string $id,
        public readonly HostingPackage $package,
        public readonly string $clientRef,
        int $createdAt,
    ) {
        if (trim($id) === '' || trim($clientRef) === '') {
            throw new InvalidArgumentException('A hosting account needs an id and a client reference.');
        }
        $this->history[] = ['status' => self::PENDING, 'at' => $createdAt, 'actor' => '', 'note' => 'created'];
    }

    public function status(): string
    {
        return $this->status;
    }

    /** Verified provider resource id — set only when the provider reported one. */
    public function providerRef(): ?string
    {
        return $this->providerRef;
    }

    public function primaryDomain(): ?string
    {
        return $this->primaryDomain;
    }

    public function terminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    public function history(): array
    {
        return $this->history;
    }

    public function operations(): array
    {
        return $this->operations;
    }

    public function disputeOpen(): bool
    {
        return $this->disputeOpen;
    }

    /** Whether the remote outcome of the last attempt is still unknown. */
    public function awaitingReconciliation(): bool
    {
        return $this->unresolvedOperation !== null;
    }

    /**
     * Immutable identity of the dispatched attempt awaiting a provider
     * result — `acct#n`, minted at each dispatch transition. The adapter
     * must echo it as `attemptRef` on the result; null when no provider
     * call may be in flight.
     */
    public function pendingAttempt(): ?string
    {
        return $this->pendingAttempt;
    }

    // ── Provisioning ────────────────────────────────────────────

    /** Move pending → provisioning once cleared payment and checks exist. */
    public function beginProvisioning(string $actor, int $at): void
    {
        $this->requireStatus(self::PENDING);
        $this->beginAttempt();
        $this->move(self::PROVISIONING, $actor, $at, 'provisioning started');
    }

    /**
     * Record the provider's result for the in-flight provisioning call.
     * The result must carry this attempt's identity; an applied result
     * without the provider resource id is held unresolved for identity
     * reconciliation rather than activating with no resource.
     */
    public function provisionResult(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::PROVISIONING);
        $this->requireBound($result, self::OP_PROVISION);
        $this->operations[] = $result;
        if ($result->ok()) {
            if ($result->resourceRef === null || trim($result->resourceRef) === '') {
                $this->unresolvedOperation = self::OP_PROVISION;
                $this->unresolvedAttempt = $this->pendingAttempt;
                $this->move(self::FAILED, $actor, $at, 'provider reported success without a resource id — reconcile identity before activating: ' . $result->receipt);
                return;
            }
            $this->pendingAttempt = null;
            $this->providerRef = $result->resourceRef;
            $this->move(self::ACTIVE, $actor, $at, 'provisioned: ' . $result->receipt);
        } elseif ($result->outcome === OperationResult::TIMEOUT) {
            $this->unresolvedOperation = self::OP_PROVISION;
            $this->unresolvedAttempt = $this->pendingAttempt;
            $this->move(self::FAILED, $actor, $at, 'provider timeout — reconcile before any retry');
        } else {
            $this->pendingAttempt = null;
            $this->move(self::FAILED, $actor, $at, 'provisioning failed: ' . $result->error);
        }
    }

    /**
     * A failed provisioning attempt returns to pending for an explicit
     * retry. Refused while the remote outcome is unknown (`timeout`
     * awaiting `reconcileTimeout`) and when the provider already named
     * the failure not safe to retry — a retryable:false result means the
     * adapter itself ruled out re-execution.
     */
    public function retry(string $actor, int $at): void
    {
        $this->requireStatus(self::FAILED);
        if ($this->unresolvedOperation !== null) {
            throw new LogicException('The last attempt timed out — reconcile the provider state before retrying.');
        }
        $last = $this->operations[count($this->operations) - 1] ?? null;
        if ($last !== null && $last->outcome === OperationResult::FAILED && !$last->retryable) {
            throw new LogicException('The provider marked this failure not safe to retry.');
        }
        $this->move(self::PENDING, $actor, $at, 'queued for retry after failure');
    }

    /**
     * Close an unresolved timeout with reconciliation evidence. The
     * caller states what the provider actually confirmed, with evidence:
     *
     *  - provision timed out, remote resource exists → it is *adopted*
     *    (resourceRef required), never recreated;
     *  - provision timed out, no remote resource → stays `failed`, and
     *    `retry()` is safe;
     *  - suspend/resume timed out → the account moves to the state the
     *    provider confirms (suspended/active or active/suspended);
     *  - deprovision timed out → confirmed becomes `deprovisioned`,
     *    unconfirmed stays `deprovisioning` for a fresh attempt.
     *
     * The callback must name the exact attempt it reconciles — evidence
     * for an earlier attempt can never clear a newer dispatch that may
     * still be in flight.
     */
    public function reconcileTimeout(string $actor, int $at, bool $appliedRemotely, ?string $resourceRef, string $evidence, string $attemptRef): void
    {
        if ($this->unresolvedOperation === null || $this->unresolvedAttempt === null) {
            throw new LogicException('No timed-out operation is awaiting reconciliation.');
        }
        if (trim($attemptRef) === '' || !hash_equals($this->unresolvedAttempt, $attemptRef)) {
            throw new InvalidArgumentException('Reconciliation must name the exact unresolved attempt.');
        }
        if (trim($evidence) === '') {
            throw new InvalidArgumentException('Reconciliation needs provider evidence.');
        }
        if ($appliedRemotely && $this->unresolvedOperation === self::OP_PROVISION && ($resourceRef === null || trim($resourceRef) === '')) {
            throw new InvalidArgumentException('Adopting a remote resource needs its provider id.');
        }
        $operation = $this->unresolvedOperation;
        $this->unresolvedOperation = null;
        $this->unresolvedAttempt = null;
        $this->pendingAttempt = null;
        $note = 'reconciled ' . $operation . ' attempt ' . $attemptRef . ': ' . $evidence;
        if ($appliedRemotely) {
            match ($operation) {
                self::OP_PROVISION => $this->adoptRemote($resourceRef, $actor, $at, $note),
                self::OP_SUSPEND => $this->move(self::SUSPENDED, $actor, $at, $note . ' — provider confirms suspended'),
                self::OP_RESUME => $this->move(self::ACTIVE, $actor, $at, $note . ' — provider confirms active'),
                self::OP_DEPROVISION => $this->move(self::DEPROVISIONED, $actor, $at, $note . ' — provider confirms removed'),
                default => throw new LogicException("Cannot reconcile '{$operation}'."),
            };
        } else {
            if ($operation === self::OP_DEPROVISION) {
                // A confirmed "not removed" arms a fresh attempt identity so a
                // delayed result for the failed attempt can never satisfy it.
                $this->beginAttempt();
            }
            match ($operation) {
                self::OP_PROVISION => $this->move(self::FAILED, $actor, $at, $note . ' — provider confirms no resource; retry is safe'),
                self::OP_SUSPEND => $this->move(self::ACTIVE, $actor, $at, $note . ' — provider confirms still active'),
                self::OP_RESUME => $this->move(self::SUSPENDED, $actor, $at, $note . ' — provider confirms still suspended'),
                self::OP_DEPROVISION => $this->move(self::DEPROVISIONING, $actor, $at, $note . ' — provider confirms not removed; re-attempt armed under a fresh attempt id'),
                default => throw new LogicException("Cannot reconcile '{$operation}'."),
            };
        }
    }

    /** Link the primary domain once registration confirms. */
    public function linkDomain(string $domain, string $actor, int $at): void
    {
        $this->requireStatus(self::ACTIVE);
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/i', $domain)) {
            throw new InvalidArgumentException("Invalid primary domain '{$domain}'.");
        }
        $this->primaryDomain = strtolower($domain);
        $this->move(self::ACTIVE, $actor, $at, 'primary domain: ' . $this->primaryDomain);
    }

    // ── Suspension ──────────────────────────────────────────────

    /** Record a billing grace deadline; suspension cannot precede it. */
    public function setGraceUntil(int $until, string $actor, int $at): void
    {
        $this->requireStatus(self::ACTIVE);
        if ($until <= $at) {
            throw new InvalidArgumentException('A grace deadline must be in the future.');
        }
        $this->graceUntil = $until;
        $this->move(self::ACTIVE, $actor, $at, 'billing grace until ' . gmdate('c', $until));
    }

    public function openDispute(string $actor, int $at): void
    {
        $this->requireStatus(self::ACTIVE);
        $this->disputeOpen = true;
        $this->move(self::ACTIVE, $actor, $at, 'billing dispute opened — access preserved');
    }

    public function closeDispute(string $actor, int $at): void
    {
        $this->disputeOpen = false;
        $this->move($this->status, $actor, $at, 'billing dispute closed');
    }

    /**
     * Local suspension intent. Refused while a dispute is open or before
     * the recorded grace deadline. `suspending` is not `suspended` — the
     * account is only suspended once `suspendResult` records an applied
     * provider outcome.
     */
    public function suspend(string $actor, int $at, string $reason): void
    {
        $this->requireStatus(self::ACTIVE);
        if ($this->disputeOpen) {
            throw new LogicException('Cannot suspend while a billing dispute is open.');
        }
        if ($this->graceUntil !== null && $at < $this->graceUntil) {
            throw new LogicException('Cannot suspend before the grace deadline.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Suspension needs a recorded reason.');
        }
        $this->beginAttempt();
        $this->move(self::SUSPENDING, $actor, $at, 'suspension requested: ' . $reason);
    }

    /** Record the provider's result for a suspension in flight. */
    public function suspendResult(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::SUSPENDING);
        $this->requireBound($result, self::OP_SUSPEND);
        $this->operations[] = $result;
        if ($result->ok()) {
            $this->pendingAttempt = null;
            $this->move(self::SUSPENDED, $actor, $at, 'suspended: ' . $result->receipt);
        } elseif ($result->outcome === OperationResult::TIMEOUT) {
            $this->unresolvedOperation = self::OP_SUSPEND;
            $this->unresolvedAttempt = $this->pendingAttempt;
            $this->move(self::SUSPENDING, $actor, $at, 'suspend timed out — reconcile before assuming either state');
        } else {
            $this->pendingAttempt = null;
            $this->move(self::ACTIVE, $actor, $at, 'suspend failed; account remains active: ' . $result->error);
        }
    }

    /** Local resumption intent for a suspended account. */
    public function beginResume(string $actor, int $at): void
    {
        $this->requireStatus(self::SUSPENDED);
        $this->beginAttempt();
        $this->move(self::RESUMING, $actor, $at, 'resumption requested');
    }

    /** Record the provider's result for a resumption in flight. */
    public function resumeResult(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::RESUMING);
        $this->requireBound($result, self::OP_RESUME);
        $this->operations[] = $result;
        if ($result->ok()) {
            $this->pendingAttempt = null;
            $this->move(self::ACTIVE, $actor, $at, 'resumed: ' . $result->receipt);
        } elseif ($result->outcome === OperationResult::TIMEOUT) {
            $this->unresolvedOperation = self::OP_RESUME;
            $this->unresolvedAttempt = $this->pendingAttempt;
            $this->move(self::RESUMING, $actor, $at, 'resume timed out — reconcile before assuming either state');
        } else {
            $this->pendingAttempt = null;
            $this->move(self::SUSPENDED, $actor, $at, 'resume failed; account remains suspended: ' . $result->error);
        }
    }

    // ── Cancellation and deprovisioning ─────────────────────────

    /**
     * Withdraw a never-provisioned order. Impossible while a provisioning
     * timeout is unresolved — "never provisioned" cannot be claimed while
     * the provider may have created the resource.
     */
    public function cancelOrder(string $actor, int $at): void
    {
        if (!in_array($this->status, [self::PENDING, self::FAILED], true)) {
            throw new LogicException('Only a never-provisioned order can be cancelled.');
        }
        if ($this->unresolvedOperation === self::OP_PROVISION) {
            throw new LogicException('Remote create outcome is unknown — reconcile before cancelling.');
        }
        $this->move(self::CANCELLED, $actor, $at, 'order cancelled before provisioning');
    }

    /** Step one of two: request cancellation of a provisioned account. */
    public function requestCancellation(string $actor, int $at): void
    {
        if (!in_array($this->status, [self::ACTIVE, self::SUSPENDED], true)) {
            throw new LogicException('Cancellation can only be requested from active or suspended.');
        }
        if (trim($actor) === '') {
            throw new InvalidArgumentException('Cancellation needs a named requester.');
        }
        $this->cancellationRequester = $actor;
        $this->move(self::CANCELLATION_REQUESTED, $actor, $at, 'cancellation requested — second approval required');
    }

    /**
     * Step two of two: approve deprovisioning. Requires a *different*
     * non-empty actor and confirmed data retention.
     */
    public function approveDeprovision(string $actor, bool $retentionConfirmed, int $at): void
    {
        $this->requireStatus(self::CANCELLATION_REQUESTED);
        if (trim($actor) === '') {
            throw new InvalidArgumentException('Deprovisioning needs a named approver.');
        }
        if ($actor === $this->cancellationRequester) {
            throw new LogicException('Deprovisioning needs a second approver, not the requester.');
        }
        if (!$retentionConfirmed) {
            throw new LogicException('Deprovisioning requires confirmed data retention.');
        }
        $this->beginAttempt();
        $this->move(self::DEPROVISIONING, $actor, $at, 'deprovisioning approved with retention confirmed');
    }

    /** Record the provider's result for deprovisioning. */
    public function deprovisionResult(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::DEPROVISIONING);
        $this->requireBound($result, self::OP_DEPROVISION);
        $this->operations[] = $result;
        if ($result->ok()) {
            $this->pendingAttempt = null;
            $this->move(self::DEPROVISIONED, $actor, $at, 'deprovisioned: ' . $result->receipt);
        } elseif ($result->outcome === OperationResult::TIMEOUT) {
            $this->unresolvedOperation = self::OP_DEPROVISION;
            $this->unresolvedAttempt = $this->pendingAttempt;
            $this->move(self::DEPROVISIONING, $actor, $at, 'deprovision timed out — reconcile before re-attempting');
        } else {
            // A recorded failure is not an armed request: hold the account in
            // a distinct failed state with no attempt in flight. Only the
            // explicit retryDeprovision() decision arms a fresh attempt.
            $this->pendingAttempt = null;
            $this->deprovisionRetryable = $result->retryable;
            $this->move(self::DEPROVISION_FAILED, $actor, $at, 'deprovision attempt failed — ' . ($result->retryable ? 'retry permitted via retryDeprovision()' : 'provider marked it unsafe to retry') . ': ' . $result->error);
        }
    }

    /**
     * Deliberately re-arm deprovisioning after a recorded failure. Refused
     * unless the provider's own result marked the failure retryable — a
     * known-unsafe destructive call can never be re-dispatched by accident.
     */
    public function retryDeprovision(string $actor, int $at): void
    {
        $this->requireStatus(self::DEPROVISION_FAILED);
        if ($this->deprovisionRetryable !== true) {
            throw new LogicException('The provider marked this deprovision failure not safe to retry.');
        }
        $this->deprovisionRetryable = null;
        $this->beginAttempt();
        $this->move(self::DEPROVISIONING, $actor, $at, 'deprovision retried after eligible failure');
    }

    // ── Internals ───────────────────────────────────────────────

    private function requireStatus(string $status): void
    {
        if ($this->status !== $status) {
            throw new LogicException("Hosting account is {$this->status}, not {$status}.");
        }
    }

    /** Mint the immutable identity of a newly dispatched attempt. */
    private function beginAttempt(): void
    {
        $this->attempts++;
        $this->pendingAttempt = $this->id . '#' . $this->attempts;
    }

    /**
     * A result resolves only the operation it names, for the account it
     * names, under the exact attempt identity minted at dispatch — and
     * never while a timed-out attempt is still unresolved. A delayed
     * result for the timed-out attempt, a stale receipt from an earlier
     * attempt, or a result for another operation or account is an
     * adapter defect and mutates nothing.
     */
    private function requireBound(OperationResult $result, string $operation): void
    {
        if ($this->unresolvedOperation !== null) {
            throw new LogicException('A timed-out attempt is awaiting reconciliation — no further result can be recorded.');
        }
        if ($result->operation !== $operation || $result->subjectRef !== $this->id) {
            throw new InvalidArgumentException('The provider result is not bound to this account operation.');
        }
        if ($this->pendingAttempt === null || $result->attemptRef === null || !hash_equals($this->pendingAttempt, $result->attemptRef)) {
            throw new InvalidArgumentException('The provider result is not bound to the dispatched attempt.');
        }
    }

    /** Reconciliation found a real remote resource — adopt it, never recreate. */
    private function adoptRemote(?string $resourceRef, string $actor, int $at, string $note): void
    {
        if ($resourceRef === null || trim($resourceRef) === '') {
            throw new InvalidArgumentException('Adopting a remote resource needs its provider id.');
        }
        $this->providerRef = $resourceRef;
        $this->move(self::ACTIVE, $actor, $at, $note . ' — adopted existing provider resource ' . $resourceRef);
    }

    private function move(string $status, string $actor, int $at, string $note): void
    {
        $this->status = $status;
        $this->history[] = ['status' => $status, 'at' => $at, 'actor' => $actor, 'note' => $note];
    }
}
