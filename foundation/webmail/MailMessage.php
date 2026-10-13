<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Webmail;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A-06 — an immutable listing-level summary of one message in one mailbox.
 *
 * Carries only what a folder listing shows: uid, folder, envelope fields
 * and flags. There is deliberately no body — fetching and safely rendering
 * message content is the adapter's separate concern, so a listing object
 * can never smuggle unescaped HTML to the UI. Read/unread transitions are
 * explicit flag changes; `flagged`/`draft`/`answered`/`attachment` are
 * descriptive flags.
 *
 * @internal Roadmap A-06; additively inserted.
 */
final readonly class MailMessage
{
    public const FLAG_SEEN = 'seen';
    public const FLAG_ANSWERED = 'answered';
    public const FLAG_FLAGGED = 'flagged';
    public const FLAG_DRAFT = 'draft';
    public const FLAG_ATTACHMENT = 'attachment';

    public const FLAGS = [
        self::FLAG_SEEN, self::FLAG_ANSWERED, self::FLAG_FLAGGED,
        self::FLAG_DRAFT, self::FLAG_ATTACHMENT,
    ];

    /** @param list<string> $flags subset of FLAGS */
    public function __construct(
        public int $uid,
        public string $mailboxId,
        public string $folder,
        public string $from,
        public string $subject,
        public int $date,
        public array $flags = [],
    ) {
        if ($uid < 1) {
            throw new InvalidArgumentException('Message uid must be >= 1.');
        }
        foreach (['mailboxId' => $mailboxId, 'folder' => $folder, 'from' => $from] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Message {$f} must not be empty.");
            }
        }
        foreach ($flags as $flag) {
            if (!in_array($flag, self::FLAGS, true)) {
                throw new InvalidArgumentException("Unknown message flag '{$flag}'.");
            }
        }
        if ($date < 0) {
            throw new InvalidArgumentException('Message date cannot be negative.');
        }
    }

    public function isUnread(): bool
    {
        return !in_array(self::FLAG_SEEN, $this->flags, true);
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags, true);
    }

    public function markSeen(): self
    {
        return $this->withFlag(self::FLAG_SEEN, true);
    }

    public function markUnread(): self
    {
        return $this->withFlag(self::FLAG_SEEN, false);
    }

    public function withFlag(string $flag, bool $on): self
    {
        if (!in_array($flag, self::FLAGS, true)) {
            throw new InvalidArgumentException("Unknown message flag '{$flag}'.");
        }
        $flags = $this->flags;
        if ($on && !in_array($flag, $flags, true)) {
            $flags[] = $flag;
        }
        if (!$on) {
            $flags = array_values(array_filter($flags, static fn ($f) => $f !== $flag));
        }
        return new self($this->uid, $this->mailboxId, $this->folder, $this->from, $this->subject, $this->date, $flags);
    }
}
