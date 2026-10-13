<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Webmail;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

require_once __DIR__ . '/MailMessage.php';
require_once __DIR__ . '/MailboxAssignment.php';

/**
 * A-06 — an immutable combined-inbox snapshot for one staff member.
 *
 * Construction takes the staff id, their assignments, and the message
 * summaries the adapter fetched. Any message addressed to a mailbox the
 * staff member has no active assignment for is a construction-time
 * error — combined-inbox privacy is structural. Duplicate full message
 * identities (mailbox + folder + uidValidity + uid) are rejected rather
 * than double-counted.
 *
 * This is a SNAPSHOT: it freezes the grants and messages supplied at
 * construction. After any assignment change (issue/revoke/expire) or
 * new sync, the adapter must rebuild it — a stale snapshot keeps
 * showing its frozen state, never silently widens.
 *
 * Authorization note: this view enforces *assignment* scope only. The
 * canonical per-request authority remains
 * `CajunWebPro\Foundation\Access\PermissionPolicy` with server-loaded
 * `Access\MailboxGrant` capabilities (`canRead`/`canSend`); an active
 * assignment may produce those capabilities, never replace them.
 *
 * @internal Roadmap A-06; additively inserted.
 */
final class UnifiedInbox
{
    /** @var list<MailMessage> newest-first */
    private array $messages = [];

    /**
     * @param iterable<MailboxAssignment> $assignments the staff member's
     *        assignments — expired/revoked/not-yet-issued authorize nothing.
     * @param iterable<MailMessage> $messages fetched summaries; foreign
     *        mailboxes and duplicate identities are rejected.
     */
    public function __construct(
        private readonly string $staffId,
        iterable $assignments,
        iterable $messages,
        int $now,
    ) {
        if ($now < 0) {
            throw new InvalidArgumentException('Now must be non-negative.');
        }
        $allowed = [];
        foreach ($assignments as $g) {
            if (!$g instanceof MailboxAssignment) {
                throw new InvalidArgumentException('UnifiedInbox accepts MailboxAssignment records only.');
            }
            if ($g->staffId === $staffId && $g->active($now)) {
                $allowed[$g->mailboxId] = true;
            }
        }
        $seen = [];
        foreach ($messages as $m) {
            if (!$m instanceof MailMessage) {
                throw new InvalidArgumentException('UnifiedInbox accepts MailMessage records only.');
            }
            if (!isset($allowed[$m->mailboxId])) {
                throw new InvalidArgumentException(
                    "Message uid {$m->uid} is in mailbox {$m->mailboxId}, which {$staffId} has no active assignment for."
                );
            }
            $key = $m->identity();
            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Duplicate message identity {$key} in one inbox.");
            }
            $seen[$key] = true;
            $this->messages[] = $m;
        }
        usort($this->messages, static fn (MailMessage $a, MailMessage $b) => $b->date <=> $a->date);
    }

    /** Newest-first page across all assigned mailboxes. */
    public function page(int $offset = 0, int $limit = 50): array
    {
        if ($offset < 0 || $limit < 1) {
            throw new InvalidArgumentException('Page requires offset >= 0 and limit >= 1.');
        }
        return array_slice($this->messages, $offset, $limit);
    }

    public function unreadCount(): int
    {
        return count(array_filter($this->messages, static fn (MailMessage $m) => $m->isUnread()));
    }

    /** Unread count restricted to one mailbox — per-account badge data. */
    public function unreadIn(string $mailboxId): int
    {
        return count(array_filter(
            $this->messages,
            static fn (MailMessage $m) => $m->mailboxId === $mailboxId && $m->isUnread()
        ));
    }

    public function total(): int
    {
        return count($this->messages);
    }
}
