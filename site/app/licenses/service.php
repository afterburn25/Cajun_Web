<?php
/** License key generation and management for Website Core bundled products. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

/** Read-only upgrade guard; existing portals must not query tables they do not yet have. */
function license_storage_ready(): bool {
    foreach(['website_licenses','license_domains','license_bundles'] as $name) {
        if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))return false;
    }
    return true;
}

/**
 * Write a licensing audit record — every mutation (create, domain
 * register/deactivate/replace, license deactivate) leaves a trail so a
 * domain change is never an unexplained state. $actor is the staff id
 * for admin actions, the client id for portal actions, 0 for system
 * paths (fulfilment, api).
 */
function license_audit(int $actor, string $action, string $detail): void {
    // detail is VARCHAR(400) — truncate so an overlong audit string can
    // never throw inside license_transaction and roll back the mutation
    // it was only meant to record.
    query('INSERT INTO ' . table('audit') . ' (user_id, action, detail, created_at) VALUES (?, ?, ?, ?)',
        [$actor, 'license.' . $action, substr($detail, 0, 400), gmdate('Y-m-d H:i:s')]);
}

/**
 * Whether license storage has been prepared — either by a fresh install
 * (which sets license_schema_version during deploy) or by the owner-gated
 * license_migrate() on an upgraded install. Entry points (admin, portal,
 * API, fulfilment) check this before touching license tables.
 */
function license_ready(): bool {
    return (one('SELECT value FROM ' . table('settings') . ' WHERE name = ?', ['license_schema_version'])['value'] ?? '') === '3';
}

/** Pre-upgrade invoice boundary, recorded once by explicit preparation. */
function license_invoice_cutover_ready(): bool {
    $value=one('SELECT value FROM '.table('settings').' WHERE name=?',['license_invoice_cutover'])['value']??null;
    return is_string($value)&&preg_match('/^(0|[1-9][0-9]{0,9})$/D',$value)===1&&(float)$value<=4294967295;
}
function license_invoice_cutover(): int {
    $value=one('SELECT value FROM '.table('settings').' WHERE name=?',['license_invoice_cutover'])['value']??null;
    if(!is_string($value)||preg_match('/^(0|[1-9][0-9]{0,9})$/D',$value)!==1||(float)$value>4294967295)throw new RuntimeException('Prepare licensing history before fulfilling this invoice.');
    return (int)$value;
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
 * Create a new license for a customer. $invoiceId links the license to the
 * paid invoice that produced it — UNIQUE, so concurrent fulfilment of the
 * same invoice can never mint a second license. NULL for admin-created and
 * master keys (multiple NULLs are allowed by the unique index).
 */
function license_create(int $customerId, string $productId, int $actor = 0, bool $master = false, ?int $invoiceId = null): string {
    $now = gmdate('Y-m-d H:i:s');
    // license_key is UNIQUE — 128-bit random keys make collisions essentially
    // impossible, but retry rather than fail outright if one ever occurs. A
    // duplicate on a different unique index (invoice_lookup) is a real
    // conflict, not a key collision — it must propagate.
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $key = license_generate_key();
        try {
            query(
                'INSERT INTO ' . table('website_licenses') . ' (license_key, customer_id, product_id, invoice_id, is_active, is_master, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?)',
                [$key, $customerId, $productId, $invoiceId, $master ? 1 : 0, $now, $now]
            );
            license_audit($actor, 'create', 'License ' . substr($key, 0, 8) . '… customer ' . $customerId . ' product ' . $productId . ($master ? ' (master)' : ''));
            return $key;
        } catch (PDOException $e) {
            if ($attempt === 2 || (int) ($e->errorInfo[1] ?? 0) !== 1062 || !str_contains($e->getMessage(), 'license_key')) {
                throw $e; // not a key collision, or retries exhausted
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
 * Run licensing mutations in a single DB transaction. License state changes
 * are small and serialized per license, so a plain transaction (plus the
 * license row lock below) is enough — no named mutex is needed.
 */
function license_transaction(callable $work): void {
    if (db()->inTransaction()) {
        throw new LogicException('License transaction must not be nested.');
    }
    db()->beginTransaction();
    try {
        $work();
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

/**
 * Lock the license row inside a license_transaction. Every mutation takes
 * this lock first, so concurrent changes to the same license serialize —
 * two registrations can never both observe the same domain count and slip
 * past the 2-active cap together.
 */
function license_lock(int $licenseId): void {
    if (!one('SELECT id FROM ' . table('website_licenses') . ' WHERE id = ? FOR UPDATE', [$licenseId])) {
        throw new InvalidArgumentException('License not found.');
    }
}

/**
 * Lowercase/trim a domain and canonicalize www. to the apex — validation
 * treats the www/apex pair as the same site, so storing apex keeps one
 * slot per real site.
 */
function license_canonical_domain(string $domain): string {
    $domain = strtolower(trim($domain));
    if (str_starts_with($domain, 'www.')) {
        $domain = substr($domain, 4);
    }
    if (strlen($domain) > 253 || !preg_match('/^[a-z0-9][a-z0-9\-\.]*\.[a-z]{2,}$/D', $domain)) {
        throw new InvalidArgumentException('Invalid domain format.');
    }
    return $domain;
}

/**
 * Register a domain for a license.
 */
function license_register_domain(int $licenseId, string $domain, int $actor = 0): void {
    $domain = license_canonical_domain($domain);
    license_transaction(function() use ($licenseId, $domain, $actor): void {
        license_lock($licenseId);
        license_register_domain_tx($licenseId, $domain, $actor);
    });
}

/**
 * Registration guts — caller holds the transaction and the license lock,
 * and $domain is already canonical. A license supports at most 2 active
 * domains (public + test instance); registering or reactivating beyond
 * that requires deactivating one first — the deactivated site's next
 * validation then fails and it goes offline.
 */
function license_register_domain_tx(int $licenseId, string $domain, int $actor = 0): void {
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
 * domain registers in the freed slot. One transaction: if the new domain
 * cannot be registered the deactivation rolls back with it.
 */
function license_replace_domain(int $licenseId, string $oldDomain, string $newDomain, int $actor = 0): void {
    $oldDomain = license_canonical_domain($oldDomain);
    $newDomain = license_canonical_domain($newDomain);
    if ($oldDomain === $newDomain) {
        throw new InvalidArgumentException('The new domain is the same as the current one.');
    }
    license_transaction(function() use ($licenseId, $oldDomain, $newDomain, $actor): void {
        license_lock($licenseId);
        $existing = one('SELECT id FROM ' . table('license_domains') . ' WHERE license_id = ? AND domain = ? AND is_active = 1', [$licenseId, $oldDomain]);
        if (!$existing) {
            throw new InvalidArgumentException('Current domain is not active on this license.');
        }
        license_deactivate_domain_tx($licenseId, $oldDomain, $actor);
        license_register_domain_tx($licenseId, $newDomain, $actor);
        license_audit($actor, 'domain.replace', 'License ' . $licenseId . ' ' . $oldDomain . ' -> ' . $newDomain);
    });
}

/**
 * Deactivate a domain for a license.
 */
function license_deactivate_domain(int $licenseId, string $domain, int $actor = 0): void {
    $domain = license_canonical_domain($domain);
    license_transaction(function() use ($licenseId, $domain, $actor): void {
        license_lock($licenseId);
        license_deactivate_domain_tx($licenseId, $domain, $actor);
    });
}

/**
 * Deactivation guts — caller holds the transaction and the license lock,
 * and $domain is already canonical.
 */
function license_deactivate_domain_tx(int $licenseId, string $domain, int $actor = 0): void {
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
    license_transaction(function() use ($licenseId, $actor): void {
        license_lock($licenseId);
        query('UPDATE ' . table('website_licenses') . ' SET is_active = 0, updated_at = ? WHERE id = ?',
            [gmdate('Y-m-d H:i:s'), $licenseId]);
        license_audit($actor, 'deactivate', 'License ' . $licenseId . ' deactivated');
    });
}

/**
 * Reactivate a deactivated license — recovery for an accidental or
 * temporary deactivation. Previously-registered domains resume as-is.
 */
function license_reactivate(int $licenseId, int $actor = 0): void {
    license_transaction(function() use ($licenseId, $actor): void {
        license_lock($licenseId);
        query('UPDATE ' . table('website_licenses') . ' SET is_active = 1, updated_at = ? WHERE id = ?',
            [gmdate('Y-m-d H:i:s'), $licenseId]);
        license_audit($actor, 'reactivate', 'License ' . $licenseId . ' reactivated');
    });
}

/**
 * Get all domains for a license.
 */
function license_domains(int $licenseId): array {
    return all('SELECT * FROM ' . table('license_domains') . ' WHERE license_id = ? ORDER BY registered_at DESC', [$licenseId]);
}
