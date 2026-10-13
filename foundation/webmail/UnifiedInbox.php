<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Webmail;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

require_once __DIR__ . '/MailMessage.php';
require_once __DIR__ . '/MailboxGrant.php';

/**
 * A-06 — the combined-inbox view for one staff member.
 *
 * Construction takes the staff id, their active grants, and the message
 * summaries the adapter fetched. Any message addressed to a mailbox the
 * staff member has no active grant for is a construction-time error —
 * combined-inbox privacy is structural, so a mailbox leak can never surface
 * another staff member's or a grant-revoked mailbox's messages. Listing is
 * newest-first; paging and the unread count are explicit.
 *
 * @internal Roadmap A-06; additively inserted.
 */
final class UnifiedInbox
{
    /** @var list<MailMessage> newest-first */
    private array $messages = [];

    /**
     * @param iterable<MailboxGrant> $grants   the staff member's grants —
     *        expired/revoked grants authorize nothing.
     * @param iterable<MailMessage> $messages  fetched summaries; foreign
     *        mailboxes are rejected.
     */
    public function __construct(
        private readonly string $staffId,
        iterable $grants,
        iterable $messages,
        int $now,
    ) {
        $allowed = [];
        foreach ($grants as $g) {
            if (!$g instanceof MailboxGrant) {
                throw new InvalidArgumentException('UnifiedInbox accepts MailboxGrant records only.');
            }
            if ($g->staffId === $staffId && $g->active($now)) {
                $allowed[$g->mailboxId] = true;
            }
        }
        foreach ($messages as $m) {
            if (!$m instanceof MailMessage) {
                throw new InvalidArgumentException('UnifiedInbox accepts MailMessage records only.');
            }
            if (!isset($allowed[$m->mailboxId])) {
                throw new InvalidArgumentException(
                    "Message uid {$m->uid} is in mailbox {$m->mailboxId}, which {$staffId} has no active grant for."
                );
            }
            $this->messages[] = $m;
        }
        usort($this->messages, static fn (MailMessage $a, MailMessage $b) => $b->date <=> $a->date);
    }

    /** Newest-first page across all granted mailboxes. */
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
