<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Proposals;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * An immutable proposal snapshot. Once submitted the exact content a
 * client saw is frozen — edits always produce a NEW version, so an
 * approval can never apply to silently-changed terms. `contentHash`
 * binds the snapshot bytes for later tamper-evidence checks.
 */
final readonly class ProposalVersion
{
    /**
     * @param string[] $deliverables
     * @param string[] $exclusions
     * @param array<int,array{label:string,amountCents:int}> $milestones
     */
    public function __construct(
        public string $id,
        public int $number,
        public array $deliverables,
        public array $exclusions,
        public string $scheduleNotes,
        public array $milestones,
        public string $authorId,
        public string $createdAt,
        public string $contentHash,
    ) {
        foreach (['id', 'authorId', 'createdAt', 'contentHash'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A version needs ' . $field . '.');
            }
        }
        if ($number < 1) {
            throw new InvalidArgumentException('Version numbers start at 1.');
        }
        if ($deliverables === []) {
            throw new InvalidArgumentException('A version needs at least one deliverable.');
        }
        foreach ($milestones as $m) {
            if (trim($m['label'] ?? '') === '' || !is_int($m['amountCents'] ?? null) || $m['amountCents'] < 0) {
                throw new InvalidArgumentException('Each milestone needs a label and a non-negative cent amount.');
            }
        }
        if (!preg_match('/^[0-9a-f]{64}$/', $contentHash)) {
            throw new InvalidArgumentException('contentHash must be a sha256 hex digest.');
        }
    }

    /**
     * Build a version, hashing the canonical content so the stored
     * digest provably covers deliverables, exclusions, schedule and
     * milestones — exactly what an approver is shown.
     */
    public static function create(
        string $id,
        int $number,
        array $deliverables,
        array $exclusions,
        string $scheduleNotes,
        array $milestones,
        string $authorId,
        string $createdAt,
    ): self {
        $canonical = json_encode([
            'deliverables' => array_values($deliverables),
            'exclusions' => array_values($exclusions),
            'scheduleNotes' => $scheduleNotes,
            'milestones' => $milestones,
        ], JSON_UNESCAPED_SLASHES);
        return new self(
            $id, $number, $deliverables, $exclusions, $scheduleNotes,
            $milestones, $authorId, $createdAt, hash('sha256', $canonical),
        );
    }

    public function totalCents(): int
    {
        return array_sum(array_column($this->milestones, 'amountCents'));
    }
}
