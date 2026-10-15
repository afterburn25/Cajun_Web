<?php
/** Fulfil a paid catalog invoice by creating a license and generating a bundle. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/licenses/bundle.php';
require_once CWP_CODE_ROOT.'/app/billing/catalog.php';

/**
 * Map a catalog product SKU to a Website Core industry module.
 * Convention: SKU WEBSITE-{INDUSTRY} maps to module industry-{industry}.
 * Returns the industry slug or null if the product is not a licensed website.
 */
function license_product_industry(array $product): ?string {
    $sku = strtoupper($product['sku'] ?? '');
    if (!preg_match('/^WEBSITE-([A-Z]+)$/D', $sku, $m)) return null;
    $industry = strtolower($m[1]);
    // Only map to modules that actually exist in the build tool's list
    $known = ['restaurant', 'salon'];
    return in_array($industry, $known, true) ? $industry : null;
}

/**
 * Fulfil a paid invoice: create license + generate bundle if the product
 * is a licensed website. Idempotent — returns existing license if already fulfilled.
 *
 * Call this from catalog_transition() after marking invoice 'paid',
 * or manually from the admin license page.
 */
function license_fulfill_invoice(int $invoiceId): ?array {
    if (!catalog_ready()) return null;
    $snapshot = catalog_snapshot($invoiceId);
    if (!$snapshot) return null; // not a catalog invoice

    $industry = license_product_industry($snapshot);
    if ($industry === null) return null; // not a licensed product

    $invoice = one(
        'SELECT i.*, j.client_id FROM '.table('studio_invoices').' i
         JOIN '.table('studio_jobs').' j ON i.job_id=j.id
         WHERE i.id=?',
        [$invoiceId]
    );
    if (!$invoice || $invoice['status'] !== 'paid') return null;

    $clientId = (int)$invoice['client_id'];
    $productKey = (string)($snapshot['sku'] ?? '');

    // Idempotent: return existing license for this client+product
    $existing = one(
        'SELECT * FROM '.table('website_licenses').' WHERE customer_id=? AND product_id=?',
        [$clientId, $productKey]
    );
    if ($existing) return $existing;

    // Create license
    $key = license_create($clientId, $productKey);
    $license = one('SELECT * FROM '.table('website_licenses').' WHERE license_key=?', [$key]);
    if (!$license) return null;

    // Generate bundle in background storage
    $storage = config()['storage'] ?? sys_get_temp_dir();
    $outDir = $storage.'/bundles/license-'.$license['id'];
    if (!is_dir($outDir)) mkdir($outDir, 0700, true);

    $coreDir = license_core_source_dir();
    if ($coreDir !== null) {
        try {
            $zip = license_bundle($key, $industry, $coreDir, $outDir);
            license_bundle_store((int)$license['id'], $zip, $snapshot['title']);
        } catch (Throwable) {
            // Bundle generation failed — license still exists, admin can regenerate
        }
    }

    return $license;
}

/**
 * Locate the Website Core source directory for bundle builds.
 * Checks for a sibling checkout or a configured path.
 */
function license_core_source_dir(): ?string {
    $candidates = [
        CWP_ROOT.'/../Website_Core',
        CWP_ROOT.'/../../Website_Core',
        dirname(CWP_ROOT).'/Website_Core',
        config()['license_core_dir'] ?? '',
    ];
    foreach ($candidates as $dir) {
        if ($dir !== '' && is_dir($dir.'/site') && is_dir($dir.'/site/modules')) {
            return realpath($dir) ?: $dir;
        }
    }
    return null;
}
