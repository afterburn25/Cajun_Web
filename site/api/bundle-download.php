<?php
declare(strict_types=1);
define('CWP_BOOT',true);
require __DIR__.'/../includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/licenses/service.php';

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

$bundleId = $_GET['id'] ?? '';
if (!is_string($bundleId) || !preg_match('/^\d+$/D', $bundleId)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Bundle not found']);
    exit;
}

$bundle = one(
    'SELECT b.*, l.customer_id, l.license_key FROM ' . table('license_bundles') . ' b
     JOIN ' . table('website_licenses') . ' l ON b.license_id = l.id
     WHERE b.id = ? AND l.customer_id = ? AND l.is_active = 1',
    [(int)$bundleId, $client['id']]
);

if (!$bundle) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Bundle not found or access denied']);
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

// Log the download
query(
    'INSERT INTO ' . table('audit') . ' (user_id, action, detail, created_at) VALUES (?, ?, ?, ?)',
    [$client['id'], 'bundle.download', 'Bundle ' . $bundleId . ' license ' . $bundle['license_id'], gmdate('Y-m-d H:i:s')]
);

// Serve the file
$filename = 'Website-Core-' . $bundle['title'] . '-' . substr($bundle['license_key'], 0, 8) . '.zip';
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
