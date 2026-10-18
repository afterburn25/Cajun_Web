<?php
/**
 * Functional check for license_bundle() against a real Website_Core checkout.
 * CLI-only; stubs the platform helpers so no database is required.
 *
 * Usage: php tests/license_bundle_functional.php [path-to-Website_Core]
 * Exit 0 = all checks pass; output is a JSON report.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

define('CWP_BOOT', true);
define('CWP_CODE_ROOT', realpath(__DIR__ . '/../site'));
define('CWP_ROOT', CWP_CODE_ROOT);

$checks = [];
function bf_ok(string $name, bool $ok): void { global $checks; $checks[] = ['name' => $name, 'passed' => $ok]; if (!$ok) throw new RuntimeException($name); }
function bf_deny(string $name, callable $fn): void { try { $fn(); } catch (InvalidArgumentException | RuntimeException) { bf_ok($name, true); return; } bf_ok($name, false); }

// --- minimal platform stubs ------------------------------------------------
$GLOBALS['bf_license'] = ['id' => 7, 'license_key' => 'TEST-1234-ABCD-5678', 'is_active' => 1];
$GLOBALS['bf_bundles'] = [];
function table(string $n): string { return 't_' . $n; }
function one(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'website_licenses')) return $GLOBALS['bf_license'];
    return null;
}
function all(string $sql, array $p = []): array { return $GLOBALS['bf_bundles']; }
function query(string $sql, array $p = []): void { $GLOBALS['bf_bundles'][] = ['license_id' => $p[0], 'file_path' => $p[1], 'title' => $p[2], 'sha256' => $p[3] ?? null]; }
function config(): array { return ['storage' => sys_get_temp_dir()]; }

require CWP_CODE_ROOT . '/app/licenses/service.php';
require CWP_CODE_ROOT . '/app/licenses/bundle.php';

// --- locate Website_Core ----------------------------------------------------
$coreDir = $argv[1] ?? '';
foreach ([$coreDir, 'D:/Devin/Website_Core', dirname(CWP_ROOT) . '/Website_Core'] as $cand) {
    if ($cand !== '' && is_dir($cand . '/examples/industry-restaurant')) { $coreDir = $cand; break; }
}
bf_ok('Website Core checkout with restaurant module found', is_dir($coreDir . '/examples/industry-restaurant'));

$outDir = sys_get_temp_dir() . '/bf-bundle-' . bin2hex(random_bytes(4));
if (!is_dir($outDir)) mkdir($outDir, 0700, true);

// --- build ------------------------------------------------------------------
$zipPath = license_bundle('TEST-1234-ABCD-5678', 'restaurant', $coreDir, $outDir);
bf_ok('Bundle ZIP created', is_file($zipPath));
bf_ok('ZIP exceeds 100KB (core included)', filesize($zipPath) > 102400);

// --- inspect ----------------------------------------------------------------
$zip = new ZipArchive();
bf_ok('ZIP opens', $zip->open($zipPath) === true);

$names = [];
for ($i = 0; $i < $zip->numFiles; $i++) $names[] = $zip->getNameIndex($i);
bf_ok('No backslash paths in archive', !array_filter($names, fn($n) => str_contains($n, '\\')));
bf_ok('site/ prefix present', in_array('site/index.php', $names, true) || count(array_filter($names, fn($n) => str_starts_with($n, 'site/'))) > 50);

$manifestEntry = 'site/modules/industries/industry-restaurant/addon.json';
bf_ok('Module manifest at deploy path', in_array($manifestEntry, $names, true));
$manifest = json_decode($zip->getFromName($manifestEntry), true);
bf_ok('license_key embedded in manifest', ($manifest['license_key'] ?? '') === 'TEST-1234-ABCD-5678');
bf_ok('Manifest is a module manifest', ($manifest['type'] ?? '') === 'industry');

bf_ok('No secrets in bundle', !array_filter($names, fn($n) =>
    str_contains($n, 'config/config.php') || str_ends_with($n, '.key') || str_ends_with($n, '.pem') || str_contains($n, '.env')
));
bf_ok('No runtime storage in bundle', !array_filter($names, fn($n) => str_starts_with($n, 'site/storage/')));
bf_ok('config/ limited to shipped scaffolding', !array_filter($names, fn($n) =>
    str_starts_with($n, 'site/config/')
    && !in_array($n, ['site/config/.htaccess', 'site/config/config.example.php'], true)
));

// --- guards -----------------------------------------------------------------
bf_deny('Deactivated license refused', function () {
    $GLOBALS['bf_license']['is_active'] = 0;
    try { license_bundle('TEST-1234-ABCD-5678', 'restaurant', sys_get_temp_dir(), sys_get_temp_dir()); }
    finally { $GLOBALS['bf_license']['is_active'] = 1; }
});
bf_deny('Master license refused for bundling', function () {
    $GLOBALS['bf_license']['is_master'] = 1;
    try { license_bundle('TEST-1234-ABCD-5678', 'restaurant', sys_get_temp_dir(), sys_get_temp_dir()); }
    finally { $GLOBALS['bf_license']['is_master'] = 0; }
});
bf_deny('Master license refused for bundle storage', function () {
    $GLOBALS['bf_license']['is_master'] = 1;
    try { license_bundle_store(7, sys_get_temp_dir() . '/never.zip', 'master'); }
    finally { $GLOBALS['bf_license']['is_master'] = 0; }
});
bf_deny('Unknown industry refused', fn() => license_bundle('TEST-1234-ABCD-5678', 'nonexistent', sys_get_temp_dir(), sys_get_temp_dir()));

// --- bundle store -----------------------------------------------------------
license_bundle_store(7, $zipPath, 'Restaurant bundle');
bf_ok('Bundle record stored', count($GLOBALS['bf_bundles']) === 1 && $GLOBALS['bf_bundles'][0]['file_path'] === $zipPath);
bf_ok('Bundle SHA-256 recorded for download integrity', ($GLOBALS['bf_bundles'][0]['sha256'] ?? null) === hash_file('sha256', $zipPath));

// Regeneration must produce a NEW file — overwriting a path another record's
// stored checksum verifies against would permanently break that download.
$zipPath2 = license_bundle('TEST-1234-ABCD-5678', 'restaurant', $coreDir, $outDir);
bf_ok('Regeneration writes a distinct bundle file', $zipPath2 !== $zipPath && is_file($zipPath) && is_file($zipPath2));

// --- cross-repo install verification ----------------------------------------
// Prove the artifact WC's installer would receive actually installs: extract
// the bundled module from the real ZIP, run WC migrations on sqlite, write
// license + packages rows, run module migrations, enable, and boot the real
// provider through ModuleRegistry. Mirrors Installer's bundled-module path.
bf_ok('pdo_sqlite available for WC install check', extension_loaded('pdo_sqlite'));
bf_ok('WC test bootstrap present', is_file($coreDir . '/tests/bootstrap.php'));
require $coreDir . '/tests/bootstrap.php';

$instDir = sys_get_temp_dir() . '/bf-inst-' . bin2hex(random_bytes(4));
for ($i = 0; $i < $zip->numFiles; $i++) {
    $n = $zip->getNameIndex($i);
    if (!str_starts_with($n, 'site/modules/industries/')) continue;
    $rel = substr($n, strlen('site/modules/'));
    if (str_ends_with($n, '/')) { @mkdir($instDir . '/' . $rel, 0777, true); continue; }
    @mkdir(dirname($instDir . '/' . $rel), 0777, true);
    copy('zip://' . $zipPath . '#' . $n, $instDir . '/' . $rel);
}
$moduleDir = $instDir . '/industries/industry-restaurant';
bf_ok('Bundled module extracted from ZIP', is_file($moduleDir . '/addon.json'));

$wcDb = new \Core\Database\Connection(new PDO('sqlite::memory:'), 'wc_');
(new \Core\Database\Migrator($wcDb, WC_ROOT . '/database/migrations'))->migrate();

$lt = $wcDb->table('license');
$wcDb->run("INSERT INTO `{$lt}` (license_key, license_domain, license_valid, license_validated_at, created_at) VALUES (?, ?, 1, ?, ?)",
    ['TEST-1234-ABCD-5678', 'bf.example.com', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
$pt = $wcDb->table('packages');
$wcDb->run("INSERT INTO `{$pt}` (package_id, state, version, manifest, updated_at) VALUES (?, 'installed', ?, ?, ?)",
    [(string) $manifest['id'], (string) ($manifest['version'] ?? ''), json_encode($manifest), date('Y-m-d H:i:s')]);
$migDir = trim((string) ($manifest['migrations'] ?? ''), '/');
if ($migDir !== '' && is_dir($moduleDir . '/' . $migDir)) {
    (new \Core\Database\Migrator($wcDb, $moduleDir . '/' . $migDir))->migrate();
}
$wcDb->run("UPDATE `{$pt}` SET state = 'enabled', updated_at = ? WHERE package_id = ?", [date('Y-m-d H:i:s'), (string) $manifest['id']]);

$lic = $wcDb->fetch("SELECT * FROM `{$lt}` LIMIT 1");
bf_ok('WC license row stored valid', $lic['license_key'] === 'TEST-1234-ABCD-5678' && (int) $lic['license_valid'] === 1);
bf_ok('WC module migrations ran', $wcDb->tableExists('restaurant_categories') && $wcDb->tableExists('restaurant_items'));

mkdir($instDir . '/addons', 0777, true);
mkdir($instDir . '/themes', 0777, true);
$registry = new \Core\Modules\ModuleRegistry($wcDb, $instDir . '/industries', $instDir . '/addons', $instDir . '/themes');
$registry->discover();
bf_ok('WC registry sees bundled module enabled', $registry->state('wcref/restaurant') === 'enabled');
$booted = $registry->bootEnabled(
    fn (string $p, string $d) => \CoreAutoload::register($p, $d),
    fn (string $id) => new \Core\Modules\ModuleContext($id, new \Core\Routing\Router(), new \Core\Events\Dispatcher())
);
bf_ok('WC boots real bundled provider', $booted === ['wcref/restaurant']);

$zip->close();
@unlink($zipPath);
@rmdir($outDir);

$failed = array_filter($checks, fn($c) => !$c['passed']);
echo json_encode(['checks' => $checks, 'passed' => count($checks) - count($failed), 'failed' => count($failed)], JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed ? 1 : 0);
