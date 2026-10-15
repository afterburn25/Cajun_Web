<?php
/** License key generation and management for Website Core bundled products. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Generate a unique license key.
 * Format: XXXX-XXXX-XXXX-XXXX (32 hex characters)
 */
function license_generate_key(): string {
    $key = strtoupper(bin2hex(random_bytes(16)));
    return substr($key, 0, 4) . '-' . substr($key, 4, 4) . '-' . substr($key, 8, 4) . '-' . substr($key, 12, 4);
}

/**
 * Create a new license for a customer.
 */
function license_create(int $customerId, string $productId): string {
    $now = gmdate('Y-m-d H:i:s');
    // license_key is UNIQUE — 128-bit random keys make collisions essentially
    // impossible, but retry rather than fail outright if one ever occurs.
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $key = license_generate_key();
        try {
            query(
                'INSERT INTO ' . table('website_licenses') . ' (license_key, customer_id, product_id, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)',
                [$key, $customerId, $productId, $now, $now]
            );
            return $key;
        } catch (PDOException $e) {
            if ($attempt === 2 || (int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e; // not a duplicate, or retries exhausted
            }
        }
    }
    throw new RuntimeException('Could not allocate a unique license key.');
}

/**
 * Get a license by key.
 */
function license_get(string $key): ?array {
    $row = one('SELECT * FROM ' . table('website_licenses') . ' WHERE license_key = ?', [$key]);
    return $row ?: null;
}

/**
 * Register a domain for a license.
 */
function license_register_domain(int $licenseId, string $domain): void {
    $domain = strtolower(trim($domain));
    // Canonicalize www. to the apex — validation treats the www/apex pair
    // as the same site, so storing apex keeps one slot per real site.
    if (str_starts_with($domain, 'www.')) {
        $domain = substr($domain, 4);
    }
    if (strlen($domain) > 253 || !preg_match('/^[a-z0-9][a-z0-9\-\.]*\.[a-z]{2,}$/D', $domain)) {
        throw new InvalidArgumentException('Invalid domain format.');
    }
    // A license supports at most 2 active domains (public + test instance).
    // Registering or reactivating beyond that requires deactivating one first —
    // the deactivated site's next validation then fails and it goes offline.
    $now = gmdate('Y-m-d H:i:s');
    $existing = one('SELECT * FROM ' . table('license_domains') . ' WHERE license_id = ? AND domain = ?', [$licenseId, $domain]);
    if (!$existing || !(int) $existing['is_active']) {
        $active = (int) query(
            'SELECT COUNT(*) FROM ' . table('license_domains') . ' WHERE license_id = ? AND is_active = 1',
            [$licenseId]
        )->fetchColumn();
        if ($active >= 2) {
            throw new InvalidArgumentException('This license already has 2 active domains (public + test). Deactivate one before registering a new site.');
        }
    }
    query(
        'INSERT INTO ' . table('license_domains') . ' (license_id, domain, is_active, registered_at) VALUES (?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE is_active = 1, deactivated_at = NULL, registered_at = ?',
        [$licenseId, $domain, $now, $now]
    );
}

/**
 * Deactivate a domain for a license.
 */
function license_deactivate_domain(int $licenseId, string $domain): void {
    query(
        'UPDATE ' . table('license_domains') . ' SET is_active = 0, deactivated_at = ? WHERE license_id = ? AND domain = ?',
        [gmdate('Y-m-d H:i:s'), $licenseId, $domain]
    );
}

/**
 * Deactivate an entire license.
 */
function license_deactivate(int $licenseId): void {
    query('UPDATE ' . table('website_licenses') . ' SET is_active = 0, updated_at = ? WHERE id = ?',
        [gmdate('Y-m-d H:i:s'), $licenseId]);
}

/**
 * Get all domains for a license.
 */
function license_domains(int $licenseId): array {
    return all('SELECT * FROM ' . table('license_domains') . ' WHERE license_id = ? ORDER BY registered_at DESC', [$licenseId]);
}
