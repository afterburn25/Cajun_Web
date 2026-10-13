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

## Current release — 1.6.0

**Published and downloaded-back verified: [CajunWebPro v1.6.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.6.0).** Source/tag `e5d4c17456faff0522429ab3f58093a06a523bd5`, merged through [PR #18](https://github.com/afterburn25/Cajun_Web/pull/18). [Complete upload ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.6.0/CajunWebPro-1.6.0-COMPLETE-UPLOAD.zip) · [UPDATE ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.6.0/CajunWebPro-1.6.0-UPDATE.zip). Earlier releases remain immutable. Later publication records are documentation only.

Delivered bounded M2 / partial HC-04 and HC-11: owner-only sandbox card tokenization for an existing prepared invoice request, a durable single-attempt ledger, safe transaction-detail matching and explicit reconciliation. Intent and audit commit before dispatch; duplicate submissions do not send another charge. Unknown/submitting/held attempts block conflicting manual/catalog invoice and request changes. Amounts come from saved invoices. Sandbox results never record real payments, send customer receipts or provision services. [Sandbox guide](docs/SANDBOX_PAYMENTS.md).

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38380935411): **296 native checks (69 sandbox checks), 421 HTTP/Chromium checks**, PHP 8.3/MariaDB 10.11. Actual retained 1.5.0 UPDATE preserves gateway ciphertext/master key, requests, invoices, accounts/invitations/support/catalog records and private/config/guard bytes. Earlier upgrade/fresh-install regressions pass. Independent 251 permission and 46 invitation checks pass on PHP 8.2/8.4. Desktop/320px screenshots reviewed. [Report](docs/TEST_REPORT_1.6.0.md), [scope matrix](docs/RELEASE_1.6.0.md), [download verification](docs/RELEASE_VERIFICATION_1.6.0.json). All eight draft/public assets and tag match exact-main CI and local rebuild.

Existing sites back up files/database/private keys, merge UPDATE and have an owner enable **Sandbox trials** with their current password and backup confirmation. Earlier sites complete workspace/administrator/payment-preparation setup first. Fresh one-page setup creates empty trial tables without merchant credentials. Trials require HTTPS, sandbox credentials, a USD-only merchant and its test mode off. Complete/update contain 61/60 flat files. Never rerun fresh setup on an installed site.

**Production/client checkout, guest links, signed webhooks, automatic discovery/reconciliation, real-money settlement, receipts/refunds and recurring billing remain disabled or incomplete.** Gateway response tests and intercepted Accept.js/POST boundary tests are synthetic. Real sandbox authentication/tokenization/transactions were not verified because merchant credentials were not supplied. Loopback proxy-header browser checks do not establish real HTTPS/shared-host acceptance or PCI compliance. No live deployment, customer mail/data or provider operation occurred. Full 20i, admin email/templates/bells, website ZIP delivery, MFA and VPS SSH remain roadmap work.

**Next:** preserve Devin's independent portfolio PR #7 at `55d60670497d987286e64630e037e1ba2ec3a318`; [handoff](docs/DEVIN_HANDOFF_1.6.0.md). Codex next owns verified signed payment events, actual sandbox acceptance and a distinct real-money/client design-checkout workflow before production enablement. Sandbox records must never be relabeled as real payments. Configure test credentials securely in the owner UI; do not ask for secrets in Git/chat. An unknown attempt without a matching transaction stays unresolved. Refresh main/open work before continuing. No background continuation is scheduled.

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
