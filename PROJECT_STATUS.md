# CajunWebPro current status

## Verified populated v1.25 upgrade and supported runtime coverage — 2026-10-28

The real populated upgrade rehearsal at source `3fd87727ebafaa9290f03e37c97043f9e029cf75` passes all **268 checks** in [run 40783809553](https://github.com/afterburn25/Cajun_Web/actions/runs/40783809553). Downloaded artifact 12859025835 has verified SHA-256/CRC/member hashes; CI checkout `b789ca9697d9349208645890c234e5609a56d999` and source share tree `b7d9bd101bf63e6f3c80817f2b851ad63de631d8`. Each root/subfolder × rewrite/no-rewrite scope passes 67 checks on actual PHP8.3.6/MariaDB10.11.19. All four installs verify 2,894 published files and actual OPCache residency with timestamp checks disabled. Populated business rows, encrypted provider/mail keys, inventory approvals, unknown operations, submission fences, access checks, interrupted update, recovery and reapplication survive. [Audited retained success evidence](docs/evidence/published-125-upgrade-success.json).

The same dedicated gate now selects PHP8.2/8.4 × MariaDB10.11/MySQL8.4, reusing the maintained Apache workflow's exact signed-package-source and runtime setup. Each lane verifies actual CLI/extensions/database through the existing environment probe and actual Apache PHP/SAPI separately for all four installation scopes. Wrong or missing runtime evidence stops before fixture population. Artifacts include lane identity and environment reports with distinct names. All existing 268 acceptance assertions remain; four HTTP runtime checks are added. The matrix is **prepared, awaiting hosted results**, not inferred from the PHP8.3 success. No full Application rerun is manually dispatched.

Before this extension, the strengthened offline suite reproduced six failures and one error, including hard-coded PHP8.3 and unverified runtime. All 15 methods now pass on PHP8.2.35/8.4.25; existing environment contracts, Python syntax, all99 workflow YAML parses and every gate shell block (`bash -n`) pass. These local contracts replace process/HTTP/SQL boundaries and do not claim real matrix acceptance. [Bounded matrix preparation evidence](docs/evidence/published-125-upgrade-runtime-matrix.json).

Version1.26.0 remains a provisional upgrade candidate, not an approved release. This branch excludes the independent #285/#286/#287/#289 financing/domain corrections. Required combined/main acceptance, independent approvals, owner-selected release version, real sandbox credentials and final package/release verification remain separate. No real customer operation, payment activation, merge, release or deployment occurred. Earlier failed-run records below remain preserved and are superseded only for this exact PHP8.3/MariaDB upgrade candidate.

## Published package diagnostic version identity — 2026-10-28

The separate extraction diagnostic `tests/release_next_from_125.py` required a repository-root `VERSION` member that is absent from the immutable published COMPLETE-UPLOAD package. Exact local execution on the digest-verified 2,895-member archive reproduces that false rejection. It now reads the single deployed `CWP_VERSION` declaration from `config/defaults.php`; the existing pinned asset digest/size and exact `1.25.0` checks remain required. A mismatching root `VERSION` file cannot override runtime identity, and missing or ambiguous declarations remain invalid.

The actual published archive now identifies as 1.25.0. Five synthetic archive cases cover the deployed layout, wrong version, misleading root file, absent runtime constant and ambiguity. All 13 harness methods pass on PHP8.2/8.4, alongside Python syntax, YAML, documentation/links and exact staged-byte checks. This remains an extraction diagnostic, not successful populated upgrade, release or deployment evidence. [Before/after actual-archive proof](docs/evidence/published-125-diagnostic-version.json).

## Published-v1.25 inventory scope follow-up — 2026-10-28

Run [40782764921](https://github.com/afterburn25/Cajun_Web/actions/runs/40782764921) passes the corrected version/schema guard, then its synthetic inventory reader is refused. The published v1.25 application requires the exact account/operation scope; this older fixture called the projector with an empty default scope. The v1.25 fixture now supplies `twenty-i|<profile_id>|packages.list`, retaining the legacy v1.24 scope and the real production security check. Failure messages include only the finite result state for diagnosis.

The actual fixture closure, using projection classes proven byte-identical to the immutable published package, reproduces both account-scope failures before correction. All 12 contract methods pass afterward on PHP 8.2.35 and 8.4.25, including a second profile identity and the unchanged legacy case. PHP lints, Python syntax, documentation/links, whitespace and exact staged bytes pass. This is test-only correction and local projection evidence; full Linux/Apache populated upgrade/recovery remains required. [Exact run and retained original failure](docs/evidence/published-125-upgrade-scope-followup.json).

## Published-v1.25 inventory fixture follow-up — 2026-10-28

Corrected root-context run [40781572702](https://github.com/afterburn25/Cajun_Web/actions/runs/40781572702) passes installation, actual owner login, encrypted provider settings and history seeding, then fails because the reused inventory fixture demands the published-v1.24 schema. Independently downloading and hashing the immutable v1.25 package confirms schema **5** and populated `operation` context. The fixture now requires the declared published runtime/schema pair (1.24/schema3 or 1.25/schema5) before seeding, preserves every 1.25 operation value in exact snapshots, and retains the older default 1.24 migration checks. The caller supplies the version only for 1.25. Existing upgrade, authorization, recovery and retained-data assertions remain unchanged.

The identical 11-method contract suite reproduces seven original failures and passes with the correction on PHP8.2.35 and8.4.25. The actual fixture PHP fragments execute in both runtimes, covering wrong-baseline/schema refusal and exact operation-context preservation. PHP lint, Python syntax, YAML, documentation and exact staged source checks pass. A local published-installer attempt verified all2,894 files but correctly refused Windows permissions; its failed evidence is retained and does **not** count as native/HTTP upgrade acceptance. The disposable HTTP process and database were stopped without deleting test data. Shipping bytes are unchanged by this follow-up; fresh Linux/Apache hosted upgrade remains required. [Run identity, original failure and bounded evidence](docs/evidence/published-125-upgrade-inventory-followup.json).

## Actual published-v1.25 root upgrade follow-up — 2026-10-28

The first actual rehearsal [run 40779590487](https://github.com/afterburn25/Cajun_Web/actions/runs/40779590487) failed after successful root installation and two passing checks. Its installer report confirms private storage is outside the real Apache web root. The CLI seeding fixture already accepts a validated explicit document-root argument, but the root caller omitted it and inherited the historical parent-directory default. The root caller now passes the real boundary; subfolder calls and all private-storage checks remain unchanged. The new exact-command regression fails before this correction and all eight harness tests pass afterward. This is a test-context correction, not successful populated-upgrade acceptance; the corrected hosted run remains required. [Retained run identity, artifact hashes and first failure](docs/evidence/published-125-upgrade-first-run.json).

## Independent published-v1.25 upgrade rehearsal — 2026-10-28

This isolated draft candidate starts from frozen integration `3789bb50c90cb836530f2d5dc2400cee394763d0` and uses **provisional test version 1.26.0** solely to exercise the existing populated published-v1.25 upgrade/recovery gate. It does not select or publish the final commercial release. It contains no independent #285/#286/#287 corrections; their review/incorporation and exact combined acceptance remain separate.

Shipping changes are synchronized version constants and canonical generated bytes only. The upgrade harness now supplies its required isolated OPCache settings, exercises root/subfolder with rewriting on/off, preserves failure evidence and refuses populated runtime resets. Every existing populated-data/auth/recovery assertion remains unchanged. Seven offline harness tests, PHP 8.2/8.4 lint, canonical refresh, 69 build guards, Python/YAML and documentation checks pass; actual PHP 8.3/MariaDB hosted upgrade acceptance and the full supported matrix remain pending. [Scope and evidence](docs/CODEX_V125_UPGRADE_REHEARSAL.md).

Current Application run 40767087342 remains preserved. No full Application rerun is manually dispatched for this rehearsal. Actual sandbox credentials, independent review, branch protection, approved final version, final combined/main acceptance and release verification remain outstanding. No gateway activation, charge, deployment, merge into an owned branch or publication is performed.

## 2026-10-28 — current Application failure isolated and focused repair prepared

Exact PR #266 head `d062bd5c` Application run `40728300754` is terminal. Build, recovery, Apache and native database phases passed; the installed browser phase recorded 2,997 passing checks before its first exception. Job `122971621925` timed out at the unchanged 30-second Playwright limit loading the StackCP-focused provider-settings URL. Checkpoint artifact `12850372335` and its PHP server log prove repeated 27–31 second responses: the page still rendered every unrelated provider workspace and reprojected their accumulated histories. The integration candidate now scopes bounded StackCP pagination to the provider account/inventory plus both StackCP sections, preserves focused redirects, and adds installed assertions that unrelated control workspaces are absent. Authentication, CSRF, history validation and timeouts are unchanged. Python syntax, whitespace and 69 build guards pass locally; exact hosted PHP/database/browser execution is mandatory. [Run review](docs/CODEX_PR266_APPLICATION_40728300754.md).

## 2026-10-28 — full-browser session-isolation repair prepared

Frozen Application run `40705973907` passed build, Apache and recovery, and its native/browser job passed the native database phase before the browser process exited at `Form missing catalog_save ... view=login`. Complete job logs show every preceding support, catalog, provider and licensing check passed; the next software-commerce action borrowed the bootstrap owner cookie after the complete matrix had exceeded the application's real 30-minute idle limit. The integration candidate now gives both the software-commerce and immediately following financing suites fresh, suite-owned owner/buyer/foreign-client sessions through the real login forms. The production idle timeout and authorization checks are unchanged. Python syntax, whitespace, 69 build guards and 283 artifact-helper checks pass locally; exact hosted HTTP/Chromium execution remains required. Current PR #266 Application run `40728300754` is still preserved in progress and was not duplicated or cancelled.

## 2026-10-28 — exact feature `d062bd5c` refresh prepared without moving CI

PR #266 advanced from `5326ecb8` to exact head `d062bd5c3b78f3ec265ffc8a404e253013cfa924`. Its only shipping change is Devin's direct `mail_drafts_account()` repair and generated inventory update; the frozen integration candidate already contains the same repair plus the independently reviewed sender authorization regressions. Prepared merge `ec6549713190c6a325c0ad4dd3e97f8885eb4053` has both `bcfc2b5d` and `d062bd5c` as parents and preserves the combined inventory. The protected ref remains at `74b5af3a` while Application run `40705973907` finishes; no duplicate workflow was dispatched and Devin's branch was not modified.

## 2026-10-28 — feature `5326ecb8` payment-upgrade fixtures reviewed

PR #266 advanced by one test-only commit while exact integration candidate `74b5af3a` was in protected CI. The new feature delta prepares payments schema 2 through the real owner `payments_migrate` action before later payment-gated checks in cumulative upgrade fixtures. The integration candidate already uses the stronger retained-record helper for published 1.5/1.6/1.7 and the published-v1.24 continuation; the compatible union adds the missing current-install and 1.8/1.9/1.10 preparation without replacing those preservation checks. No shipping or generated-inventory bytes change. The running frozen acceptance remains preserved; refresh to exact feature ancestry will occur only after it is terminal.

## 2026-10-28 — feature `2c0579c4` frozen without overwriting integration fixes

GitHub advanced PR #266 from reviewed `93bfa54f` to exact head `2c0579c4f1379505b1fb8a53c3f90905d3e5dcdc`. Its shipping correction explicitly loads the software-commerce migration before three studio lifecycle readiness guards; those exact controller bytes were already present in integration head `03c4f606`, so recording the new feature ancestry introduces no shipping-tree conflict. The feature-only generated inventory is not copied over the integration inventory, which also covers the independent product-validation and finance-mail corrections. Devin's branch remains untouched.

## 2026-10-28 — high-severity product-validation correction

Independent review found that a new software-entitlement key could validate without a `product_id`; the existing mismatch check ran only when the caller supplied that optional field. A customer could therefore reuse a valid key/domain for one product when activating different software on the same domain. Focused PR #281 (`14008290`) requires product identity for rows in `software_entitlements`, retains exact matching, binds master software keys to their product, and preserves the legacy website-key request shape for licenses without an entitlement row. Native and installed regressions accompany the shipping correction; local source/artifact guards pass, while hosted PHP/database/HTTP execution and independent approval remain required. [Focused evidence](docs/CODEX_SOFTWARE_PRODUCT_VALIDATION_REVIEW.md).

## 2026-10-28 — populated Apache upgrade warning reproduced

Protected Application run `40684017215` passed 2,747 Apache protection/root/subfolder/installer checks, then its immutable published-v1.24 (`7190b582`) root-with-rewrite upgrade to the development v1.25 candidate passed all 158 functional checks and both browser screenshots before the Apache log gate found `Undefined variable $mail_drafts_account` five times. The finance-agreement sender list used an accidental variable function; the surrounding catch hid the broken sender choice while the warning correctly failed acceptance. Focused PR #282 (`d40c3671`) calls the already-loaded `mail_drafts_account()` service directly and adds a source regression. Exact combined Apache and Application reruns remain required. [Evidence](docs/CODEX_FINANCE_MAIL_DRAFT_APACHE_REVIEW.md).

## 2026-10-28 — financing email sender correction pending integration

Current `03c4f606` Application Apache acceptance passes 2,747 protection checks, then its populated published-v1.24-to-development-v1.25 continuation detects an undefined `$mail_drafts_account` callable in the licensing view. The focused correction invokes the existing authorized account function and adds real sender-picker/local-draft acceptance. All 36 targeted HTTP/PDO checks pass across PHP 8.2/8.4 × MariaDB/MySQL, with four original failures preserved. Full combined hosted acceptance remains required; this is not main/release completion. See [failure, scope and evidence](docs/FINANCE_MAIL_SENDER_REVIEW.md).

## 2026-10-28 — attributed support-denial acceptance

PR #274 advanced to `617e67cb` after reproducing the next unreached fixture error: a forbidden software POST to the private support software view correctly returns HTTP403, so that response cannot also expose the ordinary blocked-action message. The installed test now posts the forged mutation through the ordinary support portal, requires HTTP200 plus the refusal message and exactly one attributed denied support event, and separately preserves the private software/download 403 checks. Support readiness also requires both schema and explicit policy confirmation. Shipping permissions and handlers are unchanged.

## 2026-10-28 — feature `af377c89` reviewed; regression excluded

PR #266 advanced to `af377c89` after installed run `40676646028` passed the notice, purchased-download and second-installation assertions, then failed a page-wide seat-control assertion. The feature attempted to hide add-install controls from every invoice-unbound trial, temporary and owner-issued standard grant. Independent real HTTP/SQL review showed that change prevents a customer from redeploying an otherwise valid license after deactivating its last domain. The integration history records Devin's exact head but retains the prior authorized renderer; Devin's branch is untouched.

Focused PR #274 scopes the installed assertions and domain-move selector to the exact license while proving an independent trial keeps its own authorized control. PR #275 replaces a colliding fixed provider-settings CI port with the Actions-assigned service port. PR #276 explicitly prepares published-1.24 payment schema1→2 through the real owner form before creating current recurring fixtures, retaining original records/private files and disabled checkout. These corrections change tests/workflow only; fresh combined hosted evidence is required.

## 2026-10-28 — installed lifecycle acceptance corrected

Focused PR #273 showed that the final installed software block searched for legacy website deactivate/reactivate forms that are intentionally absent for software entitlements. The integration candidate now uses the real `software_license_lifecycle` suspend/resume forms, supplies their required reasons, and proves a forged legacy action cannot bypass the explicit lifecycle controls. Shipping lifecycle code is unchanged. The correction retains the existing portal state, download denial/recovery, SQL state and key-preservation assertions. Independent focused execution reports 48 passing HTTP/SQL checks across PHP 8.2/8.4 × MariaDB/MySQL; full exact-candidate hosted execution remains required.

## 2026-10-28 — refreshed to Devin feature head `cb916c07`

Exact integration run `40667109804` passed the installed software flow through setup, checkout, tenant isolation, CSP, trial-to-paid upgrade and fulfillment, then failed at the first owner delivery retry because `software_notices.php` called `software_commerce_ready()` without declaring its migration dependency. Devin repaired that first cause at `62a1b601`, refreshed the generated inventory, and also corrected purchased-download ordering so an invoice-bound entitlement is selected ahead of an owner-issued entitlement for the same asset. The existing installed assertion requires one link bound to the exact paid license plus `purchase #<invoice> · unit 1`.

`codex/licensing-integration-review` now normal-merges exact feature head `cb916c07`; Devin's branch remains untouched. The merge conflict was limited to generated inventory and was resolved from the exact combined shipping bytes. Locally available helper checks pass (283 artifact cases and 69 build rejection cases). PHP/native/browser execution is unavailable in this workspace, so fresh exact-head hosted execution remains mandatory. The preceding exact head otherwise recorded success for software native/policy/gateway/package/security gates, financing installed acceptance, recurring agreements, WordPress installation and domain-renewal dispatch; those parent results diagnose regressions but do not certify this refresh.

## 2026-10-28 — refreshed to Devin feature head `9a1e9451`

PR #266 advanced from `726fc7db` through the software admin dispatcher repair and generated inventory to `9a1e9451`, which adds the canonical workspace import to the installed software-commerce fixture. The separate integration lane normal-merges that exact head; Devin's branch remains untouched. The integration already contains the compatible dispatcher correction plus stricter owner-only prefix authority and readiness imports, so only the non-duplicated fixture change enters the combined tree. Fresh exact-head hosted execution is required.

## 2026-10-28 — owner software administration routing correction

Independent real-router reproduction found two application defects on the combined candidate: every `software_*` administration POST fell through to `Unknown administration action`, and whole-license deactivate/reactivate called `software_commerce_ready()` before loading its definition. The integration lane now routes software posts through the existing studio controller, assigns the whole prefix explicit owner-only `staff.manage` authority, and loads the commerce readiness boundary before the lifecycle checks. A fast source contract and the existing real-form installed acceptance cover the repair; hosted PHP and installed execution on the new exact head remain required.

CI review found that PRs targeting the licensing feature branch did not start the full Application acceptance or Software focused installed acceptance workflows. Both existing suites now admit that integration base, with the focused paths including the repaired dispatcher and permission boundary. Application acceptance retains its protected per-SHA, non-canceling evidence policy; no manual duplicate dispatch is required.

The three maintained package workflows had the same integration-base gap. Source integrity, fast direct-extraction/UPDATE audit, and full FULL-PROJECT/COMPLETE-UPLOAD/UPDATE verification now run for the licensing integration PR. These candidate checks do not replace the still-deferred populated published-v1.25-to-next-version gate while VERSION remains `1.25.0`.

The remaining software-only syntax, native commerce, amount, CSP, reversal, subscription-lifecycle and webhook contract workflows were also push-only and are not all subsumed by Application acceptance. They now admit the licensing integration PR so exact-candidate evidence does not depend on older Devin-branch results.

Feature run `40664435482` then reached the shared software browser fixture and failed sandbox setup because the CLI fixture loaded payment functions without the canonical workspace dependency that defines `studio_ready()` and support-session state. The fixture now imports `app/clients/workspace.php` after commerce migration, matching the real application bootstrap boundary. This is test-harness repair only; it does not enable gateway traffic or weaken payment readiness.

## 2026-10-28 — customer domain cooldown bypass correction integrated

Focused PR #272 reproduced a customer restriction bypass: with a one-install entitlement and 24-hour cooldown, replace A with B, deactivate B and register C could succeed when the replacement and standalone deactivation shared the same second-resolution timestamp. The combined integration candidate now uses audit IDs already serialized by the license lock to distinguish event order while retaining timestamp-based cooldown expiry, same-domain recovery and owner overrides. The exact #272 source, 12 native cases and focused four-lane workflow are merged into the review lane; fresh combined-head execution remains required.

## 2026-10-28 — refreshed to Devin financing 404 repair

PR #266 advanced from `36721397` to `726fc7db` with a focused customer-financing error-path correction and its generated inventory hash. The separate integration lane normal-merges that exact feature head: an unavailable or foreign finance agreement now resolves before layout output and returns the intended HTTP 404 without a headers-already-sent warning. Installed tenant-isolation acceptance now requires that 404 plus the existing private no-store/no-referrer headers, rather than passing on absence of agreement text alone. Devin's branch remains untouched. Combined exact-head hosted acceptance is required; no prior parent run certifies this refresh.

## 2026-10-28 — interrupted financing setup recovery

Independent migration review found that a process stop after financed-license, finance-agreement or finance-gateway `CREATE TABLE` but before the version commit left an empty table that every retry rejected as unrecognized. Each owner-gated setup now writes an audited pending marker before DDL, resumes only its recognized still-empty partial setup, validates the completed schema and clears the marker with the version commit. Unmarked tables and partial tables containing data still fail closed for owner review. Native regression coverage simulates one interrupted DDL in all three layers; exact hosted PHP/database execution remains required.

## 2026-10-28 — high-severity payoff/owner-control correction

Independent source review found that a paid-off financing plan blocked every owner whole-license deactivation and validation silently reactivated an inactive paid-off key. Payoff must permanently remove only financing suspension, not owner authority for fraud, breach or unauthorized resale. The integration candidate now preserves explicit owner deactivation, keeps the paid-off financing verdict immutable, and permits deliberate reactivation of the same key. Native and installed-browser regressions are updated; hosted execution remains required. Domain binding and default no-resale/no-distribution rights are unchanged.

## 2026-10-28 — combined PR266 integration candidate refreshed

`codex/licensing-integration-review` now contains Devin feature head `726fc7db`, prior review head `25e2d34f`, optional-finance routing correction #269 and delayed-lifecycle correction #270. The support forgery browser case posts through the real support action dispatcher and requires one attributed denial plus unchanged agreement/payment state. The combined payoff regression moves Devin's single validation-rate-window wait ahead of all six post-payoff probes, preserving the production rate limit without adding a second delay. Independent package review also rejects noncanonical and case-colliding customer software ZIP members before publication. Canonical installer generation, 283 artifact-helper cases, 69 build rejection cases, documentation and Python syntax pass locally; PHP/native/browser execution is unavailable in this workspace and remains a hosted gate. Exact refreshed-head acceptance is required. No merge, release, live charging or deployment.

## 2026-10-28 — native SQL evidence for the independent corrections

The continuing independent review adds 40 actual PDO/InnoDB cases to the existing recurring/financing native lane. All 40 pass locally on PHP 8.2/8.4 with MariaDB 10.11.19 and MySQL 8.4.13, including audit rollback and an independently connected worker re-reading a replacement fence after waiting for the account mutex. Before-fix archives reproduce both defects against real SQL. Correction commit `168c4999` also passes the complete hosted 588-check native matrix and 55 focused regressions; those runs predate these 40 new cases. The complete Windows suite stops later at the unchanged required 0600 private-file boundary (209 checks passed); installed acceptance remains required. No shipping code or production safeguard changes. [Evidence and scope](docs/CODEX_LICENSING_REVIEW_2026_10_28.md).

## 2026-10-28 — independent delayed-financing-event correction

Branch `codex/licensing-lifecycle-ordering`, stacked on [routing correction #269](https://github.com/afterburn25/Cajun_Web/pull/269), preserves cancelled, reversal-recovery and replacement states under delayed gateway lifecycle events, and retains confirmed suspension after a delayed failure. The real-service regression has 45 passing isolated cases (17 failed before the fix). Both corrections remain proposed for the existing integration writer; native/gateway/installed acceptance is still required. [Review and evidence](docs/CODEX_LICENSING_REVIEW_2026_10_28.md).

## 2026-10-28 — independent licensing webhook correction

Branch `codex/licensing-webhook-isolation` prepares two focused fixes for PR #266: optional financing no longer queries absent finance tables for software captures or requires financing production activation for unrelated software refunds/voids. Ten actual-service regression cases pass locally; broader acceptance, sandbox and release gates remain open. The owner retained the separate **Complete Licensing Integration** chat as integration writer. [Exact source, findings, evidence and handoff](docs/CODEX_LICENSING_REVIEW_2026_10_28.md). Not merged, released or deployed.

## 2026-10-28 — financed replacement recovery hardening

The Codex companion now also repairs replacement enrollment after the original start date and requires verified terminal retirement of the prior ARB before creating parallel billing. Policy and synthetic gateway regressions pass; full installer refresh passes. Hosted native/browser and independent security/real-sandbox gates remain open. [Exact evidence and current gates](docs/PR266_INTEGRATION_ACCEPTANCE.md).

## 2026-10-28 — PR266 independent correction candidate

Owner assigned Codex a separate licensing/financing integration pass on `codex/licensing-integration-review`, refreshed to Devin parent `6045b801`. Preserves Devin’s project-isolation fix; adds installed independence assertions, a dynamic document-roots CI port, and narrowly filtered companion-PR acceptance. Local checks pass; hosted candidate acceptance and security/release gates remain pending. [Exact failures, evidence and remaining gates](docs/PR266_INTEGRATION_ACCEPTANCE.md). No production activation or deployment.

## 2026-10-26 - CI queue-hygiene repair (in flight)

Branch `devin/ci-concurrency-hygiene`: all 34 superseded-run-blocking workflows audited; 32 development checks regrouped from per-`github.sha` concurrency (which made `cancel-in-progress` a no-op) to per-PR/per-branch groups with main-exempt cancellation; two unguarded checks gained the same block. `tools/ci_health.py` + weekly `ci-health.yml` + `docs/CI_HEALTH.md` added. Protected release/acceptance workflows unchanged. Backlog root cause was sha-scoped groups + a ~35-workflow-per-push lane against a ~4-5-slot account runner ceiling, not a GitHub outage. Not merged; no release or deployment implied.

## 2026-10-22 - merge-first delivery cycle begins

Per the standing merge-first directive (AGENTS.md), completed work is delivered to `main` in small accepted increments. First increment: **PR261** merges the accepted 20i reset-email-template source `467ec1bd` (original full-green run [39422487199](https://github.com/afterburn25/Cajun_Web/actions/runs/39422487199)) into main — candidate merge `e334678e`, hosted acceptance and independent review in progress. Integration queue: `docs/DEVIN_INTEGRATION_QUEUE.md`. No release or deployment is implied.

## 2026-10-19 — owner 20i reset email template controls

Separate branch `codex/twentyi-reset-email-template`, from main `a86f6f4f`, adds explicit reading, exact replacement review, one admitted save and separate provider readback for the StackCP password-reset email template. Encrypted history and current owner/account checks protect the flow; no email is sent. [Behavior, actual evidence and remaining gates](docs/TWENTYI_PASSWORD_RESET_TEMPLATE.md). Native and installed-browser acceptance remain pending; this is not a release.

Payment-link companion [PR253](https://github.com/afterburn25/Cajun_Web/pull/253) is pushed at `b1948eeb` with its first full acceptance queued. Separate renewal-invoicing PR252 and immutable published1.25.0 are preserved.

## 2026-10-19 - owner transfers the complete project to Devin

**Devin now owns all remaining 20i and non-20i implementation, integration, testing, review coordination and releases. The immediate priority is to review and merge completed work from both workers into main, then finish the remaining full roadmap independently.** [Full directive](docs/DEVIN_FULL_SITE_TAKEOVER.md) · [Devin start here](DEVIN_START_HERE.md) · [Current handoff](SESSION_HANDOFF.md).

Earlier instructions assigning 20i or releases exclusively to Codex and limiting Devin to non-20i are superseded. Existing quality, data-preservation, repository approval and live-operation gates remain. This is a documentation/assignment change only: it does not merge any application candidate, publish a new software release, deploy the site, stop an external worker or start a Devin session.

## 2026-10-18 — client cloud website journey

From main `e397d9a6e293850e8dff61a593e3ba39ee178400`, branch `codex/twentyi-client-cloud-websites` implements primary-client request/cancel, owner decline/explicit creation, retained outcomes and exact returned-package confirmation navigation. Client dispatch and the existing shared grant commit atomically; replay never sends again. The current intake requires a complete cloud observation within15 minutes. [Behavior, authority, evidence and remaining gates](docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md).

Local evidence:141 document and98 modeled UI checks per PHP mode; four actual module cold loads;34 legacy-context checks per mode;271 final artifact-helper checks;35 portable installed-update guards;19 actual published-baseline negative checks. Original1.24 and1.25 ZIP bytes validate against their retained member inventories, with all27 earlier release maps unchanged. Independent service, browser, installed-update and shared-wiring review is recorded in the feature guide. Native SQL/concurrency, packaged browser pixels, installed Linux/Apache execution and full combined acceptance remain pending. No live operation, new release or deployment; published1.25 and all separate original CI handles remain unchanged.

## Verified baseline at this handoff

- Main before this documentation change: `a86f6f4fa592e6f715a9420aec8de4622258642e`.
- Latest published release read from GitHub: [v1.25.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.25.0), source `b79c67110829126916e3a65e616191542448bccc`. Prior source/tag/assets remain unchanged.
- [PR216](https://github.com/afterburn25/Cajun_Web/pull/216) remains an open direct-hosting-catalog candidate at `f67b1631683b29096dd8ec1daee405e104dc0b83`. Its capture successor and numerous hosting, nameserver, runtime, StackCP, client and non-20i branches require current-source review and combined integration.
- No new completion percentage is established by this instruction update. Prior chat estimates are not audited project counters.

The [takeover inventory](docs/DEVIN_FULL_SITE_TAKEOVER.md) identifies initial PR families, concrete contacts/payment-link/VPS caveats, required acceptance and the complete remaining scope. Devin must refresh all source/CI/release states, build the dependency-based integration queue and merge only accepted useful slices. An old PR body may lag current job results; a passed parent cannot certify a successor.

## Immediate next actions

Create or safely reuse a Devin-owned main-based integration branch, classify all pending Codex/Devin work, resolve mature blockers and merge ready slices sequentially. Complete a verified incremental release with install/update ZIPs and actual screenshots before allowing another indefinite branch backlog. Then continue all remaining 20i and non-20i capabilities without waiting for Codex or a new continue instruction.

CI cleanup is not feature completion. Preserve the completed narrow queue-cleanup records and original required tests; do not replay old cancellation batches or redispatch queued work. Review and publication follow [the existing release workflow](docs/RELEASE_WORKFLOW.md).

## Preserved detailed history

The complete pre-takeover status and every dated source/test/queue checkpoint remain at the [exact previous main version](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/PROJECT_STATUS.md). Feature guides, [continuation evidence](docs/ROADMAP_CONTINUATION_2026_10_19.md), [1.25 publication evidence](docs/RELEASE_1.25.0_PUBLICATION.md), roadmaps, original failures and release assets remain intact. The current ownership above supersedes old allocation prose; it does not reinterpret historical results.
# 2026-10-28 licensing integration security correction

Independent forgery review found new license issuance truncated a 16-byte random value to 64 bits. The integration candidate now emits all 128 random bits while retaining compatibility with every existing shorter key. Exact-head native and installed acceptance remain required before merge.
