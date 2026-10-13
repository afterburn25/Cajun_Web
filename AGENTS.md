# Working agreement for CajunWebPro

**Current release — 2026-10-13:** [v1.6.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.6.0) is published and downloaded-back verified at `e5d4c17456faff0522429ab3f58093a06a523bd5` through [PR #18](https://github.com/afterburn25/Cajun_Web/pull/18). Bounded M2: owner-only Accept.js sandbox trials, durable single-attempt requests, safe reporting/reconciliation and shared manual/catalog/request locking. Sandbox states never pay real invoices. Production/client checkout, webhooks, receipts/refunds and recurring billing remain disabled or incomplete. [Sandbox guide](docs/SANDBOX_PAYMENTS.md). Exact-main: 296 native and 421 HTTP/Chromium checks; eight assets/tag verified. Gateway and tokenization tests are synthetic, not real merchant acceptance. No live deployment/customer/provider operation. Devin PR #7 is preserved. Next: actual sandbox acceptance using securely configured credentials, verified signed events and client/design checkout with a real-money ledger before production enablement. Full 20i/mail/ZIP/SSH remain pending. [Devin handoff](docs/DEVIN_HANDOFF_1.6.0.md).

**Previous 1.5.0 checkpoint (superseded) — 2026-10-13:** [v1.5.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.5.0) is published and downloaded-back verified at `3e95bdf81ca0126d99a69c41f2ff6eb0c08da1e1` via [PR #16](https://github.com/afterburn25/Cajun_Web/pull/16). Bounded M2 preparation: owner-only protected separate Authorize.net credentials, sandbox-authentication diagnostics, reviewed invoice requests with expiry/revocation/replacement and strict audit. Card collection, production connections and transaction processing remain disabled. [Payment guide](docs/PAYMENT_PREPARATION.md). Exact-main acceptance: 227 native and 401 HTTP/Chromium checks; all eight assets/tag verified. Provider results use synthetic fixtures, not real merchant authentication. Earlier releases and Devin PR #7 remain unchanged. No live deployment/customer/provider operation. Next: tokenized sandbox payment attempts, verified events and reconciliation before actual design-only checkout; full M2/20i/mail/ZIP/SSH remain pending. [Devin handoff](docs/DEVIN_HANDOFF_1.5.0.md).

**Previous 1.4.0 checkpoint (superseded) — 2026-10-13:** [v1.4.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.4.0) is published and downloaded-back verified at `0cdea7fbd17e887d3f4bc0574a411b157431b7df` through [PR #14](https://github.com/afterburn25/Cajun_Web/pull/14). Bounded M1 catalog/billing groundwork: approved service SKUs and exact USD prices, private costs, public configured cards, price revisions, reviewed immutable snapshots on existing invoices, duplicate prevention and audited manual paid/void transitions. [Catalog guide](docs/SERVICE_CATALOG.md). Exact-main acceptance: 165 native and 345 HTTP/Chromium checks, all eight retained assets/tag verified. Earlier releases and Devin PR #7 remain unchanged. No live deployment or payment/provider operation. Next bounded work: M2 Authorize.net sandbox settings and payment-request foundation, followed by verified design-only checkout. Broader catalog/order/tax/subscription/20i/mail/ZIP/SSH work remains incomplete. [Devin handoff](docs/DEVIN_HANDOFF_1.4.0.md).



**Previous 1.3.0 checkpoint (superseded) — 2026-10-13:** [v1.3.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.3.0) is published and downloaded-back verified at `7856cccf230a1646d02a4a92ad4bfb1c280efa19` through [PR #12](https://github.com/afterburn25/Cajun_Web/pull/12). A-09 is delivered for the current client project portal: explicit client/staff permissions, separate expiring support identity, persistent banner/return, attributed strict audit and sensitive-action denials. [Client support guide](docs/CLIENT_SUPPORT.md). Exact-main acceptance: 50 original + 31 staff + 35 support native and 289 HTTP/Chromium checks; eight retained assets and immutable tag verified. Earlier releases and Devin PR #7 remain unchanged. No live deployment/provider operations. Next bounded platform work: M1 product/price and billing groundwork before M2 Authorize.net checkout; multi-account mail, 20i, website ZIP delivery and A-10 VPS SSH remain roadmap work. [Devin handoff](docs/DEVIN_HANDOFF_1.3.0.md).


**Previous 1.2.0 checkpoint (superseded) — 2026-10-13:** [CajunWebPro v1.2.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.2.0) is published and downloaded-back verified at `f85627e05b171e4ee8508231e2ed6ee7b9374274` through [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10). Core A-05 administrator accounts are integrated; [administrator guide](docs/ADMIN_ACCOUNTS.md). Exact-main acceptance passed 50 original + 31 staff native and 216 HTTP/Chromium checks; all eight release assets, extracted bytes and tag target match the tested build. v1.1.0 remains immutable. No live deployment or provider operations. The owner resumed the roadmap after 1.1.0, superseding that task's older stop instructions. Devin PR #7 remains at `55d6067`; [updated handoff](docs/DEVIN_HANDOFF_1.2.0.md) preserves its ownership. Next bounded platform work: A-09 audited client-support sessions using the existing staff/client identities; payments, 20i and mail remain tracked. Publication records are documentation-only and do not alter release bytes.


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
