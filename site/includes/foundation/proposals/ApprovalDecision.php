<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Proposals;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One client decision pinned to an exact version id. Approving is only
 * possible on the proposal's current version — a decision created for
 * a superseded snapshot is rejected by Proposal::decide, so nobody can
 * "approve" terms that were already replaced.
 */
final readonly class ApprovalDecision
{
    public const APPROVE = 'approve';
    public const REQUEST_CHANGES = 'request-changes';

    public function __construct(
        public string $id,
        public string $versionId,
        public string $actorId,
        public string $kind,
        public string $note,
        public string $at,
    ) {
        foreach (['id', 'versionId', 'actorId', 'at'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A decision needs ' . $field . '.');
            }
        }
        if (!in_array($kind, [self::APPROVE, self::REQUEST_CHANGES], true)) {
            throw new InvalidArgumentException('A decision is approve or request-changes.');
        }
        if ($kind === self::REQUEST_CHANGES && trim($note) === '') {
            throw new InvalidArgumentException('A change request needs a note describing what must change.');
        }
    }
}
