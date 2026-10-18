<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Revisions;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * A line-level comparison of two revisions: added/removed line sets
 * plus whether the contents are identical. Deliberately simple —
 * the UI adapter renders a richer diff if it needs one; this contract
 * only guarantees an honest, deterministic comparison.
 */
final readonly class RevisionDiff
{
    /**
     * @param string[] $added   lines present in the newer revision only
     * @param string[] $removed lines present in the older revision only
     */
    public function __construct(
        public string $fromRevisionId,
        public string $toRevisionId,
        public array $added,
        public array $removed,
    ) {
    }

    public static function between(ContentRevision $from, ContentRevision $to): self
    {
        $fromLines = explode("\n", $from->content);
        $toLines = explode("\n", $to->content);
        return new self(
            $from->id,
            $to->id,
            array_values(array_diff($toLines, $fromLines)),
            array_values(array_diff($fromLines, $toLines)),
        );
    }

    public function identical(): bool
    {
        return $this->added === [] && $this->removed === [];
    }

    public function changedLineCount(): int
    {
        return count($this->added) + count($this->removed);
    }
}
