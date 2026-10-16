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
deploys set `license_schema_version='4'` automatically; upgraded installs
prepare storage once via Studio → Website licenses with the administrator
password. All license entry points (admin actions, portal actions/view,
`api/validate.php`, `api/bundle-download.php`, invoice fulfilment) check
`license_ready()` first — unprepared installs never touch license tables
and the API answers inconclusive 500, never a false denial. Version 2
added `website_licenses.invoice_id`; version 3 added
`license_bundles.sha256`; version 4 adds `website_licenses.is_master`
(master keys predate the marker — populated pre-master databases gain the
column in place, existing licenses defaulting to ordinary). The stored
marker is checked before any schema change: version 1–4 markers migrate,
an empty marker only adopts known-empty tables, and an unknown or future
marker is refused untouched so a later migration path can inspect it.

### 3. License service

**File:** `site/app/licenses/service.php`

Functions:
- `license_generate_key()` — generates unique license keys (XXXX-XXXX-XXXX-XXXX)
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
creation like normal keys — keep them private. The internal-only boundary
is enforced at every delivery layer, not just the interface:

- the customer workspace lists only `is_master=0` licenses, and the
  portal's domain actions refuse master IDs even when the customer owns
  the attached account
- `license_bundle()` and `license_bundle_store()` reject master keys —
  no deliverable ZIP can embed an unrestricted key
- the admin bundle action refuses masters before building
- `api/bundle-download.php` requires `is_master=0`, so a master bundle
  recorded before the guard cannot be downloaded through a customer URL

### 5. Customer portal UI

**File:** `site/portal/views/workspace.php` (updated)
**File:** `site/portal/controllers/actions.php` (updated)

Adds license management to the customer workspace:
- View active licenses with the **full license key** — self-service
  recovery when the fulfilment email is lost (the key is the customer's
  own; the portal only exposes it to the authenticated owner). Internal
  master keys are excluded (`is_master=0`) even when attached to the
  customer's account, and the portal's domain actions apply the same
  predicate so a master ID cannot be mutated through the portal
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

- `license_fulfill_invoice($invoiceId)` — idempotent; creates license + bundle when a catalog invoice becomes `paid`. Before minting it also reconciles licenses created before `invoice_id` existed: when exactly one unlinked (`invoice_id IS NULL`), non-master license matches the invoice's customer+product, that license is claimed for the invoice (`WHERE id=? AND invoice_id IS NULL` keeps concurrent fulfillers honest) instead of minting a duplicate. When several unlinked candidates exist the pairing is ambiguous, so fulfilment refuses and the link is reconciled explicitly — a guess could attach the wrong historical purchase.
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
  invoice_id (unique, nullable), is_active, is_master, created_at, updated_at
)

license_domains (
  id, license_id, domain, is_active, registered_at, deactivated_at
)

license_bundles (
  id, license_id, file_path (private storage), title, sha256, created_at
)
```

## Test coverage

- `tests/license_native.php` — service-layer checks (key generation,
  domain normalization, multi-domain, 2-active cap, atomic replace,
  mutation audit, master keys, deactivation, SKU
  mapping, bundle guards) plus the upgrade and isolation cases: populated
  pre-master migration (rows/domains/bundles preserved, `is_master` added,
  marker stamped v4), repeated migration, unknown/future marker refusal
  without touching the stored marker, refusal to adopt populated unmarked
  tables, master exclusion from the portal predicate/bundle
  builder/bundle store/direct-download authorization, ordinary and
  revoked bundle authorization, legacy NULL-invoice fulfilment reuse, and
  ambiguous-candidate refusal. Runs in CI on MariaDB.
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
