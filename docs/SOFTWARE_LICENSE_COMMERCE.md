# Software licenses, purchases and unified client portal — implementation contract

**Owner request:** October 25, 2026. This branch is an independently staged feature request; it is **not merged, shipped or deployed**. `main` is the authoritative production source. Devin remains integration/release lead. Avoid editing Devin's integration branch and require review of shared billing/auth/schema/portal changes.

## Existing functionality to retain (verified in `main` source)

- `site/app/licenses/service.php`: perpetual customer keys, internal master keys, up to two active domains per license, serialized domain replacement, audits.
- `site/database/schema/license_schema.php` and `site/database/migrations/license_migrate.php`: existing v3 license storage, invoice linking, explicit owner-gated preparation and legacy history recovery.
- `site/api/handlers/license.php`: rate-limited validation API; only HTTP 200 with `authorized:false` is a definitive denial. Malformed requests, outages and unprepared storage must remain inconclusive, non-200.
- `site/app/licenses/fulfill.php`: idempotent license fulfillment following a verified paid catalog invoice; attempts private bundle creation and sends a best-effort email with a client-portal URL.
- `site/app/licenses/bundle.php`, `site/api/bundle-download.php`: licensed ZIP creation, private stored copy, ownership and integrity validation. Master keys cannot be shipped.
- `site/portal/controllers/actions.php` + `site/portal/views/workspace.php`: existing logged-in customer license list, registered domains, self-service replacement/deactivation and downloads.
- The same client portal already surfaces domains, hosting, agreements, invoices, projects and website releases. Extend it; **do not build a separate license/payment portal**.

## Requested end-to-end experience

### Staff/software catalog
1. Define software products with SKU, public/private download visibility, versioned releases, price, optional module/dependency, update entitlement, and allowed concurrent registered installations. Any supported software product may be licensed; do not limit the registry to hardcoded restaurant/salon SKUs.
2. Issue **standard** (paid, perpetual), **trial** (free, fixed expiry), **temporary** (staff issued, expiry) and **master** (privileged internal use only) licenses. Require licensee name and installation domain for non-master types. Tie the license to a real customer account/product, never only an email/domain string.
3. Master licenses permit any domain but are strictly staff-only; require owner reauthentication, explicit purpose, recorded issuer and event audit. They are never exposed to customers, emails, bundles, downloadable archives, or API responses.
4. Keep a complete owner-side license register: license type, product/version, licensee/client, creation source/invoice, expiry, suspension/revocation, registered active and retired domains, recent validation/heartbeat, current activation count, and immutable issuer/transfer history.
5. Trial and temporary grants expire reliably **server-side** without a nightly job. Upgrades to paid grants must preserve history and not allow replay or accidental double fulfillment.

### Purchase -> verified payment -> delivery
1. Software is listed alongside hosting, domains and web-design services in the **existing customer portal** and storefront.
2. Checkout creates an invoice/order pending verified payment. Never mark an invoice paid because of a browser redirect, unsigned callback, amount mismatch, sandbox event or unverified provider status.
3. On the verified paid transition, allocate the purchased product grant **idempotently by paid order line and entitlement unit**; the existing one-license-per-invoice link is insufficient for multiple products/quantities. Replays and concurrent webhook processing may not mint duplicates.
4. Release only customer-eligible downloadable versions, from server-private storage. Tie each download to the exact purchaser, order, product, version and authorized entitlement. Keep checksums, content length, filename and immutable asset history. No user-controlled paths or publicly enumerable direct archive URLs.
5. Queue durable delivery notice for sending after the DB transaction commits. Customer receives email with a link to the **same authenticated portal** where the approved downloads, license keys, approved domains and receipts appear. Email must not embed master keys or permanently reusable download tokens. Failed mail is retryable without granting a duplicate key or reversing payment.
6. Ensure purchases, invoices, subscription renewals and downloads all appear in the same customer's unified account, but with permission checks on each service; support/impersonation cannot change domains or download restricted products without explicit authority.

### Client self-service
- **Dashboard:** My Services (domains, hosting, web projects, software), Billing & Payments, My Software (licenses/activations/versioned downloads), Support, Account & Team.
- Customers may register the purchased product's initial domain and **replace an approved domain** on a license already owned, using the existing atomic replacement path. Require CSRF/session, active ownership, operation cooldown, clear warning that the old domain loses authority at next validation, and audit of prior/new domain. No changes to master licenses.
- Show license status (trial days remaining, expiration, active, suspended, pending deployment), assigned product, current domain(s), usage summaries and purchase details. Avoid falsely showing "in use" merely because a domain is registered.
- Domain change should not require repurchase; policy may restrict abusive switching with a configurable cooldown while allowing support review.

### Validation and usage
- Preserve `POST /api/validate.php` semantics for existing products; introduce optional versioned client request fields only with backward-compatible parsing. Validate server-side type, expiry, product, client-owned entitlement, revocation, registered domain and seat limit.
- Record bounded validation outcomes and last-seen timestamps per activation using a well-defined verified signal. Rate-limit and batch writes; keep separate immutable security audit, short operational retention and no raw visitor analytics. A successful validation is evidence of a check, not proof the software is currently running.
- For unavailable license storage or transient failures return non-200 (inconclusive) instead of forcing installed customer websites offline. Expiry/revocation of a verified grant is a definitive denial only when entitlements and history are trustworthy.

## Incremental implementation gates

**A — Entitlement policy and contract (this initial branch slice).**
`site/app/licenses/software_license_policy.php` defines strict pure rules for four types, name/domain binding, UTC expiry, staff-only master and authorization; `tests/software_license_policy.php` exercises them without DB/network. No production endpoint, schema or portal behavior is changed in this slice.

**B — Durable schema and guarded migration.**
Add additive grants/activation history and immutable entitlement-to-order-line rows without changing existing v3 records or creating master keys in customer data. Use explicit owner preparation, preserve interrupted-migration journal, and establish schema readiness for all new entry points. Test migrations from published 1.25 and populated current `main`, repeat/interruption/recovery and missing-history refusal.

**C — Issuance and validation.**
Wire policy through staff actions and public API with a single checked read path and type/expiry/seat tests; retain existing perpetual license behavior. Add bounded activity/verification dashboard, no leak of full keys in audit.

**D — Catalog/verified checkout fulfillment.**
Replace hardcoded industry-only licensed SKUs with registered products, tie entitlements to verified payment/order lines, block sandbox as payment, make fulfillment exactly-once and deliver approved archives privately.

**E — Unified portal and transactional delivery notices.**
Add My Software section to existing portal (not a new app), line items/downloads/invoices/status; safe self-service domain transfers; outbox-backed email containing only a login-bound portal link. Existing project/hosting/domain/mail navigation must remain intact.

**F — Release acceptance.**
Native PHP/MySQL + installed root/subfolder HTTP/Chromium + published-v1.25 UPDATE + CSRF, unauthorized customer access, support-session restrictions, expired trial, payment replay, refund/reversal review, email retry, oversized ZIP, missing source, checksum/archival integrity and cross-tenant isolation. Independent review, exact merged-`main` acceptance, package asset audit and new tagged release **before any live deployment**.

## Key non-negotiables

- No direct edits to `main`, Devin-owned branches or live site from this feature branch.
- Do not replace existing billing, licensing, installer, client identities or bundled key contract.
- No master license download, no plaintext master keys in customer email, no public unauthenticated file URLs.
- No paid fulfillment before trusted payment confirmation. Do not assume a sandbox receipt is real payment.
- Existing license domains and historical purchases survive upgrade. Use one portal and one customer identity.
- Build UPDATE and full/complete packages **without a ZIP inside another ZIP**.

## 2026-10-25/26 feature branch implementation status

Branch PR #266 implements owner-gated software database preparation, standard/trial/temporary/master issuance, expiration-aware license validation, observed checks, private versioned ZIP publishing, on-site Accept.js tokenization, payment attempt fencing, initial charge readback, ARB enrollment and cancellation, signed webhook reconciliation of initial/renewal transactions, subscription receipt idempotency, customer domain changes, license downloads and delivery email retry.

**Shipping/activation gates still open:** No production merchant key was used, no real payment was charged, no external webhook was provisioned, and the repo has not passed a complete hosted database/HTTP/Chromium acceptance or published an updated installer. The software payment selector permits disabled/sandbox modes only; production remains disabled until independently reviewed gateway setup, refund/chargeback reconciliation, live payment tests, retention/privacy/PCI review, owner approval and new release. Do not treat the existence of these sources as authorization to process live payments.

**PCI note:** A merchant-hosted card form that uses Accept.js is a PCI SAQ A-EP integration, not automatically SAQ A. To reduce scope in a later independent hardening pass, consider the Authorize.net AcceptUI hosted-card form while keeping the same branded order/receipt page.

**Outstanding test scope:** native MariaDB/MySQL migration and invoice ledger fixtures, end-to-end sandbox Accept.js script/SRI/CSP path, real gateway transaction detail shapes, ARB webhook replay/race, refund/void/chargeback handling, cancellation during billing, domain movement with active subscriptions, legacy installed licenses, actual old-version UPDATE, unzip safety, and email retry. Do not merge into main or ship while unresolved.
