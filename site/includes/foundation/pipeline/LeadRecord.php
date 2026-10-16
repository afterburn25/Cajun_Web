<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Pipeline;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * One lead's pipeline state: current stage, links to related records
 * (briefs, quotes, projects — referenced by id, not embedded), an
 * optional follow-up date, and append-only transition history carrying
 * actor and note. All stage moves go through `moveTo`, which consults
 * the board — the UI cannot place a card somewhere the rules forbid.
 */
final class LeadRecord
{
    private string $stageId;

    /** @var string[] opaque references: 'brief:12', 'quote:7', 'project:3' */
    private array $links = [];

    private ?string $followUpAt = null;
    private ?string $note = null;

    /** @var array<int,array{at:string,actor:string,event:string}> */
    private array $history = [];

    public function __construct(
        public readonly string $id,
        private readonly PipelineBoard $board,
        public readonly string $contactName,
        string $createdBy,
        string $createdAt,
    ) {
        if (trim($id) === '' || trim($contactName) === '' || trim($createdBy) === '' || trim($createdAt) === '') {
            throw new InvalidArgumentException('A lead needs id, contact name, creator and timestamp.');
        }
        $this->stageId = $board->initialStageId;
        $this->record($createdAt, $createdBy, 'created');
    }

    /** Move to a new stage — only configured transitions succeed. */
    public function moveTo(string $stageId, string $actorId, string $at, string $note = ''): self
    {
        if (!$this->board->canTransition($this->stageId, $stageId)) {
            throw new LogicException('Cannot move ' . $this->id . ' from ' . $this->stageId . ' to ' . $stageId . '.');
        }
        $target = $this->board->stage($stageId);
        $this->stageId = $stageId;
        $event = 'moved:' . $this->stageId . '->' . $stageId;
        if ($target->isWon) {
            $event = 'won';
        } elseif ($target->isLost) {
            $event = 'not-proceeding';
        }
        $this->record($at, $actorId, $event . ($note !== '' ? ' — ' . $note : ''));
        return $this;
    }

    /** Link a related record by opaque reference ('brief:12' etc.). */
    public function link(string $reference, string $actorId, string $at): self
    {
        if (!preg_match('/^[a-z]+:\S+$/', $reference)) {
            throw new InvalidArgumentException('Links are typed references like brief:12.');
        }
        if (!in_array($reference, $this->links, true)) {
            $this->links[] = $reference;
            $this->record($at, $actorId, 'linked:' . $reference);
        }
        return $this;
    }

    public function setFollowUp(?string $date, string $actorId, string $at): self
    {
        if ($date !== null && strtotime($date) === false) {
            throw new InvalidArgumentException('Follow-up must be a parseable date or null.');
        }
        $this->followUpAt = $date;
        $this->record($at, $actorId, 'follow-up-set:' . ($date ?? 'cleared'));
        return $this;
    }

    public function setNote(string $note, string $actorId, string $at): self
    {
        $this->note = $note;
        $this->record($at, $actorId, 'note-updated');
        return $this;
    }

    public function stageId(): string
    {
        return $this->stageId;
    }

    public function stage(): PipelineStage
    {
        return $this->board->stage($this->stageId);
    }

    public function links(): array
    {
        return $this->links;
    }

    public function followUpAt(): ?string
    {
        return $this->followUpAt;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function history(): array
    {
        return $this->history;
    }

    private function record(string $at, string $actor, string $event): void
    {
        $this->history[] = ['at' => $at, 'actor' => $actor, 'event' => $event];
    }
}
