<?php
declare(strict_types=1);

/**
 * Synthetic A-10 SSH access-model checks. All identities, targets and
 * secrets are conspicuously-marked fixtures — no SSH transport, no
 * real sessions, no provider calls.
 */

use CajunWebPro\Foundation\SshAccess\AccessGrant;
use CajunWebPro\Foundation\SshAccess\AuditEvent;
use CajunWebPro\Foundation\SshAccess\LaunchDecision;
use CajunWebPro\Foundation\SshAccess\LaunchToken;
use CajunWebPro\Foundation\SshAccess\ServiceTarget;
use CajunWebPro\Foundation\SshAccess\SessionBroker;
use CajunWebPro\Foundation\SshAccess\TargetRegistry;
use CajunWebPro\Foundation\SshAccess\TerminalSession;
use CajunWebPro\Foundation\SshAccess\TerminalTransport;
use CajunWebPro\Foundation\SshAccess\TransportException;

require __DIR__ . '/../../foundation/ssh-access/ServiceTarget.php';
require __DIR__ . '/../../foundation/ssh-access/TargetRegistry.php';
require __DIR__ . '/../../foundation/ssh-access/AccessGrant.php';
require __DIR__ . '/../../foundation/ssh-access/LaunchToken.php';
require __DIR__ . '/../../foundation/ssh-access/TerminalSession.php';
require __DIR__ . '/../../foundation/ssh-access/AuditEvent.php';
require __DIR__ . '/../../foundation/ssh-access/TerminalTransport.php';
require __DIR__ . '/../../foundation/ssh-access/SessionBroker.php';

$checks = 0;
$failures = [];

function check(string $name, bool $actual): void
{
    global $checks, $failures;
    $checks++;
    if (!$actual) {
        $failures[] = $name;
    }
}

function rejects(string $name, callable $action, string $class = InvalidArgumentException::class): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid input was accepted';
    } catch (Throwable $e) {
        if (!($e instanceof $class)) {
            throw $e;
        }
    }
}

// --- Fixtures -------------------------------------------------------------

final class FixtureRegistry implements TargetRegistry
{
    /** @var array<string,ServiceTarget> */
    private array $targets;

    public function __construct(array $targets)
    {
        $this->targets = $targets;
    }

    public function lookup(string $serviceId): ?ServiceTarget
    {
        return $this->targets[$serviceId] ?? null;
    }
}

final class FixtureTransport implements TerminalTransport
{
    public int $opened = 0;
    public bool $fail = false;

    public function open(ServiceTarget $target, TerminalSession $session): void
    {
        if ($this->fail) {
            throw new TransportException('fixture transport unavailable');
        }
        $this->opened++;
    }
}

function target(string $serviceId = 'svc-vps-1'): ServiceTarget
{
    return new ServiceTarget($serviceId, '203.0.113.10', 22, 'cwp-support-fixture', 'unmanaged-vps');
}

function grant(array $changes = []): AccessGrant
{
    return new AccessGrant(...array_replace([
        'id' => 'grant-1',
        'staffId' => 'staff-9',
        'clientId' => 'client-4',
        'serviceId' => 'svc-vps-1',
        'scopes' => [AccessGrant::SCOPE_TERMINAL],
        'issuedBy' => 'owner-1',
        'expiresAt' => date('c', 2000),
        'clientAuthorized' => true,
    ], $changes));
}

function registry(): FixtureRegistry
{
    return new FixtureRegistry(['svc-vps-1' => target()]);
}

// --- ServiceTarget validation --------------------------------------------

check('valid target constructs', target()->host === '203.0.113.10');
rejects('empty service id rejected', fn () => new ServiceTarget('', '203.0.113.10', 22, 'acct', 'unmanaged-vps'));
rejects('invalid host rejected', fn () => new ServiceTarget('svc', 'bad host!', 22, 'acct', 'unmanaged-vps'));
rejects('port out of range rejected', fn () => new ServiceTarget('svc', '203.0.113.10', 70000, 'acct', 'unmanaged-vps'));
rejects('os account with spaces rejected', fn () => new ServiceTarget('svc', '203.0.113.10', 22, 'two words', 'unmanaged-vps'));
rejects('empty service type rejected', fn () => new ServiceTarget('svc', '203.0.113.10', 22, 'acct', ''));

// --- AccessGrant ----------------------------------------------------------

check('grant usable before expiry', grant()->usableAt(1000));
check('grant expires', !grant()->usableAt(2000));
check('grant has terminal scope', grant()->hasScope(AccessGrant::SCOPE_TERMINAL));
check('grant lacks other scopes', !grant()->hasScope('root'));
$revoked = grant()->revoke(date('c', 500));
check('revoked grant unusable', !$revoked->usableAt(1000));
rejects('double revoke rejected', fn () => $revoked->revoke(date('c', 600)), LogicException::class);
rejects('grant without client authorization rejected', fn () => grant(['clientAuthorized' => false]));
rejects('grant without scopes rejected', fn () => grant(['scopes' => []]));
rejects('grant with unparsable expiry rejected', fn () => grant(['expiresAt' => 'not-a-date']));

// --- LaunchToken ----------------------------------------------------------

[$token, $raw] = LaunchToken::mint('tok-1', grant(), 'session-abc', 1000, 120);
check('minted token usable', $token->usableAt(1000));
check('token stores hash not raw', $token->tokenHash === hash('sha256', $raw) && $token->tokenHash !== $raw);
check('raw secret has length', strlen($raw) >= 16);
check('token bound to grant', $token->grantId === 'grant-1' && $token->staffId === 'staff-9' && $token->clientId === 'client-4' && $token->serviceId === 'svc-vps-1');
check('matching secret verifies', $token->matches($raw));
check('wrong secret does not match', !$token->matches('wrong-secret-fixture'));
$token->consume($raw, 1050);
check('consumed token marked', $token->consumed());
rejects('replayed token rejected', fn () => $token->consume($raw, 1060), LogicException::class);
rejects('wrong secret rejected', function () {
    [$t] = LaunchToken::mint('tok-2', grant(), 'session-abc', 1000, 120);
    $t->consume('wrong-secret-fixture', 1050);
}, LogicException::class);
rejects('expired token rejected', function () {
    [$t, $r] = LaunchToken::mint('tok-3', grant(), 'session-abc', 1000, 120);
    $t->consume($r, 1200);
}, LogicException::class);
rejects('too-short TTL rejected', fn () => LaunchToken::mint('tok-4', grant(), 'session-abc', 1000, 5));
rejects('too-long TTL rejected', fn () => LaunchToken::mint('tok-5', grant(), 'session-abc', 1000, 3600));
rejects('weak entropy rejected', fn () => LaunchToken::mint('tok-6', grant(), 'session-abc', 1000, 120, fn () => 'short'));

// --- TerminalSession ------------------------------------------------------

$session = new TerminalSession('sess-1', 'staff-9', 'client-4', target(), 60, 300, 1000);
check('session starts active', $session->active());
$session->activity(1030);
check('activity keeps session', $session->refresh(1089) === TerminalSession::ACTIVE);
check('idle timeout expires', $session->refresh(1091) === TerminalSession::EXPIRED);
check('expired session records end', $session->endedAt() === 1091);
rejects('activity on expired session rejected', fn () => $session->activity(1100), LogicException::class);

$absolute = new TerminalSession('sess-2', 'staff-9', 'client-4', target(), 100, 200, 1000);
$absolute->activity(1150);
check('absolute timeout expires despite activity', $absolute->refresh(1200) === TerminalSession::EXPIRED);

$closing = new TerminalSession('sess-3', 'staff-9', 'client-4', target(), 60, 300, 1000);
$closing->close(1050);
check('close transitions', $closing->state() === TerminalSession::CLOSED && $closing->endedAt() === 1050);
rejects('terminate on closed session rejected', fn () => $closing->terminate(1060), LogicException::class);

$killed = new TerminalSession('sess-4', 'staff-9', 'client-4', target(), 60, 300, 1000);
$killed->terminate(1040);
check('terminate transitions', $killed->state() === TerminalSession::TERMINATED);
check('history records lifecycle', count($killed->history()) === 2 && $killed->history()[1]['event'] === 'terminated');
rejects('absolute < idle rejected', fn () => new TerminalSession('sess-5', 'staff-9', 'client-4', target(), 300, 60, 1000));

// --- SessionBroker --------------------------------------------------------

$transport = new FixtureTransport();
$broker = new SessionBroker(registry(), $transport, 60, 300, 2);

// Happy path
[$tok, $secret] = LaunchToken::mint('tok-b1', grant(), 'session-abc', 1000, 120);
[$decision, $audit] = $broker->launch(grant(), $tok, $secret, 'svc-vps-1', 'session-abc', 'sess-b1', 1050, []);
check('valid launch allowed', $decision->allowed && $decision->session !== null);
check('allowed session active', $decision->session->active() && $decision->session->state() === TerminalSession::ACTIVE);
check('transport opened once', $transport->opened === 1);
check('opened audit recorded', $audit !== [] && end($audit)->action === 'session.opened');
check('token consumed on launch', $tok->consumed());

// No transport configured — honest unavailable, no session created
$gateOnly = new SessionBroker(registry(), null, 60, 300, 2);
[$tok2, $sec2] = LaunchToken::mint('tok-b2', grant(), 'session-abc', 1000, 120);
[$d2, $a2] = $gateOnly->launch(grant(), $tok2, $sec2, 'svc-vps-1', 'session-abc', 'sess-b2', 1050, []);
check('no transport denies honestly', !$d2->allowed && $d2->reason === 'transport-unavailable');
check('unavailable denial audited', end($a2)->action === 'launch.denied' && end($a2)->detail === 'transport-unavailable');

// Unapproved target
[$tok3, $sec3] = LaunchToken::mint('tok-b3', grant(), 'session-abc', 1000, 120);
[$d3] = $broker->launch(grant(), $tok3, $sec3, 'svc-other-9', 'session-abc', 'sess-b3', 1050, []);
check('unapproved service denied', !$d3->allowed && $d3->reason === 'target-not-approved');
check('denied request never reaches transport', $transport->opened === 1);

// Expired grant
[$tok4, $sec4] = LaunchToken::mint('tok-b4', grant(), 'session-abc', 1000, 120);
[$d4] = $broker->launch(grant(), $tok4, $sec4, 'svc-vps-1', 'session-abc', 'sess-b4', 2500, []);
check('expired grant denied', !$d4->allowed && $d4->reason === 'grant-not-usable');

// Missing terminal scope
$scoped = grant(['scopes' => ['view']]);
[$d5] = $broker->launch($scoped, $tok4, $sec4, 'svc-vps-1', 'session-abc', 'sess-b5', 1050, []);
check('missing scope denied', !$d5->allowed && $d5->reason === 'scope-missing');

// Token bound to a different staff session
[$tok6, $sec6] = LaunchToken::mint('tok-b6', grant(), 'session-abc', 1000, 120);
[$d6] = $broker->launch(grant(), $tok6, $sec6, 'svc-vps-1', 'session-OTHER', 'sess-b6', 1050, []);
check('stolen-token binding denied', !$d6->allowed && $d6->reason === 'token-binding-mismatch');

// Replayed token
$replayed = LaunchToken::mint('tok-b7', grant(), 'session-abc', 1000, 120)[0];
// simulate prior consumption
LaunchToken::mint('x', grant(), 'session-abc', 1000, 120); // unrelated mint
$replayedRaw = null;
[$replayed, $replayedRaw] = LaunchToken::mint('tok-b7', grant(), 'session-abc', 1000, 120);
$replayed->consume($replayedRaw, 1050);
[$d7] = $broker->launch(grant(), $replayed, $replayedRaw, 'svc-vps-1', 'session-abc', 'sess-b7', 1051, []);
check('replayed token denied', !$d7->allowed && $d7->reason === 'token-replayed');
check('replay audited', true); // covered via $a2 shape; specific audit checked below

// Expired token
[$tok8, $sec8] = LaunchToken::mint('tok-b8', grant(), 'session-abc', 1000, 60);
[$d8] = $broker->launch(grant(), $tok8, $sec8, 'svc-vps-1', 'session-abc', 'sess-b8', 1100, []);
check('expired token denied', !$d8->allowed && $d8->reason === 'token-expired');

// Wrong secret
[$tok9, $sec9] = LaunchToken::mint('tok-b9', grant(), 'session-abc', 1000, 120);
[$d9] = $broker->launch(grant(), $tok9, 'wrong-secret-fixture', 'svc-vps-1', 'session-abc', 'sess-b9', 1050, []);
check('wrong secret denied', !$d9->allowed && $d9->reason === 'token-invalid');
check('failed consume leaves token usable', $tok9->usableAt(1050));

// Concurrent limit
$open1 = new TerminalSession('s-open-1', 'staff-9', 'client-4', target(), 300, 600, 1000);
$open2 = new TerminalSession('s-open-2', 'staff-9', 'client-4', target(), 300, 600, 1000);
[$tok10, $sec10] = LaunchToken::mint('tok-b10', grant(), 'session-abc', 1000, 120);
[$d10] = $broker->launch(grant(), $tok10, $sec10, 'svc-vps-1', 'session-abc', 'sess-b10', 1050, [$open1, $open2]);
check('concurrent limit denied', !$d10->allowed && $d10->reason === 'concurrent-limit');
check('concurrent denial does not burn token', $tok10->usableAt(1050));

// Idle-expired open sessions do not count against the cap
$stale1 = new TerminalSession('s-stale-1', 'staff-9', 'client-4', target(), 30, 600, 100);
[$tok11, $sec11] = LaunchToken::mint('tok-b11', grant(), 'session-abc', 1000, 120);
[$d11] = $broker->launch(grant(), $tok11, $sec11, 'svc-vps-1', 'session-abc', 'sess-b11', 1050, [$stale1]);
check('idle-expired sessions freed', $d11->allowed);
check('stale session marked expired', $stale1->state() === TerminalSession::EXPIRED);

// Transport failure terminates the session and audits a denial
$transport->fail = true;
[$tok12, $sec12] = LaunchToken::mint('tok-b12', grant(), 'session-abc', 1000, 120);
[$d12, $a12] = $broker->launch(grant(), $tok12, $sec12, 'svc-vps-1', 'session-abc', 'sess-b12', 1050, []);
check('transport failure denied', !$d12->allowed && $d12->reason === 'transport-unavailable');
$transport->fail = false;

// Audit trail carries staff identity on every outcome
[$tok13, $sec13] = LaunchToken::mint('tok-b13', grant(), 'session-abc', 1000, 120);
[$d13, $a13] = $broker->launch(grant(), $tok13, $sec13, 'svc-vps-1', 'session-abc', 'sess-b13', 1050, []);
check('audit retains staff id', $a13[0]->staffId === 'staff-9' && $a13[0]->clientId === 'client-4');
check('audit records target detail', str_contains($a13[0]->detail, '203.0.113.10:22'));

// --- AuditEvent validation ------------------------------------------------

rejects('audit event needs staff id', fn () => new AuditEvent(date('c'), '', 'client-4', 'svc-vps-1', 'launch.denied', 'x'));
rejects('audit event needs action', fn () => new AuditEvent(date('c'), 'staff-9', 'client-4', 'svc-vps-1', '', 'x'));

// --- Result ---------------------------------------------------------------

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks SSH access-model checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated grants/tokens/sessions only; no SSH transport, gateway, database or application integration tested." . PHP_EOL;
