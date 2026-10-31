<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SupportAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * One live support session: staff working inside a client's portal.
 * A-09 requires a visible banner, a Return-to-admin path, expiry,
 * revocation and action-attributed audit — the session carries the
 * staff identity (banner/attribution) and the grant it came from, and
 * every performed action is appended to an internal log naming the
 * staff actor. Sensitive actions require the grant's separate
 * sensitive-action permission; nothing here lets an ordinary customer
 * session become a support session (construction is package-private
 * through the broker).
 */
final class SupportSession
{
    private bool $closed = false;

    /** @var list<array{at:int,actor:string,action:string,allowed:bool}> */
    private array $log = [];

    /** @internal created only by SupportBroker::launch() */
    public function __construct(
        public readonly string $id,
        public readonly SupportGrant $grant,
        public readonly int $startedAt,
        public readonly int $expiresAt,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('A support session needs an id.');
        }
        if ($expiresAt <= $startedAt) {
            throw new InvalidArgumentException('Expiry must be after start.');
        }
    }

    public function staffId(): string
    {
        return $this->grant->staffId;
    }

    public function clientId(): string
    {
        return $this->grant->clientId;
    }

    /** Banner text the integrated UI must render while this session is live. */
    public function bannerText(): string
    {
        return 'Support session — staff ' . $this->staffId() . ' viewing ' . $this->clientId() . ' (Return to admin to exit)';
    }

    public function live(int $now): bool
    {
        return !$this->closed && !$this->grant->revoked() && $now < $this->expiresAt;
    }

    /**
     * Record an action performed in the client's portal. Returns false
     * when the action is not permitted (expired/closed session, or a
     * sensitive action the grant does not allow); the denial is still
     * logged with attribution.
     */
    public function perform(string $action, int $now, bool $sensitive = false): bool
    {
        if (trim($action) === '') {
            throw new InvalidArgumentException('An action needs a description.');
        }
        $allowed = $this->live($now) && (!$sensitive || $this->grant->permitsSensitive);
        $this->log[] = ['at' => $now, 'actor' => $this->staffId(), 'action' => $action, 'allowed' => $allowed];
        return $allowed;
    }

    /** Return-to-admin / explicit end. */
    public function close(string $actor): void
    {
        if ($this->closed) {
            throw new LogicException('Session is already closed.');
        }
        if (trim($actor) === '') {
            throw new InvalidArgumentException('Closing a session needs an actor.');
        }
        $this->closed = true;
    }

    public function closed(): bool
    {
        return $this->closed;
    }

    /** @return list<array{at:int,actor:string,action:string,allowed:bool}> */
    public function actionLog(): array
    {
        return $this->log;
    }
}
