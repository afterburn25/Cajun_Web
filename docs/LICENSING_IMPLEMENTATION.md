# Licensing System Implementation — Cajunwebpro.com

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
- Audit logging: all validation attempts logged to `audit` table
- Multiple domain support: allows test + public sites on same license key, capped at 2 active domains per license — a third requires deactivating one first, which takes that site offline at its next validation
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

### 9. Remaining

- Email notification to customer with license key + download link
- Optional: list catalog products in the admin create-license form

## Integration points

- Website Core calls `POST https://cajunwebpro.com/api/validate.php` with `{license_key, domain}` (base URL configurable via `license.api_url` in Website Core)
- Website Core expects `{"authorized": true/false, "error": "message"}`
- Multiple domains per license key are supported (test + public sites)
- Domain changes deactivate the old site automatically on next validation

## Database schema reference

```sql
website_licenses (
  id, license_key (unique), customer_id, product_id, is_active,
  created_at, updated_at
)

license_domains (
  id, license_id, domain, is_active, registered_at, deactivated_at
)
```

## Next steps

1. Implement module bundling tool (creates downloadable ZIPs)
2. Integrate with purchase flow (auto-generate license key on sale)
3. Test the complete flow end-to-end
