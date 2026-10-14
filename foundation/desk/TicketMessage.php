<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Desk;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * HC-12 — one immutable message inside a support ticket.
 *
 * Deliberately separate from mailbox email (A-06) and from application
 * notification SMTP (P-07): a ticket message is an internal record, not
 * a mail envelope. `internal` notes are staff-only — the flag is set at
 * construction and `clientVisible()` answers it structurally, so a
 * staff note can never be shown to the client by accident. Bodies are
 * stored as plain text; rendering and escaping are the UI's concern.
 *
 * @internal Roadmap HC-12; additively inserted.
 */
final readonly class TicketMessage
{
    public const AUTHOR_CLIENT = 'client';
    public const AUTHOR_STAFF = 'staff';
    public const AUTHOR_SYSTEM = 'system';

    public function __construct(
        public string $id,
        public string $ticketId,
        public string $authorKind,
        public string $authorRef,
        public string $body,
        public int $createdAt,
        public bool $internal = false,
    ) {
        foreach (['id' => $id, 'ticketId' => $ticketId, 'authorRef' => $authorRef] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Message {$f} must not be empty.");
            }
        }
        if (!in_array($authorKind, [self::AUTHOR_CLIENT, self::AUTHOR_STAFF, self::AUTHOR_SYSTEM], true)) {
            throw new InvalidArgumentException("Unknown author kind '{$authorKind}'.");
        }
        if (trim($body) === '') {
            throw new InvalidArgumentException('A ticket message needs a body.');
        }
        if ($internal && $authorKind !== self::AUTHOR_STAFF) {
            throw new InvalidArgumentException('Only staff can write internal notes.');
        }
        if ($createdAt < 0) {
            throw new InvalidArgumentException('Message time cannot be negative.');
        }
    }

    /** Staff-only notes are structurally invisible to clients. */
    public function clientVisible(): bool
    {
        return !$this->internal;
    }
}
