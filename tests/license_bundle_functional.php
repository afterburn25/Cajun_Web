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
function query(string $sql, array $p = []): void { $GLOBALS['bf_bundles'][] = ['license_id' => $p[0], 'file_path' => $p[1], 'title' => $p[2]]; }
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
    str_contains($n, 'config/config.php') || str_ends_with($n, '.key') || str_ends_with($n, '.pem')
));
bf_ok('No runtime storage in bundle', !array_filter($names, fn($n) => str_starts_with($n, 'site/storage/')));

// --- guards -----------------------------------------------------------------
bf_deny('Deactivated license refused', function () {
    $GLOBALS['bf_license']['is_active'] = 0;
    try { license_bundle('TEST-1234-ABCD-5678', 'restaurant', sys_get_temp_dir(), sys_get_temp_dir()); }
    finally { $GLOBALS['bf_license']['is_active'] = 1; }
});
bf_deny('Unknown industry refused', fn() => license_bundle('TEST-1234-ABCD-5678', 'nonexistent', sys_get_temp_dir(), sys_get_temp_dir()));

// --- bundle store -----------------------------------------------------------
license_bundle_store(7, $zipPath, 'Restaurant bundle');
bf_ok('Bundle record stored', count($GLOBALS['bf_bundles']) === 1 && $GLOBALS['bf_bundles'][0]['file_path'] === $zipPath);

$zip->close();
@unlink($zipPath);
@rmdir($outDir);

$failed = array_filter($checks, fn($c) => !$c['passed']);
echo json_encode(['checks' => $checks, 'passed' => count($checks) - count($failed), 'failed' => count($failed)], JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed ? 1 : 0);
