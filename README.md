# CajunWebPro — project source of truth




Custom web design and development services, portfolio, and business-management website. **This is a service business website, not a do-it-yourself website builder.**

Approved expansion: our own custom hosting/billing/client-management platform covering the agreed functions otherwise handled by WHMCS, with 20i infrastructure, Authorize.net payments, web-design services, staff accounts and admin webmail. See the [custom platform blueprint](docs/CUSTOM_PLATFORM_BLUEPRINT.md); this is planned scope, not a completed platform.

Repository: `afterburn25/Cajun_Web` · Authoritative branch: `main`.

## Start here in every session

1. Read [AGENTS.md](AGENTS.md) for the working rules.
2. Read [PROJECT_STATUS.md](PROJECT_STATUS.md) for what exists, what is pending, and what is unverified.
3. Read [SESSION_HANDOFF.md](SESSION_HANDOFF.md) for the exact next actions.
4. Read [Project direction](docs/PROJECT_DIRECTION.md) and [Roadmap](docs/ROADMAP.md) before changing scope or design.

Every working session must update the status, handoff, and [session log](docs/SESSION_LOG.md). Record implementation changes in [CHANGELOG.md](CHANGELOG.md), and important choices in [Decisions](docs/DECISIONS.md). Documentation belongs in Git with the work, not only in chat.

## Current release — 1.4.0

**Published and downloaded-back verified: [CajunWebPro v1.4.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.4.0).** Source/tag `0cdea7fbd17e887d3f4bc0574a411b157431b7df`, merged through [PR #14](https://github.com/afterburn25/Cajun_Web/pull/14). [Complete upload ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.4.0/CajunWebPro-1.4.0-COMPLETE-UPLOAD.zip) · [UPDATE ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.4.0/CajunWebPro-1.4.0-UPDATE.zip). v1.3.0 and earlier releases remain immutable. Publication records are documentation only.

Delivered bounded M1 / partial HC-01 and HC-06: managed draft/published/retired service SKUs, approved USD unit/setup prices and service periods, internal cost/reference privacy, public configured service cards, append-only product revisions, server-reviewed invoice totals and immutable issued service/price snapshots on existing client projects. Repeated/concurrent identical submissions create one invoice. Catalog edits cannot rewrite issued invoices; strict audit protects issue/manual-paid/void transitions. [Catalog guide](docs/SERVICE_CATALOG.md).

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38371380103): **165 native checks (50 original + 31 staff + 35 support + 49 catalog) and 345 real HTTP/Chromium checks**, PHP 8.3/MariaDB 10.11. Actual published 1.3.0 update preserves passwords, roles, pending invitations, support policies/audit, projects, manual invoices and private/config/guard bytes. Older upgrades/fresh install and earlier account/support/client workflows still pass. Independent policy/invitation and documentation gates passed. Actual desktop/mobile screenshots reviewed. [Test report](docs/TEST_REPORT_1.4.0.md), [scope matrix](docs/RELEASE_1.4.0.md), [download verification](docs/RELEASE_VERIFICATION_1.4.0.json). All eight draft/public assets, archive/extracted hashes and exact tag match the tested source.

Existing sites merge UPDATE after a full backup, then owners enable **Service catalog** using their current password. Older sites complete existing workspace/administrator setup first. Fresh install creates an empty catalog. No retail prices or products are automatically published. Complete/update contain 55/54 flat files; do not rerun fresh setup on installed sites. Catalog invoices require both catalog and client-workspace access. Existing free-form invoices and estimator ranges remain intact.

No live deployment, customer mail/data, payment or provider operation occurred. This release does not implement cart/orders, tax/discount calculation, multiple currencies, checkout, gateway receipts/refunds/reconciliation or recurring subscriptions. One service SKU/period per invoice, 1–100 units, USD only, setup once. Provider-dependent families cannot publish or issue invoices. Full 20i controls, email/templates/bells, client website ZIPs, MFA and VPS SSH remain roadmap work.

**Next:** keep the split with Devin. [PR #7](https://github.com/afterburn25/Cajun_Web/pull/7) remains intact at `55d60670497d987286e64630e037e1ba2ec3a318`; [current handoff](docs/DEVIN_HANDOFF_1.4.0.md) preserves its portfolio scope. Handoff does not establish Devin receipt/running status. Codex's next bounded step is M2 Authorize.net sandbox settings and payment-request foundation, then verified design-only checkout. Inspect current provider documentation and secure configuration needs at implementation time; no sandbox/live charge or provider behavior has been proven by this catalog release. Refresh main/open work before continuing. No background work is scheduled.

## Non-negotiable direction

- PHP/MySQL or MariaDB on shared hosting; no required Node service, SSH, root access, or command-line installation.
- Preserve the approved navy/royal-blue and metallic-gold design and original horizontal CajunWebPro logo, without `.com` in the branding.
- Use the user's attached fleur-de-lis as the favicon source. Do not substitute another crest or regenerate the logo.
- Fancy, organized, technology-driven presentation; no dull template redesign, flying pixels, forced loading animation, or obstructive effects.
- One visible installer form, all configuration on one page, one Install submission, inline errors, no Next/Back wizard, and no installation key.
- Preserve private configuration, real content, media, and installation locks. Verify installer deletion and report failures honestly.
- Ship one complete, unambiguously named deployment package with no enclosing version or upload folder.
- Keep requested features, mockups, implemented source, local tests, and confirmed deployment clearly distinguished.

## Documentation map

| Record | Purpose |
|---|---|
| [Project direction](docs/PROJECT_DIRECTION.md) | Positioning, design, audience, and scope |
| [Roadmap](docs/ROADMAP.md) | All approved additions and acceptance criteria |
| [Admin mail roadmap](docs/ADMIN_MAIL_ROADMAP.md) | Multiple mailboxes, additional administrators, templates and admin-only notification bells |
| [Custom platform blueprint](docs/CUSTOM_PLATFORM_BLUEPRINT.md) | First-party replacement for the agreed WHMCS-style business functions, module boundaries and delivery phases |
| [Client portal roadmap](docs/CLIENT_PORTAL_ROADMAP.md) | Native hosting controls, web-design progress, updates and verified website ZIP downloads |
| [Architecture](docs/ARCHITECTURE.md) | Inspected PHP baseline and planned extension boundaries |
| [Installation and updates](docs/INSTALLATION_AND_UPDATES.md) | Single-page installer, ownership, packaging, and release checks |
| [GitHub testing and release workflow](docs/RELEASE_WORKFLOW.md) | Automated checks, incremental versions, retained release ZIPs and agent handoff requirements |
| [Release register](docs/RELEASES.md) | Version provenance, published package links and verification limits |
| [Codex and Devin coordination](docs/AGENT_COORDINATION.md) | Separate work branches, task ownership, Devin handoff and integration checks |
| [Access foundation](docs/ACCESS_FOUNDATION.md) | Implemented permission component, test scope and pending 1.1.0 adapter requirements |
| [Staff invitations](docs/STAFF_INVITATIONS.md) | Expiring one-use setup tokens, native transaction tests and account-adapter requirements |
| [Brand assets](docs/BRAND_ASSETS.md) | Exact logo, favicon source, and visual-reference provenance |
| [Baseline inventory](docs/BASELINE_INVENTORY.md) | Artifact identity, file inventory, and inspection limits |
| [Decisions](docs/DECISIONS.md) | Accepted constraints and why earlier approaches were rejected |
| [Session log](docs/SESSION_LOG.md) | Dated work record |
| [Session template](docs/SESSION_TEMPLATE.md) | Required end-of-session record |

The Git repository is not the public document root. Do not upload project documentation, private configuration, customer records, or development tooling as website assets.
