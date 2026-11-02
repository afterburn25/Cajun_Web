<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Notifications;

use InvalidArgumentException;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

require_once __DIR__ . '/Notification.php';

/**
 * P-07 — a recipient's per-kind delivery preferences.
 *
 * Portal (in-app bell/timeline) delivery is unconditional — it is the
 * product's own surface. Email delivery is an opt-out per kind, so a client
 * can silence billing email without hiding the portal item. Preferences are
 * recorded per (audience, recipientId); there are no implicit global defaults
 * beyond "enabled", and unknown kinds are rejected rather than stored.
 *
 * @internal Roadmap P-07; additively inserted.
 */
final class NotificationPreferences
{
    /** @var array<string, bool> kind => email enabled */
    private array $email = [];

    public function __construct(
        public readonly string $recipientId,
        public readonly string $audience,
        array $email = [],
    ) {
        foreach ($email as $kind => $enabled) {
            $this->assertKind($kind);
            $this->email[$kind] = (bool) $enabled;
        }
    }

    /** Portal delivery cannot be disabled — the bell is the product surface. */
    public function portalEnabled(string $kind): bool
    {
        $this->assertKind($kind);
        return true;
    }

    /** Email defaults to enabled; explicit opt-out per kind. */
    public function emailEnabled(string $kind): bool
    {
        $this->assertKind($kind);
        return $this->email[$kind] ?? true;
    }

    public function setEmail(string $kind, bool $enabled, string $actor): void
    {
        $this->assertKind($kind);
        if ($actor !== $this->recipientId) {
            throw new InvalidArgumentException('Only the recipient may change their own preferences.');
        }
        $this->email[$kind] = $enabled;
    }

    /** @return array<string, bool> explicit overrides only */
    public function overrides(): array
    {
        return $this->email;
    }

    private function assertKind(string $kind): void
    {
        if (!in_array($kind, Notification::KINDS, true)) {
            throw new InvalidArgumentException("Unknown notification kind '{$kind}'.");
        }
    }
}
