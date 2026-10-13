# CajunWebPro custom hosting commerce, billing and client platform

## Implementation continuity when external accounts are unavailable (owner policy 2026-10-13)

See [dependency gate policy](DEPENDENCY_GATES.md): `HC-07` **Awaiting API verification**, `HC-09` and `HC-10` **Development pending — API verification**. Continue first-party product, pricing, cart, client portal, invoice/workflow, adapter interface, protected settings, idempotency, jobs and test-fixture work without using fabricated live 20i results. Keep real reseller/domain/VPS operations unorderable or disabled until exact account entitlements, costs, endpoints and staging acceptance are verified. A missing key is not a reason to halt unrelated web-design, payment foundation or portal work. These statuses describe validation gates and **do not change implemented/release state**.

## 2026-10-13 — Verified 1.9.0 client ZIP delivery increment

Prepared, reviewed and versioned client ZIP handover is delivered in [v1.9.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.9.0) / `080f14aea8fe5c35e04e4eba62976609aef7cd67`. Guarded private storage, immutable manifests, owner publication, client history and authenticated resumable downloads preserve previous records through actual 1.8.0 UPDATE acceptance. 539 native / 569 HTTP/Chromium checks pass. Full P-06 remains partial: generated builds, database handover, larger jobs, dynamic restore and notifications remain pending. Actual merchant acceptance/card checkout and broader 20i/mail/SSH remain pending. [Guide](CLIENT_DELIVERIES.md) · [report](TEST_REPORT_1.9.0.md).

## 2026-10-13 — Verified 1.8.0 invoice payment increment

External USD receipts, partial/full balances, retained owner corrections, legacy balance import and client-owned statements/printable receipts are delivered in [v1.8.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.8.0) / `c36a36e80c40485c9e3e7e6cdd4884ced25db961`. Existing 1.7.0 invoices, attempts/notifications, credentials/keys and earlier data survive UPDATE. New requests/trials use remaining balances; sandbox evidence never becomes revenue. 427 native / 503 HTTP/Chromium checks pass. Actual merchant acceptance, production/client checkout, refunds/tax/credits/subscriptions remain pending. [Guide](INVOICE_LEDGER.md) · [report](TEST_REPORT_1.8.0.md).

## 2026-10-13 — Verified 1.7.0 sandbox notification increment

Signed sandbox receipt, durable minimal inbox and explicit owner-triggered current reporting are delivered in [v1.7.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.7.0) / `1fe0670142755f86aee235fabe1847e5d118ee52`. Receiver defaults off; schema is additive, invoice settlement remains disabled and no runtime daemon is needed. Original 1.6.0 UPDATE preserves attempts/events and all earlier records/keys. 358 native / 451 HTTP/Chromium checks pass; signatures/reporting remain synthetic. Actual merchant acceptance, production/client checkout and automatic processing are pending. [Guide](SANDBOX_NOTIFICATIONS.md) · [report](TEST_REPORT_1.7.0.md).

## 2026-10-13 — Verified 1.6.0 sandbox increment

Owner-only tokenized sandbox trials, durable intent/events, transaction-detail reconciliation and shared manual/catalog/request locking are delivered in [v1.6.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.6.0) / `e5d4c17456faff0522429ab3f58093a06a523bd5`. Sandbox never pays real invoices. Production/client checkout, webhooks, receipts/refunds and recurring billing remain pending. Original 1.5.0 UPDATE preserves credentials/key, requests and existing records. 296 native / 421 HTTP/Chromium checks pass; gateway and browser tokenization fixtures remain synthetic. Back up and enable Sandbox trials after UPDATE. [Guide](SANDBOX_PAYMENTS.md) · [report](TEST_REPORT_1.6.0.md).


**2026-10-13 payment checkpoint:** [1.5.0](RELEASE_1.5.0.md) delivers partial M2 / HC-04 / HC-11 settings and prepared invoice requests. [Guide](PAYMENT_PREPARATION.md). Requests require explicit payment and client-workspace grants; credentials remain owner-only. Card collection/production processing remain disabled. Real merchant/provider acceptance and broader roadmap capabilities remain pending.

**2026-10-13 catalog checkpoint:** [v1.4.0](RELEASE_1.4.0.md) releases partial HC-01/HC-06: managed service prices and reviewed immutable snapshots on current project invoices. [Guide](SERVICE_CATALOG.md). It does not complete checkout, taxes, recurring billing or provider services. Current source and prior account/support capabilities remain verified; older pending-source statements below are historical.

**2026-10-13 released checkpoint:** [1.3.0](RELEASE_1.3.0.md) delivers A-09 for the existing project portal, following core staff lifecycle in 1.2.0. [Support scope and limits](CLIENT_SUPPORT.md). Provider SSO/A-10 SSH, full billing/catalog, native 20i management, mail and website ZIP delivery remain incomplete. Older pending-source statements below are historical; current PHP source is verified on main.

Approved scope: 2026-10-12. Planning only — NOT implemented, integrated, paid for, verified, or deployed.

## Business and delivery decision

CajunWebPro is BOTH (1) a done-for-you web design/development/maintenance company and (2) a first-party branded hosting/domain/VPS reseller storefront. Use the existing navy-and-metallic-gold website language, original horizontal brand logo and supplied favicon. One brand, shared customer identity, coherent navigation, separate service modules. This is not a self-service website builder.

The owner's explicit clarification is to **build our own WHMCS from scratch**: our own customer/order/billing/service/support/admin platform, including design payments and admin email. The [custom platform blueprint](CUSTOM_PLATFORM_BLUEPRINT.md) defines ownership, module boundaries and phased delivery. Existing HC-01 through HC-14 are the implementation requirements for this platform; it must not become merely a themed wrapper around WHMCS or HostShop.

**Selected stack:** existing PHP 8.2+ and MySQL/MariaDB shared-hosting architecture; 20i reseller APIs for infrastructure; Authorize.net for payments and card-on-file; first-party custom storefront, shopping cart, checkout, invoice engine, subscription management, client portal, admin console, notification system and support workflows. Do not make WHMCS, HostShop, Stripe, WordPress, Node, SSH, Docker or a daemon a required runtime dependency. Client control-panel access may link securely into 20i's white-label StackCP where necessary; do not pretend it is first-party software.

The owner wants an attractive **custom on-site card payment form** fitting CajunWebPro's theme, NOT a visually disconnected third-party checkout. Authorize.net Accept.js with first-party card form is an available candidate; card entry must go directly to Authorize.net tokenization and never traverse CajunWebPro servers. It typically falls under PCI DSS SAQ A-EP rather than SAQ A; complete merchant-specific PCI review before live launch. Offer an integrated Authorize.net-hosted-fields/Accept Hosted fallback if a lower PCI footprint becomes necessary. Never represent a hosted iframe as wholly custom-controlled card fields.

**Dependency:** Recover, verify and import the owner's current 1.1.0 source before implementation. The locally inspected 1.0.8 archive is historical reference only. The repo contains documentation only; current 1.1.0 source has not been inspected here, so reconcile existing capabilities before adding them. All work below is approved scope without implementation evidence in Git. No live API credentials, products, wholesale costs, retail prices, licenses or merchant account access have been supplied. Price examples from chat are proposals, not authorized published rates.

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

**Expanded owner requirement:** Everything customer-facing belongs in the client portal, including native management of verified 20i services, actual design-project progress, updates/notifications and downloadable versioned website ZIPs. Use [Client portal roadmap](CLIENT_PORTAL_ROADMAP.md), P-05/P-06/P-07 for full acceptance, explicit existing-service/StackCP-user mapping and the hosting-control capability checklist. Native controls are the goal; a labeled provider-panel handoff is a transitional bridge, not a completed native feature. Client notifications and customer-owned email tools are isolated from admin webmail/internal notes.

**Acceptance:** Real authenticated buyer can complete an order then see only their own service, invoice and support history. Role and cross-tenant access tests pass.

Also verify that a design-only customer can follow progress/approvals, receive an update and download their published website release, while a combined customer manages both project and verified hosting controls under one identity. Validate artifact integrity and safe restore, and deny cross-client downloads and notifications.

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

Maintain a per-feature endpoint/permission/entitlement/test register for the client portal, and a reviewed import/linking flow for existing users, packages, domains and VPS. Reconcile provider IDs and service grants before creating or assigning resources; avoid automatic ownership inference from email/domain matches. Verify actual delegated-login capabilities before promising SSO.

References: https://docs.20i.com/api/20i-api-documentation ; https://docs.20i.com/api/provision-hosting-package-api ; https://docs.20i.com/api/retrieve-packages-api

## HC-08 — Shared hosting lifecycle (P0/P1)

Map each first-party shared-hosting SKU to actual 20i package type and limits. Create package after cleared payment and checks; link domain reference if registration is pending; create/update allowed StackCP access; sync primary domain, plan, region, status and usage if supported. Queue upgrades, downgrades, renewal, suspension/restore with grace windows, controlled cancellation and deprovisioning only after approval/retention protections. Preserve backups and access during billing disputes and technical outages per policy.

**Acceptance:** Provision, reconcile, change plan, fail and recover from timeout, payment lapse/recovery, restore access and cancel in staging with no accidental duplicate package or data loss.

The service workspace must also cover authorized website/email/database/DNS/security/backup/runtime controls and measured status where account APIs and plan permissions permit. Follow the client-portal capability register; expose unsupported or unverified operations honestly. Website delivery ZIPs belong to P-06 and must not be confused with provider backup snapshots or their retention.

## HC-09 — Domain catalog, resale and registrar lifecycle (P1)

Use 20i domain capabilities after verifying access: live availability and dynamic TLD/wholesale cost lookup; real new registration, transfer, renewal, auto-renew option, contact verification and expiry monitoring; nameservers/DNS and transfer lock/EPP code if exposed. Capture registrant ownership and consents; do not register CajunWebPro as customer owner without explicit authorization. Handle premium domains, reserved/unavailable names, registry restrictions, transfer failures, grace/redemption and different first-year vs renewal prices. Confirm reseller prefunding and sufficient credit **before** submitting domain registration.

**Acceptance:** Complete authorized sandbox/provider-supported register/transfer/renew lifecycle with correct registrant, pricing, invoices, expiries and failure recovery; where no safe test registration exists, mark live operation blocked and require explicitly authorized low-cost end-to-end test.

## HC-10 — VPS/cloud catalog and lifecycle (P2)

**Progress 2026-10-13:** the VPS lifecycle contract is implemented as an isolated component on `devin/vps-foundation` — validated specs, admin-priced offerings (null = not orderable), entitlement/usage display, and a guarded server state machine with two-actor decommission, in-flight failure rollback and no silent cancellation; 58 synthetic checks. See [VPS_FOUNDATION](VPS_FOUNDATION.md). Catalog/comparison UI, persistence, provider adapter wiring and real provision/cancel verification remain pending (gate: Development pending — API verification).

Sell unmanaged/managed VPS only if 20i account product access, API endpoints, prices, provisioning/cancellation operations, IP allocations and support boundaries are verified. Product variants include CPU/RAM/disk/OS/region, addons, licensing, capacity and known wholesale costs. Confirm whether payment requires prepaid credit or separate monthly provider charges. After payment/fraud check, create server, monitor build state, show credentials securely by one-time reveal or delegated panel, and expose only supported power/reboot/rebuild/backups controls. Destructive operations require confirmation, verified backups/retention warnings and logged authorization. Managed support and SLAs are separate published products only when deliverable.

**Acceptance:** Real test provision/build readiness, safe retry, start/stop as available, billing synchronization and teardown verified. Don't publish VPS inventory and accept money before tested provisioning supports it.

## HC-11 — Web-design payment forms and maintenance commerce (P0 payments / P1 project integration)

The owner explicitly wants payment forms for web-design services as well as infrastructure checkout. Deliver a standalone design-payment flow in the approved navy/gold theme using the same Authorize.net settings and payment engine. It must work with 20i disabled and no hosting/domain/VPS purchase. Do not delay design payments until domain provisioning is available. This is a planned requirement; inspect current 1.1.0 source before determining the implementation gap.

### Payment options and customer form

- Full payment for a real fixed-price web-design service published by admin.
- Fixed-amount or percentage deposit calculated from the accepted project quote; show both amount due now and remaining quoted balance.
- Milestone payment requests tied to the exact approved proposal/project stage, followed by final-balance payment.
- One-off additional work on an approved invoice, and optional recurring maintenance/retainers with explicit recurring consent and separate terms.
- Entry from a published service, the client's own portal invoice, or an admin-generated secure payment link. Quote-specific prices remain private and cannot be inferred from an estimator or URL parameter.
- Responsive form with customer/billing details, service/invoice reference, itemized amount, applicable tax, total due now, prior payments and remaining balance where relevant, terms, clear Pay action, recoverable inline errors and confirmed receipt. Do not preselect optional purchases or recurring consent.

### Admin and payment-request lifecycle

Admin can create, preview, activate, expire and revoke a service payment request linked to customer, project, proposal version and invoice. Configure description, approved line items/prices, currency, due date, deposit rule and allowed payment stage; preserve an immutable snapshot when issued. The generated link can be copied for sharing. Issued price/term changes supersede the old request; paid invoices cannot be silently rewritten. Track draft, awaiting payment, processing, paid, failed, canceled/expired and refund states with amount due and an audit trail.

Require authenticated invoice ownership or a narrowly scoped, unpredictable, expiring and revocable payment token that grants no other portal access. Keep tokens protected and out of logs/referrers; no personal details, card data or gateway credentials in the URL. Resolve payable amount and eligibility server-side on every submission. Serialize payment attempts so duplicate links, tabs or retries cannot charge the same obligation twice or overpay the project. A timed-out attempt remains pending until gateway reconciliation establishes whether it succeeded.

Use HC-04/05/06 for tokenized card processing, sandbox/live separation, verified transaction/webhook handling, receipts, refunds and reconciliation. A failed charge retains the invoice as unpaid. A confirmed payment updates the invoice, project financial balance, admin and client history; it does not automatically approve a design, mark work finished or provision infrastructure. Reconcile partial/full refunds and remaining balance according to recorded policy. Recurring maintenance is a distinct opted-in subscription, never an automatic consequence of paying a one-off invoice.

### Project and hosting integration

Join the proposal/lead/project roadmap: public service packages and estimator with honest ranges; quote approval; progress/files/approvals; support and handover. Optional bundles include a domain and hosting plan with separate financial treatment and ownership terms. The single client dashboard displays both design payments and infrastructure services, with distinct fulfillment states. Payment-only requests can launch with M2 once their own security/staging gates pass; richer project workflow and cross-sell remain M4.

P-05/P-06/P-07 require the full client project workspace: milestones and real updates, preview/revision/approval history, portal/email notifications, latest and prior website ZIPs, authorized data exports and handover instructions. Staff explicitly publishes deliverables; payment or project completion alone cannot fabricate a ready download. Use [Client portal roadmap](CLIENT_PORTAL_ROADMAP.md) for secure publication, ownership, archive verification and restore acceptance.

**Acceptance:** In sandbox with 20i disabled, complete a design-only full payment, deposit, milestone and final balance; confirm invoice/project balances and receipts. Demonstrate declined, duplicate, concurrent and uncertain/timeout attempts recover without duplicate charges; server-side amount/currency validation; wrong-client access denial; expired/revoked/superseded/already-paid link handling; verified webhook replay handling; partial/full refund reconciliation; and explicit recurring-maintenance consent. Then verify a mixed hosting/design order gives each item its correct fulfillment state and the client can see both in the same dashboard. No existing implementation or live charge is claimed by these criteria.

## HC-12 — Admin operations and service desk (P1)

Admin navigation groups: Overview, Sales/Orders, Customers, Services/Hosting, Domains, VPS, Web Design/Projects, Plans/Pricing, Subscriptions/Invoices/Refunds, Support, Email (Inboxes/Compose/Accounts/Templates), Administrators, Marketing/Coupons, Reports, Integrations (20i/Authorize.net/SMTP), Security/Audit, Settings/Appearance.

Admin functions: search/filter/export; owner/authorized staff one-click client portal support access with configured customer-support authorization, separate actor identity, visible banner and audit (A-09); separately permissioned one-click VPS SSH after verified authentication/transport setup (A-10); provisioning queue/error diagnostics, safe task retries, reconciliation/manual fulfillment, product/pricing CRUD, stock/capacity rules, branded email templates, tax rules, staff permissions, role-based approvals for refunds/irreversible operations and comprehensive audit records. Never store or display full payment cards. An admin switch cannot fake a completed provider operation.

Include owner-managed additional admin accounts, scoped mailbox read/send/setup access, multi-account webmail with IMAP/SMTP setup, reusable email templates, and unread-mail notification bells in admin and on public pages for authenticated admins only. Each notification opens the corresponding protected webmail message. Full requirements and acceptance are in [Admin mail roadmap](ADMIN_MAIL_ROADMAP.md), A-05 through A-08; keep actual mailbox email separate from internal support-ticket messages and application notification SMTP.

**Acceptance:** Staff can trace any paid order → provider operation → invoice/service/client record and safely recover interrupted jobs without code edits or raw database access. Additional-admin and mailbox permissions, two-account send/receive, template compose/preview, unread-message deep links and anonymous/public-cache isolation pass the A-05 to A-08 matrix.

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
| M1 Product UI + clients/staff foundation | HC-01, HC-02, A-05/A-09, P-07 foundation | Matching branded responsive plan pages, client/staff authentication and scoped support-session permissions, one portal, notification records/preferences, price/config CRUD and tenant isolation |
| M2 Payments + billing engine | HC-03 to HC-06, HC-11 payment forms, P-03 | Authorize.net admin's four keys in both environments; themed checkout and standalone web-design payment forms; full/deposit/milestone/final-balance sandbox payments with 20i disabled; secure invoice links, verified events, duplicate protection, receipts/profiles and recurring-billing tests |
| M3 Shared hosting fulfillment | HC-07, HC-08, P-07 hosting events | Paid order→one provider package→client dashboard→renewal/cancellation recovery; reviewed existing-service grants and verified native client controls/status/events in isolated test accounts |
| M4 Domains and full design workspace | HC-09, HC-11, P-05/P-06/P-07 | Verified domain lifecycle; design stages/updates/previews/approvals and payment history; client notifications plus latest/prior verified website ZIPs, authorized content/database exports and tested handover |
| M5 VPS and support/admin operations | HC-10, HC-12, A-06/A-07/A-08/A-10 | Real endpoint/cost validation, VPS lifecycle and verified one-click support SSH; queues/support/reporting/admin recovery; multi-account webmail/templates and admin-only bells. Mail operations remain independent of VPS readiness |
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
11. Standalone web-design payment form with 20i disabled: full service price, fixed/percentage deposit, milestones, final balance, approved extras, invoice/receipt/project balance and opt-in maintenance.
12. Design-payment request security and lifecycle: foreign-client invoice, altered amount/currency, expired/revoked/superseded/paid link, concurrent duplicate attempts, ambiguous gateway timeout, webhook replay and partial/full refund reconciliation.
13. Additional administrator creation/invitation, scoped role/mailbox permissions, reset, deactivation/session revocation, privilege-escalation denial and last-owner protection.
14. Two real test mailboxes: secure setup, send/receive/reply/forward/drafts/attachments, template preview/variables, provider failure/uncertain send and malicious-message rendering protection.
15. Unread-email bell and exact-message links in admin and the admin-only public header; mailbox read-state synchronization, revoked permissions, moved/deleted messages and anonymous/client/cache isolation.
16. Unified client service mapping and native control coverage: existing/new service grants, plan restrictions, unsupported endpoints, stale data and secure provider handoffs without cross-client or reseller access.
17. Design-only and combined-client progress updates, protected preview/revision approval, notification delivery/read state and absence of internal staff notes/mail.
18. Latest/prior website ZIP publication/download, checksum and isolated dynamic-site restore, client-scoped database export, secret exclusion, expired/revoked links, cross-client denial and large/failed package jobs.
19. Measured service-health observations and outdated/unavailable data, renewal/SSL/backup-age notices where implemented, correct per-contact permissions and deduplicated update delivery.
20. One-click client support session: real staff attribution, customer scope, visible exit/banner, multiple tabs, expiry/revocation, permissions and denial of customer-identity financial/approval actions.
21. Real isolated VPS SSH launch/disconnect: correct product/user/host, staff role, single-use token, protected credentials, host-key verification, failure/timeout/revocation, and gateway independence from shared-host storefront.

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
