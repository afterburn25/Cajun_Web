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

## Current release — 1.10.0

**Published and downloaded-back verified: [CajunWebPro v1.10.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.10.0).** Source/tag `4649a913d40166b9874f9550e57b4186edfc43ab`, [PR #26](https://github.com/afterburn25/Cajun_Web/pull/26). [Complete upload ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.10.0/CajunWebPro-1.10.0-COMPLETE-UPLOAD.zip) · [UPDATE ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.10.0/CajunWebPro-1.10.0-UPDATE.zip). Prior releases remain immutable; publication documentation does not alter release bytes.

Delivered bounded P-07: clients receive activity for studio messages, reviews, files, invoices, payment recordings/corrections and published website releases. Staff with client-workspace access receive client replies, uploads and review decisions in their own centers. Explicit read/unread status is personal; Mark visible changes only the reviewed page. Exact-source links, older-history pagination and unread bells recheck current identity/grants/ownership. Staff also get a workspace bell on public pages after a private check. Count responses contain no message bodies or source metadata. Support cannot access client activity.

Retained source records provide history; `activity_reads` stores only per-person status. There are no business-write notification hooks or copied bodies. Activity failures cannot undo messages/invoices/payments/releases. Visible active pages check at most once per minute without extending idle logins; failed established checks show unavailable status and later recover. Historical updates start unread. This is workspace activity, not webmail or email delivery. [Guide](docs/WORKSPACE_ACTIVITY.md).

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38402918742): **603 native checks (64 activity), 630 HTTP/Chromium checks**, PHP 8.3/MariaDB 10.11. Actual retained 1.9.0 UPDATE preserves business records, identities, encrypted credentials/master key, original settings/configuration/guards/media and published/draft website ZIPs/manifests. A retained client downloads the same original ZIP; prior balances and credentials remain usable. All older update/fresh paths pass. Desktop/320px screens and polling/failure/revocation behavior verified. Independent policy 251/invitation 46 checks pass on PHP 8.2/8.4. [Report](docs/TEST_REPORT_1.10.0.md), [scope](docs/RELEASE_1.10.0.md), [download verification](docs/RELEASE_VERIFICATION_1.10.0.json). All eight draft/public assets and immutable tag match exact-main CI/local rebuild.

Existing sites back up files/database/private storage, merge UPDATE and have an owner enable **Workspace activity** with the current password. Fresh installation includes an enabled empty table. COMPLETE contains 73 files / 48 PHP files; UPDATE contains 72, omitting only install.php. Never rerun fresh installation. No SMTP, background daemon or host CLI is needed.

**Full P-07 remains partial:** outbound email, preferences, stage timeline and hosting/renewal/ticket events remain pending. **A-06–A-08 multi-account webmail, mail templates and email bells are not implemented by this increment.** Real card checkout/actual merchant acceptance, broader billing/20i and VPS SSH remain pending. No live deployment, customer mail/payment or provider operation occurred. Synthetic gateway evidence is not actual merchant acceptance.

**Next:** preserve Devin portfolio [PR #7](https://github.com/afterburn25/Cajun_Web/pull/7) at `55d60670497d987286e64630e037e1ba2ec3a318`; [handoff](docs/DEVIN_HANDOFF_1.10.0.md). Refresh main/open work and claim the next bounded independent roadmap feature, or complete actual merchant acceptance before production card checkout. No background continuation is scheduled.

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
| [Review-request foundation](docs/REVIEW_REQUESTS_FOUNDATION.md) | Independent L-02 capture/workflow contract, evidence labeling and adapter requirements |
| [Brand assets](docs/BRAND_ASSETS.md) | Exact logo, favicon source, and visual-reference provenance |
| [Baseline inventory](docs/BASELINE_INVENTORY.md) | Artifact identity, file inventory, and inspection limits |
| [Decisions](docs/DECISIONS.md) | Accepted constraints and why earlier approaches were rejected |
| [Session log](docs/SESSION_LOG.md) | Dated work record |
| [Session template](docs/SESSION_TEMPLATE.md) | Required end-of-session record |

The Git repository is not the public document root. Do not upload project documentation, private configuration, customer records, or development tooling as website assets.

## Public-component integration in progress

[Integration contract](docs/PUBLIC_COMPONENT_INTEGRATION.md): reviewing Devin estimator PR #27 first, portfolio PR #7 second. The estimator core remains outside deployment; current estimator quantity pricing and persistence stay authoritative. Latest release remains v1.10.0.
