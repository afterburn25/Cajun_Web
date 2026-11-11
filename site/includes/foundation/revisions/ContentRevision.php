<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Revisions;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * One immutable content snapshot in a subject's history. `restoredFrom`
 * records when a revision reintroduces an older snapshot's content —
 * restores are new revisions, never mutations of history.
 */
final readonly class ContentRevision
{
    public function __construct(
        public string $id,
        public string $subject,
        public int $number,
        public string $content,
        public string $authorId,
        public string $createdAt,
        public string $note = '',
        public ?string $restoredFrom = null,
    ) {
        foreach (['id', 'subject', 'authorId', 'createdAt'] as $field) {
            if (trim($this->$field) === '') {
                throw new InvalidArgumentException('A revision needs ' . $field . '.');
            }
        }
        if ($number < 1) {
            throw new InvalidArgumentException('Revision numbers start at 1.');
        }
    }

    public function contentHash(): string
    {
        return hash('sha256', $this->content);
    }
}
