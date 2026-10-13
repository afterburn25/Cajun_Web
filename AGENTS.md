# Working agreement for CajunWebPro

**A-09 implementation checkpoint — 2026-10-13:** [PR #12](https://github.com/afterburn25/Cajun_Web/pull/12), branch `codex/client-support`, candidate v1.3.0. Implementation `0ea3e5b` adds explicit support permissions/client policy, separate bound sessions (15-minute maximum, five-minute idle), banner/return, scoped forms, real-actor writes/download audit and sensitive-action denials. The first GitHub run passed 50 original + 31 staff + 35 support native checks; packaging exposed an LF/CRLF installer hash mismatch, now corrected, with pipeline failure propagation tightened. Actual 1.2.0 upgrade and HTTP/Chromium support tests are added and pending execution. Published v1.2.0 and Devin PR #7 remain unchanged. Next: pass complete candidate acceptance, review screenshots, then checked merge/exact-main acceptance and verified release. No deployment.


**Active A-09 increment — 2026-10-13:** owner requested "Continue With Roadmap" again. Starting main `c09a15c010449e8401aaadce81e9ac34c0f6c04c`, published v1.2.0 unchanged. Codex claims `codex/client-support`: explicit support permissions and client restrictions, separate bound/expiring support sessions, portal banner/return, attributed message/upload/download audit and sensitive-action denials, with additive schema/setup and actual native/browser/package acceptance. Own support adapter/UI and necessary existing staff/portal/workspace/installer/test integration. Devin PR #7 remains `55d6067`; its portfolio paths are untouched. No live hosting/customer/provider operations. Candidate implementation and tests are next; no new release yet.

**Current release — 2026-10-13:** [CajunWebPro v1.2.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.2.0) is published and downloaded-back verified at `f85627e05b171e4ee8508231e2ed6ee7b9374274` through [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10). Core A-05 administrator accounts are integrated; [administrator guide](docs/ADMIN_ACCOUNTS.md). Exact-main acceptance passed 50 original + 31 staff native and 216 HTTP/Chromium checks; all eight release assets, extracted bytes and tag target match the tested build. v1.1.0 remains immutable. No live deployment or provider operations. The owner resumed the roadmap after 1.1.0, superseding that task's older stop instructions. Devin PR #7 remains at `55d6067`; [updated handoff](docs/DEVIN_HANDOFF_1.2.0.md) preserves its ownership. Next bounded platform work: A-09 audited client-support sessions using the existing staff/client identities; payments, 20i and mail remain tracked. Publication records are documentation-only and do not alter release bytes.


**Release gate satisfied, 2026-10-13:** [v1.1.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.1.0) is published at `3703783e979abab38e8d72e9092c51dc835833b3` and all downloaded assets verified. Read the current exact-release handoff at [docs/DEVIN_HANDOFF_1.1.0.md](docs/DEVIN_HANDOFF_1.1.0.md). Earlier waiting/recovery assignments below are historical. Codex finishes publication records, the authorized issue #2 handoff and issue #4 closure, then stops. Devin's existing work is preserved; notification/receipt/running status must not be inferred from a GitHub comment.

**Owner priority override, 2026-10-13:** finish and verify the existing 1.1.0 website/client workspace, merge actual source, publish and download-verify its GitHub Release, then hand off issue #2 and stop. Completion branch `codex/release-1.1.0` preserves application `90ba4f5`, main `b28a67c` and Devin PR #7 at `55d6067`. Codex owns release fixes/tests/integration/publication; original branches remain intact. Later roadmap work is paused. The user authorized repository changes, isolated tests, PRs, checked merges, publication and the GitHub handoff, not live hosting/customer/provider operations. This overrides earlier parallel-work timing below.

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
- Preserve `app/` and `assets/` organization. A deployment ZIP must extract the required entry points directly into the intended website directory, with no wrapper folder and no chain of patches.

## Verification and release discipline

The owner authorizes GitHub testing, incremental application releases with retained download copies, and continuously updated documentation for other agents. Follow `docs/RELEASE_WORKFLOW.md` and maintain `docs/RELEASES.md`. Use GitHub Actions for isolated tests, keep ready verified work on `main`, and publish tested complete packages as versioned GitHub Release assets. Preserve previous versions and never move published version tags. The current 1.1.0 release has recovered, verified source; subsequent releases still require application acceptance, not documentation CI alone. This standing direction does not authorize live deployment, charges or customer mail by itself.

Test the exact artifact delivered. Application source, embedded installer dependencies, version labels, direct installer, and packaged installer must agree. Regenerate dependency hashes whenever shipping files change. Do not reintroduce the old key-based or multi-step installer through a stale copy.

Label evidence accurately: source inspection, syntax check, filesystem test, offline/mock UI test, native database integration, live HTTPS browser flow, or target-host verification. Historical reports are not current test runs. No assertion counts without named results. Screenshots of mockups are not screenshots of a deployed application.

Compare Wix and Squarespace using current primary documentation with source/date/plan scope. Sell the actual service offered; do not copy unsupported competitor claims from generated artwork. Do not promise unlimited features, guaranteed rankings, or higher conversion without evidence.

Do not commit `config.php`, credentials, private storage, client uploads, database dumps, tokens, or host-private diagnostics. Live payments and customer messaging require configured accounts and appropriate authorization. A docs commit is not deployment authorization.

## End every session

Commit the appropriate documentation with the work, verify it exists on the intended branch, and report the commit plus what did and did not change. Leave an actionable `SESSION_HANDOFF.md` so the next session does not ask the owner to repeat already documented requirements.
