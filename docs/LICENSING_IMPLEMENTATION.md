## Owner allocation — software-product licensing, 2026-10-15

The owner is assigning Devin licensing for software products offered by CajunWebPro, including generation of licenses used to install other products. Continue the existing licensing implementation; this assignment does not establish that a Devin session is running or that licensing is complete. No new Devin branch/PR has been supplied yet. Previous mainb8f8528b licensing changes are preserved in the20i integration branch. New mainb8c432ca adds master-key runtime; it is inspected but not merged pending the migration and internal-key delivery findings in [the licensing handoff](LICENSING_INTEGRATION_REVIEW.md).

Codex and its three agents continue complete20i P0. Reserve licensing service/validation/schema/bundling implementation for Devin. Shared purchase/invoice fulfillment, portal actions/views, admin routing, base schema and installer inventory require sequential integration preserving both lanes. Root reviews the actual incoming Git diff and combined tests before merging; no branch resets, replacement platform or duplicate licensing rewrite. CajunWebPro's own installer remains the approved one-page, keyless installer; licenses here are for other software products. No direct message was sent to Devin by this allocation record.

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

That two-field shape remains supported for genuine legacy website licenses.
New software-entitlement clients must also send the exact catalog SKU as
`product_id` (and may send their installed `version`). Omitting `product_id`
for a software entitlement is a definitive denial; a key for one product must
never be reused to activate another product on the same domain.

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
- Active check: deactivated licenses are rejected — including master keys
- Master keys: `is_master` licenses authorize any domain (staff testing only); the domain check lives in `license_domain_authorized()` so the handler and native suite exercise identical logic

### 2. Database schema

**File:** `site/database/schema/license_schema.php`

Creates three tables:

- `website_licenses` — stores license keys, customer association, active
  status, `is_master` (staff test keys that authorize any domain), and
  `invoice_id` (UNIQUE, nullable — links each licence to the paid invoice
  that produced it; fulfilment is idempotent per invoice while a second
  purchase of the same product mints a new license)
- `license_domains` — stores registered domains per license (allows multiple domains per license for test/public sites)
- `license_bundles` — records generated bundle ZIPs per license

**File:** `site/database/schema/base.php` — updated to include `license_schema($prefix)`

**File:** `site/database/migrations/license_migrate.php` — owner-gated
adoption for existing installs (`studio_license_setup` in admin). Fresh
deploys set `license_schema_version='2'` automatically; upgraded installs
prepare storage once via Studio → Website licenses with the administrator
password. All license entry points (admin actions, portal actions/view,
`api/validate.php`, `api/bundle-download.php`, invoice fulfilment) check
`license_ready()` first — unprepared installs never touch license tables
and the API answers inconclusive 500, never a false denial. Version 2
added `website_licenses.invoice_id`; `license_migrate()` upgrades a
version-1 adoption in place (`ALTER TABLE` + re-validate) without losing
existing license rows.

### 3. License service

**File:** `site/app/licenses/service.php`

Functions:
- `license_generate_key()` — generates new 128-bit unique license keys as eight four-hex groups; previously issued four-group keys remain valid
- `license_create($customerId, $productId, $actor, $master)` — creates a
  new license; `$master` produces a staff test key that validates on any
  domain (internal use — never issue to customers)
- `license_domain_authorized($license, $domain)` — single source for the
  domain check used by `api/validate.php`: master → true; otherwise an
  active `license_domains` row (www/apex treated as one site)
- `license_get($key)` — retrieves license by key
- `license_register_domain($licenseId, $domain)` — registers a domain
- `license_replace_domain($licenseId, $oldDomain, $newDomain)` — atomic
  "change website": deactivates the old domain (old install goes offline
  at its next validation) and registers the new one in the freed slot
- `license_deactivate_domain($licenseId, $domain)` — deactivates a domain
- `license_deactivate($licenseId)` — deactivates entire license
- `license_reactivate($licenseId)` — reverses a license deactivation;
  previously-registered domains resume as-is (domain-level deactivations
  stay deactivated)
- `license_domains($licenseId)` — gets all domains for a license

**Mutation serialization:** every license mutation runs inside
`license_transaction()` and first takes the license row `FOR UPDATE`
(`license_lock`). Domain changes to the same license serialize — the
2-active cap cannot be bypassed by concurrent registrations — while
changes to different licenses stay concurrent. `license_replace_domain`
runs its deactivate + register in one transaction, so a failed new-domain
registration rolls back the old domain's deactivation too (replace is
atomic, never half-applied). All domain paths canonicalize www to apex
before touching storage.

### 4. Admin UI

**File:** `site/admin/views/studio/licenses.php`
**File:** `site/admin/controllers/studio.php` (updated)

Adds "Website licenses" to the Studio admin section with:
- Create license (customer + product selection, master-key checkbox)
- List all licenses with customer, product, status (master keys badged)
- Add/remove domains per license
- Deactivate entire license

**Master keys:** for internal testing/demos only. The validation API
authorizes any well-formed domain for a master license without domain
registration or slot consumption; `is_active` still applies so a master
key can be revoked at any time. Master keys are displayed once at
creation like normal keys — keep them private.

### 5. Customer portal UI

**File:** `site/portal/views/workspace.php` (updated)
**File:** `site/portal/controllers/actions.php` (updated)

Adds license management to the customer workspace:
- View active licenses with the **full license key** — self-service
  recovery when the fulfilment email is lost (the key is the customer's
  own; the portal only exposes it to the authenticated owner)
- Register new domains
- Replace a domain in one step (old site auto-deactivates — goes offline
  at its next validation)
- Deactivate existing domains

### 6. Module bundling

**File:** `site/app/licenses/bundle.php`

- `license_bundle($licenseKey, $industry, $coreDir, $outputDir)` — creates bundled ZIP (core + industry module with embedded license_key). Each build writes a unique `bundle-{key}-{timestamp}-{rand}.zip` — records are immutable, so regeneration never overwrites bytes another record's checksum verifies against
- `license_bundle_store($licenseId, $zipPath, $title)` — stores bundle record with the ZIP's SHA-256 for download integrity

**Admin UI:** `site/admin/views/studio/licenses.php` — "Generate bundle" button creates downloadable ZIP

**Database:** `license_bundles` table stores bundle records (schema v3 adds `sha256 CHAR(64) NULL`; `license_migrate` applies the additive column on v1/v2 adoptions)

### 7. Bundle downloads

**File:** `site/api/bundle-download.php`

- Authenticated endpoint — requires customer session
- Verifies customer owns the license
- Realpath-containment check keeps served files inside private storage
- **Integrity check:** when a SHA-256 was recorded, the bytes on disk must match or the download is refused (404 + `bundle.download.integrity_failed` audit) — a corrupted or tampered bundle is never served. NULL checksums (pre-v3 records) download without the check
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
  domain normalization, multi-domain, 2-active cap, atomic replace,
  mutation audit, master keys, deactivation, SKU
  mapping, bundle guards); runs in CI on MariaDB.
- `tests/license_bundle_functional.php` — builds a real bundle from the
  Website_Core checkout, verifies the extracted layout
  (`site/install.php` + `site/modules/industries/*/addon.json` with
  embedded `license_key`) matches the Website Core installer's
  expectations, and exercises the real WC install path: module
  extraction from the ZIP → WC + module migrations on sqlite →
  license/packages rows → enable → real provider boot via
  `ModuleRegistry`. 21 checks, all passing.
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
