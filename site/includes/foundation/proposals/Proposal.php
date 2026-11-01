<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Proposals;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * A client-facing proposal's guarded lifecycle:
 *
 *   draft → sent ⇄ changes-requested → approved (terminal)
 *         ↘ withdrawn (staff, from non-approved states; terminal)
 *
 * Every submitted version is immutable and numbered; re-submission
 * after changes creates a new version and supersedes the previous one.
 * Decisions are pinned to the current version id — stale approvals are
 * impossible. Withdrawal is blocked once approved. All mutations carry
 * an actor id and append to history; nothing is silent.
 */
final class Proposal
{
    public const DRAFT = 'draft';
    public const SENT = 'sent';
    public const CHANGES_REQUESTED = 'changes-requested';
    public const APPROVED = 'approved';
    public const WITHDRAWN = 'withdrawn';

    private const TERMINAL = [self::APPROVED, self::WITHDRAWN];

    private string $state = self::DRAFT;

    /** @var ProposalVersion[] numbered 1..N, append-only */
    private array $versions = [];

    /** @var ApprovalDecision[] */
    private array $decisions = [];

    /** @var array<int,array{at:string,actor:string,event:string}> */
    private array $history = [];

    public function __construct(
        public readonly string $id,
        public readonly string $clientId,
        public readonly string $title,
        string $createdBy,
        string $createdAt,
    ) {
        foreach (['id', 'clientId', 'title'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A proposal needs ' . $field . '.');
            }
        }
        if (trim($createdBy) === '') {
            throw new InvalidArgumentException('A proposal needs a creator.');
        }
        $this->record($createdAt, $createdBy, 'created');
    }

    /** Add a version while drafting. */
    public function addVersion(ProposalVersion $version, string $actorId, string $at): self
    {
        $this->requireState(self::DRAFT);
        if ($version->number !== count($this->versions) + 1) {
            throw new LogicException('Versions must be numbered sequentially.');
        }
        $this->versions[] = $version;
        $this->record($at, $actorId, 'version-' . $version->number . '-added');
        return $this;
    }

    /**
     * Send the current version to the client for review. Only drafts
     * send — after change requests a new version must be created via
     * revise() first, so the client never re-receives stale terms.
     */
    public function send(string $actorId, string $at): self
    {
        $this->requireState(self::DRAFT);
        if ($this->versions === []) {
            throw new LogicException('Cannot send a proposal with no version.');
        }
        $this->state = self::SENT;
        $this->record($at, $actorId, 'sent-v' . $this->current()->number);
        return $this;
    }

    /**
     * Record a client decision. The decision's versionId must match the
     * current version and the proposal must be under review — an
     * approval created against a superseded or draft version throws.
     */
    public function decide(ApprovalDecision $decision, string $at): self
    {
        $this->requireState(self::SENT);
        $current = $this->current();
        if ($decision->versionId !== $current->id) {
            throw new LogicException('Decisions must target the current version (' . $current->id . ').');
        }
        $this->decisions[] = $decision;
        $this->state = $decision->kind === ApprovalDecision::APPROVE ? self::APPROVED : self::CHANGES_REQUESTED;
        $this->record($at, $decision->actorId, $this->state . '-v' . $current->number);
        return $this;
    }

    /**
     * Staff creates a revision after change requests. Adds the version
     * and marks the prior one superseded; the proposal returns to draft
     * until re-sent.
     */
    public function revise(ProposalVersion $version, string $actorId, string $at): self
    {
        $this->requireState(self::CHANGES_REQUESTED);
        if ($version->number !== count($this->versions) + 1) {
            throw new LogicException('Versions must be numbered sequentially.');
        }
        $this->versions[] = $version;
        $this->state = self::DRAFT;
        $this->record($at, $actorId, 'revised-v' . $version->number);
        return $this;
    }

    /** Staff withdrawal — never allowed once approved. */
    public function withdraw(string $actorId, string $at): self
    {
        if ($this->state === self::APPROVED) {
            throw new LogicException('An approved proposal cannot be withdrawn; supersede or correct through a new proposal.');
        }
        $this->requireState([self::DRAFT, self::SENT, self::CHANGES_REQUESTED]);
        $this->state = self::WITHDRAWN;
        $this->record($at, $actorId, 'withdrawn');
        return $this;
    }

    public function current(): ?ProposalVersion
    {
        return $this->versions === [] ? null : end($this->versions);
    }

    public function state(): string
    {
        return $this->state;
    }

    /** @return ProposalVersion[] */
    public function versions(): array
    {
        return $this->versions;
    }

    /** @return ApprovalDecision[] */
    public function decisions(): array
    {
        return $this->decisions;
    }

    /** All versions except the current one — superseded snapshots. */
    public function supersededVersions(): array
    {
        return array_slice($this->versions, 0, -1);
    }

    public function history(): array
    {
        return $this->history;
    }

    /** @param string|string[] $allowed */
    private function requireState($allowed): void
    {
        $allowed = (array) $allowed;
        if (!in_array($this->state, $allowed, true)) {
            throw new LogicException('Cannot perform that action while ' . $this->state . '.');
        }
    }

    private function record(string $at, string $actor, string $event): void
    {
        $this->history[] = ['at' => $at, 'actor' => $actor, 'event' => $event];
    }
}
