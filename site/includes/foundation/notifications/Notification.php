<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Notifications;

use InvalidArgumentException;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * P-07 — a single portal notification, scoped to one recipient and one audience.
 *
 * The audience split is structural: staff-only content can never be surfaced
 * through a client feed, and client notifications carry no staff-only payload —
 * which makes the "client notifications never include staff-only notes"
 * acceptance an invariant of the model rather than a UI convention.
 *
 * This is a model layer: it owns no persistence, rendering or delivery.
 *
 * @internal Roadmap P-07; additively inserted.
 */
final readonly class Notification
{
    public const AUDIENCE_CLIENT = 'client';
    public const AUDIENCE_STAFF = 'staff';

    public const KINDS = [
        'project-update', 'approval-request', 'download-ready',
        'support-reply', 'billing', 'service-event',
    ];

    /**
     * @param string $link Typed opaque target reference `kind:id` such as
     *                     `project:7` — mirrors LeadRecord's linking. The
     *                     object type is explicit so a renderer can authorize
     *                     before producing a URL; the model never builds URLs.
     */
    public function __construct(
        public string $id,
        public string $audience,
        public string $recipientId,
        public string $kind,
        public string $title,
        public string $body,
        public string $link,
        public int $createdAt,
    ) {
        if (!in_array($audience, [self::AUDIENCE_CLIENT, self::AUDIENCE_STAFF], true)) {
            throw new InvalidArgumentException("Unknown audience '{$audience}'.");
        }
        if (!in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Unknown notification kind '{$kind}'.");
        }
        foreach (['id' => $id, 'recipientId' => $recipientId, 'title' => $title] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Notification {$f} must not be empty.");
            }
        }
        if (!preg_match('/^[a-z][a-z0-9-]*:[A-Za-z0-9_-]+$/', $link)) {
            throw new InvalidArgumentException("Link '{$link}' must be a typed reference like 'project:7'.");
        }
    }

    /**
     * Assert this notification may be surfaced to a client recipient.
     * Throws on staff audience — a feed adapter must never pass staff
     * content through client rendering.
     */
    public function assertClientVisible(): void
    {
        if ($this->audience !== self::AUDIENCE_CLIENT) {
            throw new InvalidArgumentException('Staff-audience notification cannot be shown to a client.');
        }
    }
}
