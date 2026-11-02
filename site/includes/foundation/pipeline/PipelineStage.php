<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Pipeline;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One named pipeline stage. `next` lists the stages a lead may move to
 * from here — empty means terminal. Stage order is declared by the
 * board definition, not inferred from ids.
 */
final readonly class PipelineStage
{
    /**
     * @param string[] $next allowed successor stage ids (empty = terminal)
     */
    public function __construct(
        public string $id,
        public string $label,
        public int $position,
        public array $next,
        public bool $isWon = false,
        public bool $isLost = false,
    ) {
        foreach (['id', 'label'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A stage needs ' . $field . '.');
            }
        }
        if ($position < 0) {
            throw new InvalidArgumentException('Stage position must be non-negative.');
        }
        if ($isWon && $isLost) {
            throw new InvalidArgumentException('A stage cannot be both won and lost.');
        }
    }

    public function terminal(): bool
    {
        return $this->next === [];
    }
}
