<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
boot();

/**
 * License validation API for Website Core.
 *
 * Website Core calls this endpoint to validate bundled module licenses.
 * See Website_Core/docs/LICENSING_API.md for the contract.
 */

function license_response(bool $authorized, ?string $error = null): never {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'authorized' => $authorized,
        'error' => $error,
    ], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['authorized' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate limiting: max 10 requests per minute per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'license_validate_' . hash('sha256', $ip);
if (!consume_rate($rateKey, 10, 60)) {
    license_response(false, 'Rate limit exceeded. Please try again later.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    license_response(false, 'Invalid JSON payload');
}

$licenseKey = $input['license_key'] ?? '';
$domain = $input['domain'] ?? '';

if (!is_string($licenseKey) || strlen($licenseKey) < 8 || strlen($licenseKey) > 255) {
    license_response(false, 'Invalid license key format');
}

if (!is_string($domain) || strlen($domain) < 4 || strlen($domain) > 253 || !str_contains($domain, '.')) {
    license_response(false, 'Invalid domain format');
}

$domain = strtolower(trim($domain));

try {
    // Log the validation attempt for security audit
    $auditData = substr((string)json_encode(['license_key' => substr($licenseKey, 0, 8) . '...', 'domain' => $domain, 'ip' => $ip]), 0, 400);
    query('INSERT INTO ' . table('audit') . ' (user_id, action, detail, created_at) VALUES (0, ?, ?, ?)',
        ['license.validate', $auditData, gmdate('Y-m-d H:i:s')]);

    // Look up the license key
    $license = one(
        'SELECT id, license_key, is_active FROM ' . table('website_licenses') . ' WHERE license_key = ?',
        [$licenseKey]
    );

    if (!$license) {
        license_response(false, 'License key not found');
    }

    if (!(bool) $license['is_active']) {
        license_response(false, 'License is deactivated');
    }

    // Check if domain is registered for this license
    $domainRegistered = one(
        'SELECT id FROM ' . table('license_domains') . ' WHERE license_id = ? AND domain = ? AND is_active = 1',
        [$license['id'], $domain]
    );

    if (!$domainRegistered) {
        license_response(false, 'Domain not registered for this license');
    }

    license_response(true);
} catch (\Throwable $e) {
    report_error($e);
    license_response(false, 'Internal server error');
}
