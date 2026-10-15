# Licensing System Implementation — Cajunwebpro.com

Integration note,2026-10-15: inherited from main161e3edd. The20i integration retains this work and repairs its schema conflict-marker syntax error, missing-table portal/admin reads and broad website-editor access to license administration. These compatibility repairs do not certify licensing as complete. Source inspection still finds string product choices versus an integer product_id column, domain actions placed behind a project lookup although their portal forms are on the all-projects page, a root-relative bundle link, and a sibling Website_Core checkout requirement. Those remain explicit acceptance/review gates; no bundle generation or external Website_Core operation has been exercised by the20i work. The historical implementation claims below are not current end-to-end evidence.

## What has been implemented

### 1. License validation API endpoint

**File:** `site/api/license.php` (public entry point)
**File:** `site/api/handlers/license.php` (handler)

Implements `POST /api/license` — the endpoint that Website Core calls to validate license keys.

**Request:**
```json
{
  "license_key": "ABCD-1234-EFGH",
  "domain": "example.com"
}
```

**Response:**
```json
{"authorized": true}
// or
{"authorized": false, "error": "License key not found"}
```

**Features:**
- Rate limiting: 10 requests per minute per IP
- Audit logging: all validation attempts logged to `audit` table
- Multiple domain support: allows test + public sites on same license key
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

### 8. What still needs to be implemented

**Purchase flow integration:**
- Auto-generate license key when customer buys a product
- Auto-create bundled ZIP on purchase
- Email license key + download link to customer

## Integration points

- Website Core calls `POST https://cajunwebpro.com/api/license` with `{license_key, domain}`
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
