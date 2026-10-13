# CajunWebPro — project source of truth

**1.2.0 administrator-account candidate — 2026-10-13:** [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10), `codex/admin-accounts`. Core A-05 now integrates owner-controlled accounts, selected website/client access, private setup/recovery links, revocation and concurrent last-owner protection. [Administrator guide](docs/ADMIN_ACCOUNTS.md). Starting main `ffdcef3`; latest published release remains immutable v1.1.0. Candidate `53cf246` passed [application acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38361215151): 50 original + 31 staff native checks and 212 real HTTP/Chromium checks. Desktop/320px captures were reviewed. Final explicit pending-password login guard, extra assigned-access tests and documentation require the next exact-head run before merge/publication. No live deployment/provider actions. Devin PR #7 remains unchanged at `55d6067`; no work has been pushed to its branch.


Custom web design and development services, portfolio, and business-management website. **This is a service business website, not a do-it-yourself website builder.**

Approved expansion: our own custom hosting/billing/client-management platform covering the agreed functions otherwise handled by WHMCS, with 20i infrastructure, Authorize.net payments, web-design services, staff accounts and admin webmail. See the [custom platform blueprint](docs/CUSTOM_PLATFORM_BLUEPRINT.md); this is planned scope, not a completed platform.

Repository: `afterburn25/Cajun_Web` · Authoritative branch: `main`.

## Start here in every session

1. Read [AGENTS.md](AGENTS.md) for the working rules.
2. Read [PROJECT_STATUS.md](PROJECT_STATUS.md) for what exists, what is pending, and what is unverified.
3. Read [SESSION_HANDOFF.md](SESSION_HANDOFF.md) for the exact next actions.
4. Read [Project direction](docs/PROJECT_DIRECTION.md) and [Roadmap](docs/ROADMAP.md) before changing scope or design.

Every working session must update the status, handoff, and [session log](docs/SESSION_LOG.md). Record implementation changes in [CHANGELOG.md](CHANGELOG.md), and important choices in [Decisions](docs/DECISIONS.md). Documentation belongs in Git with the work, not only in chat.

## Current release — 1.1.0

**Published and downloaded back: [CajunWebPro v1.1.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.1.0).** Immutable release source/tag: `3703783e979abab38e8d72e9092c51dc835833b3`; application merged in [PR #8](https://github.com/afterburn25/Cajun_Web/pull/8). [COMPLETE deployment ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.1.0/CajunWebPro-1.1.0-COMPLETE-UPLOAD.zip) · [1.0.8 UPDATE ZIP](https://github.com/afterburn25/Cajun_Web/releases/download/v1.1.0/CajunWebPro-1.1.0-UPDATE.zip). The publication record/handoff is a later **documentation-only commit**; it does not change the tested release source, tag or packages.

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38357271480) passed **50 native database and 157 real HTTP/Chromium checks**, plus package consistency. Full app: PHP 8.3.6 / MariaDB 10.11.19; non-root filesystem and actual extracted package tests. Access: 146 checks on each PHP 8.2.34/8.4.26. Invitations: 46 native checks on each with MariaDB 11.4.13. Documentation passed. [Test report](docs/TEST_REPORT_1.1.0.md) and [download verification](docs/RELEASE_VERIFICATION_1.1.0.json) record precise scope; all seven release assets were freshly downloaded and verified, including extracted hashes and the tag target.

Delivered: public growth/comparison content, server-priced estimator and saved requests; content/pricing/pipeline/history and portfolio controls; client invitations, projects, milestones, messages, private documents, version-specific decisions, manual invoices and an additive database upgrade. Original branding and one-page keyless setup remain intact.

Read [Devin's exact-release handoff](docs/DEVIN_HANDOFF_1.1.0.md). His PR #7/component branch remains intact. Issue #2 is the authorized GitHub handoff channel; a comment does not establish receipt or a running Devin session. No live hosting, customer data/mail, charges or provider operations occurred. At the 1.1.0 checkpoint those capabilities were deferred; current administrator progress is recorded above. Payments/20i/webmail/support SSH/ZIP delivery/backup and full updater remain later work. This historical 1.1.0 task stop was superseded by the owner's new "Continue With Roadmap" request.

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
