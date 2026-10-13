<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Access;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/** Current, server-loaded mailbox membership; never populated from request input. */
final readonly class MailboxGrant
{
    public function __construct(
        public string $mailboxId,
        public bool $canRead,
        public bool $canSend,
        public bool $enabled,
    ) {
        if (trim($mailboxId) === '') {
            throw new InvalidArgumentException('A mailbox grant needs an identifier.');
        }
    }
}
