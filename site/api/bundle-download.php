<?php
declare(strict_types=1);
define('CWP_BOOT',true);
require __DIR__.'/../includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/licenses/financing.php';
require_once CWP_CODE_ROOT.'/app/licenses/bundle.php';

/**
 * Download a license bundle (Website Core + industry module ZIP).
 * Access is restricted to the license owner.
 */
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
boot();

$client = studio_client();
if (!$client || support_current()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

if (!license_ready()) {
    http_response_code(404);
    exit;
}

$bundleId = $_GET['id'] ?? '';
if (!is_string($bundleId) || !preg_match('/^\d+$/D', $bundleId)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Bundle not found']);
    exit;
}

$bundle = one(
    'SELECT b.*, l.customer_id, l.license_key, l.is_master FROM ' . table('license_bundles') . ' b
     JOIN ' . table('website_licenses') . ' l ON b.license_id = l.id
     WHERE b.id = ? AND l.customer_id = ? AND l.is_active = 1 AND l.is_master = 0',
    [(int)$bundleId, $client['id']]
);

if (!$bundle || license_bundle_is_software_entitlement((int)$bundle['license_id'])) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Bundle not found or access denied']);
    exit;
}
try{
    $financing=license_financing_access((int)$bundle['license_id']);
}catch(Throwable $e){
    report_error($e);
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Financing status is unavailable; retry later']);
    exit;
}
if($financing!==null&&!$financing['authorized']){
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => (string)$financing['reason']]);
    exit;
}

$path = $bundle['file_path'];
$real = is_string($path) ? realpath($path) : false;
$storage = realpath(config()['storage'] ?? '');
if ($real === false || $storage === false || !str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', $storage) . '/')) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Bundle file is unavailable']);
    exit;
}
$path = $real;

// Integrity: the recorded SHA-256 must match the bytes on disk — a
// corrupted or tampered bundle is refused rather than served. NULL
// checksums (bundles recorded before v3) are served without the check.
$expected = (string) ($bundle['sha256'] ?? '');
if ($expected !== '') {
    $actual = @hash_file('sha256', $path);
    if ($actual === false || !hash_equals($expected, $actual)) {
        query(
            'INSERT INTO ' . table('audit') . ' (user_id, action, detail, created_at) VALUES (?, ?, ?, ?)',
            [0, 'bundle.download.integrity_failed', 'Bundle ' . $bundleId . ' checksum mismatch — file refused', gmdate('Y-m-d H:i:s')]
        );
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Bundle file is unavailable']);
        exit;
    }
}

// Log the download
query(
    'INSERT INTO ' . table('audit') . ' (user_id, action, detail, created_at) VALUES (?, ?, ?, ?)',
    [$client['id'], 'bundle.download', 'Bundle ' . $bundleId . ' license ' . $bundle['license_id'], gmdate('Y-m-d H:i:s')]
);

// Serve the file
$safeTitle = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $bundle['title']);
$filename = 'Website-Core-' . trim($safeTitle, '-') . '-' . substr($bundle['license_key'], 0, 8) . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

readfile($path);
exit;
