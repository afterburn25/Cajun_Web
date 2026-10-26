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

// Contract: HTTP 200 + authorized:false means a DEFINITIVE license/domain
// denial (takes the installed site offline at next validation). Transient
// conditions — rate limiting, bad input, server errors — must use non-200
// status codes so Website Core treats them as inconclusive and retries,
// never as license denial. See Website_Core/docs/LICENSING_API.md.
function license_response(bool $authorized, ?string $error = null, int $status = 200): never {
    http_response_code($status);
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
    license_response(false, 'Rate limit exceeded. Please try again later.', 429);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    license_response(false, 'Invalid JSON payload', 400);
}

$licenseKey = $input['license_key'] ?? '';
$domain = $input['domain'] ?? '';
$productId = $input['product_id'] ?? null;
$version = $input['version'] ?? null;

if (!is_string($licenseKey) || strlen($licenseKey) < 8 || strlen($licenseKey) > 255) {
    license_response(false, 'Invalid license key format', 400);
}

if (!is_string($domain) || strlen($domain) < 4 || strlen($domain) > 253 || !str_contains($domain, '.')) {
    license_response(false, 'Invalid domain format', 400);
}
if($productId!==null){
    if(!is_string($productId)||!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',strtoupper(trim($productId)))){
        license_response(false,'Invalid product format',400);
    }
    $productId=strtoupper(trim($productId));
}
if($version!==null){
    if(!is_string($version)||!preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,39}$/D',trim($version))){
        license_response(false,'Invalid version format',400);
    }
    $version=trim($version);
}

$domain = strtolower(trim($domain));

try {
    // License storage must be prepared — an install that has not run
    // license_migrate() (or fresh deploy) answers 500, which Website Core
    // treats as inconclusive rather than a denial.
    require_once CWP_CODE_ROOT . '/app/licenses/service.php';
    if (!license_ready()) {
        license_response(false, 'Licensing storage is not prepared', 500);
    }

    // Do not write a permanent audit row for every heartbeat. Successful
    // validations are recorded below by software_usage_record() as one
    // minute-bucketed license/domain observation. Security rate limiting uses
    // the request IP without retaining it in the business audit trail.

    // Look up the license key
    $license = one(
        'SELECT id, license_key, product_id, is_active, is_master FROM ' . table('website_licenses') . ' WHERE license_key = ?',
        [$licenseKey]
    );

    if (!$license) {
        license_response(false, 'License key not found');
    }

    if (!(bool) $license['is_active']) {
        license_response(false, 'License is deactivated');
    }
    require_once CWP_CODE_ROOT.'/app/licenses/financing.php';
    $financingAllowed=license_financing_authorized((int)$license['id']);
    if($financingAllowed===false){
        license_response(false,'Financing payment is past the agreed grace period');
    }
    if($productId!==null&&!hash_equals((string)$license['product_id'],$productId)){
        license_response(false,'License is not authorized for this product');
    }

    // Check if domain is authorized for this license. The www/apex pair
    // names the same site — a customer who registered example.com and
    // installs at www.example.com (or vice versa) is not a second
    // instance and must not be denied. Master licenses are staff-only
    // test keys that authorize any domain without registration.
    require_once CWP_CODE_ROOT . '/app/licenses/service.php';
    if (!license_domain_authorized($license, $domain)) {
        license_response(false, 'Domain not registered for this license');
    }

    // Existing v3 keys remain valid; new time-limited grants must also pass
    // their server-side type/expiration policy before granting authority.
    require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
    if (!software_grant_verdict($license, $domain)) {
        license_response(false, 'License expired, over its installation limit, or not authorized');
    }
    if($version!==null){
        $versionAllowed=software_grant_version_verdict($license,$version);
        if($versionAllowed===null)license_response(false,'Unknown software version',400);
        if(!$versionAllowed)license_response(false,'Software version is outside this license update entitlement');
    }
    require_once CWP_CODE_ROOT.'/app/licenses/software_usage.php';
    software_usage_record((int)$license['id'], $domain);
    license_response(true);
} catch (\Throwable $e) {
    report_error($e);
    license_response(false, 'Internal server error', 500);
}
