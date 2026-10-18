<?php
declare(strict_types=1);

/**
 * Synthetic A-03 backup/restore contract checks. Fixture manifests only —
 * no archives, filesystem writes, or application integration.
 */

use CajunWebPro\Foundation\Backups\BackupManifest;
use CajunWebPro\Foundation\Backups\RestorePlan;

require __DIR__ . '/../../foundation/backups/BackupManifest.php';
require __DIR__ . '/../../foundation/backups/RestorePlan.php';

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

function rejects(string $name, callable $action): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid action was accepted';
    } catch (InvalidArgumentException|LogicException) {
        // Expected; other exceptions must fail the runner.
    }
}

function h(string $s): string
{
    return hash('sha256', $s);
}

function manifest(array $changes = []): BackupManifest
{
    return new BackupManifest(...array_replace([
        'id' => 'backup-1',
        'kind' => 'fixture-db+media',
        'createdBy' => 'owner-1',
        'createdAt' => '2026-10-10T09:00:00Z',
        'items' => [
            'db/services' => h('fixture services rows'),
            'db/projects' => h('fixture projects rows'),
            'media/logo-upload.png' => h('fixture upload bytes'),
        ],
        'retainUntil' => '2026-11-10',
    ], $changes));
}

// --- BackupManifest --------------------------------------------------------

check('manifest constructs', manifest()->id === 'backup-1');
check('manifest hash deterministic', manifest()->manifestHash() === manifest()->manifestHash());
check('hash changes with items', manifest()->manifestHash() !== manifest(['items' => ['db/services' => h('other')]])->manifestHash());
check('verifyItem match', manifest()->verifyItem('db/services', h('fixture services rows')));
check('verifyItem mismatch', !manifest()->verifyItem('db/services', h('tampered')));
check('verifyItem unknown', !manifest()->verifyItem('db/nope', h('x')));
check('not expired before retainUntil', !manifest()->expired(strtotime('2026-10-15')));
check('expired after retainUntil', manifest()->expired(strtotime('2026-11-15')));
rejects('retention before creation rejected', fn () => manifest(['retainUntil' => '2026-10-01']));
rejects('retention equal to creation rejected', fn () => manifest(['retainUntil' => '2026-10-10T09:00:00Z']));

// Secrets can never be scoped into a backup
rejects('config.php rejected from scope', fn () => manifest(['items' => ['config.php' => h('x')]]));
rejects('key file rejected from scope', fn () => manifest(['items' => ['keys/payment-master.key' => h('x')]]));
rejects('.env rejected from scope', fn () => manifest(['items' => ['.env' => h('x')]]));
rejects('storage path rejected from scope', fn () => manifest(['items' => ['storage/private/x' => h('x')]]));
rejects('pem rejected from scope', fn () => manifest(['items' => ['certs/site.pem' => h('x')]]));
rejects('installed flag rejected', fn () => manifest(['items' => ['.installed.php' => h('x')]]));
rejects('nested config.php rejected at depth', fn () => manifest(['items' => ['includes/config.php' => h('x')]]));
rejects('nested .env rejected at depth', fn () => manifest(['items' => ['app/vars/.env' => h('x')]]));
rejects('nested storage directory rejected at depth', fn () => manifest(['items' => ['deploy/storage/keys' => h('x')]]));
rejects('nested .installed.php rejected at depth', fn () => manifest(['items' => ['releases/v2/.installed.php' => h('x')]]));
rejects('empty scope rejected', fn () => manifest(['items' => []]));
rejects('non-sha item hash rejected', fn () => manifest(['items' => ['db/x' => 'abc']]));
rejects('unparsable dates rejected', fn () => manifest(['retainUntil' => 'soon']));

// --- RestorePlan -----------------------------------------------------------

$current = [
    'db/services' => h('fixture services rows'),          // identical
    'db/projects' => h('current different rows'),          // differs
    // media/logo-upload.png absent → missing
];

$plan = RestorePlan::preview('restore-1', manifest(), $current, BackupManifest::FORMAT_VERSION);
check('plan starts preview', $plan->state() === RestorePlan::PREVIEW);
check('impact classified', $plan->impact['db/services']['current'] === 'identical'
    && $plan->impact['db/projects']['current'] === 'differs'
    && $plan->impact['media/logo-upload.png']['current'] === 'missing');
check('changedItems excludes identical', count($plan->changedItems()) === 2);

rejects('apply before authorize rejected', fn () => $plan->applied());
rejects('wrong phrase rejected', fn () => $plan->authorize('owner-1', 'nope', 'RESTORE backup-1', strtotime('2026-10-15')));
check('still preview after bad auth', $plan->state() === RestorePlan::PREVIEW);

// Expired backup cannot be authorized — retention passed between creation and now
$stale = manifest(['createdAt' => '2026-09-01', 'retainUntil' => '2026-10-01']);
$plan2 = RestorePlan::preview('restore-2', $stale, $current, BackupManifest::FORMAT_VERSION);
rejects('expired backup authorize rejected', fn () => $plan2->authorize('owner-1', 'RESTORE backup-1', 'RESTORE backup-1', strtotime('2026-10-15')));

// Newer format cannot be previewed
rejects('newer format rejected', fn () => RestorePlan::preview('restore-3', manifest(), $current, BackupManifest::FORMAT_VERSION - 1));

$plan->authorize('owner-1', 'RESTORE backup-1', 'RESTORE backup-1', strtotime('2026-10-15'));
check('authorized records actor', $plan->state() === RestorePlan::AUTHORIZED && $plan->authorizedBy() === 'owner-1');
rejects('double authorize rejected', fn () => $plan->authorize('owner-2', 'RESTORE backup-1', 'RESTORE backup-1', strtotime('2026-10-15')));

$plan->applied();
check('applied is terminal', $plan->state() === RestorePlan::APPLIED);
rejects('abandon after applied rejected', fn () => $plan->abandon());

$plan3 = RestorePlan::preview('restore-4', manifest(), $current, BackupManifest::FORMAT_VERSION);
$plan3->abandon();
check('abandon from preview', $plan3->state() === RestorePlan::ABANDONED);
rejects('authorize after abandon rejected', fn () => $plan3->authorize('owner-1', 'RESTORE backup-1', 'RESTORE backup-1', strtotime('2026-10-15')));

// --- Result ---------------------------------------------------------------

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "PASS: $checks backup/restore checks on PHP " . PHP_VERSION . PHP_EOL;
echo "Scope: isolated manifest/restore-plan contract only; no archives, filesystem, database or application integration tested." . PHP_EOL;
