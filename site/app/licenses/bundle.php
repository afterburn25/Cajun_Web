<?php
/** Module bundling tool — creates downloadable ZIPs for purchased products. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/licenses/service.php';

function license_bundle_is_software_entitlement(int $licenseId): bool {
    if($licenseId<1)return false;
    $table=config()['db']['prefix'].'software_entitlements';
    $present=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$table]);
    return $present && (bool)one('SELECT license_id FROM '.table('software_entitlements').' WHERE license_id=?',[$licenseId]);
}

/**
 * Create a bundled ZIP for a licensed product.
 * Returns the path to the created ZIP file.
 */
function license_bundle(string $licenseKey, string $industry, string $coreDir, string $outputDir): string {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('The zip PHP extension is required to build bundles.');
    }
    $license = license_get($licenseKey);
    if (!$license) {
        throw new InvalidArgumentException('License key not found.');
    }
    if (!(bool) $license['is_active']) {
        throw new RuntimeException('License is deactivated.');
    }
    if ((bool) ($license['is_master'] ?? 0)) {
        // Master keys are internal staff testing keys — embedding one in a
        // deliverable ZIP would leak a key that authorizes every domain.
        throw new InvalidArgumentException('Master license keys cannot be bundled for delivery.');
    }
    if(license_bundle_is_software_entitlement((int)$license['id'])){
        throw new InvalidArgumentException('Software entitlements use versioned private releases and cannot use legacy website bundles.');
    }

    // Find the industry module in Website Core examples
    $moduleDir = $coreDir . '/examples/industry-' . $industry;
    if (!is_dir($moduleDir)) {
        throw new InvalidArgumentException('Industry module not found: ' . $industry);
    }

    // Create output directory
    if (!is_dir($outputDir)) {
        @mkdir($outputDir, 0750, true);
    }

    // Unique filename per build — bundle records are immutable, so a later
    // regeneration must never overwrite bytes an earlier record's stored
    // SHA-256 still verifies against.
    $zipPath = $outputDir . '/bundle-' . $licenseKey . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create bundle ZIP.');
    }

    // Add core files
    $coreBase = $coreDir . '/site';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($coreBase, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isLink() || !$file->isFile()) continue;
        $path = $file->getPathname();
        $rel = str_replace('\\', '/', substr($path, strlen($coreBase) + 1));
        // Skip secrets and runtime artifacts. config/ is an allowlist —
        // only the two shipped scaffolding files may travel, so a future
        // runtime artifact (config.php, locks, tokens, env files) can never
        // be bundled accidentally.
        if (str_starts_with($rel, 'storage/')
            || str_starts_with($rel, 'upload/')
            || str_contains($rel, '.key')
            || str_contains($rel, '.pem')
            || str_contains($rel, '.env')
            || (str_starts_with($rel, 'config/')
                && !in_array($rel, ['config/.htaccess', 'config/config.example.php'], true))
        ) {
            continue;
        }
        $zip->addFile($path, 'site/' . $rel);
    }

    // Add module files with embedded license_key
    $moduleBase = $moduleDir;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($moduleBase, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isLink() || !$file->isFile()) continue;
        $path = $file->getPathname();
        $rel = str_replace('\\', '/', substr($path, strlen($moduleBase) + 1));
        if ($rel === 'addon.json') {
            $content = file_get_contents($path);
            $manifest = is_string($content) ? json_decode($content, true) : null;
            if (!is_array($manifest)) {
                throw new RuntimeException('Module manifest is not valid JSON.');
            }
            $manifest['license_key'] = $licenseKey;
            $zip->addFromString('site/modules/industries/' . basename($moduleDir) . '/addon.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $zip->addFile($path, 'site/modules/industries/' . basename($moduleDir) . '/' . $rel);
        }
    }

    $zip->close();
    return $zipPath;
}

/**
 * Store a bundle for download in the customer portal. The ZIP's SHA-256 is
 * recorded alongside it so the download endpoint can prove the bytes served
 * are the bytes built — a corrupted or tampered bundle is refused.
 */
function license_bundle_store(int $licenseId, string $zipPath, string $title): void {
    if ((int)(one('SELECT is_master FROM '.table('website_licenses').' WHERE id=?',[$licenseId])['is_master']??0)===1) {
        throw new InvalidArgumentException('Master license keys cannot be bundled for delivery.');
    }
    if(license_bundle_is_software_entitlement($licenseId)){
        throw new InvalidArgumentException('Software entitlements cannot be stored as legacy website bundles.');
    }
    $now = gmdate('Y-m-d H:i:s');
    $sha = is_file($zipPath) ? hash_file('sha256', $zipPath) : false;
    query(
        'INSERT INTO ' . table('license_bundles') . ' (license_id, file_path, title, sha256, created_at) VALUES (?, ?, ?, ?, ?)',
        [$licenseId, $zipPath, $title, $sha === false ? null : $sha, $now]
    );
}
