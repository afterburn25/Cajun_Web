<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\SshAccess;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;
use LogicException;

/**
 * The launch decision returned by the broker. `denied` carries a short
 * reason key for the UI/audit; `session` is populated only on allow.
 * No transport is ever touched on a denial.
 */
final readonly class LaunchDecision
{
    private function __construct(
        public bool $allowed,
        public ?TerminalSession $session,
        public ?string $reason,
    ) {
    }

    public static function allow(TerminalSession $session): self
    {
        return new self(true, $session, null);
    }

    public static function deny(string $reason): self
    {
        return new self(false, null, $reason);
    }
}

/**
 * The single gate through which a terminal session can be opened.
 * Checks, in order: the service is an approved registry target, the
 * staff member holds a usable grant for this client+service with the
 * terminal scope, the launch token matches that grant and session and
 * is unconsumed/unexpired, and the concurrent-session cap is not
 * reached. Only then does the transport open — a denied request never
 * reaches the wire. Every outcome is returned as an AuditEvent list
 * the caller persists durably.
 */
final class SessionBroker
{
    /**
     * @param int $idleTimeoutSeconds   idle cap applied to opened sessions
     * @param int $absoluteTimeoutSeconds hard lifetime cap
     * @param int $maxConcurrentPerStaff sessions one staff member may hold
     */
    public function __construct(
        private readonly TargetRegistry $registry,
        private readonly ?TerminalTransport $transport,
        private readonly int $idleTimeoutSeconds = 900,
        private readonly int $absoluteTimeoutSeconds = 3600,
        private readonly int $maxConcurrentPerStaff = 2,
    ) {
        if ($idleTimeoutSeconds < 1 || $absoluteTimeoutSeconds < $idleTimeoutSeconds || $maxConcurrentPerStaff < 1) {
            throw new InvalidArgumentException('Invalid broker timeout/concurrency policy.');
        }
    }

    /**
     * Attempt to open a session. Returns [decision, auditEvents[]].
     * On allow, consumes the token, creates the session, then asks the
     * transport to open — a transport failure produces an audited
     * 'unavailable' denial and the session is terminated.
     *
     * @param TerminalSession[] $openSessions caller-supplied list of
     *     this staff member's currently active sessions
     * @return array{0:LaunchDecision,1:AuditEvent[]}
     */
    public function launch(
        AccessGrant $grant,
        LaunchToken $token,
        string $rawSecret,
        string $serviceId,
        string $staffSessionId,
        string $sessionId,
        int $now,
        array $openSessions,
    ): array {
        $audit = [];
        $deny = function (string $reason) use ($grant, $now, &$audit): array {
            $audit[] = new AuditEvent(
                date('c', $now), $grant->staffId, $grant->clientId,
                $grant->serviceId, 'launch.denied', $reason,
            );
            return [LaunchDecision::deny($reason), $audit];
        };

        $target = $this->registry->lookup($serviceId);
        if ($target === null || $target->serviceId !== $grant->serviceId) {
            return $deny('target-not-approved');
        }
        if ($grant->serviceId !== $serviceId || !$grant->usableAt($now)) {
            return $deny('grant-not-usable');
        }
        if (!$grant->hasScope(AccessGrant::SCOPE_TERMINAL)) {
            return $deny('scope-missing');
        }
        if ($token->grantId !== $grant->id
            || $token->staffSessionId !== $staffSessionId
            || $token->serviceId !== $serviceId) {
            return $deny('token-binding-mismatch');
        }
        if (!$token->usableAt($now)) {
            return $deny($token->consumed() ? 'token-replayed' : 'token-expired');
        }
        if (count(array_filter($openSessions, fn (TerminalSession $s) => $s->active() && $s->refresh($now) === TerminalSession::ACTIVE)) >= $this->maxConcurrentPerStaff) {
            return $deny('concurrent-limit');
        }
        try {
            $token->consume($rawSecret, $now);
        } catch (LogicException) {
            return $deny('token-invalid');
        }
        if ($this->transport === null) {
            $audit[] = new AuditEvent(
                date('c', $now), $grant->staffId, $grant->clientId,
                $grant->serviceId, 'launch.denied', 'transport-unavailable',
            );
            return [LaunchDecision::deny('transport-unavailable'), $audit];
        }

        $session = new TerminalSession(
            $sessionId, $grant->staffId, $grant->clientId, $target,
            $this->idleTimeoutSeconds, $this->absoluteTimeoutSeconds, $now,
        );
        try {
            $this->transport->open($target, $session);
        } catch (TransportException) {
            $session->terminate($now);
            $audit[] = new AuditEvent(
                date('c', $now), $grant->staffId, $grant->clientId,
                $grant->serviceId, 'launch.denied', 'transport-unavailable',
            );
            return [LaunchDecision::deny('transport-unavailable'), $audit];
        }
        $audit[] = new AuditEvent(
            date('c', $now), $grant->staffId, $grant->clientId,
            $grant->serviceId, 'session.opened', 'target ' . $target->host . ':' . $target->port,
        );
        return [LaunchDecision::allow($session), $audit];
    }
}
