# Approved roadmap and acceptance criteria

Updated: 2026-10-12. **All items below are pending implementation/verification in Git unless their status is changed with commit and test evidence.** The initial repository work is documentation only. A rendered concept is not a finished feature.

## Foundation and release recovery

### F-01 — Import the exact application baseline

Import the complete inspected 1.0.8 PHP distribution and original assets, preserve filenames and functionality, document repository-to-upload layout, exclude secrets/private data, and make repeatable flat packages with manifests. Verify version constants and the installer's embedded hashes after every shipping-file change. Acceptance: no missing includes/assets, no wrapper folders, no mixed-build files, no stale direct-download installer, and a source/commit/artifact provenance record.

### F-02 — Real staging acceptance

Reconcile the user's reported 1.0.5 running screen and root-owned storage against the actual deployed entry point before diagnosing the cause. Exercise the one-page installer with native MySQL/MariaDB, GD, SMTP, HTTPS, subdirectory hosting, shared-host worker permissions, retries, cleanup and installed-site guards. Record measured results separately from historical reports or mocked tests. Preserve existing data; no forced reinstall.

### F-03 — Exact favicon and brand preservation

Use `icon(1).png`, not a generated replacement. Produce appropriate square transparent icon variants and ICO/PNG references without stretching/cropping the fleur-de-lis. Keep the original horizontal wordmark unchanged. Verify public/admin/installer icon references, browser rendering, subfolder paths, and updated installer integrity hashes. Record sizes and source fingerprint. App-icon/PWA installation is not implied merely by shipping icon files.

## Public experience and lead generation

### W-01 — Interactive portfolio and case studies

Real desktop/mobile captures, accessible before/after slider for actual redesigns, optional short user-initiated walkthroughs, filterable project categories, expanded challenge/solution/deliverables/results, and live links where approved. Admin controls for capture/media placement and clear concept/client labels. Acceptance: keyboard/touch usability, no false performance/result claims, no broken media, genuine responsive captures rather than a shrunken desktop screenshot.

### W-02 — Signature homepage motion

Preserve approved navy/gold composition while adding restrained metallic logo highlights, layered device depth, polished service/portfolio responses, and section reveals. Acceptance: immediate readable content, reduced-motion and no-JavaScript behavior, touch-safe controls, no flying pixels/autoplay audio/forced loading, and measured load/layout/interactivity before claiming performance improvements.

### L-01 — Single-page project estimator

One form for service/project type, approximate pages/products, functionality, content/branding/migration/maintenance options, timing, and contact information. Optional fields can appear in place; no wizard. Admin-editable price rules and ranges, an explanation of assumptions, and a consultation result for uncertain/custom scopes. Acceptance: server-side recomputation, validation, spam controls, saved lead/brief before notification, input preservation on failure, no invented starting prices, and 'estimate, not final quote' labeling.

### L-02 — Website-review requests

Capture existing website, business goals, and contact details in the lead inbox. Start with human-reviewed findings and a saved review/report before sending. Automated performance data is optional later and must label sources, observation date, lab versus real-user evidence, and absent data. Acceptance: valid request persistence, admin workflow, appropriate consent and delivery tracking. Any later server-side URL fetching requires SSRF protections, limits, and no access to private/internal addresses.

### C-01 — Why custom / Wix and Squarespace comparisons

Build an engaging homepage teaser plus detailed, separately scoped comparison content and migration explanations. Compare service model, design process, customization, integrations, responsibilities, content portability, operating costs, and support only with current primary-source evidence. Separate provider/plan facts from CajunWebPro's actual offered deliverables. Include who each route may suit and switching considerations. Acceptance: source/date/plan registry, no copied unsupported mockup claims, no guarantees of rankings/speed/conversion or universal superiority, and a clear consultation CTA.

### C-02 — Packages, FAQ, local/industry information

Admin-managed real package ranges and inclusions, service detail, process, ownership/handover, revisions, timeline responsibilities, hosting/maintenance, practical FAQ, legitimate local service information, and industry examples. Acceptance: no fake prices, locations, customer logos, endorsements, or results; meaningful content rather than duplicate search pages; accurate metadata and structured data only where facts support it.

### L-03 — Consultation scheduling

Start with an honest appointment-request workflow or integrate the selected calendar provider after configuration. Acceptance: timezone handling, real availability/conflict protection if promising instant booking, confirmations, cancellation/rescheduling, spam controls, and no appointment described as booked before persistence/provider confirmation. Provider selection remains a configuration decision, not an existing connection.

## Client operations

### P-01 — Private client portal

Admin-controlled invitation/access, client-isolated projects, milestone status, design previews, comments, revision requests, files, messages, and handover materials. Acceptance: authorization on every project/file/action; one client cannot access another client's records; protected uploads; audit events; session/reset controls; actual milestone updates rather than fabricated progress percentages.

### P-02 — Versioned approvals and proposals

Freeze the exact design/proposal version under review; capture authorized approval or change requests against that version. Proposals contain approved deliverables, exclusions, revisions, schedule assumptions and payment milestones. Acceptance: immutable approved snapshots/audit history, clear superseded versions, ownership checks, deliberate confirmation, and no implied legal e-signature compliance without separate review.

### P-03 — Deposits, invoices and maintenance payments

Integrate an owner-selected hosted payment/invoice provider; Stripe was proposed, not configured or activated. Use provider-hosted card handling. Map customer/project/proposal/milestones, deposits, invoice status, and opt-in care-plan subscriptions. Acceptance: verified signed notifications, idempotent events, amount/currency/project validation, replay/error handling, reconciliation, separate test/live configuration, no raw card storage, and no 'paid' status from a success-page visit alone. Real charges/subscriptions are not authorized by documentation work.

### P-04 — Maintenance/service plans

Owner-defined inclusions, exclusions, limits, support expectations and request workflow. Acceptance: agreed prices and terms, explicit recurring-payment consent where applicable, genuine service status, and no invented 'unlimited support' promise.

## Administration and resilience

### A-01 — Visual lead pipeline

Extend existing inquiries with stages such as New, Consultation, Proposal Sent, Won, and Not Proceeding; configurable where appropriate. Link briefs, notes, quotes, follow-up dates, source, reviews and client projects. Acceptance: authorized edits, validated transitions, useful search/filtering/export, audit events, and reliable saved state. Dragging cards must not be the only accessible control.

### A-02 — Content revision history

Save meaningful versions, compare changes, restore as draft, and retain approval context separately. Acceptance: permissions, no silent overwrite of later edits, explicit restore action, and recorded author/time.

### A-03 — Backup and restore

Protected export of required database/media/configuration with clear scope, integrity metadata, secrets protection, storage limits and retention. Restore must show impact, validate compatibility, require deliberate authorization, and support recovery. Acceptance: a successful restore drill in an isolated environment; an archive download alone is not a tested backup system.

### A-04 — Controlled update manager

Identify installed and available versions, verify trusted package integrity/compatibility, preserve private data/configuration, back up affected material, apply ordered migrations, report failures, and offer a tested recovery path. Acceptance: path-traversal/symlink/partial-write protections, concurrent-run prevention, migration tracking, exact artifact manifest, and no reintroduction of install keys or multi-step setup. Signing/trust provisioning must be designed before automatic updates are enabled.

## Implementation order and completion rule

Import and validate the baseline first. Land exact branding/favicon and public experience/content plus lead workflows next, then client operations and resilience in tested increments. Access control and backup/recovery checks must precede storing real client documents or enabling payments/automatic updates.

Move an item through `approved` → `in progress` → `implemented` → `verified` → `deployed` only with evidence for each transition. Record branch/commit, routes, schema/config changes, tests, limitations, and deployment status. Update this roadmap and the session handoff with every meaningful change.


## H — Hosting commerce, white-label resale, custom billing and unified clients (approved 2026-10-12)

**Full requirements and phase gates:** [Custom hosting and billing roadmap](HOSTING_AND_BILLING_ROADMAP.md). This expands P-01/P-03/P-04 and adds the owner's selected payment/provider direction; existing web-design, branding, installer and safety requirements still apply. Do not treat a future launch as implemented.

- **HC-01/02:** Real hosting/domain/VPS/web-design catalog and prices editable in admin; branded unified client portal and strict tenant isolation.
- **HC-03/04/05:** Cart and first-party checkout; Authorize.net settings with API Login ID, Transaction Key, Signature Key, Public Client Key (separate sandbox/live), custom theme-matched Accept.js card form and cryptographically verified webhooks; no raw card storage.
- **HC-06:** Own invoice, recurring billing, deposits, refunds, dunning and payment-profile/token management; no WHMCS/HostShop dependency.
- **HC-07/08:** 20i Reseller API configuration, package types and automated shared hosting fulfillment/reconciliation/suspension safeguards.
- **HC-09/10:** Real domain catalog/register/transfer/renew; separately priced VPS/managed cloud products, only after API/cost and operational validation.
- **HC-11/12:** Web design projects/proposals/maintenance alongside hosting; staff admin, service desk, support tickets, queue recovery, financial reports.
- **HC-13/14:** Modular shared-host PHP/MySQL source, one-page/keyless installer compatibility, upgrade migrations, cron/reliability, PCI/privacy/compliance and backup/restore release gates.

**Implementation order:** M0 baseline import/API feasibility → M1 plans + clients → M2 Authorize.net custom checkout + billing → M3 20i shared hosting → M4 domains + web-design workflows → M5 VPS/support → M6 hardened staging/live approval. Prices are owner-administered; past illustrative rates are not approved retail or verified wholesale.
