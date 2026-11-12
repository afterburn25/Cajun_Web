<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Revisions;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * A line-level comparison of two revisions: added/removed line occurrences
 * plus whether the contents are identical. Deliberately simple —
 * the UI adapter renders a richer diff if it needs one; this contract
 * only guarantees an honest, deterministic comparison.
 */
final readonly class RevisionDiff
{
    /**
     * @param string[] $added   unmatched line occurrences in newer order
     * @param string[] $removed unmatched line occurrences in older order
     */
    private function __construct(
        public string $fromRevisionId,
        public string $toRevisionId,
        public array $added,
        public array $removed,
        private bool $identicalContents,
    ) {
    }

    public static function between(ContentRevision $from, ContentRevision $to): self
    {
        $fromLines = explode("\n", $from->content);
        $toLines = explode("\n", $to->content);
        return new self(
            $from->id,
            $to->id,
            self::unmatched($toLines, $fromLines),
            self::unmatched($fromLines, $toLines),
            $from->content === $to->content,
        );
    }

    /** Preserve duplicate multiplicity without treating numeric-looking lines as array indexes. */
    private static function unmatched(array $candidates, array $available): array
    {
        $counts=[];
        foreach($available as $line){$key=strlen($line).':'.$line;$counts[$key]=($counts[$key]??0)+1;}
        $unmatched=[];
        foreach($candidates as $line){
            $key=strlen($line).':'.$line;
            if(($counts[$key]??0)>0){$counts[$key]--;continue;}
            $unmatched[]=$line;
        }
        return $unmatched;
    }

    public function identical(): bool
    {
        return $this->identicalContents;
    }

    public function changedLineCount(): int
    {
        return count($this->added) + count($this->removed);
    }
}
