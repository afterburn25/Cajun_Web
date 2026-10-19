<?php
declare(strict_types=1);

/**
 * Synthetic A-09 support-access contract checks. Fixture grants/sessions
 * only — no real portal sessions, impersonation or application integration.
 */

use CajunWebPro\Foundation\SupportAccess\SupportBroker;
use CajunWebPro\Foundation\SupportAccess\SupportDenied;
use CajunWebPro\Foundation\SupportAccess\SupportGrant;
use CajunWebPro\Foundation\SupportAccess\SupportSession;

require __DIR__ . '/../../foundation/support-access/SupportDenied.php';
require __DIR__ . '/../../foundation/support-access/SupportGrant.php';
require __DIR__ . '/../../foundation/support-access/SupportSession.php';
require __DIR__ . '/../../foundation/support-access/SupportBroker.php';

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

function rejects(string $name, callable $action, string $class = ''): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid action was accepted';
    } catch (SupportDenied $e) {
        if ($class === SupportDenied::class) {
            return;
        }
        // SupportDenied is also a LogicException — fine for generic rejects.
    } catch (InvalidArgumentException|LogicException) {
        if ($class === SupportDenied::class) {
            $failures[] = $name . ': expected SupportDenied, got a generic exception';
        }
    }
}

$T0 = strtotime('2026-10-13T09:00:00Z');

function grant(array $changes = []): SupportGrant
{
    global $T0;
    return new SupportGrant(...array_replace([
        'id' => 'grant-1',
        'staffId' => 'staff-amy',
        'clientId' => 'client-7',
        'issuedBy' => 'owner-1',
        'issuedAt' => $T0,
        'ttlSeconds' => 3600,
    ], $changes));
}

// --- SupportGrant ----------------------------------------------------------

check('grant constructs', grant()->staffId === 'staff-amy');
check('grant usable when issued', grant()->usable($T0));
check('permitsSensitive defaults false', grant()->permitsSensitive === false);

rejects('identical staff/client rejected', fn () => grant(['clientId' => 'staff-amy']));
rejects('ttl under 60s rejected', fn () => grant(['ttlSeconds' => 30]));
rejects('ttl over 8h rejected', fn () => grant(['ttlSeconds' => 30000]));
rejects('empty staff rejected', fn () => grant(['staffId' => '']));
rejects('empty issuer rejected', fn () => grant(['issuedBy' => '']));

$g = grant();
$g->revoke('owner-1');
check('revoked grant unusable', !$g->usable($T0) && $g->revoked());
rejects('double revoke rejected', fn () => $g->revoke('owner-1'));
rejects('empty revoke actor rejected', fn () => grant()->revoke(''));

// --- Broker launch ----------------------------------------------------------

$broker = new SupportBroker();
$grant = grant();
$session = $broker->launch($grant, 'sess-1', $T0);

check('launch returns session', $session instanceof SupportSession);
check('session keeps both identities', $session->staffId() === 'staff-amy' && $session->clientId() === 'client-7');
check('session expiry = start+ttl', $session->expiresAt === $T0 + 3600);
check('banner names staff and client', str_contains($session->bannerText(), 'staff-amy') && str_contains($session->bannerText(), 'client-7') && str_contains($session->bannerText(), 'Return to admin'));
check('grant audited', count($broker->auditLog()) === 1 && $broker->auditLog()[0]['type'] === SupportBroker::GRANTED);

rejects('empty session id rejected', fn () => $broker->launch($grant, '', $T0));

// Revoked grants cannot launch
$deadGrant = grant(['id' => 'grant-dead']);
$deadGrant->revoke('owner-1');
try {
    $broker->launch($deadGrant, 'sess-x', $T0);
    check('revoked launch denied', false);
} catch (SupportDenied $e) {
    check('revoked launch denied', $e->reason === 'grant-revoked');
}
check('denial audited', $broker->auditLog()[1]['type'] === SupportBroker::DENIED);

// Concurrency: one live session per client by default
try {
    $broker->launch(grant(['id' => 'grant-2', 'staffId' => 'staff-ben']), 'sess-2', $T0);
    check('second session same client denied', false);
} catch (SupportDenied $e) {
    check('second session same client denied', $e->reason === 'client-session-limit');
}
check('other client unaffected', $broker->launch(grant(['id' => 'grant-3', 'staffId' => 'staff-ben', 'clientId' => 'client-9']), 'sess-3', $T0) instanceof SupportSession);
check('active sessions for client-7', count($broker->activeSessionsFor('client-7', $T0)) === 1);

// --- Session actions -------------------------------------------------------

check('ordinary action allowed', $session->perform('view invoice list', $T0 + 60));
check('sensitive denied without flag', !$session->perform('reset client password', $T0 + 60, sensitive: true));
check('denial still logged', count($session->actionLog()) === 2 && $session->actionLog()[1]['allowed'] === false && $session->actionLog()[1]['actor'] === 'staff-amy');

$sensitiveGrant = grant(['id' => 'grant-s', 'clientId' => 'client-5', 'permitsSensitive' => true]);
$senSession = $broker->launch($sensitiveGrant, 'sess-s', $T0);
check('sensitive allowed when granted', $senSession->perform('change billing email', $T0 + 60, sensitive: true));

// Expiry and close
check('live before expiry', $session->live($T0 + 3599));
check('dead at expiry', !$session->live($T0 + 3600));
check('action after expiry denied', !$session->perform('view page', $T0 + 3600));

$broker->close($session, 'staff-amy', $T0 + 3600);
check('closed session dead', $session->closed());
rejects('double close rejected', fn () => $session->close('staff-amy'));
check('close audited', array_values(array_filter($broker->auditLog(), fn ($e) => $e['type'] === SupportBroker::CLOSED)) !== []);

// Grant revocation kills its live sessions
$g2 = grant(['id' => 'grant-4', 'clientId' => 'client-11']);
$s2 = $broker->launch($g2, 'sess-4', $T0);
check('session live before revoke', $s2->live($T0 + 10));
$broker->revokeGrant($g2, 'owner-1', $T0 + 20);
check('revoked grant kills session', !$s2->live($T0 + 30));
check('revocation audited', array_values(array_filter($broker->auditLog(), fn ($e) => $e['type'] === SupportBroker::REVOKED)) !== []);

rejects('empty action rejected', fn () => $session->perform('', $T0));
rejects('close empty actor rejected', fn () => $senSession->close(''));

// --- Result ---------------------------------------------------------------

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks support-access checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated grant/session/broker model only; no real portal sessions, impersonation or application integration tested." . PHP_EOL;
