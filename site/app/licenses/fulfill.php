<?php
/** Fulfil a paid catalog invoice by creating a license and generating a bundle. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/licenses/bundle.php';
require_once CWP_CODE_ROOT.'/app/billing/catalog.php';
require_once CWP_CODE_ROOT.'/app/mail/transport.php';

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
 * is a licensed website. Idempotent per invoice — the UNIQUE invoice_id
 * index means a re-run or concurrent call returns the same license, while
 * a SECOND purchase of the same product correctly mints a new license.
 *
 * Call this from catalog_transition() after marking invoice 'paid',
 * or manually from the admin license page.
 */
function license_fulfill_invoice(int $invoiceId): ?array {
    // New software orders use invoice-unit entitlements; the historical
    // WEBSITE-* fulfillment path remains untouched for existing invoices.
    require_once CWP_CODE_ROOT.'/app/licenses/software_fulfill.php';
    if (software_commerce_ready() && one('SELECT id FROM '.table('software_orders').' WHERE invoice_id=?',[$invoiceId])) {
        $license=software_fulfill_paid_order($invoiceId);
        if($license){
            require_once CWP_CODE_ROOT.'/app/licenses/software_notices.php';
            software_delivery_notice_send($invoiceId);
        }
        return $license;
    }
    if (!catalog_ready() || !license_ready()) return null;
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

    // Idempotent per invoice: return the license this invoice already produced.
    // (Not customer+product — a second purchase must mint a second license.)
    $existing = one(
        'SELECT * FROM '.table('website_licenses').' WHERE invoice_id=?',
        [$invoiceId]
    );
    if ($existing) return $existing;

    // Old invoices have no reliable automatic link to pre-invoice licenses.
    if($invoiceId<=license_invoice_cutover())throw new RuntimeException('This older invoice needs an explicit owner licensing review before fulfillment.');

    // Create license — UNIQUE(invoice_id) collapses a concurrent fulfilment
    // race: the loser reads the winner's row instead of minting a duplicate.
    try {
        $key = license_create($clientId, $productKey, 0, false, $invoiceId);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            $existing = one('SELECT * FROM '.table('website_licenses').' WHERE invoice_id=?',[$invoiceId]);
            if ($existing) return $existing;
        }
        throw $e;
    }
    $license = one('SELECT * FROM '.table('website_licenses').' WHERE license_key=?', [$key]);
    if (!$license) return null;

    // Generate bundle in private storage — never fall back to a shared temp dir
    $storage = config()['storage'] ?? '';
    if ($storage === '') return $license; // cannot store bundles; admin can regenerate
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

    license_notify_customer($clientId, $key, $snapshot);
    return $license;
}

/**
 * Email the customer their license key and portal link after fulfilment.
 * Mail delivery is best-effort — a mail failure must not break fulfilment.
 */
function license_notify_customer(int $clientId, string $licenseKey, array $snapshot): void {
    $client = one('SELECT email, name FROM '.table('studio_clients').' WHERE id=?', [$clientId]);
    if (!$client || !filter_var($client['email'], FILTER_VALIDATE_EMAIL)) return;
    $portal = site_url('portal/index.php');
    $body = "Hello ".$client['name'].",\n\n"
        ."Your purchase of ".$snapshot['title']." is ready.\n\n"
        ."License key: ".$licenseKey."\n\n"
        ."Your website package and downloads are in your client workspace:\n".$portal."\n\n"
        ."Register the domain where this website will run from your workspace. "
        ."The license is tied to that domain — if you move the site, update the "
        ."registered domain and the old installation will deactivate automatically.\n\n"
        ."CajunWebPro";
    try {
        send_message($client['email'], 'Your website license · '.$snapshot['title'], $body);
    } catch (Throwable) {
        // Mail not configured or delivery failed — license remains valid,
        // and the key is visible in the portal workspace.
    }
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
        // Bundle builds read core files from site/ and module sources from examples/
        if ($dir !== '' && is_dir($dir.'/site') && is_dir($dir.'/examples')) {
            return realpath($dir) ?: $dir;
        }
    }
    return null;
}
