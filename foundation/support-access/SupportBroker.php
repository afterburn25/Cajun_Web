<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SupportAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/**
 * The single launch gate for A-09 support access. `launch()` is the
 * only way a `SupportSession` should be created in the integrated
 * application — it checks the grant (identity pair, client scope,
 * revocation) and enforces a concurrency cap so one incident cannot
 * flood a client record with parallel sessions. Every launch outcome —
 * granted or denied — is recorded in an audit list; ordinary customer
 * sessions have no path here because issuance requires a staff grant.
 */
final class SupportBroker
{
    public const GRANTED = 'granted';
    public const DENIED = 'denied';
    public const CLOSED = 'closed';
    public const REVOKED = 'revoked';

    /** @var list<SupportSession> */
    private array $sessions = [];

    /** @var list<array{at:int,type:string,staff:string,client:string,detail:string}> */
    private array $audit = [];

    public function __construct(
        public readonly int $maxConcurrentPerClient = 1,
        public readonly int $maxConcurrentPerStaff = 3,
    ) {
        if ($maxConcurrentPerClient < 1 || $maxConcurrentPerStaff < 1) {
            throw new InvalidArgumentException('Concurrency limits must be positive.');
        }
    }

    /**
     * Attempt to open a support session under a grant. Returns the
     * session on success; throws `SupportDenied` with an honest reason
     * on failure. Denials are audited too — support access attempts are
     * security-relevant either way.
     */
    public function launch(SupportGrant $grant, string $sessionId, int $now): SupportSession
    {
        if (trim($sessionId) === '') {
            throw new InvalidArgumentException('A launch needs a session id.');
        }
        $this->assertGrantUsable($grant, $now);
        $this->assertCapacity($grant, $now);

        $session = new SupportSession($sessionId, $grant, $now, $now + $grant->ttlSeconds);
        $this->sessions[] = $session;
        $this->audit[] = [
            'at' => $now,
            'type' => self::GRANTED,
            'staff' => $grant->staffId,
            'client' => $grant->clientId,
            'detail' => 'session ' . $sessionId . ' (reason: ' . ($grant->reason !== '' ? $grant->reason : 'not recorded') . ')',
        ];
        return $session;
    }

    /** Close a session and record the end. */
    public function close(SupportSession $session, string $actor, int $now): void
    {
        $session->close($actor);
        $this->audit[] = [
            'at' => $now,
            'type' => self::CLOSED,
            'staff' => $session->staffId(),
            'client' => $session->clientId(),
            'detail' => 'session ' . $session->id . ' closed by ' . $actor,
        ];
    }

    /** Revoke a grant; live sessions under it stop being usable immediately. */
    public function revokeGrant(SupportGrant $grant, string $actor, int $now): void
    {
        $grant->revoke($actor);
        $this->audit[] = [
            'at' => $now,
            'type' => self::REVOKED,
            'staff' => $grant->staffId,
            'client' => $grant->clientId,
            'detail' => 'grant ' . $grant->id . ' revoked by ' . $actor,
        ];
    }

    /** Live sessions for one client — powers the "who is inside" admin view. */
    public function activeSessionsFor(string $clientId, int $now): array
    {
        return array_values(array_filter(
            $this->sessions,
            fn ($s) => $s->clientId() === $clientId && $s->live($now),
        ));
    }

    /** @return list<array{at:int,type:string,staff:string,client:string,detail:string}> */
    public function auditLog(): array
    {
        return $this->audit;
    }

    private function assertGrantUsable(SupportGrant $grant, int $now): void
    {
        if ($grant->revoked()) {
            $this->deny($grant, 'grant-revoked', $now);
        }
        if (!$grant->usable($now)) {
            $this->deny($grant, 'grant-unusable', $now);
        }
    }

    private function assertCapacity(SupportGrant $grant, int $now): void
    {
        $clientLive = count($this->activeSessionsFor($grant->clientId, $now));
        $staffLive = count(array_filter(
            $this->sessions,
            fn ($s) => $s->staffId() === $grant->staffId && $s->live($now),
        ));
        if ($clientLive >= $this->maxConcurrentPerClient) {
            $this->deny($grant, 'client-session-limit', $now);
        }
        if ($staffLive >= $this->maxConcurrentPerStaff) {
            $this->deny($grant, 'staff-session-limit', $now);
        }
    }

    /** @throws SupportDenied */
    private function deny(SupportGrant $grant, string $reason, int $now): void
    {
        $this->audit[] = [
            'at' => $now,
            'type' => self::DENIED,
            'staff' => $grant->staffId,
            'client' => $grant->clientId,
            'detail' => $reason,
        ];
        throw new SupportDenied($reason);
    }
}
