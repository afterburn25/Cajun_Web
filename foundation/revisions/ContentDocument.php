<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Revisions;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * One subject's revision history. The core A-02 guarantees:
 *
 * - Revisions are append-only and immutable; history is never rewritten.
 * - Saves create a *draft* — publication is a separate explicit action.
 * - Restores produce a new draft pointing at the old snapshot
 *   (`restoredFrom`); the published revision is never overwritten.
 * - A restore/save never discards an existing draft silently — the
 *   caller must discardDraft() first.
 * - No-op saves (content identical to the pending draft or the
 *   published revision) are rejected — only meaningful versions exist.
 * - Approvals pin to the published revision and persist independently
 *   of later drafts.
 * - Every event records actor and time.
 */
final class ContentDocument
{
    /** @var ContentRevision[] */
    private array $revisions = [];
    private ?string $publishedId = null;
    private ?string $draftId = null;

    /** @var ApprovalContext[] */
    private array $approvals = [];

    /** @var array<int,array{at:string,actor:string,event:string}> */
    private array $history = [];

    public function __construct(
        public readonly string $subject,
    ) {
        if (trim($subject) === '') {
            throw new InvalidArgumentException('A document needs a subject.');
        }
    }

    /** Save a new draft revision. */
    public function save(string $id, string $content, string $authorId, string $at, string $note = ''): ContentRevision
    {
        $baseline = $this->draftId !== null ? $this->revision($this->draftId)
            : ($this->publishedId !== null ? $this->revision($this->publishedId) : null);
        if ($baseline !== null && $baseline->content === $content) {
            throw new LogicException('No-op save rejected: content is identical to the current draft/published revision.');
        }
        $revision = new ContentRevision(
            $id, $this->subject, count($this->revisions) + 1, $content, $authorId, $at, $note,
        );
        $this->revisions[] = $revision;
        $this->draftId = $revision->id;
        $this->record($at, $authorId, 'saved-v' . $revision->number);
        return $revision;
    }

    /** Explicitly publish the pending draft. */
    public function publish(string $actorId, string $at): self
    {
        if ($this->draftId === null) {
            throw new LogicException('No draft to publish.');
        }
        $this->publishedId = $this->draftId;
        $this->draftId = null;
        $this->record($at, $actorId, 'published-' . $this->publishedId);
        return $this;
    }

    /**
     * Restore an older revision's content as a new draft. Never touches
     * the published pointer and refuses to silently discard a pending
     * draft — the caller discards it explicitly first.
     */
    public function restoreAsDraft(string $id, string $revisionId, string $actorId, string $at, string $note = ''): ContentRevision
    {
        $source = $this->revision($revisionId);
        if ($source === null) {
            throw new LogicException('Revision ' . $revisionId . ' does not exist.');
        }
        if ($this->draftId !== null) {
            throw new LogicException('A pending draft exists; discardDraft() before restoring to avoid silent overwrite.');
        }
        $revision = new ContentRevision(
            $id, $this->subject, count($this->revisions) + 1, $source->content, $actorId, $at,
            $note === '' ? 'Restored from ' . $source->id : $note,
            $source->id,
        );
        $this->revisions[] = $revision;
        $this->draftId = $revision->id;
        $this->record($at, $actorId, 'restored-' . $source->id . '-as-v' . $revision->number);
        return $revision;
    }

    /** Explicitly discard a pending draft. */
    public function discardDraft(string $actorId, string $at): self
    {
        if ($this->draftId === null) {
            throw new LogicException('No draft to discard.');
        }
        $this->record($at, $actorId, 'draft-discarded-' . $this->draftId);
        $this->draftId = null;
        return $this;
    }

    /** Record an approval against the currently published revision. */
    public function approve(ApprovalContext $approval): self
    {
        if ($this->publishedId === null) {
            throw new LogicException('Nothing published to approve.');
        }
        if ($approval->revisionId !== $this->publishedId) {
            throw new LogicException('Approvals pin to the published revision (' . $this->publishedId . ').');
        }
        $this->approvals[] = $approval;
        $this->record($approval->at, $approval->approverId, 'approved-' . $approval->revisionId);
        return $this;
    }

    /** Compare two revisions of this subject. */
    public function compare(string $fromId, string $toId): RevisionDiff
    {
        $from = $this->revision($fromId);
        $to = $this->revision($toId);
        if ($from === null || $to === null) {
            throw new LogicException('Both revisions must exist.');
        }
        return RevisionDiff::between($from, $to);
    }

    public function published(): ?ContentRevision
    {
        return $this->publishedId === null ? null : $this->revision($this->publishedId);
    }

    public function draft(): ?ContentRevision
    {
        return $this->draftId === null ? null : $this->revision($this->draftId);
    }

    /** @return ContentRevision[] */
    public function revisions(): array
    {
        return $this->revisions;
    }

    /** @return ApprovalContext[] */
    public function approvals(): array
    {
        return $this->approvals;
    }

    public function history(): array
    {
        return $this->history;
    }

    private function revision(string $id): ?ContentRevision
    {
        foreach ($this->revisions as $r) {
            if ($r->id === $id) {
                return $r;
            }
        }
        return null;
    }

    private function record(string $at, string $actor, string $event): void
    {
        $this->history[] = ['at' => $at, 'actor' => $actor, 'event' => $event];
    }
}
