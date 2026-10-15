# Licensing System Implementation — Cajunwebpro.com

Integration note,2026-10-15: the20i branch now retains main13f9662d and its separate licensing changes. Earlier findings about integer product identifiers, project-bound domain actions and root-relative downloads have been corrected upstream. Our optional-storage portal/admin guards and owner-only licensing permissions remain. Main also introduces paid-invoice fulfillment/mail hooks, native licensing tests and optional external bundle-source configuration. This normal merge still needs combined billing/portal/packaged acceptance; neither this note nor20i confirmation tests certify licensing end to end. No Website_Core checkout was read, changed or executed by this20i work, and no customer mail was sent. Historical implementation claims below describe the inherited module, not newly established live acceptance.

## What has been implemented

### 1. License validation API endpoint

**File:** `site/api/validate.php` (public entry point)
**File:** `site/api/handlers/license.php` (handler)

Implements `POST /api/validate.php` — the endpoint that Website Core calls to validate license keys. A `.htaccess` rewrite also exposes the clean URL `POST /api/validate`.

**Request:**
```json
{
  "license_key": "ABCD-1234-EFGH",
  "domain": "example.com"
}
```

**Response:**
```json
HTTP 200 {"authorized": true}
// or — explicit denial (takes the site offline at next validation):
HTTP 200 {"authorized": false, "error": "License key not found"}
```

**Transient conditions use non-200 status codes** — this is contractually
required. `200 + authorized:false` is the only definitive denial verdict;
Website Core treats any non-200 (429 rate limit, 400 malformed request,
5xx server error) as inconclusive and retries rather than deactivating
the site.

**Features:**
- Rate limiting: 10 requests per minute per IP (HTTP 429)
- Audit logging: all validation attempts logged to `audit` table — and every license mutation (`license.create`, `license.domain.register`, `license.domain.deactivate`, `license.domain.replace`, `license.deactivate`) records the acting staff/client id (0 for system paths like fulfilment), so a domain change is never an unexplained state
- Multiple domain support: allows test + public sites on same license key, capped at 2 active domains per license — a third requires deactivating one first, which takes that site offline at its next validation
- www/apex equivalence: `www.example.com` and `example.com` name the same site — registration canonicalizes `www.` to the apex and validation accepts either form, so the pair never consumes two slots
- Active check: deactivated licenses are rejected

### 2. Database schema

**File:** `site/database/schema/license_schema.php`

Creates two tables:

- `website_licenses` — stores license keys, customer association, active status
- `license_domains` — stores registered domains per license (allows multiple domains per license for test/public sites)

**File:** `site/database/schema/base.php` — updated to include `license_schema($prefix)`

### 3. License service

**File:** `site/app/licenses/service.php`

Functions:
- `license_generate_key()` — generates unique license keys (XXXX-XXXX-XXXX-XXXX)
- `license_create($customerId, $productId)` — creates a new license
- `license_get($key)` — retrieves license by key
- `license_register_domain($licenseId, $domain)` — registers a domain
- `license_replace_domain($licenseId, $oldDomain, $newDomain)` — atomic
  "change website": deactivates the old domain (old install goes offline
  at its next validation) and registers the new one in the freed slot
- `license_deactivate_domain($licenseId, $domain)` — deactivates a domain
- `license_deactivate($licenseId)` — deactivates entire license
- `license_domains($licenseId)` — gets all domains for a license

### 4. Admin UI

**File:** `site/admin/views/studio/licenses.php`
**File:** `site/admin/controllers/studio.php` (updated)

Adds "Website licenses" to the Studio admin section with:
- Create license (customer + product selection)
- List all licenses with customer, product, status
- Add/remove domains per license
- Deactivate entire license

### 5. Customer portal UI

**File:** `site/portal/views/workspace.php` (updated)
**File:** `site/portal/controllers/actions.php` (updated)

Adds license management to the customer workspace:
- View active licenses with domains
- Register new domains
- Replace a domain in one step (old site auto-deactivates — goes offline
  at its next validation)
- Deactivate existing domains

### 6. Module bundling

**File:** `site/app/licenses/bundle.php`

- `license_bundle($licenseKey, $industry, $coreDir, $outputDir)` — creates bundled ZIP (core + industry module with embedded license_key)
- `license_bundle_store($licenseId, $zipPath, $title)` — stores bundle record for download tracking

**Admin UI:** `site/admin/views/studio/licenses.php` — "Generate bundle" button creates downloadable ZIP

**Database:** `license_bundles` table stores bundle records

### 7. Bundle downloads

**File:** `site/api/bundle-download.php`

- Authenticated endpoint — requires customer session
- Verifies customer owns the license
- Logs download to audit table
- Serves ZIP from private storage

**Portal UI:** `site/portal/views/workspace.php` — shows "Downloads" section with bundle links per license

### 8. Purchase flow integration

**File:** `site/app/licenses/fulfill.php`

- `license_fulfill_invoice($invoiceId)` — idempotent; creates license + bundle when a catalog invoice becomes `paid`
- Product mapping: SKU `WEBSITE-{INDUSTRY}` → module `industry-{industry}` (e.g., `WEBSITE-RESTAURANT` → restaurant module)
- Hooked into both payment paths:
  - `catalog_transition()` — non-ledger path, fires after invoice marked paid
  - `ledger_receive()` — ledger path, fires after full-balance receipt marks invoice paid
- Both hooks run AFTER the transaction commits — fulfilment failure never rolls back a recorded payment
- Bundle generation requires a Website Core source checkout: `license_core_source_dir()` checks sibling dirs and `config()['license_core_dir']`; if unavailable the license is still created and the bundle can be generated later from admin
- Payment reversal does NOT deactivate licenses — deactivation stays a manual admin action
- Customer receives a best-effort email with the license key and portal link on fulfilment (`send_message()`); failure is logged, not fatal

### 9. Customer email notification

On fulfilment the customer is emailed their license key plus a link to the
portal workspace where bundles can be downloaded and domains registered.
Best-effort: a mail failure is logged but does not block license creation.

## Integration points

- Website Core calls `POST https://cajunwebpro.com/api/validate.php` with `{license_key, domain}` (base URL configurable via `license.api_url` in Website Core)
- Website Core expects `{"authorized": true/false, "error": "message"}`
- Multiple domains per license key are supported (test + public sites)
- Domain changes deactivate the old site automatically on next validation

## Database schema reference

```sql
website_licenses (
  id, license_key (unique), customer_id, product_id (VARCHAR(60) SKU),
  is_active, created_at, updated_at
)

license_domains (
  id, license_id, domain, is_active, registered_at, deactivated_at
)

license_bundles (
  id, license_id, title, path (private storage), created_at
)
```

## Test coverage

- `tests/license_native.php` — service-layer checks (key generation,
  domain normalization, multi-domain, 2-active cap, deactivation, SKU
  mapping, bundle guards); runs in CI on MariaDB.
- `tests/license_bundle_functional.php` — builds a real bundle from the
  Website_Core checkout and verifies the extracted layout
  (`site/install.php` + `site/modules/industries/*/addon.json` with
  embedded `license_key`) matches the Website Core installer's
  expectations. 14 checks, all passing.
- Website Core side: `LicenseApiContractTest` spawns a real `php -S`
  mock of this API and verifies the HTTP wire contract including the
  200-vs-non-200 denial/transient semantics.

## Next steps

1. Configure `license_core_dir` on production pointing to a Website Core
   checkout so fulfilment can generate bundles automatically.
2. Run a live end-to-end cycle: purchase → fulfilment → portal download →
   Website Core install → periodic validation → domain change → old-site
   deactivation. None of this has been run against a live environment.


## Integration correction — preserve the original site when replacement fails

The20i integration branch normally preserves main16f07e49. Actual local MariaDB reproduction found `license_replace_domain()` deactivated the old domain before validating/inserting the new domain: both invalid input and an injected database failure left the original inactive. The integration correction locks the original active domain in a new transaction and commits deactivation/registration together. Any exception rolls back; an existing caller transaction is refused without mutation or rollback. This fixes replacement atomicity; it does not claim to redesign all licensing concurrency or permissions.

Actual local temporary-table repro/fix reports retain2 original failures and4 corrected cases. The merged native suite passes35 named cases with an isolated prefix/config (application functions unchanged), including invalid replacement preservation, injected insertion failure and caller-transaction preservation. CI now retains `qa/license-native-results.json`. Combined browser/current-head Linux and independent review remain pending. No Website_Core checkout was modified or executed; no live purchase, license change or customer message occurred.
