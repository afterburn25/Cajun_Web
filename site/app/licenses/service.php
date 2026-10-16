<?php
/** License key generation and management for Website Core bundled products. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/**
 * Write a licensing audit record — every mutation (create, domain
 * register/deactivate/replace, license deactivate) leaves a trail so a
 * domain change is never an unexplained state. $actor is the staff id
 * for admin actions, the client id for portal actions, 0 for system
 * paths (fulfilment, api).
 */
function license_audit(int $actor, string $action, string $detail): void {
    query('INSERT INTO ' . table('audit') . ' (user_id, action, detail, created_at) VALUES (?, ?, ?, ?)',
        [$actor, 'license.' . $action, $detail, gmdate('Y-m-d H:i:s')]);
}

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
function license_create(int $customerId, string $productId, int $actor = 0, bool $master = false): string {
    $now = gmdate('Y-m-d H:i:s');
    // license_key is UNIQUE — 128-bit random keys make collisions essentially
    // impossible, but retry rather than fail outright if one ever occurs.
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $key = license_generate_key();
        try {
            query(
                'INSERT INTO ' . table('website_licenses') . ' (license_key, customer_id, product_id, is_active, is_master, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?, ?)',
                [$key, $customerId, $productId, $master ? 1 : 0, $now, $now]
            );
            license_audit($actor, 'create', 'License ' . substr($key, 0, 8) . '… customer ' . $customerId . ' product ' . $productId . ($master ? ' (master)' : ''));
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
 * Whether a domain is authorized for the license.
 * Master licenses are staff-only test keys — they authorize any domain
 * and never consume domain slots. Normal licenses require an active
 * license_domains row; the www/apex pair names the same site.
 */
function license_domain_authorized(array $license, string $domain): bool {
    if ((int) ($license['is_master'] ?? 0) === 1) {
        return true;
    }
    $alt = str_starts_with($domain, 'www.') ? substr($domain, 4) : 'www.' . $domain;
    return (bool) one(
        'SELECT id FROM ' . table('license_domains') . ' WHERE license_id = ? AND domain IN (?, ?) AND is_active = 1',
        [(int) $license['id'], $domain, $alt]
    );
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
function license_register_domain(int $licenseId, string $domain, int $actor = 0): void {
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
    license_audit($actor, 'domain.register', 'License ' . $licenseId . ' domain ' . $domain);
}

/**
 * Replace one registered domain with another in a single step — the
 * customer's "change website" action. The old domain is deactivated at
 * once, so its install goes offline at its next validation; the new
 * domain registers in the freed slot.
 */
function license_replace_domain(int $licenseId, string $oldDomain, string $newDomain, int $actor = 0): void {
    $oldDomain = strtolower(trim($oldDomain));
    if (str_starts_with($oldDomain, 'www.')) {
        $oldDomain = substr($oldDomain, 4);
    }
    $newDomain = strtolower(trim($newDomain));
    if (str_starts_with($newDomain, 'www.')) {
        $newDomain = substr($newDomain, 4);
    }
    if ($oldDomain === $newDomain) {
        throw new InvalidArgumentException('The new domain is the same as the current one.');
    }
    $existing = one('SELECT id FROM ' . table('license_domains') . ' WHERE license_id = ? AND domain = ? AND is_active = 1', [$licenseId, $oldDomain]);
    if (!$existing) {
        throw new InvalidArgumentException('Current domain is not active on this license.');
    }
    license_deactivate_domain($licenseId, $oldDomain, $actor);
    license_register_domain($licenseId, $newDomain, $actor);
    license_audit($actor, 'domain.replace', 'License ' . $licenseId . ' ' . $oldDomain . ' -> ' . $newDomain);
}

/**
 * Deactivate a domain for a license.
 */
function license_deactivate_domain(int $licenseId, string $domain, int $actor = 0): void {
    query(
        'UPDATE ' . table('license_domains') . ' SET is_active = 0, deactivated_at = ? WHERE license_id = ? AND domain = ?',
        [gmdate('Y-m-d H:i:s'), $licenseId, $domain]
    );
    license_audit($actor, 'domain.deactivate', 'License ' . $licenseId . ' domain ' . $domain);
}

/**
 * Deactivate an entire license.
 */
function license_deactivate(int $licenseId, int $actor = 0): void {
    query('UPDATE ' . table('website_licenses') . ' SET is_active = 0, updated_at = ? WHERE id = ?',
        [gmdate('Y-m-d H:i:s'), $licenseId]);
    license_audit($actor, 'deactivate', 'License ' . $licenseId . ' deactivated');
}

/**
 * Get all domains for a license.
 */
function license_domains(int $licenseId): array {
    return all('SELECT * FROM ' . table('license_domains') . ' WHERE license_id = ? ORDER BY registered_at DESC', [$licenseId]);
}
