<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Notifications;

use InvalidArgumentException;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

require_once __DIR__ . '/Notification.php';

/**
 * P-07 — one recipient's notification feed with per-person read state.
 *
 * A feed is constructed for exactly one (audience, recipientId) pair and
 * structurally rejects notifications addressed to anyone else: read state and
 * listing can therefore never cross recipients — the private-unread-bell
 * acceptance holds by construction. Marking read/unread requires the actor to
 * be the recipient themself; staff acting on a client's feed is denied and
 * audited by the caller (the model has no logging dependency).
 *
 * Ordering is newest-first; paging is explicit for paginated history.
 *
 * @internal Roadmap P-07; additively inserted.
 */
final class NotificationFeed
{
    /** @var array<string, Notification> id => notification */
    private array $items = [];

    /** @var array<string, true> notification id => unread */
    private array $unread = [];

    /**
     * @param iterable<Notification> $notifications All records addressed to
     *        this recipient; foreign records are a construction-time error.
     * @param array<string, true>    $readIds       ids already marked read.
     */
    public function __construct(
        private readonly string $recipientId,
        private readonly string $audience,
        iterable $notifications = [],
        array $readIds = [],
    ) {
        foreach ($notifications as $n) {
            if (!$n instanceof Notification) {
                throw new InvalidArgumentException('Feed accepts Notification records only.');
            }
            if ($n->recipientId !== $recipientId) {
                throw new InvalidArgumentException(
                    "Notification {$n->id} is addressed to {$n->recipientId}, not {$recipientId}."
                );
            }
            if ($n->audience !== $audience) {
                throw new InvalidArgumentException(
                    "Notification {$n->id} belongs to the {$n->audience} audience, not {$audience}."
                );
            }
            if (isset($this->items[$n->id])) {
                throw new InvalidArgumentException("Duplicate notification id {$n->id}.");
            }
            $this->items[$n->id] = $n;
        }
        foreach ($readIds as $id => $v) {
            if (!isset($this->items[$id])) {
                throw new InvalidArgumentException("Read marker for unknown notification {$id}.");
            }
        }
        $this->unread = array_diff_key($this->items, $readIds);
    }

    /** Newest-first page of this feed. */
    public function page(int $offset = 0, int $limit = 25): array
    {
        if ($offset < 0 || $limit < 1) {
            throw new InvalidArgumentException('Page requires offset >= 0 and limit >= 1.');
        }
        $all = array_values($this->items);
        usort($all, fn (Notification $a, Notification $b) => $b->createdAt <=> $a->createdAt);
        return array_slice($all, $offset, $limit);
    }

    public function unreadCount(): int
    {
        return count($this->unread);
    }

    public function isUnread(string $notificationId): bool
    {
        return isset($this->unread[$notificationId]);
    }

    /**
     * Mark a notification read. Only the recipient themself may change their
     * own read state — a different actor is refused, so support/admin views
     * cannot silently consume a client's unread bell.
     */
    public function markRead(string $notificationId, string $actor): void
    {
        $this->assertActor($actor);
        $this->assertKnown($notificationId);
        unset($this->unread[$notificationId]);
    }

    public function markUnread(string $notificationId, string $actor): void
    {
        $this->assertActor($actor);
        $this->assertKnown($notificationId);
        $this->unread[$notificationId] = true;
    }

    public function markAllRead(string $actor): void
    {
        $this->assertActor($actor);
        $this->unread = [];
    }

    private function assertActor(string $actor): void
    {
        if ($actor !== $this->recipientId) {
            throw new InvalidArgumentException(
                'Only the recipient may change read state on their own feed.'
            );
        }
    }

    private function assertKnown(string $notificationId): void
    {
        if (!isset($this->items[$notificationId])) {
            throw new InvalidArgumentException("Unknown notification {$notificationId}.");
        }
    }
}
