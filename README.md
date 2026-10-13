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

## Current release — 1.3.0

**Published and downloaded-back verified: [CajunWebPro v1.3.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.3.0).** Source/tag `7856cccf230a1646d02a4a92ad4bfb1c280efa19`, merged through [PR #12](https://github.com/afterburn25/Cajun_Web/pull/12). [Complete upload ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.3.0/CajunWebPro-1.3.0-COMPLETE-UPLOAD.zip) · [UPDATE ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.3.0/CajunWebPro-1.3.0-UPDATE.zip). v1.2.0 and v1.1.0 remain available unchanged. Later publication-record changes are documentation only.

Delivered A-09 for the existing project portal: one-click support access from the client record, separately bound staff/client identities, owner-confirmed client restrictions, explicit support view/write/download permissions, 15-minute maximum/five-minute idle expiry, persistent banner/return, stale-tab and cross-client isolation, real-staff messages/uploads/downloads, and strict audit. Customer decisions, authentication changes, payments and unrecognized mutations are blocked in support mode. Existing customer login keys/passwords are preserved. [Support guide](docs/CLIENT_SUPPORT.md).

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38367798995): **50 original + 31 staff + 35 support native checks and 289 real HTTP/Chromium checks**, on PHP 8.3/MariaDB 10.11. Actual published 1.2.0 update preserves populated records, passwords, roles, pending invitations, config/guards/private files; previous 1.0.8/1.1.0 and fresh-install acceptance remain passing. Policy/invitation component checks and documentation checks passed. Desktop/narrow screenshots reviewed. [Test report](docs/TEST_REPORT_1.3.0.md), [scope matrix](docs/RELEASE_1.3.0.md), [fresh-download verification](docs/RELEASE_VERIFICATION_1.3.0.json). Eight public assets match the tested bytes, extracted hashes and exact tag.

Existing installations merge UPDATE after a full backup, then owners explicitly enable **Client support** and allow individual clients under the support agreement/notice. Older versions first complete existing workspace/administrator setup. Fresh installation creates support tables but still requires explicit policy confirmation. No customer is automatically opted in. Complete/update have 52/51 files; never rerun fresh setup on an installed website.

No live deployment, customer mail/data, payment, SSH or provider operation occurred. Provider SSO, VPS SSH, MFA, per-staff client assignment lists, online payments/subscriptions, full 20i control, multi-account mail/templates/bells, client website ZIP delivery, backups and full file updater remain future work.

**Next:** preserve the split with Devin. Its [PR #7](https://github.com/afterburn25/Cajun_Web/pull/7) remains open at `55d60670497d987286e64630e037e1ba2ec3a318`; [updated assignment](docs/DEVIN_HANDOFF_1.3.0.md) retains portfolio ownership and current adapter requirements. GitHub handoff does not prove receipt/running status. Codex's next bounded increment is M1 owner-managed product/price and billing groundwork before M2 Authorize.net checkout; inspect the existing estimator/manual invoices and avoid duplicate systems. A-10 SSH belongs with verified VPS/transport integration. Refresh main/open PRs before work; no background work is scheduled.

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
