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

## Current release — 1.7.0

**Published and downloaded-back verified: [CajunWebPro v1.7.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.7.0).** Source/tag `1fe0670142755f86aee235fabe1847e5d118ee52`, [PR #20](https://github.com/afterburn25/Cajun_Web/pull/20). [Complete upload ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.7.0/CajunWebPro-1.7.0-COMPLETE-UPLOAD.zip) · [UPDATE ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.7.0/CajunWebPro-1.7.0-UPDATE.zip). Earlier releases remain immutable; later publication records are documentation only.

Delivered bounded M2 / partial HC-04 and HC-11: disabled-by-default signed sandbox notification receipt, minimal durable inbox, exact-body HMAC and replay/account/webhook protection. Receipt commits audit before HTTP 200 without provider requests or invoice/attempt changes. Owners explicitly verify queued messages against current sandbox reporting. Matching attempt observation, inbox processing and audit commit together; stale/conflicting facts remain queued. Real invoices never become paid from sandbox events. [Guide](docs/SANDBOX_NOTIFICATIONS.md).

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38385343738): **358 native checks (62 notification checks), 451 HTTP/Chromium checks**, PHP 8.3/MariaDB 10.11. Real retained 1.6.0 UPDATE preserves attempts/events, requests, encrypted credentials/master key, invoices, identities, catalog/support records and private/config/guard files. Older updates and one-page fresh installation pass. Independent policy 251 and invitations 46 checks pass on PHP 8.2/8.4. Desktop/320px UI screenshots reviewed. [Report](docs/TEST_REPORT_1.7.0.md), [scope](docs/RELEASE_1.7.0.md), [download verification](docs/RELEASE_VERIFICATION_1.7.0.json). Eight draft/public assets and tag match CI/local rebuilt bytes.

Existing sites back up files/database/private key, merge UPDATE and have an owner enable **Sandbox notifications** after existing Sandbox trials setup. Fresh installation creates an empty inbox with receipt disabled. Configure HTTPS, sandbox credentials and expected webhook ID through owner controls. Credential edits pause receipt until rechecked and reconfigured. Provider registration is separate; this increment performs none. Owners process queued messages manually. Packages contain 64 COMPLETE / 63 UPDATE files; UPDATE omits only install.php. Never rerun fresh setup.

**Actual merchant delivery/key compatibility, TLS/shared-host acceptance, client checkout, production settlement, scheduled processing, refunds/receipts and recurring billing remain unverified or incomplete.** Synthetic HMAC/reporting and intercepted Accept.js checks do not establish real provider or PCI acceptance. No live deployment, customer mail, real charges or provider operations occurred. Full 20i, admin webmail/templates/bells, website ZIP delivery, MFA and VPS SSH remain roadmap work.

**Next:** preserve Devin portfolio PR #7 at `55d60670497d987286e64630e037e1ba2ec3a318`; [handoff](docs/DEVIN_HANDOFF_1.7.0.md). Continue separately through actual sandbox acceptance, a real-money ledger and client/design checkout before enabling production. Sandbox records must never be relabeled as real payments. Configure credentials securely through owner controls, never Git/chat. No background continuation is scheduled. Refresh main/open work before editing.

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
