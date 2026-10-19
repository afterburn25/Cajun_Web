<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Vps;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * Lifecycle state machine for one VPS. Transitions are guarded and
 * append an immutable history entry. Destructive teardown is a
 * deliberate two-actor action: `requestDecommission()` records one
 * actor's request and `approveDecommission()` requires a *different*
 * caller-supplied actor identifier. The adapter must authenticate distinct
 * canonical accounts and their permissions; this string check is not auth.
 * An unconfirmed remote outcome remains uncertain: it
 * cannot restore a stable state, be cancelled, or authorize a retry.
 *
 * Every remote attempt has a retained identity. Completion requires a
 * terminal-outcome witness bound to that exact attempt. This pure class
 * validates the witness's scope, NOT its truth or authenticity. A future
 * adapter must authenticate its evidence before invoking reconciliation;
 * an unchanged state, absence, timeout or HTTP status is not such proof.
 */
final class VpsServer
{
    public const PENDING = 'pending';
    public const PROVISIONING = 'provisioning';
    public const ACTIVE = 'active';
    public const SUSPENDING = 'suspending';
    public const SUSPENDED = 'suspended';
    public const RESUMING = 'resuming';
    public const DECOMMISSION_REQUESTED = 'decommission_requested';
    public const DECOMMISSIONING = 'decommissioning';
    public const TERMINATED = 'terminated';
    public const FAILED = 'failed';
    public const UNCERTAIN = 'uncertain';
    public const CANCELLED = 'cancelled';

    private const TERMINAL = [self::TERMINATED, self::CANCELLED];

    private string $status = self::PENDING;
    private ?string $providerRef = null;
    private ?string $decommissionRequester = null;
    private string $decommissionReturnTo = self::ACTIVE;
    /** @var list<array{id:string,ordinal:int,operation:string,provider_ref:string,from:string,to:string,actor:string}> */
    private array $attempts = [];
    private ?int $pendingAttempt = null;
    /** @var array<string,true> */
    private array $usedEvidenceIds = [];
    /** @var array<string,true> */
    private array $usedEvidenceDigests = [];
    /** @var list<array{status:string,at:string,actor:string,note:string,provider_ref:?string,attempt:?array,evidence:?array}> */
    private array $history = [];

    public function __construct(
        public readonly string $id,
        public readonly VpsOffering $offering,
        public readonly string $clientRef,
    ) {
        if (trim($id) === '' || trim($clientRef) === '') {
            throw new InvalidArgumentException('A VPS order needs an id and a client reference.');
        }
        $this->go(self::PENDING, '', 'created');
    }

    public function status(): string
    {
        return $this->status;
    }

    /** Last admitted provider reference, retained even after a known-no-effect attempt. */
    public function providerRef(): ?string
    {
        return $this->providerRef;
    }

    /** @return list<array{status:string,at:string,actor:string,note:string,provider_ref:?string,attempt:?array,evidence:?array}> */
    public function history(): array
    {
        return $this->history;
    }

    public function terminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    /** Immutable initiation records; terminal witnesses are retained in history(). */
    public function attempts(): array
    {
        return $this->attempts;
    }

    /** The exact unresolved attempt to bind a future authenticated outcome to. */
    public function currentAttempt(): ?array
    {
        return $this->pendingAttempt === null ? null : $this->attempts[$this->pendingAttempt];
    }

    /** Provider reference recorded when provisioning begins. */
    public function beginProvisioning(string $providerRef): self
    {
        $this->require(self::PENDING, 'beginProvisioning');
        if (trim($providerRef) === '') {
            throw new InvalidArgumentException('Provisioning needs the provider reference for reconciliation.');
        }
        return $this->beginAttempt('provision', $providerRef, self::PROVISIONING, self::ACTIVE, '', 'provisioning admitted');
    }

    /** Completes both provisioning→active and resuming→active. */
    public function markActive(string $note = 'provider confirmed running', ?array $evidence = null): self
    {
        if (!in_array($this->status, [self::PROVISIONING, self::RESUMING], true)) {
            throw new LogicException('Cannot activate a server in state ' . $this->status . '.');
        }
        return $this->confirmed($evidence, $note);
    }

    /**
     * An unconfirmed provisioning outcome never means that nothing happened.
     * Only reconcile(... outcome=not_applied) can establish FAILED.
     */
    public function fail(string $reason): self
    {
        $this->require(self::PROVISIONING, 'fail');
        return $this->go(self::UNCERTAIN, '', $reason === '' ? 'provisioning outcome unknown' : $reason);
    }

    /** Only a definitively not-applied provisioning may be deliberately retried. */
    public function retry(): self
    {
        $this->require(self::FAILED, 'retry');
        if ($this->pendingAttempt !== null) throw new LogicException('An unresolved attempt cannot be retried.');
        return $this->go(self::PENDING, '', 'queued for retry');
    }

    // --- suspend / resume ---------------------------------------------------

    public function suspend(): self
    {
        $this->require(self::ACTIVE, 'suspend');
        return $this->beginAttempt('suspend', $this->providerRef, self::SUSPENDING, self::SUSPENDED, '', 'suspension admitted');
    }

    public function markSuspended(?array $evidence = null): self
    {
        $this->require(self::SUSPENDING, 'markSuspended');
        return $this->confirmed($evidence, 'provider confirmed suspended');
    }

    /** No stable-state inference is made from an unconfirmed suspension. */
    public function suspendFailed(string $note = 'suspension failed'): self
    {
        $this->require(self::SUSPENDING, 'suspendFailed');
        return $this->go(self::UNCERTAIN, '', $note);
    }

    public function resume(): self
    {
        $this->require(self::SUSPENDED, 'resume');
        return $this->beginAttempt('resume', $this->providerRef, self::RESUMING, self::ACTIVE, '', 'resume admitted');
    }

    /** No stable-state inference is made from an unconfirmed resume. */
    public function resumeFailed(string $note = 'resume failed'): self
    {
        $this->require(self::RESUMING, 'resumeFailed');
        return $this->go(self::UNCERTAIN, '', $note);
    }

    // --- decommission (two-actor teardown) -----------------------------------

    /** Step 1: one actor requests teardown. */
    public function requestDecommission(string $actor): self
    {
        if (!in_array($this->status, [self::ACTIVE, self::SUSPENDED], true)) {
            throw new LogicException('Decommission can only be requested on an active or suspended server.');
        }
        if (trim($actor) === '') {
            throw new InvalidArgumentException('A decommission request needs an actor.');
        }
        $this->decommissionRequester = $actor;
        $this->decommissionReturnTo = $this->status;
        return $this->go(self::DECOMMISSION_REQUESTED, $actor, 'teardown requested');
    }

    /** Step 2: a different actor approves — teardown begins. */
    public function approveDecommission(string $actor): self
    {
        $this->require(self::DECOMMISSION_REQUESTED, 'approveDecommission');
        if (trim($actor) === '') {
            throw new InvalidArgumentException('A decommission approval needs an actor.');
        }
        if ($actor === $this->decommissionRequester) {
            throw new LogicException('Teardown approval requires a different actor than the requester.');
        }
        return $this->beginAttempt('decommission', $this->providerRef, self::DECOMMISSIONING, self::TERMINATED, $actor, 'teardown approved');
    }

    /** The requester (or anyone) backs out before approval — server returns to its prior state. */
    public function cancelDecommission(string $actor, string $note = 'teardown request withdrawn'): self
    {
        $this->require(self::DECOMMISSION_REQUESTED, 'cancelDecommission');
        $back = $this->decommissionReturnTo;
        $this->decommissionRequester = null;
        return $this->go($back, $actor, $note);
    }

    /**
     * An unconfirmed teardown retains its attempt and two-actor history.
     * It cannot be requested or approved again until reconciled.
     */
    public function decommissionFailed(string $note = 'teardown failed'): self
    {
        $this->require(self::DECOMMISSIONING, 'decommissionFailed');
        return $this->go(self::UNCERTAIN, '', $note);
    }

    public function markTerminated(?array $evidence = null): self
    {
        $this->require(self::DECOMMISSIONING, 'markTerminated');
        return $this->confirmed($evidence, 'provider confirmed destroyed');
    }

    /**
     * Cancel only before provisioning (or after a known-not-applied attempt). An
     * active/suspended server must go through decommission — never a
     * silent cancel.
     */
    public function cancel(string $actor, string $note = 'cancelled'): self
    {
        if (!in_array($this->status, [self::PENDING, self::FAILED], true)) {
            throw new LogicException('Only pending or failed orders can be cancelled.');
        }
        return $this->go(self::CANCELLED, $actor, $note);
    }

    /**
     * Apply adapter-authenticated TERMINAL operation evidence, never a raw read.
     * Exact keys: server_id, client_ref, attempt_id, provider_ref, operation,
     * outcome (applied|not_applied), resulting_state, evidence_id (local 32-hex identifier),
     * evidence_sha256 (64-hex digest of the adapter's retained evidence).
     *
     * not_applied must establish this attempt can no longer have an effect
     * and the stated resulting_state remains valid (FAILED means no provisioned VPS);
     * absence, a current state alone, a timeout or a rejection status cannot
     * establish that here. This component supplies no provider verifier.
     */
    public function reconcile(array $evidence): self
    {
        return $this->resolve($evidence, 'terminal outcome reconciled');
    }

    // -----------------------------------------------------------------------

    private function require(string $expected, string $action): void
    {
        if ($this->status !== $expected) {
            throw new LogicException('Cannot ' . $action . ' a server in state ' . $this->status . '.');
        }
    }

    private function beginAttempt(string $operation, ?string $providerRef, string $status, string $target, string $actor, string $note): self
    {
        if ($this->pendingAttempt !== null || $providerRef === null) throw new LogicException('An attempt needs an available bound resource.');
        // Generate before mutation: a failure to obtain randomness leaves the state intact.
        $attempt = ['id' => bin2hex(random_bytes(16)), 'ordinal' => count($this->attempts) + 1,
            'operation' => $operation, 'provider_ref' => $providerRef,
            'from' => $operation === 'decommission' ? $this->decommissionReturnTo : $this->status,
            'to' => $target, 'actor' => $actor];
        $this->providerRef = $providerRef;
        $this->pendingAttempt = count($this->attempts);
        $this->attempts[] = $attempt;
        return $this->go($status, $actor, $note);
    }

    private function confirmed(?array $evidence, string $note): self
    {
        if ($evidence === null || ($evidence['outcome'] ?? null) !== 'applied') {
            throw new LogicException('Confirmation requires exact applied terminal evidence.');
        }
        return $this->resolve($evidence, $note);
    }

    private function resolve(array $evidence, string $note): self
    {
        $attempt = $this->currentAttempt();
        if ($attempt === null || !in_array($this->status, [self::PROVISIONING, self::SUSPENDING, self::RESUMING, self::DECOMMISSIONING, self::UNCERTAIN], true)) {
            throw new LogicException('No unresolved attempt accepts reconciliation.');
        }
        $keys = array_keys($evidence); sort($keys);
        if ($keys !== ['attempt_id', 'client_ref', 'evidence_id', 'evidence_sha256', 'operation', 'outcome', 'provider_ref', 'resulting_state', 'server_id']) {
            throw new InvalidArgumentException('Reconciliation needs the exact evidence fields.');
        }
        foreach ($evidence as $value) if (!is_string($value)) throw new InvalidArgumentException('Evidence fields must be strings.');
        if ($evidence['server_id'] !== $this->id || $evidence['client_ref'] !== $this->clientRef
            || $evidence['attempt_id'] !== $attempt['id'] || $evidence['provider_ref'] !== $attempt['provider_ref']
            || $evidence['operation'] !== $attempt['operation']) {
            throw new LogicException('Reconciliation belongs to another resource or attempt.');
        }
        if (!in_array($evidence['outcome'], ['applied', 'not_applied'], true)
            || preg_match('/\A[a-f0-9]{32}\z/', $evidence['evidence_id']) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/', $evidence['evidence_sha256']) !== 1) {
            throw new InvalidArgumentException('Reconciliation needs a terminal outcome and evidence identity.');
        }
        if (isset($this->usedEvidenceIds[$evidence['evidence_id']]) || isset($this->usedEvidenceDigests[$evidence['evidence_sha256']])) {
            throw new LogicException('Terminal evidence has already been consumed.');
        }
        $next = $evidence['outcome'] === 'applied' ? $attempt['to']
            : ($attempt['operation'] === 'provision' ? self::FAILED : $attempt['from']);
        if ($evidence['resulting_state'] !== $next) throw new LogicException('Terminal evidence does not establish the resulting state.');
        // Copy each validated scalar into a fresh canonical array. Copying the
        // caller's array itself would retain PHP reference-valued elements.
        $retained = ['server_id' => $evidence['server_id'], 'client_ref' => $evidence['client_ref'],
            'attempt_id' => $evidence['attempt_id'], 'provider_ref' => $evidence['provider_ref'],
            'operation' => $evidence['operation'], 'outcome' => $evidence['outcome'],
            'resulting_state' => $evidence['resulting_state'], 'evidence_id' => $evidence['evidence_id'],
            'evidence_sha256' => $evidence['evidence_sha256']];
        // All checks precede mutation; retain the evidence and old attempt in this event.
        $this->usedEvidenceIds[$retained['evidence_id']] = true;
        $this->usedEvidenceDigests[$retained['evidence_sha256']] = true;
        if ($attempt['operation'] === 'decommission') $this->decommissionRequester = null;
        $this->go($next, '', $note, $retained);
        $this->pendingAttempt = null;
        return $this;
    }

    private function go(string $status, string $actor, string $note, ?array $evidence = null): self
    {
        $this->status = $status;
        $this->history[] = ['status' => $status, 'at' => gmdate('c'), 'actor' => $actor, 'note' => $note,
            'provider_ref' => $this->providerRef, 'attempt' => $this->currentAttempt(), 'evidence' => $evidence];
        return $this;
    }
}
