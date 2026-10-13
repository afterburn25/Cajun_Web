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

## Current checkpoint — 2026-10-13

Actual **1.1.0 website/client-workspace source** is in `site/`, with native/browser tests and `tools/build.py`. [Completion PR #8](https://github.com/afterburn25/Cajun_Web/pull/8) reconciles recovered source with main. Read the [feature/test matrix](docs/RELEASE_1.1.0.md), [installation instructions](docs/INSTALLATION_AND_UPDATES.md) and [release register](docs/RELEASES.md) for exact evidence and publication status. Historical missing-source incidents no longer mean the source is absent.

Deploy only `site/` contents in the flat release package. Independent staff components in `foundation/` remain outside deployment; Devin's portfolio component is preserved in PR #7. The historical 1.0.8 ZIP in `tests/fixtures/` exists only for upgrade acceptance. Later roadmap work is paused until 1.1.0 publication and handoff.

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
