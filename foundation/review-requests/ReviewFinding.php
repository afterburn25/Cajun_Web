<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\ReviewRequests;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A single reported finding. Human judgment needs no observation date; lab,
 * field and absent evidence must carry both an observation timestamp and a
 * source label so reports never blur measured data with opinion, and gaps
 * are stated rather than silently omitted.
 */
final readonly class ReviewFinding
{
    public const EVIDENCE_HUMAN = 'human-review';
    public const EVIDENCE_LAB = 'lab-data';
    public const EVIDENCE_FIELD = 'field-data';
    public const EVIDENCE_ABSENT = 'absent-data';

    public function __construct(
        public string $title,
        public string $detail,
        public string $evidence = self::EVIDENCE_HUMAN,
        public ?int $observedAt = null,
        public ?string $source = null,
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('A review finding needs a title.');
        }
        if (trim($detail) === '') {
            throw new InvalidArgumentException('A review finding needs detail.');
        }
        if (!in_array($evidence, [self::EVIDENCE_HUMAN, self::EVIDENCE_LAB, self::EVIDENCE_FIELD, self::EVIDENCE_ABSENT], true)) {
            throw new InvalidArgumentException("Unknown review evidence kind '{$evidence}'.");
        }
        $measured = $evidence !== self::EVIDENCE_HUMAN;
        if ($measured && $observedAt === null) {
            throw new InvalidArgumentException("Evidence kind '{$evidence}' needs an observation timestamp.");
        }
        if ($measured && ($source === null || trim($source) === '')) {
            throw new InvalidArgumentException("Evidence kind '{$evidence}' needs a source label.");
        }
        if ($observedAt !== null && $observedAt < 0) {
            throw new InvalidArgumentException('An observation timestamp cannot be negative.');
        }
        if (!$measured && $source !== null && trim($source) === '') {
            throw new InvalidArgumentException('A finding source must be non-empty or null.');
        }
    }

    /** Whether this finding states that expected data was unavailable. */
    public function declaresAbsence(): bool
    {
        return $this->evidence === self::EVIDENCE_ABSENT;
    }
}
