## Release 1.25.0 priority — 2026-10-16

New20i capabilities and nonessential refactors are frozen until1.25.0 publication and all-asset download verification. Canonical [codex/release-1.25.0](https://github.com/afterburn25/Cajun_Web/tree/codex/release-1.25.0) is published at `b98b96eb2a33e4bdeb72e15217213431885aad67`, normally merging current main35d8 and mature3fa. The [frozen scope](https://github.com/afterburn25/Cajun_Web/blob/codex/release-1.25.0/docs/RELEASE_SCOPE_1.25.0.md) and [integration map](https://github.com/afterburn25/Cajun_Web/blob/codex/release-1.25.0/docs/RELEASE_1.25_INTEGRATION_MAP.md) are also copied into main for discovery. The owner wants the mature increment now, without waiting for complete20i.

Root and agents are closing existing licensing defects, extending real populated1.24 root/subfolder client-data upgrades, and preparing complete PHP8.2/8.4 × MariaDB10.11/MySQL8.4 application/browser/update runs. Devin continues separate non20i work; Codex owns the narrow release licensing corrections and shared seams. Proposed PRs are not automatically included. Unfinished branches and original failed evidence remain preserved.

Merge checkpoint passes canonical installer generation, all shipping PHP/JavaScript syntax, documentation and exact2886-file staged preservation checks. Imported3fa focused38933590118 passes11 reports/1440 executions in each of4 environments and its Windows packages. These are scoped results, not combined release acceptance. Exact-candidate full checks/images, independent review, normal main merge, exact-main full checks, packages, publication and download verification remain required. Published1.24 is unchanged; no1.25 release or live deployment exists.

This main checkpoint changes documentation only; runtime remains35d8. Follow the release branch for implementation and acceptance. Earlier current-status labels below are historical where superseded.

## Current owner allocation - 2026-10-16

**Devin: continue all remaining non-20i site work, including licensing. Codex and its three agents: continue complete20i.** This supersedes older unrelated-work pause statements for Devin. [Start here](DEVIN_START_HERE.md) gives the full assignment, direct licensing code locations, shared-file boundaries and instructions that work without the GitHub API. Licensing is already on main; inspect and repair its documented gaps rather than recreating it. Work on separate feature branches; Codex coordinates final integration/releases. Assignment [#157](https://github.com/afterburn25/Cajun_Web/issues/157) is active. Devin [acknowledged the assignment and reported work](https://github.com/afterburn25/Cajun_Web/issues/157#issuecomment-6217621738). Licensing PR #159, tickets #158 and project progress #160 are open; their proposed changes require independent acceptance and sequential integration. Licensing159 has [actionable review findings](https://github.com/afterburn25/Cajun_Web/pull/159#issuecomment-6218434878): unsupported sole-license invoice association, unmarked populated legacy upgrade, and stale installer inventory.

# Working agreement for CajunWebPro

Codex owns the complete 20i P0 integration. [CajunWebPro 1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0) is published and freshly downloaded/verified at immutable source `7190b582a02be8a1daa501db14b5899cfc1eb5d0`. All16 assets, checksums, ZIP members and the tag agree with accepted source; previous1.23 assets are unchanged. This is a GitHub release, not live-site deployment. Full 20i remains unfinished. Codex stays focused on 20i; Devin continues the remaining non-20i site work under the current owner allocation.

Read [takeover](docs/TWENTYI_CODEX_TAKEOVER.md), [requirements and evidence](docs/TWENTYI_REQUIREMENTS_EVIDENCE.md), current status and handoff first. The priority is implementation through verified incremental releases. Former allocation and stopping points in the [historical agreement](docs/archive/2026-10-15-AGENTS.md) are superseded.

These are the owner's project requirements and the repository continuity procedure. Follow them for work in `afterburn25/Cajun_Web`. Do not treat older chat summaries, screenshots, or ZIP filenames as proof of the current implementation.

## Begin every session

1. Read `README.md`, `PROJECT_STATUS.md`, `SESSION_HANDOFF.md`, `docs/PROJECT_DIRECTION.md`, and the relevant roadmap items.
2. Inspect the current `main` commit, source tree, and relevant open work before changing files. If source is not in Git yet, follow the documented baseline-import task; do not invent a replacement application.
3. Identify the actual starting version and scope. Distinguish Git source, generated distribution, uploaded server files, and the running installation.
4. Preserve existing work. Use a feature branch for substantial implementation and document its branch/commit. Never force-push over another session's changes.

## Document every session

The owner authorizes splitting work between Codex and Devin on separate branches. Read `docs/AGENT_COORDINATION.md` before claiming work. Keep bounded task/file ownership, agree shared contracts, and integrate completed PRs sequentially with combined checks. Codex coordinates integration/releases; a documented assignment is not proof Devin is running.

Owner clarification on 2026-10-13: 1.1.0 is being finished by another worker and will be published to GitHub; start independent work now. Isolated components, explicit contracts and synthetic tests may proceed outside the deployment tree without that source. This supersedes the earlier blanket wait for all implementation. Integrating with application routes/schema/login, modifying the installer and producing a deployable release still require inspecting verified current source. Respect `codex/finish-1.1.0` ownership and do not rebuild or downgrade the application.

Update `PROJECT_STATUS.md` and `SESSION_HANDOFF.md`, append a dated entry to `docs/SESSION_LOG.md`, and update `CHANGELOG.md` when tracked files or behavior change. Update `docs/DECISIONS.md` when direction changes and `docs/ROADMAP.md` when scope or feature status changes. Keep architecture, branding, installation, and test documentation synchronized with relevant code.

Each entry must state: the request, starting commit, files/actions changed, actual tests and their scope, results, known failures, remaining blockers, and the next concrete action. Include commit/PR/artifact identifiers where available. For a commit that contains its own log, record its parent and descriptive commit message; the enclosing Git history identifies the resulting commit without a self-referential SHA.

Investigations with no runtime changes still need a short session record. Record useful findings, not private reasoning, secrets, customer records, or full chat transcripts. Do not mark a feature complete merely because it was discussed, illustrated, or added to documentation.

## Preserve the product

- This is a web design company's service and portfolio website, not a DIY site builder.
- Keep PHP/MySQL shared-hosting deployment. Do not introduce a required Node server, SSH, root access, Composer command, or background daemon into customer installation.
- Keep the approved original navy-and-gold horizontal logo without `.com`. Use the exact attached fleur-de-lis as the favicon source; consult `docs/BRAND_ASSETS.md`.
- Preserve the approved rich, sharp, organized, technology-driven visual direction. No flying pixels, unrelated rebranding, generic dull replacement, fabricated business metrics, or invented client endorsements.
- The installer must show all setup fields on one page with one Install submission. No Next/Back wizard, tabs hiding mandatory sections, activation key, or separate repair-installer route.
- Check failures must stay inline and preserve entered values. They may block installation but must not hide the form.
- Never delete installed configuration, permanent locks, customer media, or existing data to make setup run again. Never promise PHP can grant itself ownership privileges.
- Follow the current organized directory contract. Deployment contents extract directly without an enclosing wrapper; this never requires substantial root PHP modules. The historical app/assets-only convention is superseded.

## Verification and release discipline

The owner authorizes GitHub testing, incremental application releases with retained download copies, and continuously updated documentation for other agents. Follow `docs/RELEASE_WORKFLOW.md` and maintain `docs/RELEASES.md`. Use GitHub Actions for isolated tests, keep ready verified work on `main`, and publish tested complete packages as versioned GitHub Release assets. Preserve previous versions and never move published version tags. The current 1.1.0 release has recovered, verified source; subsequent releases still require application acceptance, not documentation CI alone. This standing direction does not authorize live deployment, charges or customer mail by itself.

Test the exact artifact delivered. Application source, embedded installer dependencies, version labels, direct installer, and packaged installer must agree. Regenerate dependency hashes whenever shipping files change — run `python tools/build.py --refresh` and commit the updated `site/install/*.generated.php`; the CI "Exact source, installer inventory and package checks" step fails otherwise ("Installer dependency hashes are stale"). Run `php -l` on every touched PHP file before committing — a leftover merge marker in `site/database/schema/base.php` shipped broken code until the build caught it. Do not reintroduce the old key-based or multi-step installer through a stale copy.

Label evidence accurately: source inspection, syntax check, filesystem test, offline/mock UI test, native database integration, live HTTPS browser flow, or target-host verification. Historical reports are not current test runs. No assertion counts without named results. Screenshots of mockups are not screenshots of a deployed application.

Compare Wix and Squarespace using current primary documentation with source/date/plan scope. Sell the actual service offered; do not copy unsupported competitor claims from generated artwork. Do not promise unlimited features, guaranteed rankings, or higher conversion without evidence.

Do not commit `config.php`, credentials, private storage, client uploads, database dumps, tokens, or host-private diagnostics. Live payments and customer messaging require configured accounts and appropriate authorization. A docs commit is not deployment authorization.

## End every session

Commit the appropriate documentation with the work, verify it exists on the intended branch, and report the commit plus what did and did not change. Leave an actionable `SESSION_HANDOFF.md` so the next session does not ask the owner to repeat already documented requirements.
