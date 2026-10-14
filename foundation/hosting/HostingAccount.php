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
 * active/suspended → cancellation_requested → deprovisioning → deprovisioned.
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
 *    cancelled as "never-provisioned" and cannot be retried. Only
 *    `reconcileTimeout()` with evidence decides whether an existing remote
 *    resource is adopted or the call provably never landed.
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

    // ── Provisioning ────────────────────────────────────────────

    /** Move pending → provisioning once cleared payment and checks exist. */
    public function beginProvisioning(string $actor, int $at): void
    {
        $this->requireStatus(self::PENDING);
        $this->move(self::PROVISIONING, $actor, $at, 'provisioning started');
    }

    /** Record the provider's result for the in-flight provisioning call. */
    public function provisionResult(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::PROVISIONING);
        $this->requireBound($result, self::OP_PROVISION);
        $this->operations[] = $result;
        if ($result->ok()) {
            $this->providerRef = $result->resourceRef;
            $this->move(self::ACTIVE, $actor, $at, 'provisioned: ' . $result->receipt);
        } elseif ($result->outcome === OperationResult::TIMEOUT) {
            $this->unresolvedOperation = self::OP_PROVISION;
            $this->move(self::FAILED, $actor, $at, 'provider timeout — reconcile before any retry');
        } else {
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
     */
    public function reconcileTimeout(string $actor, int $at, bool $appliedRemotely, ?string $resourceRef, string $evidence): void
    {
        if ($this->unresolvedOperation === null) {
            throw new LogicException('No timed-out operation is awaiting reconciliation.');
        }
        if (trim($evidence) === '') {
            throw new InvalidArgumentException('Reconciliation needs provider evidence.');
        }
        if ($appliedRemotely && $this->unresolvedOperation === self::OP_PROVISION && ($resourceRef === null || trim($resourceRef) === '')) {
            throw new InvalidArgumentException('Adopting a remote resource needs its provider id.');
        }
        $operation = $this->unresolvedOperation;
        $this->unresolvedOperation = null;
        $note = 'reconciled ' . $operation . ': ' . $evidence;
        if ($appliedRemotely) {
            match ($operation) {
                self::OP_PROVISION => $this->adoptRemote($resourceRef, $actor, $at, $note),
                self::OP_SUSPEND => $this->move(self::SUSPENDED, $actor, $at, $note . ' — provider confirms suspended'),
                self::OP_RESUME => $this->move(self::ACTIVE, $actor, $at, $note . ' — provider confirms active'),
                self::OP_DEPROVISION => $this->move(self::DEPROVISIONED, $actor, $at, $note . ' — provider confirms removed'),
                default => throw new LogicException("Cannot reconcile '{$operation}'."),
            };
        } else {
            match ($operation) {
                self::OP_PROVISION => $this->move(self::FAILED, $actor, $at, $note . ' — provider confirms no resource; retry is safe'),
                self::OP_SUSPEND => $this->move(self::ACTIVE, $actor, $at, $note . ' — provider confirms still active'),
                self::OP_RESUME => $this->move(self::SUSPENDED, $actor, $at, $note . ' — provider confirms still suspended'),
                self::OP_DEPROVISION => $this->move(self::DEPROVISIONING, $actor, $at, $note . ' — provider confirms not removed; re-attempt allowed'),
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
        $this->move(self::SUSPENDING, $actor, $at, 'suspension requested: ' . $reason);
    }

    /** Record the provider's result for a suspension in flight. */
    public function suspendResult(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::SUSPENDING);
        $this->requireBound($result, self::OP_SUSPEND);
        $this->operations[] = $result;
        if ($result->ok()) {
            $this->move(self::SUSPENDED, $actor, $at, 'suspended: ' . $result->receipt);
        } elseif ($result->outcome === OperationResult::TIMEOUT) {
            $this->unresolvedOperation = self::OP_SUSPEND;
            $this->move(self::SUSPENDING, $actor, $at, 'suspend timed out — reconcile before assuming either state');
        } else {
            $this->move(self::ACTIVE, $actor, $at, 'suspend failed; account remains active: ' . $result->error);
        }
    }

    /** Local resumption intent for a suspended account. */
    public function beginResume(string $actor, int $at): void
    {
        $this->requireStatus(self::SUSPENDED);
        $this->move(self::RESUMING, $actor, $at, 'resumption requested');
    }

    /** Record the provider's result for a resumption in flight. */
    public function resumeResult(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::RESUMING);
        $this->requireBound($result, self::OP_RESUME);
        $this->operations[] = $result;
        if ($result->ok()) {
            $this->move(self::ACTIVE, $actor, $at, 'resumed: ' . $result->receipt);
        } elseif ($result->outcome === OperationResult::TIMEOUT) {
            $this->unresolvedOperation = self::OP_RESUME;
            $this->move(self::RESUMING, $actor, $at, 'resume timed out — reconcile before assuming either state');
        } else {
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
        $this->move(self::DEPROVISIONING, $actor, $at, 'deprovisioning approved with retention confirmed');
    }

    /** Record the provider's result for deprovisioning. */
    public function deprovisionResult(OperationResult $result, string $actor, int $at): void
    {
        $this->requireStatus(self::DEPROVISIONING);
        $this->requireBound($result, self::OP_DEPROVISION);
        $this->operations[] = $result;
        if ($result->ok()) {
            $this->move(self::DEPROVISIONED, $actor, $at, 'deprovisioned: ' . $result->receipt);
        } elseif ($result->outcome === OperationResult::TIMEOUT) {
            $this->unresolvedOperation = self::OP_DEPROVISION;
            $this->move(self::DEPROVISIONING, $actor, $at, 'deprovision timed out — reconcile before re-attempting');
        } else {
            $this->move(self::DEPROVISIONING, $actor, $at, 'deprovision attempt did not confirm — stays deprovisioning');
        }
    }

    // ── Internals ───────────────────────────────────────────────

    private function requireStatus(string $status): void
    {
        if ($this->status !== $status) {
            throw new LogicException("Hosting account is {$this->status}, not {$status}.");
        }
    }

    /**
     * A result resolves only the operation it names for the account it
     * names. Anything else is an adapter defect and mutates nothing.
     */
    private function requireBound(OperationResult $result, string $operation): void
    {
        if ($result->operation !== $operation || $result->subjectRef !== $this->id) {
            throw new InvalidArgumentException('The provider result is not bound to this account operation.');
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
