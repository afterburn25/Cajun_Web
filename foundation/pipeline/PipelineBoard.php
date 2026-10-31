<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Pipeline;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * The board definition: an ordered set of stages plus the transition
 * rules between them. Validated at construction — duplicate ids,
 * dangling `next` references and missing initial stages are build
 * errors. `canTransition` is the single source of truth the UI (drag,
 * keyboard control, or dropdown) and the persistence layer both consult,
 * satisfying "dragging must not be the only accessible control".
 */
final class PipelineBoard
{
    /** @var array<string,PipelineStage> */
    private array $stages = [];

    /**
     * @param PipelineStage[] $stages in display order
     * @param string $initialStageId stage new leads enter
     */
    public function __construct(
        array $stages,
        public readonly string $initialStageId,
    ) {
        if ($stages === []) {
            throw new InvalidArgumentException('A board needs at least one stage.');
        }
        foreach ($stages as $stage) {
            if (isset($this->stages[$stage->id])) {
                throw new InvalidArgumentException('Duplicate stage id: ' . $stage->id);
            }
            $this->stages[$stage->id] = $stage;
        }
        foreach ($this->stages as $stage) {
            foreach ($stage->next as $nextId) {
                if (!isset($this->stages[$nextId])) {
                    throw new InvalidArgumentException('Stage ' . $stage->id . ' references unknown next stage ' . $nextId . '.');
                }
            }
        }
        if (!isset($this->stages[$initialStageId])) {
            throw new InvalidArgumentException('Initial stage does not exist: ' . $initialStageId);
        }
    }

    public function stage(string $id): ?PipelineStage
    {
        return $this->stages[$id] ?? null;
    }

    public function initial(): PipelineStage
    {
        return $this->stages[$this->initialStageId];
    }

    /** Whether moving from $fromId to $toId is a configured transition. */
    public function canTransition(string $fromId, string $toId): bool
    {
        $from = $this->stages[$fromId] ?? null;
        return $from !== null && in_array($toId, $from->next, true);
    }

    /** @return PipelineStage[] in display order */
    public function stages(): array
    {
        $list = array_values($this->stages);
        usort($list, fn (PipelineStage $a, PipelineStage $b) => $a->position <=> $b->position);
        return $list;
    }

    /** Stages a lead currently in $stageId may move to. */
    public function allowedMoves(string $stageId): array
    {
        $stage = $this->stages[$stageId] ?? null;
        if ($stage === null) {
            return [];
        }
        return array_values(array_filter(
            $this->stages,
            fn (PipelineStage $s) => in_array($s->id, $stage->next, true),
        ));
    }
}
