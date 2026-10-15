# Working agreement for CajunWebPro

Codex owns the complete 20i integration as P0 under the owner's 2026-10-15 override. Unrelated development is paused. Earlier Devin-only allocation is superseded; all source branches and evidence remain preserved. Current integration: `codex/twentyi-complete`, from main `68e4d411acedd9f73cf048018ba570ace9b673be`. Published v1.23.0 (`4fdb56266c77f6e6bf7b58e966d24696e1eaefc8`) remains unchanged. No release or live deployment is claimed.

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

Test the exact artifact delivered. Application source, embedded installer dependencies, version labels, direct installer, and packaged installer must agree. Regenerate dependency hashes whenever shipping files change. Do not reintroduce the old key-based or multi-step installer through a stale copy.

Label evidence accurately: source inspection, syntax check, filesystem test, offline/mock UI test, native database integration, live HTTPS browser flow, or target-host verification. Historical reports are not current test runs. No assertion counts without named results. Screenshots of mockups are not screenshots of a deployed application.

Compare Wix and Squarespace using current primary documentation with source/date/plan scope. Sell the actual service offered; do not copy unsupported competitor claims from generated artwork. Do not promise unlimited features, guaranteed rankings, or higher conversion without evidence.

Do not commit `config.php`, credentials, private storage, client uploads, database dumps, tokens, or host-private diagnostics. Live payments and customer messaging require configured accounts and appropriate authorization. A docs commit is not deployment authorization.

## End every session

Commit the appropriate documentation with the work, verify it exists on the intended branch, and report the commit plus what did and did not change. Leave an actionable `SESSION_HANDOFF.md` so the next session does not ask the owner to repeat already documented requirements.
