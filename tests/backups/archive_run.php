<?php
declare(strict_types=1);

/**
 * Synthetic A-03 archive-adapter checks. Temporary fixture trees only —
 * no database, installation, or customer data. Exercises the runtime
 * collect/hash/zip/preview/apply layer against the pure foundation.
 */

use CajunWebPro\Foundation\Backups\BackupManifest;
use CajunWebPro\Foundation\Backups\RestorePlan;

define('CWP_BOOT', true);
define('CWP_ROOT', dirname(__DIR__, 2));
define('CWP_CODE_ROOT', CWP_ROOT . '/site');
require __DIR__ . '/../../site/app/backups/archive.php';

$fixture = sys_get_temp_dir() . '/cwp-backups-root-' . getmypid() . '/site';

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
    try {
        $action();
    } catch (InvalidArgumentException | RuntimeException | LogicException) {
        check($name, true);
        return;
    }
    check($name, false);
}
function tree(string $root, array $files): void
{
    foreach ($files as $name => $body) {
        $path = $root . '/' . $name;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $body);
    }
}
function rmtree(string $root): void
{
    if (!is_dir($root)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($root);
}

rmtree(dirname($fixture));
mkdir($fixture, 0777, true);
tree($fixture, [
    'index.php' => 'site entry',
    'app/service.php' => 'service code',
    'config.php' => 'secret credentials',
    '.env' => 'SECRET=1',
    'cert.pem' => 'pem',
    'storage/private.txt' => 'private bytes',
    'deep/payment-master.txt' => 'card master',
    '.git/config' => 'git internals',
]);
$archive = dirname($fixture) . '/archive.zip';

$collected = backups_collect($fixture);
check('collection excludes secrets, storage, VCS', isset($collected['items']['index.php'], $collected['items']['app/service.php'])
    && !isset($collected['items']['config.php'], $collected['items']['.env'], $collected['items']['cert.pem'], $collected['items']['storage/private.txt'], $collected['items']['deep/payment-master.txt'], $collected['items']['.git/config']));
check('collected hashes match file contents', $collected['items']['index.php'] === hash('sha256', 'site entry'));
check('item names reject traversal and secrets', backups_item_allowed('app/x.php') && !backups_item_allowed('../x') && !backups_item_allowed('a/../b') && !backups_item_allowed('config.php') && !backups_item_allowed('storage/x'));

$manifest = new BackupManifest(str_repeat('a', 32), 'files', 'staff:1', gmdate('Y-m-d\TH:i:s\Z'), $collected['items'], gmdate('Y-m-d', strtotime('+30 days')) . 'T00:00:00Z', 'synthetic');
$size = backups_write_archive($fixture, $manifest->items, $archive);
check('archive written with every manifest item', $size > 0 && is_file($archive));
$zip = new ZipArchive();
check('archive opens and members match manifest', $zip->open($archive, ZipArchive::RDONLY) === true && $zip->numFiles === count($manifest->items) && $zip->getFromName('index.php') === 'site entry' && $zip->getFromName('config.php') === false);
$zip->close();

$current = backups_current_hashes($fixture, $manifest->items);
check('current hashes mark untouched items identical', $current['index.php'] === $manifest->items['index.php']);
$plan = RestorePlan::preview('preview', $manifest, $current, BackupManifest::FORMAT_VERSION);
check('preview marks all items identical', $plan->changedItems() === []);

file_put_contents($fixture . '/index.php', 'changed bytes');
unlink($fixture . '/app/service.php');
$current = backups_current_hashes($fixture, $manifest->items);
$plan = RestorePlan::preview('preview', $manifest, $current, BackupManifest::FORMAT_VERSION);
$impact = $plan->impact;
check('preview classifies differs and missing', $impact['index.php']['current'] === 'differs' && $impact['app/service.php']['current'] === 'missing');

rejects('apply refuses an unauthorized plan', fn() => backups_apply($fixture, $manifest, $plan, $archive));
rejects('restore refuses the wrong confirmation phrase', fn() => $plan->authorize('staff:1', 'wrong', 'RESTORE AAAAAAAA', time()));
$authorized = $plan->authorize('staff:1', 'RESTORE AAAAAAAA', 'RESTORE AAAAAAAA', time());
check('exact phrase authorizes the plan', $authorized->state() === RestorePlan::AUTHORIZED);
$written = backups_apply($fixture, $manifest, $authorized, $archive);
check('restore rewrote only the differing items', $written === 2 && file_get_contents($fixture . '/index.php') === 'site entry' && file_get_contents($fixture . '/app/service.php') === 'service code');
$replan = RestorePlan::preview('again', $manifest, backups_current_hashes($fixture, $manifest->items), BackupManifest::FORMAT_VERSION);
check('second apply is a verified no-op', backups_apply($fixture, $manifest, $replan->authorize('staff:1', 'RESTORE AAAAAAAA', 'RESTORE AAAAAAAA', time()), $archive) === 0);

$bytes = file_get_contents($archive);
file_put_contents($archive, substr($bytes, 0, -64));
rejects('truncated archive fails closed', fn() => backups_apply($fixture, $manifest, $authorized, $archive));
file_put_contents($archive, 'not a zip');
rejects('non-archive bytes fail closed', fn() => backups_apply($fixture, $manifest, $authorized, $archive));
file_put_contents($archive, $bytes);

$tampered = new BackupManifest($manifest->id, 'files', $manifest->createdBy, $manifest->createdAt, $manifest->items + ['evil.php' => hash('sha256', 'nope')], $manifest->retainUntil);
$plan2 = RestorePlan::preview('t', $tampered, backups_current_hashes($fixture, $tampered->items), BackupManifest::FORMAT_VERSION)->authorize('staff:1', 'p', 'p', time());
rejects('manifest item absent from archive fails closed', fn() => backups_apply($fixture, $tampered, $plan2, $archive));
check('archive untouched after tamper rejection', file_get_contents($fixture . '/index.php') === 'site entry');

$escape = new BackupManifest($manifest->id, 'files', $manifest->createdBy, $manifest->createdAt, ['../escape.php' => hash('sha256', 'x')], $manifest->retainUntil);
$plan3 = RestorePlan::preview('e', $escape, ['../escape.php' => 'y'], BackupManifest::FORMAT_VERSION)->authorize('staff:1', 'p', 'p', time());
rejects('path traversal manifest item refused', fn() => backups_apply($fixture, $escape, $plan3, $archive));

rejects('born-expired manifest refused by the model', fn() => new BackupManifest($manifest->id, 'files', $manifest->createdBy, $manifest->createdAt, $manifest->items, '2000-01-01T00:00:00Z'));
rejects('expired backup cannot be authorized', fn() => RestorePlan::preview('x', $manifest, $current, BackupManifest::FORMAT_VERSION)->authorize('staff:1', 'p', 'p', time() + 40 * 86400));
rejects('newer format refused', fn() => RestorePlan::preview('x', $manifest, $current, 0));

rmtree(dirname($fixture));
echo "backups archive adapter: {$checks} checks, " . count($failures) . " failures\n";
if ($failures) {
    echo implode("\n", $failures) . "\n";
    exit(1);
}
