<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Access\AuthorizationVersion;
use CajunWebPro\Foundation\Invitations\StaffInvitationStore;

require __DIR__ . '/../../foundation/access/AuthorizationVersion.php';
require __DIR__ . '/../../foundation/invitations/IssuedInvitation.php';
require __DIR__ . '/../../foundation/invitations/StaffInvitationStore.php';

function connection(): PDO
{
    $dsn = getenv('CWP_INVITATION_TEST_DSN');
    if (!$dsn || getenv('CWP_INVITATION_TEST_ALLOW') !== 'synthetic-only') {
        throw new RuntimeException('Explicit disposable invitation test database configuration is required.');
    }
    $db = new PDO($dsn, getenv('CWP_INVITATION_TEST_USER'), getenv('CWP_INVITATION_TEST_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
        || $db->query('SELECT DATABASE()')->fetchColumn() !== 'cwp_invitation_test') {
        throw new RuntimeException('Only the disposable cwp_invitation_test MySQL/MariaDB database is allowed.');
    }
    return $db;
}

function tableName(string $name): string
{
    if (preg_match('/\Acwp_fixture_[a-f0-9]{12}_(accounts|invites)\z/', $name) !== 1) {
        throw new RuntimeException('Unsafe fixture table name.');
    }
    return '`' . $name . '`';
}

function activate(PDO $db, string $accounts, string $subjectId, string $issuedVersion): bool
{
    $statement = $db->prepare('SELECT active, auth_version FROM ' . tableName($accounts) . ' WHERE id=? FOR UPDATE');
    $statement->execute([$subjectId]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$account || !(bool)$account['active'] || !AuthorizationVersion::matches($account['auth_version'], $issuedVersion)) {
        return false;
    }
    $statement = $db->prepare('UPDATE ' . tableName($accounts) . ' SET activations=activations+1, auth_version=? WHERE id=?');
    $statement->execute([bin2hex(random_bytes(16)), $subjectId]);
    return true;
}

// Separate PHP processes exercise the real database locks, not a mock store.
if (($argv[1] ?? '') === 'worker') {
    [$mode, $invites, $accounts, $token, $gate] = array_slice($argv, 1);
    tableName($invites);
    tableName($accounts);
    $db = connection();
    $store = new StaffInvitationStore($db, $invites);
    echo "READY\n";
    flush();
    $deadline = microtime(true) + 10;
    while (trim((string)file_get_contents($gate)) !== 'go') {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Concurrency start gate timed out.');
        }
        usleep(10000);
    }
    $result = $store->redeem($token, fn(PDO $db, string $id, string $version): bool => activate($db, $accounts, $id, $version));
    echo $result ? "ACCEPTED\n" : "REJECTED\n";
    exit(0);
}

$checks = 0;
function check(string $name, bool $condition): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $name);
    }
}
function rejects(string $name, callable $action, string $exception = InvalidArgumentException::class): void
{
    try {
        $action();
    } catch (Throwable $error) {
        check($name, $error instanceof $exception);
        return;
    }
    check($name, false);
}

$db = connection();
$prefix = 'cwp_fixture_' . bin2hex(random_bytes(6));
$accounts = $prefix . '_accounts';
$invites = $prefix . '_invites';
$created = [];
$workers = [];
$gate = null;
try {
    $db->exec('CREATE TABLE ' . tableName($accounts) . ' ('
        . 'id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,'
        . 'auth_version VARCHAR(64) NOT NULL, active TINYINT NOT NULL, activations INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
    $created[] = $accounts;
    $db->exec(StaffInvitationStore::createTableSql($invites));
    $created[] = $invites;
    $now = 1700000000;
    $store = new StaffInvitationStore($db, $invites, function () use (&$now): int { return $now; });
    $version = str_repeat('a', 32);
    $reset = function () use ($db, $accounts, $version): void {
        $statement = $db->prepare('INSERT INTO ' . tableName($accounts) . ' (id, auth_version, active, activations) VALUES (?, ?, 1, 0)'
            . ' ON DUPLICATE KEY UPDATE auth_version=VALUES(auth_version), active=1, activations=0');
        $statement->execute(['staff-1', $version]);
    };
    $count = fn(): int => (int)$db->query('SELECT activations FROM ' . tableName($accounts) . " WHERE id='staff-1'")->fetchColumn();
    $complete = fn(PDO $db, string $id, string $boundVersion): bool => activate($db, $accounts, $id, $boundVersion);
    $row = fn(): array => $db->query('SELECT * FROM ' . tableName($invites) . " WHERE subject_id='staff-1'")->fetch(PDO::FETCH_ASSOC);
    $reset();

    $issued = $store->issue('staff-1', $version, 60);
    check('token has 256 random bits encoded as hex', preg_match('/\A[a-f0-9]{64}\z/', $issued->token) === 1);
    check('only domain-separated digest is stored', $row()['token_hash'] === hash('sha256', "cwp:staff-invitation:v1\0" . $issued->token)
        && $row()['token_hash'] !== $issued->token);
    check('expiry is bounded and returned', $issued->expiresAt === $now + 60 && (int)$row()['expires_at'] === $issued->expiresAt);
    ob_start(); var_dump($issued); $debug = ob_get_clean();
    check('debug output redacts raw token', !str_contains($debug, $issued->token) && str_contains($debug, '[redacted]'));
    check('JSON output redacts raw token', !str_contains(json_encode($issued, JSON_THROW_ON_ERROR), $issued->token));
    rejects('table identifier rejected', fn() => new StaffInvitationStore($db, 'invites; DROP TABLE accounts'));
    rejects('schema identifier rejected', fn() => StaffInvitationStore::createTableSql('invalid-name'));
    rejects('empty subject rejected', fn() => $store->issue('', $version));
    rejects('malformed version rejected', fn() => $store->issue('staff-1', '1'));
    rejects('short expiry rejected', fn() => $store->issue('staff-1', $version, 59));
    rejects('long expiry rejected', fn() => $store->issue('staff-1', $version, 86401));
    $db->exec('ALTER TABLE ' . tableName($invites) . ' ENGINE=MyISAM');
    rejects('nontransactional invitation table rejected', fn() => new StaffInvitationStore($db, $invites));
    $db->exec('ALTER TABLE ' . tableName($invites) . ' ENGINE=InnoDB');

    $calls = 0;
    $unexpected = function () use (&$calls): bool { $calls++; return true; };
    foreach (['', 'invalid', str_repeat('A', 64), str_repeat('0', 63), str_repeat('0', 65), $issued->token . ' '] as $bad) {
        check('malformed token denied without callback', !$store->redeem($bad, $unexpected));
    }
    check('unknown well-formed token denied', !$store->redeem(str_repeat('0', 64), $unexpected));
    check('invalid tokens never invoke activation', $calls === 0);
    check('first redemption succeeds', $store->redeem($issued->token, $complete));
    check('activation and consumption committed together', $count() === 1 && (int)$row()['consumed_at'] === $now);
    check('reuse denied', !$store->redeem($issued->token, $complete) && $count() === 1);

    $reset();
    $old = $store->issue('staff-1', $version);
    $new = $store->issue('staff-1', $version);
    check('resend rotates token', $old->token !== $new->token);
    check('resend invalidates previous token', !$store->redeem($old->token, $complete));
    check('newest token remains usable', $store->redeem($new->token, $complete));
    $reset();
    $issued = $store->issue('staff-1', $version, 60);
    $now = $issued->expiresAt;
    check('expiry boundary is rejected', !$store->redeem($issued->token, $complete) && $count() === 0);

    $issued = $store->issue('staff-1', $version);
    $db->exec('UPDATE ' . tableName($accounts) . " SET active=0 WHERE id='staff-1'");
    check('current disabled account rejected', !$store->redeem($issued->token, $complete));
    $reset();
    $statement = $db->prepare('UPDATE ' . tableName($accounts) . ' SET auth_version=? WHERE id=?');
    $statement->execute([str_repeat('b', 32), 'staff-1']);
    check('changed authorization token rejected', !$store->redeem($issued->token, $complete));
    $reset();
    $store->revoke('staff-1');
    check('explicit revocation survives reactivation', !$store->redeem($issued->token, $complete));

    $issued = $store->issue('staff-1', $version);
    check('false callback rolls back writes', !$store->redeem($issued->token, function (PDO $db) use ($accounts): bool {
        $db->exec('UPDATE ' . tableName($accounts) . ' SET activations=99');
        return false;
    }) && $count() === 0 && $row()['consumed_at'] === null);
    rejects('callback exception rolls back writes', fn() => $store->redeem($issued->token, function (PDO $db) use ($accounts): bool {
        $db->exec('UPDATE ' . tableName($accounts) . ' SET activations=99');
        throw new RuntimeException('Synthetic activation failure');
    }), RuntimeException::class);
    check('exception leaves token and account unchanged', !$db->inTransaction() && $count() === 0 && $row()['consumed_at'] === null);
    check('truthy nonboolean callback is denied', !$store->redeem($issued->token, fn(): string => 'yes'));
    $db->beginTransaction();
    rejects('nested redemption rejected', fn() => $store->redeem($issued->token, $complete), LogicException::class);
    check('caller transaction remains open', $db->inTransaction());
    $db->rollBack();
    check('rollback permits valid retry', $store->redeem($issued->token, $complete));

    $reset();
    $issued = $store->issue('staff-1', $version, 60);
    check('expiry during activation rolls back', !$store->redeem($issued->token, function (PDO $db) use (&$now, $accounts): bool {
        $db->exec('UPDATE ' . tableName($accounts) . ' SET activations=99');
        $now += 60;
        return true;
    }) && $count() === 0 && $row()['consumed_at'] === null);
    $old = $store->issue('staff-1', $version);
    $db->beginTransaction();
    $rolledBack = $store->issue('staff-1', $version);
    $db->rollBack();
    check('rolled-back issuance never creates a valid link', !$store->redeem($rolledBack->token, $complete));
    check('rolled-back resend preserves previous link', $store->redeem($old->token, $complete));

    // Release two ready workers together and verify one committed activation.
    $reset();
    $liveStore = new StaffInvitationStore($db, $invites);
    $racing = $liveStore->issue('staff-1', $version, 60);
    $gate = tempnam(sys_get_temp_dir(), 'cwp-invite-test-');
    if ($gate === false) { throw new RuntimeException('Cannot create isolated test gate.'); }
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $invites, $accounts, $racing->token, $gate],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start test worker.'); }
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
        check('concurrency worker ready', trim((string)fgets($pipes[1])) === 'READY');
    }
    file_put_contents($gate, 'go');
    $outcomes = [];
    foreach ($workers as [$process, $pipes]) {
        $outcomes[] = trim(stream_get_contents($pipes[1]));
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        check('concurrency worker completed', proc_close($process) === 0 && $errors === '');
    }
    $workers = [];
    sort($outcomes);
    check('only one simultaneous redemption succeeds', $outcomes === ['ACCEPTED', 'REJECTED']);
    check('concurrent activation committed exactly once', $count() === 1);

    echo "PASS: $checks native invitation checks on PHP " . PHP_VERSION . '; database ' . $db->getAttribute(PDO::ATTR_SERVER_VERSION) . PHP_EOL;
    echo "Scope: synthetic component tables and real locking/rollback; no application migration, account UI, email or host deployment." . PHP_EOL;
} finally {
    foreach ($workers as [$process, $pipes]) {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
    }
    if ($gate !== null && is_file($gate)) { unlink($gate); }
    if ($db->inTransaction()) { $db->rollBack(); }
    foreach (array_reverse($created) as $table) { $db->exec('DROP TABLE ' . tableName($table)); }
}
