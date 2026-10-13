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

## Current release — 1.2.0

**Published and downloaded-back verified: [CajunWebPro v1.2.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.2.0).** Immutable source/tag `f85627e05b171e4ee8508231e2ed6ee7b9374274`; merged in [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10). [Complete deployment ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.2.0/CajunWebPro-1.2.0-COMPLETE-UPLOAD.zip) · [UPDATE for 1.1.0 / 1.0.8](https://github.com/afterburn25/Cajun_Web/releases/download/v1.2.0/CajunWebPro-1.2.0-UPDATE.zip). v1.1.0 remains available unchanged. The publication-record commit contains documentation only.

Delivered core A-05: additional administrator accounts, explicit website/client-workspace permissions, owner-only settings/account management, private one-use 24-hour setup/recovery, enabled/disabled state, current-password confirmation, session/setup/reset revocation and concurrent last-owner protection. Existing users remain the identity system. Fresh installs initialize the owner; upgrades explicitly select the initial owner without replacing existing passwords. [Administrator guide](docs/ADMIN_ACCOUNTS.md) and [upgrade instructions](docs/INSTALLATION_AND_UPDATES.md).

[Exact-main application acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38362411560): **50 original native + 31 staff native + 216 real HTTP/Chromium checks**, PHP 8.3.6/MariaDB 10.11.19. Access policy: 176 assertions on each PHP 8.2.34/8.4.26; invitations: 46 native checks on each with MariaDB 11.4.13. Documentation checks passed. Desktop/320px screenshots were reviewed. [Test report](docs/TEST_REPORT_1.2.0.md), [release matrix](docs/RELEASE_1.2.0.md) and [download verification](docs/RELEASE_VERIFICATION_1.2.0.json). All eight public assets were freshly downloaded and verified; complete/update archives match CI bytes, all extracted file hashes and version labels agree, and the tag resolves to the tested main SHA.

No live hosting deployment, customer mail/data, payments or provider operations occurred. Multi-account webmail/mailbox grants/templates/bells, MFA, payments, full 20i client controls, support impersonation/SSH, client website ZIP delivery, backups and a full updater remain future work. Current staff client access is agency-wide, not per-client staff assignment. The existing client workspace/manual invoices continue from 1.1.0.

**Next:** keep the Codex/Devin split. Devin's [PR #7](https://github.com/afterburn25/Cajun_Web/pull/7), observed head `55d60670497d987286e64630e037e1ba2ec3a318`, remains intact and unmerged; [current handoff](docs/DEVIN_HANDOFF_1.2.0.md) preserves the portfolio scope and flags already-delivered estimator functionality. A GitHub handoff is not proof Devin is running. Codex's next bounded M1 increment is A-09 audited client-support sessions with original-actor attribution, explicit start/stop/expiry and client/provider access limits; SSH remains A-10. Refresh current main and open work before starting. No background work is scheduled.

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
