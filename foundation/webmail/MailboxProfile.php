<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Webmail;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * A-06 — one configured incoming/outgoing mail account profile.
 *
 * Validation only: hosts, ports and encryption are checked here; the actual
 * password never enters this object. `credentialRef` is an opaque reference
 * to the application's encrypted credential store — the model can never be
 * built carrying a usable secret, so a profile leak cannot expose one.
 * Plaintext transports are refused: encryption must be ssl or starttls.
 *
 * `disabled` is an explicit operator state — a mailbox that exists but is
 * not in service — distinct from "misconfigured" (which cannot exist).
 *
 * @internal Roadmap A-06; additively inserted.
 */
final readonly class MailboxProfile
{
    public const ENCRYPTION_SSL = 'ssl';
    public const ENCRYPTION_STARTTLS = 'starttls';

    public const STATUS_CONFIGURED = 'configured';
    public const STATUS_DISABLED = 'disabled';

    public function __construct(
        public string $id,
        public string $label,
        public string $ownerStaffId,
        public string $imapHost,
        public int $imapPort,
        public string $imapEncryption,
        public string $smtpHost,
        public int $smtpPort,
        public string $smtpEncryption,
        public string $credentialRef,
        public string $status = self::STATUS_CONFIGURED,
    ) {
        foreach (['id' => $id, 'label' => $label, 'ownerStaffId' => $ownerStaffId, 'credentialRef' => $credentialRef] as $f => $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException("Mailbox {$f} must not be empty.");
            }
        }
        foreach (['imap' => [$imapHost, $imapPort, $imapEncryption], 'smtp' => [$smtpHost, $smtpPort, $smtpEncryption]] as $proto => [$host, $port, $enc]) {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]*[A-Za-z0-9]$/', $host)) {
                throw new InvalidArgumentException("Invalid {$proto} host '{$host}'.");
            }
            if ($port < 1 || $port > 65535) {
                throw new InvalidArgumentException("{$proto} port must be 1–65535.");
            }
            if (!in_array($enc, [self::ENCRYPTION_SSL, self::ENCRYPTION_STARTTLS], true)) {
                throw new InvalidArgumentException("{$proto} encryption must be ssl or starttls — plaintext transport is refused.");
            }
        }
        if (!in_array($status, [self::STATUS_CONFIGURED, self::STATUS_DISABLED], true)) {
            throw new InvalidArgumentException("Unknown mailbox status '{$status}'.");
        }
    }

    /** Whether the profile may be used right now. */
    public function usable(): bool
    {
        return $this->status === self::STATUS_CONFIGURED;
    }
}
