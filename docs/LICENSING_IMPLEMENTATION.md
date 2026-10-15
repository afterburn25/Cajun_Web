# Licensing System Implementation — Cajunwebpro.com

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

### 3. What still needs to be implemented

**License key generation:**
- Create a service that generates unique license keys per purchase
- Store keys in `website_licenses` table
- Associate with customer (studio_clients) and product

**Admin UI:**
- License management page (create, view, deactivate licenses)
- Domain management (register, change, deactivate domains per license)

**Customer portal:**
- Domain registration UI — customer enters their website domain
- Domain change workflow — deactivates old domain, activates new one
- License key display — shows customer their license key

**Module bundling:**
- Tool that creates bundled ZIPs (core + industry module + license_key)
- Calls Website Core build tool: `php tools/build-packages.php bundle <industry> <license_key>`
- Stores bundle for download in customer portal

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

1. Implement license key generation service
2. Create admin UI for license management
3. Create customer portal UI for domain registration
4. Implement module bundling tool
