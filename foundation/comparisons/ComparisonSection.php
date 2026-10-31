<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Comparisons;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A publishable comparison block: a heading + intro plus the ids of the
 * registry facts it cites. `renderableFacts()` returns the resolved
 * facts and `publishable()` is false whenever any cited fact is missing
 * or past its review date — stale content cannot be presented as current.
 */
final readonly class ComparisonSection
{
    /** @param string[] $factIds */
    public function __construct(
        public string $id,
        public string $heading,
        public string $intro,
        public array $factIds,
    ) {
        foreach (['id', 'heading'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A section needs ' . $field . '.');
            }
        }
        if ($factIds === []) {
            throw new InvalidArgumentException('A section must cite at least one fact.');
        }
        if (count($factIds) !== count(array_unique($factIds))) {
            throw new InvalidArgumentException('Duplicate fact ids cited.');
        }
    }

    /**
     * Resolved facts in citation order. Throws on a missing id — a
     * section citing a nonexistent fact is a build error, not a 404.
     * @return ComparisonFact[]
     */
    public function renderableFacts(ComparisonRegistry $registry): array
    {
        $facts = [];
        foreach ($this->factIds as $id) {
            $fact = $registry->fact($id);
            if ($fact === null) {
                throw new \LogicException('Section ' . $this->id . ' cites unknown fact ' . $id . '.');
            }
            $facts[] = $fact;
        }
        return $facts;
    }

    /** True only when every cited fact exists and is unexpired. */
    public function publishable(ComparisonRegistry $registry, int $now): bool
    {
        foreach ($this->factIds as $id) {
            $fact = $registry->fact($id);
            if ($fact === null || $fact->stale($now)) {
                return false;
            }
        }
        return true;
    }

    /** Cited facts that are missing or stale — for the admin review UI. */
    public function staleCitations(ComparisonRegistry $registry, int $now): array
    {
        $stale = [];
        foreach ($this->factIds as $id) {
            $fact = $registry->fact($id);
            if ($fact === null || $fact->stale($now)) {
                $stale[] = $id;
            }
        }
        return $stale;
    }
}
