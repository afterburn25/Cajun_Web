# CajunWebPro custom hosting commerce, billing and client platform

Approved scope: 2026-10-12. Planning only — NOT implemented, integrated, paid for, verified, or deployed.

## Business and delivery decision

CajunWebPro is BOTH (1) a done-for-you web design/development/maintenance company and (2) a first-party branded hosting/domain/VPS reseller storefront. Use the existing navy-and-metallic-gold website language, original horizontal brand logo and supplied favicon. One brand, shared customer identity, coherent navigation, separate service modules. This is not a self-service website builder.

**Selected stack:** existing PHP 8.2+ and MySQL/MariaDB shared-hosting architecture; 20i reseller APIs for infrastructure; Authorize.net for payments and card-on-file; first-party custom storefront, shopping cart, checkout, invoice engine, subscription management, client portal, admin console, notification system and support workflows. Do not make WHMCS, HostShop, Stripe, WordPress, Node, SSH, Docker or a daemon a required runtime dependency. Client control-panel access may link securely into 20i's white-label StackCP where necessary; do not pretend it is first-party software.

The owner wants an attractive **custom on-site card payment form** fitting CajunWebPro's theme, NOT a visually disconnected third-party checkout. Authorize.net Accept.js with first-party card form is an available candidate; card entry must go directly to Authorize.net tokenization and never traverse CajunWebPro servers. It typically falls under PCI DSS SAQ A-EP rather than SAQ A; complete merchant-specific PCI review before live launch. Offer an integrated Authorize.net-hosted-fields/Accept Hosted fallback if a lower PCI footprint becomes necessary. Never represent a hosted iframe as wholly custom-controlled card fields.

**Dependency:** Import and verify the actual 1.0.8 source baseline into Git first. As of this decision the repo contains documentation only. All work below is APPROVED / NOT IMPLEMENTED. No live API credentials, products, wholesale costs, retail prices, licenses or merchant account access have been supplied. Price examples from chat are proposals, not authorized published rates.

## Public experience and site architecture

- Main navigation: Web Design, Portfolio, Hosting, WordPress Hosting (only if product truly supports it), VPS Servers, Domains, Maintenance, Pricing/Plans, Support, Client Login, Cart. Mobile nav and accessibility are first-class.
- Dedicated responsive hosting landing pages: polished hero, comparison tables/cards, meaningful plan names, monthly/annual switch, renewals clearly disclosed, resources/features, FAQ, availability and terms, included versus optional add-ons, compare plans, buy-now path, no fictitious unlimited promises.
- Domain storefront: search box with live availability, TLD filtering, registration period, transfer-in, domain-only checkout, renewal price versus first-year price, premium-domain exception, domain + hosting bundles.
- VPS catalog: actual sizes, virtual CPU/RAM/disk/bandwidth, OS/location/options, management level, setup time estimate only if verified, recurring pricing and upgrade path. Never equate shared hosting package capacity with per-VPS resources.
- Web-design storefront: packages/consultation/quote request, portfolio, proposals and milestone deposits, managed website plans, ongoing maintenance and hosting bundles. Web-design orders can require manual discovery and approval; do not automatically provision a bespoke custom website.
- Dedicated /clients login/dashboard and /checkout surfaces with one coherent visual system. Use clean real PHP routes and organized app folders, not dozens of unrelated files in web root or a fake single-page mockup.
- Preserve approved navy/royal-blue/metallic-gold branding, original logo, real content, subtle motion, accessible layouts, touch controls, responsive forms, contrast, reduced-motion behavior and graceful JavaScript failures.

## HC-01 — Product catalog and prices (priority P0)

Admin CRUD: product families (shared hosting/WordPress-ready, domains, VPS, web-design, maintenance, add-ons); plans/SKUs; internal cost; retail monthly/annual prices; setup fees; contract length; promotional and renewal pricing; taxes; currency; availability; resource limits; service features; badges/display order; active/draft/retired state; subscription and service lifecycle behavior.

Store wholesale 20i catalog IDs/package type IDs and capability mapping separately from public labels. Distinguish shared-reseller base subscription cost, consumption/account limits, per-domain and per-VPS provider costs, wholesale discounts, payment fees, margin/profit estimates. Provider costs must never be exposed in customer UI.

**Acceptance:** Admin edits truly change public plans and server-side checkout totals; unpublished plans cannot be purchased; tax/coupons/durations recomputed server-side; plan limits are enforced against provider/parent reseller quotas; test purchase preserves the price snapshot on the order/invoice; marketing descriptions match real provisioned limits.

## HC-02 — First-party customer identity and unified client portal (P0)

- Registration, email verification, login, reset, optional MFA, account profile, company and billing contacts, billing address, security/activity, multiple contact roles with scoped authorization.
- Dashboard cards for services, domains, VPS, web-design projects, quotes/proposals, invoices, saved payment methods (provider token IDs only), support, notices and renewal dates.
- Hosting details: plan, primary domain, status, renewal, usage if API available, DNS/name server instructions, secure branded panel login or vendor-approved delegated SSO; show limitations honestly.
- Domains: registration/expiry/renewal status, transfer workflow, registrant contacts with permission rules, DNS and transfer-lock management only where API supports.
- VPS: configuration/status, assigned IP, control links, usage and lifecycle actions only where confirmed in API. Warn before destructive actions.
- Web design: documents, project milestones, previews, proposal approvals, invoices/deposits, communication and revision requests. Integrate existing approved P-01/P-02/P-03/P-04 roadmap work; do not create a second disconnected client database.
- Client-isolation on every record, route, file, signed download and API operation. System-generated account activity and audit trail.

**Acceptance:** Real authenticated buyer can complete an order then see only their own service, invoice and support history. Role and cross-tenant access tests pass.

## HC-03 — Branded cart, checkout and order management (P0)

Order flow: browse plans → configure domain/provider options → cart → customer/contact and billing details → clear recurring-price consent/terms → authorized payment → verified order state → queued provisioning → fulfilled service and welcome notice. Allow mixed carts where supported: hosting + domain + design deposit; split fulfillment responsibilities and statuses per line item. Guest checkout optional only if identity claim process is secure.

Persist quotes, order snapshots, currency, tax, discounts, invoices, payment attempts, fulfillment tasks, and external references. Handle duplicate submissions, abandoned orders, payment failures, fraud reviews, pending setup and errors without silently charging or creating twice. Do not mark paid based solely on redirect/thank-you page.

**Acceptance:** Single and mixed-item orders settle correctly, exact one-time payment at most once, each eligible service provisions no more than once, failed provider calls remain visible/retryable and a refund/cancellation never quietly deletes customer data.

## HC-04 — Authorize.net settings screen (P0)

In Admin → Settings → Payments → Authorize.net provide **exactly these editable labeled credential fields for each separate Sandbox and Production environment**:
1. API Login ID
2. Transaction Key
3. Signature Key
4. Public Client Key

Also provide: Enable gateway, sandbox/live selector with clearly visible environment indicator, verify API connection, webhook endpoint URL and verification state, currency, accepted payment methods as truly available, authorization/capture behavior, transaction logging without secrets, webhook delivery/event log, test-payment panel, production readiness checklist, credential rotation/replacement and status/last validation timestamp.

Store Transaction Key and Signature Key encrypted/authenticated at rest using a master secret protected outside web root and excluded from Git/backups unless separately protected. Mask all secrets when reading/editing; empty edit means keep existing key. Restrict read/write/rotate to privileged admins and audit changes without values. Public Client Key is publishable to browser, and API Login ID is used client-side by Accept.js when applicable. Keep sandbox/live sets physically/logically separate and never run live charges in test mode.

**Acceptance:** Admin can save/change all four fields safely, validate sandbox credentials, recover cleanly from rejected credentials, rotate keys without exposing old values, and prove production credentials do not appear in responses/logs/source/backup artifacts.

## HC-05 — Custom Authorize.net payment forms (P0)

Use secure, native-feeling CajunWebPro payment presentation: gold-accented inputs, responsive mobile layout, billing fields, inline validation, explicit order summary, tax and recurring price, clear processing state, accessible errors, submit lock and truthful receipt.

Use Authorize.net Accept.js directly for card-to-provider tokenization (opaqueData), followed by a **server-initiated** createTransactionRequest; no raw PAN/expiration/CVV or complete bank details in PHP POST, logs, session, analytics, error trackers, database, email or server memory. Follow CSP, PCI DSS scope review and HTTPS requirements. Offer an AcceptUI/Accept Hosted variant if the merchant prefers reduced PCI scope; document tradeoffs rather than claiming all forms have identical compliance scope.

Process synchronous response and asynchronously verify webhook notifications using the Signature Key, exact raw request body and constant-time HMAC-SHA512 comparison. Persist deduplicated event IDs and fetch/verify provider transaction state as needed; verify invoice amount, currency, merchant, profile and status before fulfillment. Secure webhook endpoints and rate-limit actions.

**Acceptance:** Sandbox successful/declined/duplicate/refund tests, browser never submits card data to CajunWebPro server, forged/replayed webhook is rejected, customer-visible failure is recoverable, secrets stay hidden, gateway outages never become falsely marked success.

References: https://developer.authorize.net/api/reference/features/acceptjs.html ; https://developer.authorize.net/api/reference/features/webhooks.html ; https://developer.authorize.net/api/reference/features/accept-hosted.html

## HC-06 — Custom billing, subscriptions and finance (P0/P1)

Implement first-party recurring-invoice engine using Authorize.net customer/payment profiles rather than requiring third-party billing software. Store only customerProfileId/customerPaymentProfileId, masked card brand/last4/expiry if returned and needed; never CVV or PAN.

Features: monthly/annual terms; setup fees and design deposits; one-off invoices; card-on-file consent; renewal notices; tax calculation where required; prorated upgrades/downgrades; credits; receipts; partial/full refunds with provider-state verification; retries/dunning; overdue/grace state; cancellation-at-period-end and immediate cancellation rules; reminders and retry limits; subscription changes; manual payments/credits with audit trail; customer payment-method updates via tokenization; accounting CSV/export, invoice numbering and reconciliation against merchant settlement and 20i charges.

**Acceptance:** A queued renewal cannot double-charge under concurrency/retries; customer sees next invoice/term/renewal price; provider event loss is repaired via scheduled reconciliation; finance reconciliation flags mismatches; errors do not alter balances or access incorrectly.

References: https://developer.authorize.net/api/reference/features/customer-profiles.html

## HC-07 — 20i reseller integration and credentials (P0)

Admin → Settings → Providers → 20i: API key (secret), reseller/account identity, selected default hosting region, connection check, capabilities catalog, hosting package types, allowed product mappings, quota/usage visibility, wholesale credit balance if API provides it, error health and webhook/poll status. Support credential rotation, mask secrets, least privilege, throttling, timeout and retry policy. No hardcoded account-specific key or sandbox token.

Create provider-adapter interface with explicit support matrix, contract/fixture tests, operation idempotency and external ID reconciliation. 20i documents GET /package and POST /reseller/{id}/addWeb for package listing/provisioning. Do not assume every domain/VPS/StackCP endpoint is publicly supported: inspect the logged-in My20i API documentation and test account before implementing each action.

**Acceptance:** Fetch verified plan types and provision an isolated test hosting account once; retain provider package ID; safely inspect/retry errors and reconcile external state. Do not take payment for an unavailable unsupported SKU.

References: https://docs.20i.com/api/20i-api-documentation ; https://docs.20i.com/api/provision-hosting-package-api ; https://docs.20i.com/api/retrieve-packages-api

## HC-08 — Shared hosting lifecycle (P0/P1)

Map each first-party shared-hosting SKU to actual 20i package type and limits. Create package after cleared payment and checks; link domain reference if registration is pending; create/update allowed StackCP access; sync primary domain, plan, region, status and usage if supported. Queue upgrades, downgrades, renewal, suspension/restore with grace windows, controlled cancellation and deprovisioning only after approval/retention protections. Preserve backups and access during billing disputes and technical outages per policy.

**Acceptance:** Provision, reconcile, change plan, fail and recover from timeout, payment lapse/recovery, restore access and cancel in staging with no accidental duplicate package or data loss.

## HC-09 — Domain catalog, resale and registrar lifecycle (P1)

Use 20i domain capabilities after verifying access: live availability and dynamic TLD/wholesale cost lookup; real new registration, transfer, renewal, auto-renew option, contact verification and expiry monitoring; nameservers/DNS and transfer lock/EPP code if exposed. Capture registrant ownership and consents; do not register CajunWebPro as customer owner without explicit authorization. Handle premium domains, reserved/unavailable names, registry restrictions, transfer failures, grace/redemption and different first-year vs renewal prices. Confirm reseller prefunding and sufficient credit **before** submitting domain registration.

**Acceptance:** Complete authorized sandbox/provider-supported register/transfer/renew lifecycle with correct registrant, pricing, invoices, expiries and failure recovery; where no safe test registration exists, mark live operation blocked and require explicitly authorized low-cost end-to-end test.

## HC-10 — VPS/cloud catalog and lifecycle (P2)

Sell unmanaged/managed VPS only if 20i account product access, API endpoints, prices, provisioning/cancellation operations, IP allocations and support boundaries are verified. Product variants include CPU/RAM/disk/OS/region, addons, licensing, capacity and known wholesale costs. Confirm whether payment requires prepaid credit or separate monthly provider charges. After payment/fraud check, create server, monitor build state, show credentials securely by one-time reveal or delegated panel, and expose only supported power/reboot/rebuild/backups controls. Destructive operations require confirmation, verified backups/retention warnings and logged authorization. Managed support and SLAs are separate published products only when deliverable.

**Acceptance:** Real test provision/build readiness, safe retry, start/stop as available, billing synchronization and teardown verified. Don't publish VPS inventory and accept money before tested provisioning supports it.

## HC-11 — Web design + maintenance commerce (P1)

Join current proposal/lead/project roadmap: public service packages and estimator with honest starting ranges; deposit checkout from accepted proposal; milestone invoices; retainer/maintenance subscriptions; project progress/file/approval, support and site handover. Optional packages include a domain and hosting plan with separate line-item financial treatment and ownership/transfer terms. Prevent automation from treating website design as instantly fulfilled infrastructure.

**Acceptance:** A signed-in client can purchase hosting and pay for an approved custom-design milestone, see both in one dashboard, receive separate correct fulfillment states, approve project deliverables and open support requests.

## HC-12 — Admin operations and service desk (P1)

Admin navigation groups: Overview, Sales/Orders, Customers, Services/Hosting, Domains, VPS, Web Design/Projects, Plans/Pricing, Subscriptions/Invoices/Refunds, Support, Marketing/Coupons, Reports, Integrations (20i/Authorize.net/SMTP), Security/Audit, Settings/Appearance.

Admin functions: search/filter/export, impersonation only with explicit consent and audit if introduced, provisioning job queue, provider error diagnostics, manually retry stuck tasks, reconcile or manually fulfill with safeguards, CRUD product families/prices, stock/capacity rules, branded email templates, tax rules, staff permissions, role-based approvals for refunds/irreversible operations and comprehensive audit records. Never store or display full payment cards. An admin switch cannot fake a completed provider operation.

**Acceptance:** Staff can trace any paid order → provider operation → invoice/service/client record and safely recover interrupted jobs without code edits or raw database access.

## HC-13 — Technical design, installation and migrations (P0)

Preserve existing PHP entry points, app/ organization, assets/ theme, original installer and upgrades. After importing baseline, add modular boundaries such as app/commerce/, app/billing/, app/payments/authorize-net/, app/providers/twentyi/, app/clients/, app/support/, app/jobs/, app/admin/, and migration scripts; keep roots minimal and do not move existing files without route/dependency tests. Use typed service classes, provider adapter contracts, PDO prepared queries, transactions, FK/unique constraints and controlled sequential migrations.

Suggested normalized tables to design and review: client_accounts, contacts, products, plans, plan_prices, provider_mappings, domains, hosting_services, vps_services, design_projects, carts/cart_items, orders/order_items, invoices/invoice_items, payment_attempts, gateway_profiles, subscriptions, renewals, credits, fulfillment_jobs, provider_events, notification_outbox, tickets/ticket_messages, integration_settings, audit_events. Separate unique external transaction and provider IDs. Protect tenant ID on every query.

Retain **one-page, one-submit, keyless web installer**: optional new sections must fit the single page; no separate wizard, install key or SSH/Composer requirement. Check PHP extensions, PHP cURL/TLS, supported DB, web server ownership, private writable paths, cron availability and credential storage; do not require API/merchant secrets at install time if services can be enabled later. Dependency bundles must be prepackaged; PHP CLI scheduled jobs/cron are optional at install but **required before enabling automatic recurring operations**; clearly display unmet requirements in admin. No plaintext secret in repo, public directory, diagnostics, backups or ZIP. No chmod 0777. Preserve customer uploads/config/locks and existing data during upgrades.

## HC-14 — Security, reliability, compliance and recovery (P0 release gate)

Enforce CSRF/anti-replay, sessions/rotation, password hashing, MFA option, admin least privilege, strong input validation, encoding, prepared SQL, strict CSP, HTTPS/HSTS where supported, verified gateway signatures, rate limits/fraud review, transaction idempotency, webhook delivery replay/reconciliation, queue retries with exponential backoff and dead-letter states, audit and alerting, backed-up secret rotation, API calls via allowlisted endpoints (SSRF protection), connection timeouts, sanitized logs and privacy retention rules.

Before live billing: merchant approval and PCI DSS scope review, published pricing and renewal/cancellation/refund/acceptable-use/privacy terms, relevant tax assessment, support responsibility, provider resale/domain policies and customer ownership, test-to-live change control and 2-person review for destructive operations where practical. Do not make legal/tax/compliance guarantees without review.

Operational features: scheduled job heartbeat, daily financial/provider reconciliation, alerts for failed renewals, low 20i domain credit, customer DNS/expiry and failed provisioning, verified restore drill, metrics without invented uptime or business counts, and support incident communication. Preserve shared-host compatibility.

## Milestone sequence and acceptance gates

| Milestone | IDs | Definition of done |
|---|---|---|
| M0 Source and integration discovery | Existing F-01/F-02, HC-07 investigation | Import true baseline; inspect actual 20i account capabilities, Authorize.net sandbox and webhook settings; collect no secrets in Git; document feasible SKUs, costs, compliance choice |
| M1 Product UI + clients foundation | HC-01, HC-02 | Matching branded responsive plan pages, client authentication, price/config CRUD, tenant isolation, no fake checkout |
| M2 Payments + billing engine | HC-03 to HC-06 | Authorize.net admin's four keys in both environments, themed tokenized card form, webhook signature and idempotency, sandbox payments, invoices/receipts/profiles and recurring-billing test suite |
| M3 Shared hosting fulfillment | HC-07, HC-08 | Paid order→one provider package→client dashboard→renewal/cancellation recovery, in a tested isolated account |
| M4 Domains and design service cross-sell | HC-09, HC-11 | Dynamic domain catalog/registration/renewals; web design/maintenance invoices and project dashboard integrated without mis-fulfillment |
| M5 VPS and support/admin operations | HC-10, HC-12 | Real endpoint/cost validation, tested VPS provisioning/teardown, queues/support/reporting/admin recovery |
| M6 Hardening, release and rollout | HC-13, HC-14 and A-03/A-04 | Threat model, native integration tests, PCI review, migration/rollback/backup restore, real package hash and tested staging HTTPS checkout; only then authorize live merchant/provisioning |

Every item moves approved → in progress → implemented → verified → deployed with Git commit, test records, artifact provenance, target-host verification and owner approval for real charges. Tasks can be delivered incrementally after dependencies, but **do not skip the gates** or imply a roadmap is a working service.

## Required integration test matrix

1. New customer sign-up, reset and tenant cross-access denial.
2. Plan switch month/year, admin plan edit, disabled/retired SKU and server-side price tampering.
3. Authorize.net sandbox charge success, decline, timeout, duplicate click, fraud/manual-review decision, partial/full refund, profile token update.
4. Forged signature, replayed webhook, missing webhook, out-of-order event and reconciliation.
5. New recurring term, renewal retries, grace/restore, cancellation/refund, tax and mixed-cart deposit.
6. Provider API outage, rate limit, low prepaid 20i credit, insufficient reseller capacity, pending provisioning job, retry and reconciliation.
7. Domain unavailable/premium/transfer failure/renewal expiry ownership; VPS unsupported option, provision and safe teardown.
8. Admin roles/secret masked/credential rotation and live-vs-sandbox separation.
9. One-page installer, app upgrade/migration, backup/restore, subdirectory installation and shared-host cron/permission preflight.
10. Responsive desktop/mobile, keyboard navigation, reduced motion, clear invoice/legal text and transactionally accurate emails.

No credentials, payment cards, provider IDs from real customers, or private billing information are to be inserted in GitHub issues, commits, screenshots or test fixtures.

## Source references and current verification limits

- 20i API overview (API console must be inspected after login): https://docs.20i.com/api/20i-api-documentation
- 20i hosting package provisioning: https://docs.20i.com/api/provision-hosting-package-api
- 20i package types: https://docs.20i.com/reseller-hosting/create-hosting-package-types
- Authorize.net Accept.js / PCI form options: https://developer.authorize.net/api/reference/features/acceptjs.html
- Authorize.net hosted fallback: https://developer.authorize.net/api/reference/features/accept-hosted.html
- Authorize.net webhooks: https://developer.authorize.net/api/reference/features/webhooks.html
- Authorize.net customer profiles: https://developer.authorize.net/api/reference/features/customer-profiles.html

Research completed 2026-10-12 on public provider documentation, NOT owner account API endpoint verification. Availability and price of domain/VPS automation, 20i credit rules, exact reseller plan pricing, merchant features and compliance scope must be verified against the eventual live accounts before launch.
