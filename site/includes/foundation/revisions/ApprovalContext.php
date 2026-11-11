<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Revisions;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * An approval recorded against a specific published revision — kept as
 * a separate event rather than a flag on the revision so approval
 * context survives subsequent drafts and restores unchanged.
 */
final readonly class ApprovalContext
{
    public function __construct(
        public string $id,
        public string $revisionId,
        public string $approverId,
        public string $at,
        public string $note = '',
    ) {
        foreach (['id', 'revisionId', 'approverId', 'at'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('An approval needs ' . $field . '.');
            }
        }
    }
}
