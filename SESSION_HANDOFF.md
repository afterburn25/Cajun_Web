## 2026-10-31 — isolate the remaining combined-baseline corrections

Owner request: continue all pending integrations. PR317 already combines backups/licensing/master portal/20i. The isolated baseline repair restores focused cloud rendering, updates obsolete session-only expectations, retains complete test evidence and adds the omitted domain-search suite. Local regressions pass; exact-source native/installed acceptance remains pending. Main, live settings and delivered1.26.1 archives are unchanged. [Correction, actual evidence and next groups](docs/COMBINED_BASELINE_REPAIR_2026_10_31.md).

## 2026-10-29 — keep software orchestration evidence inside its sealed partition

PR306 full run40821246468 passed283 helpers,69 build guards, six orchestration checks, packages and fixtures, then failed because its new standalone report was outside the build partition allowlist. The guard now appends to the existing build transcript with pipefail. Nine new actual-command/sealer regressions reproduce the failure and verify successful/failing evidence; all292 helpers pass, with original assertions and allowlists unchanged. Exact corrected full acceptance remains pending. [Failure and correction evidence](docs/CODEX_PR266_APPLICATION_40820683270.md).

## 2026-10-29 — preserve acceptance command order after software-suite repair

PR304 fixes the duplicate software-suite call, but its full run40820683270 failed helper check218 because the new orchestration guard interrupted the sealed native-command sequence. Isolated `codex/software-acceptance-guard-order` moves that unchanged guard to the build step. The parent reproduces217 passes/one failure; corrected source passes all283 helper checks and six orchestration checks. All production bytes and assertions remain unchanged. Exact corrected full acceptance and required reviews remain pending. [Evidence, source and ownership](docs/CODEX_PR266_APPLICATION_40820683270.md).

## 2026-10-28 — preserve reconciliation assertions across payoff integration

Independent `codex/finance-reconcile-payoff-fixtures` declares the unpaid payoff-reader dependency in the existing strict #287/#289 fixtures. Case/assertion tails and production code are unchanged. Original combined undefined-function failure is preserved; 448 pure and 375 native executions pass against the frozen parent and combined source. [Evidence and pending review](docs/CODEX_FINANCE_RECONCILE_FIXTURES.md).

## 2026-10-28 — preserve payments during initial and replacement enrollment

Independent `codex/finance-replacement-ledger-guard` from #300 `07fa7caf` prevents partial or nonconsecutive paid installments from being billed again by a newly created full ARB. Initial and replacement finalizers retain obsolete known candidates for verified cancellation; lifecycle/refund/void/settlement paths preserve pending retirement. Final PHP/database matrix passes 6,136 native executions and 306 focused executions. Initial cases include 30 actual separate-process payment commits per lane. Original replacement 37/230, pending-state 11/22, initial 6/44 and finalizer-rollback 4/22 failures remain retained. Hosted checks and independent review are pending; unknown replacement outcomes, established-ARB receipt audit, final combined acceptance, sandbox and main integration remain open. [Review and evidence](docs/CODEX_FINANCE_ENROLLMENT_RECEIPTS.md).

## 2026-10-28 — retain and retire a replacement created during payoff

Independent `codex/finance-replacement-payoff-guard`, based on frozen #299 `fa404e59`, retains the known new gateway ID/history and verifies its retirement when payoff arrives during replacement creation or first readback. It also repairs the shared payoff reader's transaction requirement for ordinary unpaid owner reconciliation. Parent fails 40/138; the corrected PHP/database matrix passes 1,896 native and 272 focused executions. Earlier failures remain retained. Hosted exact-head and independent review are pending; final combined acceptance, unknown-create/partial-receipt audit, sandbox credentials and main integration remain gates. [Review and evidence](docs/CODEX_FINANCE_REPLACEMENT_PAYOFF.md).

## 2026-10-28 — retire recurring billing after verified financing payoff

Independent `codex/finance-payoff-subscription-retirement`, based on frozen #298 `4405433d`, closes the post-reservation/manual-payoff billing gap. It retains the gateway identity before cancellation, requires exact contract and terminal readback, preserves uncertainty for owner/scheduler reconciliation, and never rolls back payment or permanent payoff. Parent fails 57/270; corrected native PHP/database matrix passes 3,132 total executions (1,344 new), plus 272 focused checks. Original failed probes and the isolated-owner test-harness correction are retained. Hosted exact-head and independent approval remain required; no main merge or release. [Review and evidence](docs/CODEX_FINANCE_PAYOFF_RETIREMENT.md).

## 2026-10-28 — initial financing enrollment rechecks authoritative payoff

Independent review of #297 identified stale projected state after a committed payoff and an unchecked profile enrollment path. Separate `codex/finance-enrollment-payoff-guard`, based on frozen `89ecee07`, centralizes reservation-time signed agreement/customer/schedule/initial-payment and installment-ledger admission. Parent fails 138/240 native assertions; corrected source passes 240 new plus 207 existing assertions per PHP/database lane (1,788 executions across four lanes). Valid sandbox enrollment retains exact current charge/attempt proof without fulfillment. Permanent payoff, charge, replacement and production gates remain unchanged. Hosted and independent review gates remain open; no main merge or release. [Review and evidence](docs/CODEX_FINANCE_ENROLLMENT_PAYOFF_GUARD.md).

# Session handoff - Devin sole-owner continuation

## 2026-10-28 — independent cloud assignment focus correction

Run 40767087342 on integration base `3789bb50` finished with 3,218 passing browser checks followed by a 30-second cloud-assignment page navigation timeout. Independent correction `codex/cloud-assignment-focused-view` adds an explicit cloud inventory/assignment workspace with full-settings navigation and focus-preserving redirects. Existing cloud history and owner/CSRF controls are retained. Fifteen real-template checks pass on PHP 8.2/8.4 with and without INI; the old template fails the regression. Fourteen fixture checks pass. Windows native setup refuses required 0600 permissions before assertions; new native and installed browser cases require Linux CI. No timeout or security assertion was relaxed. [Evidence and scope](docs/CODEX_PR266_APPLICATION_40767087342.md).

## Native financing reconciliation audit correction — 2026-10-28

This independent correction stacks on #287 (`4f368547`) and makes both direct owner reconciliation mutations atomic with their licensing audit event. Native trigger rejection reproduces two committed-state failures per PHP/database combination on the parent. The fix passes 75 assertions on PHP 8.2.35/8.4.25 × MariaDB 10.11.19/MySQL 8.4.13 (300 executions); the snapshot fences, real account mutex, owner reauthentication and provider-read boundary remain intact. A separate PHP writer verifies preservation of concurrent subscription state. The existing native workflow now runs and retains the regression. [Source, exact scope and retained first failures](docs/CODEX_FINANCE_RECONCILE_AUDIT.md).

This branch contains #287 but not #285/#286/#288. Reserved branches and the current Application run remain untouched. The actual provider, installed HTTP, independent review, final combined/main acceptance and release gates remain pending.

## Independent financing reconciliation correction — 2026-10-28

Prepared a focused correction from integration 3789bb50c90cb836530f2d5dc2400cee394763d0 on codex/finance-reconcile-snapshot-guard. Delayed owner gateway readback must not overwrite a newer subscription identity or lifecycle state. Both affected mutation blocks now compare the locked row with their verified snapshot and require retry on change. Exact-function synthetic regression: original 67 failures / 112 assertions, corrected 112 passes on each PHP 8.2.35 and 8.4.25; existing five finance contracts and lints pass. See [review and limitations](docs/CODEX_FINANCE_RECONCILE_SNAPSHOT_REVIEW.md). Native/HTTP and combined hosted acceptance remain separate. Review and incorporation by the integration writer are pending; no main merge, release, deployment or provider operation occurred. PRs #285 and #286 remain separate corrections.

## 2026-10-28 — initial-payment integrity in license synchronization

Independent `codex/finance-initial-sync-binding`, based on PR #296 `17357a9d`, closes the separate core access-state projection gap: another agreement/customer's paid invoice, wrong amount/currency or unavailable ledger must not activate the target financing. The target native reproduction has a fully reversed receipt with 500 cents due. Shared signed-offer/ledger validation now runs before activation; the permanent payoff early boundary is byte-identical. Parent fails 22/207 assertions; corrected native PHP 8.2/8.4 × MariaDB/MySQL matrix passes 207 per lane (828 total, including 119 existing enrollment assertions). Hosted acceptance and independent review remain pending. No customer pointer-edit capability, real gateway operation, main merge or release is claimed. [Review and evidence](docs/CODEX_FINANCE_INITIAL_SYNC_REVIEW.md).

## 2026-10-28 — verified initial-payment enrollment correction

Independent `codex/finance-initial-payment-enrollment`, based on PR #295 at `27d7b9e`, corrects the portal/handler mismatch after a nonzero verified down payment. Enrollment now requires the exact agreement invoice and retained ledger payment, current client and running signed schedule, then repeats admission under the reservation lock. Paid-off plans cannot enroll new installments; existing license/domain/rights and subscription history stay intact. Native application checks pass 119 per PHP 8.2/8.4 × MariaDB/MySQL lane (476 total), plus 22 handler checks per PHP. Original handler and intermediate cross-agreement invoice substitution failures are retained. Hosted acceptance and independent review remain pending; no real gateway, merge or release claim. [Review and evidence](docs/CODEX_FINANCE_INITIAL_ENROLLMENT_REVIEW.md).

## 2026-10-28 — stale sandbox subscription continuation corrected

Independent follow-up `codex/finance-sandbox-stale-profile` addresses review comment 6483700465 on PR #291 without changing its frozen head `110c30da`. A rejected stale sandbox mark now returns false and checkout stops before recurring subscription creation. Native regression reproduces four rejected-response paths reaching the subscription boundary; after correction all 220 checks pass in each PHP 8.2/8.4 × MariaDB/MySQL lane, and 26 caller-binding checks pass per PHP. Provider, preparation and subscription boundaries remain synthetic. No real sandbox validation or approval is claimed. [Review and limits](docs/CODEX_FINANCE_SANDBOX_PROFILE_REVIEW.md).

## Delayed finance checkout result identity — 2026-10-28

Follow-up to independent PR #290 on frozen `5e56c250`: `license_finance_charge_mark()` previously selected only the current invoice row, allowing a delayed result from an older attempt to overwrite a newer processing attempt. `license_finance_charge_record_paid()` also lacked the original request-key and retained transaction checks before reaching the ledger. This does not establish an unauthorized paid transition: existing ledger uniqueness can independently reject some stale receipts.

The separate `codex/finance-charge-attempt-results` correction passes the original attempt key through every checkout response, exception and owner reconciliation path. Both helpers recheck that key and any retained transaction under the account mutex/row lock; paid recording also checks immutable attempt history before ledger access. Owner reauthentication remains inside the paid transaction. PR #290's callback function is byte-for-byte unchanged.

Canonical charge/attempt PDO tests reproduce33 failures among180 assertions per supported PHP/database lane; all180 pass after correction (720 executions across PHP8.2.35/8.4.25 and MariaDB10.11.19/MySQL8.4.13). Twenty actual orchestration checks pass on each PHP version with isolated provider/persistence doubles. Companion10 routing,54 source and45 lifecycle checks, touched-file PHP syntax and workflow YAML/bash checks pass. The native fixture uses a synthetic owner and a ledger sentinel that rolls back eligible cases; it claims no completed ledger payment, license unlock, concurrent writer, installed HTTP or real gateway transaction. [Source hashes, original failures and scope](docs/evidence/finance-charge-attempt-results.json).

The existing four-lane recurring workflow retains both new reports. No manual/duplicate dispatch, owned integration-branch push, production activation or release occurs. Independent review, hosted exact-head testing and final combined/main acceptance remain pending; real sandbox credentials remain unavailable. Disposable databases were gracefully stopped and their data retained.

## One-time finance capture history and lock correction — 2026-10-28

Independent review of frozen integration `3789bb50` reproduced a delayed-capture defect in `license_finance_charge_webhook_review()`. After reading an unsettled row, the callback could wait while another worker completed/refunded/voided the payment or began a new attempt, then overwrite the newer state with `review` and bind the old transaction to the reused charge row. A capture arriving after a replacement attempt already existed had the same problem even without a read/lock race. This could prevent safe catch-up checkout or misdirect owner reconciliation; the callback itself does not mark an invoice paid or unlock a license.

The callback now rechecks the eligible state and immutable attempt key under the existing account mutex/row transaction. It also checks retained attempt history for the verified transaction ID, refusing to associate an older attempt with the current row. Such events return the existing replay outcome; ordinary processing/uncertain/review callbacks still enter review. Exact invoice/amount/transaction verification, conflicting-transaction refusal and payment settlement gates remain unchanged. The shipping change is limited to this function and its canonically refreshed installer inventory.

Real PDO/InnoDB tests exercise canonical charge/attempt DDL, FKs/indexes, actual mutex/transaction helpers, and a second PHP process committing between the initial read and locked read. The 28 scenarios include all three eligible starting states, five completed outcomes, changed attempt keys, historical captures, current captures after replacement, amount mismatch, replay and transaction conflicts. Original code fails39 of140 assertions per PHP8.2.35/8.4.25 × MariaDB10.11.19/MySQL8.4.13 lane. An intermediate lock-only repair leaves six history assertions failing; the complete correction passes all140 per lane (560 executions), with empty PHP diagnostics, unchanged retained parent/attempt rows and released locks/transactions. [Exact source, original failures and bounded native evidence](docs/evidence/finance-capture-snapshot-native.json).

The regression is added to the existing four-lane recurring workflow, with its own retained report and test path filter; no duplicate workflow or manual dispatch is introduced. Both PHP versions pass the10-case routing suite,54 finance source contracts and touched-file syntax. Synthetic gateway details and parent records are explicit: this is not real Authorize.net, webhook-signature, installed-browser or paid-off-license acceptance. Hosted current-head testing, independent approval and exact combined/main acceptance remain required. Work stays on the separate `codex/finance-capture-snapshot` branch; Devin/integration ownership and unavailable sandbox credentials remain unchanged.

## Native software correction PR admission — 2026-10-29

Independent follow-up to PR #285 (`81ae93c9`) closes its remaining hosted-coverage gap: the full native fixture invokes the new 30 domain lifecycle race assertions, but both native workflow PR filters accepted only Devin's feature branch. The four-lane cooldown result covered only its older 12 assertions. The existing fast native workflow now also accepts main and `codex/**` PR bases while retaining all path filters, the exact native command, dynamic database port, timeout, permissions and concurrency policy. The slower native workflow and protected Application 40767087342 remain unchanged.

The same native execution now retains stdout/stderr, checkout/tree identifiers and exact service/fixture/helper SHA256 values. Explicit bash pipefail preserves nonzero PHP exits; retained synthetic 0/17 exit probes verify both outcome and output propagation. YAML/bash and structural inverse checks pass; all three application/test sources remain byte-identical to PR285, so its local 269-per-runtime evidence is reused rather than rerun. [Exact admission and source evidence](docs/evidence/software-native-review-admission.json). Hosted native execution at this new source remains pending; this is no gateway, independent approval, combined/main acceptance or release claim. No reserved branch write or manual workflow dispatch occurred.

## 2026-10-28 — customer domain lifecycle checks under the row lock

Independent review of exact integration parent `3789bb50` reproduced an in-flight domain registration/deactivation/replacement committing after a competing suspension. The license remains inactive; this is a P2 mutation boundary, not a validation bypass. The focused correction moves each active/expiry check immediately after the existing license row lock. Twelve original reproductions, 120 targeted checks and the full native suite (269 checks on each of PHP 8.2/8.4 × MariaDB/MySQL, 1,076 executions) are preserved; the targeted counts overlap the full suite. No unexpected PHP diagnostics. Both native workflow filters include the regression helper. Integration ownership, protected runs and unavailable sandbox credentials are unchanged; exact combined hosted acceptance and independent review remain required. [Evidence and limitations](docs/CODEX_SOFTWARE_DOMAIN_LOCK_REVIEW.md).

## 2026-10-28 — retain unresolved financing replacement outcomes for review

Independent review of frozen integration `3789bb50` reproduced unresolved ARB creation responses restoring `billing_suspended` and admitting another create. The focused correction keeps unknown/malformed/duplicate/internal outcomes in `review`; only explicit recognized request-validation rejection without a candidate identity permits retry. The same 100 assertions fail original source (42 failures) and pass corrected source on PHP 8.2/8.4; five existing financing/isolation suites also pass. These are exact function bodies with strict synthetic dependencies, not real gateway transactions or native SQL. Protected Application run `40767087342` is preserved, and the integration writer retains branch ownership. [Finding, evidence and limitations](docs/CODEX_FINANCE_REPLACEMENT_OUTCOME_REVIEW.md).

## Upgrade harness Python annotation correction — 2026-10-28

Run [40785345723](https://github.com/afterburn25/Cajun_Web/actions/runs/40785345723) failed in all four lanes before application upgrade testing: the new diagnostic extraction test omitted `Path` from its function execution globals. CI Python3.12 evaluates that annotation immediately; local Python3.14 deferred it. All four complete job logs retain the same `NameError`. The helper now supplies the original `Path` dependency explicitly. No assertion, application byte, database/runtime probe or matrix lane changes.

The exact original failure reproduces locally on Python3.12.14. All15 methods then pass across Python3.12.14/3.14.7 and PHP8.2.35/8.4.25. Earlier 268-check PHP8.3/MariaDB installed-upgrade success remains valid at its recorded source; the corrected four-lane matrix still requires fresh hosted acceptance. [Retained run identity and bounded local proof](docs/evidence/published-125-upgrade-python-annotation.json).

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

## 2026-10-28 — d062 Application terminal result and focused provider repair

PR #266 remains exact `d062bd5c3b78f3ec265ffc8a404e253013cfa924`; main remains `fdf40a25be3b9a7c2e154a1fad17ab4c7ae55827`; integration PR #268 remains `74b5af3a2f2dd50ad70f34ea211a5b5b973635af`. Application run `40728300754` is terminal failure. Build, recovery, Apache and native passed; browser job `122971621925` reached 2,997 passing checks and then timed out navigating the StackCP-focused provider URL. Artifact `12850372335` shows repeated real 27–31 second responses because the focused query still rendered every unrelated provider history workspace. The prepared correction scopes StackCP pagination to the account/inventory and two StackCP workspaces, retains bounded page selectors through redirects, and adds presence/absence regressions. Do not raise the browser timeout. The prior suite-owned session correction remains necessary later in the matrix. Local Python syntax, whitespace and 69 build guards pass; hosted exact-head Application remains required. See [exact evidence](docs/CODEX_PR266_APPLICATION_40728300754.md).

## 2026-10-28 — Application browser timeout first cause and compatible repair

Frozen candidate `74b5af3a` Application run `40705973907`, native/browser job `122881334881`, failed only after its native phase and the long installed-browser sequence. The retained exception is `Form missing catalog_save at .../admin/index.php?view=login`; all earlier support, catalog, provider and licensing checks passed. `software_commerce_cases.run_browser()` was the first late suite still borrowing the bootstrap `admin`, `alice` and `bob` cookie jars, so the owner cookie had legitimately crossed the production 30-minute idle boundary. The next financing suite had the same latent dependency. Both suites now authenticate isolated identities through the actual packaged login forms and assert each login before any mutation; financing passes those sessions to its nested mail-draft checks. Do not lengthen or bypass the production timeout. Local Python syntax, `git diff --check`, 69 build guards and 283 artifact checks pass. Require exact-head hosted browser completion. Preserve current PR #266 run `40728300754`; do not dispatch a duplicate while it is active.

## 2026-10-28 — exact feature `d062bd5c` incorporated in an unattached review merge

GitHub source of truth now reports PR #266 at `d062bd5c3b78f3ec265ffc8a404e253013cfa924`, a direct child of `5326ecb8`. Its application fix is byte-equivalent to the finance mail-account correction already reviewed in integration, while its generated inventory does not include the integration's additional shipping corrections. Normal merge `ec6549713190c6a325c0ad4dd3e97f8885eb4053` therefore retains the integration site bytes and inventory, records Devin's exact ancestry, and unions the handoff history. Local 69 build guards, 283 artifact checks, dependency/inventory locks, 99 workflow parses, documentation/link checks and whitespace pass. Do not move `codex/licensing-integration-review` until frozen Application run `40705973907` is terminal and the GitHub heads are rechecked; then advance by non-force lease and require fresh exact-head CI.

## 2026-10-28 — preserve current acceptance, then record feature `5326ecb8`

PR #266 advanced from `2c0579c4` to test-only head `5326ecb8e6c33a3c4b77d8851e3d0c64d8ebace4` while integration `74b5af3a` was executing. Do not cancel the frozen run. The delta correctly drives payments schema 1→2 through the owner form before gated activity/delivery/portfolio/current trial/ledger/notification cases. Integration already has stricter retained-record migration helpers for historical 1.5/1.6/1.7 plus the published-v1.24 upgrade; keep those and add only the missing fixture preparation. Prepare a normal two-parent refresh after the running acceptance is terminal, then rerun exact combined CI. Devin's branch remains untouched.

## 2026-10-28 — latest PR #266 head `2c0579c4` recorded

Use exact feature head `2c0579c4f1379505b1fb8a53c3f90905d3e5dcdc`, not the earlier `93bfa54f` checkpoint. The new `software_commerce_migrate.php` imports in the lifecycle/deactivate/reactivate controller paths are byte-identical to the existing integration tree, so the ancestry refresh is tree-neutral. Retain the integration-generated inventory because it additionally binds all accepted corrections. Recheck GitHub immediately before any ref update; never push Devin's feature branch.

## 2026-10-28 — require product identity for software validation

Integrate focused PR #281 at exact head `14008290deb933fc97049c1ca2602a19e35ab8b7`, based on `03c4f606`: the public validator previously authorized a valid software key/domain when `product_id` was omitted, permitting cross-product reuse on the same site. `software_grant_requires_product_identity()` now distinguishes new software-entitlement rows from genuine legacy website keys. The handler definitively denies missing identity only for the former, retains the existing mismatch denial, and preserves legacy `{license_key, domain}` callers. The installed suite proves the omission path and native coverage proves both classification directions. Generated inventory, 69 build guards, 283 artifact checks, Python/docs/whitespace pass; hosted PHP8.2/8.4 database and HTTP execution remains required. [Evidence](docs/CODEX_SOFTWARE_PRODUCT_VALIDATION_REVIEW.md).

## 2026-10-28 — Apache populated-upgrade mail sender warning

Integrate focused PR #282 at exact head `d40c36711f4cd7bad0be555afc9bb59d9fc1cf15`, based on `03c4f606`: `site/admin/views/studio/licenses.php` must call `mail_drafts_account($a['id'])`, not the undefined variable function `$mail_drafts_account(...)`. Protected run `40684017215`, Apache job `122816732929`, passed 2,747 protection/root/subfolder checks and 158 populated root-rewrite upgrade checks before five warnings failed the final log gate. The source contract now fixes this spelling. Regenerate/verify inventory and rerun exact combined Apache/Application acceptance; no mail is sent by this path. [Evidence](docs/CODEX_FINANCE_MAIL_DRAFT_APACHE_REVIEW.md).

## 2026-10-28 — finance sender rendering first cause

Review focused PR #280 at exact head `1994255cf5e365aeb5a5ea3c0dcb8eace9905883`, based on `03c4f606`. It fixes the invalid variable-function call found in Apache job `122816732929` and adds sender permission/revision/CSRF and local-draft regression coverage. Preserve the diagnostic guard and require fresh complete Apache acceptance after integration. The designated writer owns the integration branch; #277/#278 and the feature trial-control reconciliation remain separate. See [exact evidence and limits](docs/FINANCE_MAIL_SENDER_REVIEW.md).

## 2026-10-28 — PR #274 support follow-up

Integrate exact #274 head `617e67cb0da2bc3037a566142dc69e09c2bcd037` after its earlier seat-scoping commit. The follow-up corrects only installed acceptance/review documentation: forged support mutations use the ordinary support portal to expose the refusal while requiring one attributed denied event; the private software view and ZIP remain separately HTTP403. No shipping authority change.

## 2026-10-28 — reject feature UI regression; integrate #274–#276

Refresh the integration ancestry to exact feature `af377c89e35ffd0e10d02bead4c9d9c3698b5a31`, but retain the pre-`a0d21c1c` portal renderer because invoice-null gating hides valid trial/temporary/owner-issued redeployment controls. The invalid whole-page assertion is replaced by exact-license card/form selection from PR #274 (`8a24b10e`). Also integrate dynamic provider-settings service ports from #275 (`76dbfa3a`) and explicit retained payment preparation from #276 (`59255c8d`). No shipping payment or licensing authority is weakened; Devin's branch remains unchanged.

## 2026-10-28 — integrate PR #273 test continuation

Incorporate exact focused correction head `b2ec170c4df75879789a5f7caa7ae770d542db8a` as a second parent of the integration lane. Its shipping renderer change is already byte-identical to Devin feature `94d24cea`; the remaining material change updates `tests/software_commerce_cases.py` to select the real reasoned software suspend/resume forms and assert forged legacy deactivation is refused. Preserve `docs/CODEX_SOFTWARE_DOWNLOAD_SOURCE_REVIEW.md` as the focused evidence record. No shipping lifecycle behavior changed. Fresh combined-head installed acceptance is mandatory.

## 2026-10-28 — feature refresh after installed notice failure

Continue from the normal merge of exact PR #266 head `cb916c0729525404ccc30f79902620026e174848`. Installed run `40667109804` reached the disabled-mail retry assertion after all earlier software setup/payment/tenant/fulfillment checks passed, then failed because `software_notices.php` lacked the direct migration import defining `software_commerce_ready()`. Devin fixed that at `62a1b601`, then repaired invoice-bound download-link preference and refreshed inventory. Do not duplicate those changes. The current combined merge preserves the integration branch's workspace hash and every independent licensing correction while taking the new feature bytes. Run the fresh exact-head installed/native/package/application workflows; older green runs are supporting evidence only.

Locally available verification on the merge: 283 artifact-helper checks, 69 build rejection checks, exact inventory hashes for the three refreshed files and whitespace all pass. `tools/build.py --refresh` reached dependency/vendor validation but cannot finish here because no PHP executable is installed; do not report PHP lint or native execution from this workspace. No merge to main, release, live transaction or deployment occurred.

## 2026-10-28 — feature head `9a1e9451` normal-merged

Devin advanced PR #266 with the same `software_*` dispatcher repair already present in the integration lane, its inventory refresh, and the missing canonical workspace import in `tests/software_commerce_browser_fixture.php`. The separate integration branch normal-merges exact feature head `9a1e9451`; no duplicate fixture patch or feature-branch rewrite was made. The combined site bytes retain the independently verified owner permission/readiness hardening. Run only the new combined exact head; parent CI does not certify the merge.

## 2026-10-28 — repair owner software action dispatch and readiness loading

The latest independent HTTP matrix reproduced actual controller failures rather than browser timing: valid owner/CSRF `software_commerce_setup` returned `Unknown administration action`, while `studio_license_deactivate` and `studio_license_reactivate` reached an undefined `software_commerce_ready()`. The separate integration lane now dispatches both `studio_*` and `software_*` actions to the existing studio controller, maps all `software_*` actions to owner-only `staff.manage`, and explicitly loads the commerce migration contract before the lifecycle/deactivate/reactivate readiness calls. Keep the existing real-form software setup and paid-off owner control assertions; the new fast routing contract is supplemental. Re-run exact-head policy, focused financing and installed application acceptance before accepting the fix.

The licensing integration PR base is now included in the existing full Application acceptance and Software focused installed acceptance triggers. The focused path list includes the admin dispatcher and staff permission file. Protected Application acceptance retains a per-SHA group and `cancel-in-progress: false`; do not redispatch queued runs. Treat a skipped, absent or parent run as no evidence for the current candidate.

The existing software package source, fast and full workflows also now admit the integration PR base. Require their exact-head results for generated-source integrity, all three direct-extract archives, checksum/member safety, no nested ZIPs and the pinned v1.25 delta audit. VERSION is still `1.25.0`, so this is candidate packaging evidence—not the real populated v1.25-to-next-version upgrade gate.

All remaining software-only gates now admit the same PR base: browser-source syntax, fast/native commerce databases, exact gateway amount, payment CSP, refund/void reversal, subscription lifecycle and signed webhook contracts. These are distinct from the real Authorize.Net sandbox workflows and must not be described as provider transactions.

Feature run `40664435482` exposed the next installed-test blocker at `Software browser fixture sandbox-on succeeds`: the CLI fixture loaded `payments.php` without the workspace import that defines `studio_ready()` and loads support-session state. The integration fixture now imports the canonical client workspace immediately after software-commerce migration. Preserve the real `payments_ready()` check and rerun focused installed acceptance; no gateway call or production behavior changed.

## 2026-10-28 — integrate focused domain-cooldown correction

The integration lane now includes exact PR #272 head `9eecf573`, stacked after #269/#270. `software_grant_recent_deactivation_blocks_add()` compares serialized audit IDs so equal-second replacement and later standalone deactivation events cannot be confused. The correction retains same-domain recovery, owner override, disabled/expired cooldown and the installation cap; 12 native service cases and a dynamic-port PHP8.2/8.4 × MariaDB/MySQL workflow are included. Run the new exact combined head rather than accepting #272's isolated evidence as combined acceptance.

## 2026-10-28 — feature head refreshed without overwriting Devin

PR #266 advanced by two commits to `726fc7db`. `codex/licensing-integration-review` normal-merges that exact head, preserving Devin's 404-before-layout correction for unavailable or foreign finance agreements and regenerating the combined installer inventory. The installed foreign-customer regression now requires HTTP404 plus `no-store, private` and `no-referrer`, not merely absent agreement text. Earlier exact-parent runs do not certify this merge. Continue only the new combined-head workflows; do not duplicate pending CI. The financing owner-reactivation browser diagnostic, complete PHP/database/browser/package matrices, independent review, real sandbox, next-version upgrade and merged-main gates remain open.

## 2026-10-28 — recover interrupted financing setup

The three financing setup migrations now persist an owner-audited pending marker before nontransactional DDL. A retry may resume only that exact recognized preparation while every existing partial table is empty; it validates all schema/indexes and clears the marker atomically with the version. Unmarked tables or partial tables containing records remain blocked for manual review. Run the updated financing native/source-contract lanes on the exact integration head before accepting this recovery fix.

## 2026-10-28 — payoff no longer overrides owner license control

Treat the prior auto-reactivation behavior as a high-severity commercial-control defect. A paid-off plan remains permanently free of financing suspension, but `website_licenses.is_active` is again an independent owner decision: validation does not self-heal it and payoff synchronization does not rewrite it. Updated native/browser cases prove owner deactivate/reactivate on the same key while financing remains `paid_off`; standalone domain removal remains blocked and replacement remains available. Run the exact-head hosted matrix before accepting this correction.

## 2026-10-28 — refreshed combined PR266 review lane

Continue from `codex/licensing-integration-review`, refreshed to feature `726fc7db` with review evidence `25e2d34f`, routing correction #269 and lifecycle correction #270. The stale forged-support assertion is resolved against the actual dispatcher: the POST omits the private finance view, requires the blocked-action copy and one exact denied audit increment, and proves both offer and payment-request rows unchanged. The one validation-rate wait now precedes the combined payoff security probes. Software release publication additionally refuses noncanonical, Windows-reserved and case-colliding ZIP members. Local non-PHP checks pass; run only the refreshed exact-head hosted matrix rather than redispatching superseded heads. PHP8.2/8.4, MariaDB/MySQL, installed browser/Apache, package, real sandbox, independent review, next-version v1.25 upgrade and merged-main gates remain mandatory.

## 2026-10-28 — continued native verification

`tests/license_finance_webhook_native_cases.php` adds 40 real SQL routing/lifecycle checks to the existing signed zero-down fixture in `tests/license_financing_native_cases.php`. This extends #270 without shipping changes or browser-fixture edits. Local PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 passes all 40; preserve the separate full-Windows failure at the 0600 private-file requirement. The prior correction commit `168c4999` now has a complete passing hosted native matrix (588 recurring checks per leg) and 55 focused regressions on both PHP versions. Re-run the normal hosted matrix for the enclosing test commit; installed acceptance remains outstanding. The existing integration writer retains PR #266 and the integration branch. [Current report](docs/CODEX_LICENSING_REVIEW_2026_10_28.md).

## 2026-10-28 — delayed event correction for integration review

Independent review additionally reproduced delayed financing notifications clearing reversal/replacement fences and downgrading confirmed cancellation/suspension. `codex/licensing-lifecycle-ordering` is stacked on #269 and changes only the lifecycle service, its regression/workflow and generated hash plus documentation. Integrate the routing correction first, then this focused commit; refresh inventory if resolving changes from other branches. All 45 isolated lifecycle cases pass locally; native SQL, full installed acceptance, real sandbox and release remain unverified. The existing **Complete Licensing Integration** chat retains integration ownership. [Evidence](docs/CODEX_LICENSING_REVIEW_2026_10_28.md).

## 2026-10-28 — independent correction for the licensing integration writer

The owner explicitly retained the **Complete Licensing Integration** chat as writer of `codex/licensing-integration-review`; this local reviewer prepares the separate `codex/licensing-webhook-isolation` correction. Frozen parent `6045b801`, refreshed feature `5ba2a37d`, main `fdf40a25`. Review/integrate the two optional-financing webhook routing fixes and regenerated hashes, then test the combined candidate. [Review report](docs/CODEX_LICENSING_REVIEW_2026_10_28.md) records 10 passing local regression cases, 12 existing suite passes, baseline package hashes, historical failure causes, unavailable sandbox secrets and remaining release gates. No approval, merge, release, live charging or deployment is claimed.

## 2026-10-28 — owner reconciliation commit checks

One-time/subscription/replacement owner reconciliation now reauthenticates inside serialized mutations. Four new native rejection cases pass on local MariaDB; the full local suite stops at the unchanged Windows private-permission guard. [Exact source-bound partial evidence](docs/evidence/pr266-owner-reauth-native.json). Continue original Linux CI, full application/package acceptance, real sandbox and independent review.

## 2026-10-28 — financed replacement recovery hardening

The Codex companion now also repairs replacement enrollment after the original start date and requires verified terminal retirement of the prior ARB before creating parallel billing. Policy and synthetic gateway regressions pass; full installer refresh passes. Hosted native/browser and independent security/real-sandbox gates remain open. [Exact evidence and current gates](docs/PR266_INTEGRATION_ACCEPTANCE.md).

## 2026-10-28 — PR266 Codex correction lane

Continue `codex/licensing-integration-review` from `6045b801`, preserving Devin’s feature branch. [Evidence and remaining gates](docs/PR266_INTEGRATION_ACCEPTANCE.md). Next: inspect original companion-PR financing/browser and document-roots matrix results; fix actual next failures, complete security review and source-bound release acceptance. Do not redispatch pending checks or treat local contracts as installed acceptance. Production payments/deployment remain owner gated.
## PR #266 release stabilization — 2026-10-27/28 (in flight)

Owner directive: clear genuine release-blocking failures on `feature/software-license-commerce-20261025` (PR #266), no feature expansion; blocker-matrix discipline — failing run → root cause → smallest repair → local targeted regression → cross-component check.

**Cleared this arc (oldest→newest):**

- `5e2db4b` — payment-links restack raised `payments_ready()` to schema `2` but fixtures seeded `'1'`; seeded `'2'` in `ledger_native`, `notifications_native`, `domain_renewal_dispatch_native` and added the canonical `payments_migrate` submit to `recurring_billing_upgrade_cases` (all verified locally: 108/62/84 checks).
- `2a18049`/`73d4c37`/`6045b80`/`5ba2a37` — financing lane: fixture dir/file modes unreadable by apache (`0700`/`0600` → `0755`/`0644`); stale `pending_initial` plan shared `job_id=1` locking the active release (dedicated `studio_jobs` row); unqualified `RecurringBillingTerms` in fixture global scope (FQCN).
- `cbffd00` — `financing.php` 404 status set after layout output (moved offer lookup before `site_head`); **software dispatch**: all 15 `software_*` admin actions lived in `studio_admin_action` but the POST router only forwarded `studio_`-prefixed actions → added `software_` prefix. Verified locally: owner preparation stores `software_commerce_version=3`.
- `9a1e945` — software browser fixture never loaded `app/clients/workspace.php` → `payments_ready()`/`studio_ready()` fatal.
- `4b17cd3` — `software_notices.php` called `software_commerce_ready()` without its defining migrate file → fatal on owner delivery retry.
- `94d24ce` — downloads query `ORDER BY l.id` surfaced the trial row first so `$shown` dedup bound the link to the trial license → invoice-bound rows first via `(e.invoice_id IS NULL)`.
- `a0d21c1` — trial license's own add-install control leaked onto My Software → gate the control on `e.invoice_id !== null`.
- `4f75380` — support-blocked POST rendered generic 403, losing the blocked-action reason → `fail_page($error,403)` before the gate (avoids the CSP contract's source-ordering assertion on `site_head`).
- `93bfa54` — test tail still looked for retired `studio_license_deactivate`/`reactivate` forms → `software_license_lifecycle` + transition + reason (assertions unchanged).
- `2c0579c` — `software_commerce_ready()` called without migrate require in `software_license_lifecycle`, `studio_license_deactivate` and `studio_license_reactivate` branches → added `require_once` to each. Suspend/resume verified end-to-end locally (is_active flips, portal renders 'Standard · Suspended'/'Active', KEYPAID key shown).
- `5326ecb` — sealed `Application acceptance` failed at `2c0579c` on the payments-schema-`2` class (recurring_setup 403, payments_trial_setup form missing): every cumulative upgrade fixture (1.5→1.10, release_125 later-records, `existing` legs) now submits the canonical `payments_migrate` form before payments-gated views; record compares scoped to pre-upgrade columns (`link_hash`/`link_secret` added by migrate); settings checks declare `payments_schema_version` '1'→'2' and assert `'2'`.
- `d062bd5` — apache leg of release_125 upgrade acceptance emitted `PHP Warning: Undefined variable $mail_drafts_account` at `admin/views/studio/licenses.php:59` — stray `$` made a variable-function call instead of `mail_drafts_account($a['id'])` (functions loaded via `financing_offers.php`→`app/mail/drafts.php`). Fixed; apache leg green on `d062bd5`.
- (next) — `native-and-browser` leg on `d062bd5` (AND `main` run 40457205972 on 10-27, identical signature): `Page.goto` 30s timeout on `?view=provider-settings&stackcp_page=N&stackcp_access_page=N`. Root cause is a real perf bug: `ProviderStackCpReadProjection::accessFamilyReady`/`familyReady` re-verify the whole StackCP history chains per render with ~6 (access) and ~3 (family) correlated `audit` subqueries PER history row — O(chain × audit-scan) as suite-accumulated history grows past the 30s goto budget. Rewrote both walks to grouped LEFT JOINs (COUNT/MIN(id) by detail, and by detail+user_id for actor counts — `audit` is `utf8mb4_bin` so grouping is byte-exact, identical to BINARY comparisons; COALESCE→0 preserves COUNT miss semantics; NULL min-id preserved). Verified: `tests/stackcp_access_read_snapshot_native.php` 78/78 — byte-identical HTML vs pinned original, exact projections, fewer statements.

**Result**: sealed `Application acceptance` on `d062bd5`: apache leg green; native-and-browser red only on the StackCP walk latency — fixed pending push. Infra flake noted: `Provider settings storage checks` once failed on runner port-bind collision (33306), cleared on rerun — not code.

**Codex integration matrix** (verified via `git merge-base --is-ancestor`):
- `codex/software-acceptance-guard-order` (`0d18707b`) already contains: feature branch through `d062bd5` + PR #268 (`3789bb50`, `refs/codex-review`) + webhook lane #269/#270/#272 + acceptance-order lane #294→#304→#306. It does NOT contain `2d8d32d` (my StackCP fix).
- Still pending, stacked on `codex/licensing-integration-review`: reconcile chain `refs/codex-301` (#287→#289→#301), payoff chain `refs/codex-ledger` (#290→#291→#295→#296→#297→#298→#299→#300→#303), `refs/c293` (#293, includes #285), `refs/codex-286` (#286), `refs/codex-288` (#288 upgrade rehearsal). Sandbox lane #271→#279→#283→#284→#292 targets `main` (post-merge work).
- Merge surface vs `2d8d32d`: only `SESSION_HANDOFF.md` + `site/install/inventory.generated.php` overlap — regenerate inventory, hand-edit handoff.
- `0d18707b`'s lone sealed failure `Independent active financing does not unlock the unpaid initial agreement` = 10/min validate.php per-IP fixed-minute rate bucket shared across suites — reorder puts software (~8 POSTs) + financing (~5) clusters adjacent → tail POST gets 429. NOT an authz defect: verified locally on Codex's own overlaid code (pending_initial denial holds through setup/release ops). Fix = `time.sleep(61)` before the late POST, mirroring their own `36721397` pattern.
- Codex `3789bb50` "Focus StackCP provider acceptance view" + `0ad3df4d` "Focus cloud assignment workspace" bound the page's scope — complementary to `2d8d32d`'s batched audit verification (different files, no conflict).

**Local repro stack** (this VM): `~/bin/php` docker wrapper (`cwp-inventory-test:latest`); `docker exec cwpdb mariadb ...` resets `cwp_test` (creds cwp_test/local-ci-only, prefix `ci_`); installed site `test-runtime/web/existing` served at `127.0.0.1:8091` (owner@example.invalid / alice@example.invalid, 'Synthetic … passphrase 2026'). After editing `site/portal/views/*` or `site/admin/controllers/*` locally, `cp` the file into `test-runtime/web/existing/...` to exercise it. `tools/build.py --refresh` + commit `site/install/inventory.generated.php` after every `site/` change.

**Next**: confirm sealed `Application acceptance` green, then final integration/security review of PR #266 → merge readiness report to owner (merge needs explicit approval; no live deploy, no live Authorize.net).
## Sandbox source-review CI queue policy — 2026-10-28

Independent follow-up to PR #284 (`803a4688`) corrects its ordinary no-secret `sandbox-workflow-security.yml` to cancel superseded development checks while preserving every main run, as required by AGENTS.md. The branch-scoped group, triggers, jobs, permissions and all security assertions are unchanged. Only the cancellation expression and that file's reviewed SHA256 pin change. Both actual manual sandbox transaction workflows remain non-cancelling and byte-identical; all seven other pinned files are unchanged.

The reviewed-source contract and17 isolated runtime checks pass on Python3.12.14/3.14.7. Whole-file inverses prove the limited delta, all workflow YAML parses, and exact source hashes are retained in [evidence](docs/evidence/sandbox-review-queue-policy.json). This isolated branch does not write PR284's branch, dispatch gateway tests, read credentials, configure environments, merge, publish or deploy. Independent review, the existing prerequisite merge order, server-side sandbox policy and unavailable real sandbox credentials remain release gates.

## 2026-10-28 - Keep transaction key off dependency-install runners

Follow-up to PR283: exact workflow pins did not pin `actions/*@v4` or the Python packages installed before the later transaction-key step. The focused correction pins actions by commit and divides each manual probe across fresh runners. Tokenization receives only the browser API login/public key, refuses a transaction key before importing Playwright and emits three strict one-use nonces; the next runner receives the transaction key, installs no third-party Python code, rejects redirects/live endpoints and consumes only the validated bounded nonce file. Offline workflow and 17-check runtime contracts pass, with a dedicated no-secret PR workflow added. Environment policy, environment-only credentials, real sandbox execution, independent approval and normal integration remain open. [Security review](docs/CODEX_SANDBOX_WORKFLOW_SECURITY_REVIEW.md).

## 2026-10-28 - Review sandbox environment binding before credentials

Focused follow-up to PR279/PR271: both manual provider jobs reference `authorize-net-sandbox`; complete workflow/driver hash pins replace substring checks that accepted commented-out guards. Two unchanged-source contracts pass and eight offline negative fixtures reject source drift; YAML semantic comparison permits only the added environment binding. Read-only GitHub inspection found zero environments. Before execution, configure and verify an explicit environment deployment **branch** rule for exactly `main` and keep the three sandbox credentials only in that environment, without repository/organization fallback. Branch-local source checks do not enforce this policy. Credentials remain unavailable; keep that gate open without requesting them again. Required independent review and normal integration remain pending. No provider request, policy mutation, main write or deployment occurred. [Scope and evidence](docs/CODEX_SANDBOX_WORKFLOW_SECURITY_REVIEW.md).

## Port-PR sequencing resolved — 2026-10-26

PRs #257–#260 (vps-uncertainty, ssh-access, provider-catalog, domains ports → `devin/full-site-integration`) each carry a byte-identical copy of the shared `foundation/desk` + `foundation/notifications` layer (sha-verified across all four branches) — merge order is arbitrary and component code never conflicts. The only shared conflicts are the usual docs/config union files (AGENTS, CHANGELOG, STATUS, HANDOFF, SESSION_LOG, ROADMAP, integration queue, `application-tests.yml`, `provider-cloud-sites.yml`) plus each port's own new workflow. Still gated on hosted evidence — proceed 257→260 once runners free.

## CI queue-hygiene fix — 2026-10-26 (in flight)

Owner directive: audit all workflows, fix queue accumulation, protect release certification — implement, not recommend. Branch `devin/ci-concurrency-hygiene` from main `a331599a`.

**Root cause** (documented in `docs/CI_HEALTH.md`): 32 development-check workflows used concurrency groups containing `github.sha` — each push created a new size-1 group, so `cancel-in-progress` could never supersede an obsolete run; 16 of them additionally set `cancel-in-progress: false`. Two dev checks had no concurrency at all. Combined with ~35 workflows firing per licensing-lane push and a ~4-5-slot effective account runner ceiling, the backlog reached ~1000 queued runs (repo-reported ~2500). Not a GitHub outage — status page green; the accumulated runs were legitimate queue depth.

**Fix**: all 32 sha-scoped dev checks regrouped to `<key>-${{ github.event.pull_request.number || github.ref }}` with `cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}` — superseded dev runs cancel per-PR/per-branch; main pushes serialize for evidence. `mail-receiving`/`mail-transport` gained the same block. Protected non-canceling runs untouched: `application-tests` (per-ref+sha+mode+matrix, deliberate evidence preservation), `release-licensing`, `release-upgrade-diagnostic`, `stackcp-access-read-diagnostic`, `import-preserved-source`, `twentyi-catalog-schema`, `twentyi-cloud-root`, and the inert `codex/finish-1.1.0`-scoped recovery workflows. All 76 workflow files parse.

**New**: `tools/ci_health.py` (queued/running counts, oldest pending age, per-branch/per-workflow backlog, long legs, cancellation stats) + weekly `ci-health.yml` (Monday 06:00Z, step-summary report, cannot self-flood) + `docs/CI_HEALTH.md` diagnosis table + permanent rules in `AGENTS.md`.

**Not done / limits**: docker layer caching across runs (42 workflows rebuild `tests/invitations/Dockerfile` per leg) is a documented follow-up, not implemented — needs buildx+gha-cache evaluation first. `main` branch protection returned 404 — no required checks exist to preserve or break. PR #266 coverage untouched; its lanes keep their own workflow copies until they merge main.

## Merge-first delivery cycle — 2026-10-22

Owner directive (reaffirmed): completed features reach `main` in small, verified increments — a completed branch is undelivered until its accepted implementation lands on `main` and the resulting main commit verifies. Full standing text: `AGENTS.md` "Standing owner directive - merge-first"; queue: `docs/DEVIN_INTEGRATION_QUEUE.md`.

Delivery 1 in flight: **PR261** `devin/reset-email-template-main` — merge `e334678e` of the green accepted source `467ec1bd` (original full-green run 39422487199) into main `1067757d`; docs-only conflicts resolved preserving both sides; local `php -l` clean, `tools/build.py --refresh` no-drift, artifact verifier `{"passed": true, "checks": 230}`. Hosted acceptance running; independent review pending; merge on green then verify main.

Next candidates after PR261: cloud website intake and directory-listing controls — confirm actual dependencies against current main; do not assume earlier leaf runs certify a standalone candidate.

Staging lanes `devin/preflight-commercial` and `devin/full-site-integration` remain in flight for the wider backlog and are not the delivery path for small increments. FSI browser-lane failure at `67d70e4a` was a test-side `NameError` (`ROOT` missing from `mail_message_cases.run_browser` env pull); fixed at `9ec4e2b1`.

---

## 2026-10-19 — reset email template implementation

Parent `a86f6f4fa592e6f715a9420aec8de4622258642e`; branch `codex/twentyi-reset-email-template`; enclosing commit **feat: review and confirm 20i reset email templates**. Root owns UI/shared transport and acceptance wiring; Catalog owns encrypted runtime/storage; Domains owns typed provider contracts and independent review; VPS owns additive SQL/browser tests. [Exact scope and next gates](docs/TWENTYI_PASSWORD_RESET_TEMPLATE.md).

Continue actual exact-source native/browser acceptance, inspect produced images, integrate sequentially and complete combined-main/release gates. Do not interpret local modeled checks as installed acceptance or rerun unchanged queued jobs. My20i sign-in for the missing full catalog/registrar schemas remains pending. Full roadmap goal stays active.

Implementation and independent source review are finished with no remaining concrete findings. Local foundation, modeled runtime/forms, handler regressions, 68 root source checks and 230 acceptance-helper checks pass in their documented scopes. Installer refresh, all shipping PHP/JavaScript checks, documentation and whitespace pass; generated shipping mirrors/inventory are included. Detailed receipts and the preserved initial Windows extension-path harness failure are linked from the feature guide. No native SQL or installed browser result is claimed.

Payment-link fix is separately committed/pushed as `b1948eeb`, draftPR253 targeting DevinPR202. Its original full run39247478772 was manually dispatched once because that companion base is outside the full workflow PR branch list. PR252 source632362f6 and all original evidence stay preserved. Published1.25.0 is unchanged; no live deployment.

## Owner request recorded 2026-10-19

Transfer all remaining CajunWebPro work to Devin alone. Start by reviewing and merging completed Codex AND Devin branches into main; then finish the entire 20i and non-20i roadmap, including integration and releases. The authoritative instructions are [docs/DEVIN_FULL_SITE_TAKEOVER.md](docs/DEVIN_FULL_SITE_TAKEOVER.md) and [DEVIN_START_HERE.md](DEVIN_START_HERE.md).

This supersedes old non-20i-only and Codex-only integration/release restrictions. Devin owns shared billing/auth/schema/navigation/installer/CI changes and final delivery, subject to existing tests, review, protections and operation-specific approvals. Other workers must preserve their work and not start overlapping implementation; their external sessions have not been stopped by this document.

## 2026-10-18 — client cloud website journey

From main `e397d9a6e293850e8dff61a593e3ba39ee178400`, branch `codex/twentyi-client-cloud-websites` implements primary-client request/cancel, owner decline/explicit creation, retained outcomes and exact returned-package confirmation navigation. Client dispatch and the existing shared grant commit atomically; replay never sends again. The current intake requires a complete cloud observation within15 minutes. [Behavior, authority, evidence and remaining gates](docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md).

Local evidence:141 document and98 modeled UI checks per PHP mode; four actual module cold loads;34 legacy-context checks per mode;271 final artifact-helper checks;35 portable installed-update guards;19 actual published-baseline negative checks. Original1.24 and1.25 ZIP bytes validate against their retained member inventories, with all27 earlier release maps unchanged. Independent service, browser, installed-update and shared-wiring review is recorded in the feature guide. Native SQL/concurrency, packaged browser pixels, installed Linux/Apache execution and full combined acceptance remain pending. No live operation, new release or deployment; published1.25 and all separate original CI handles remain unchanged.

## Starting source and actions taken

Main read before the handoff: `a86f6f4fa592e6f715a9420aec8de4622258642e`. Latest release read: v1.25.0 at `b79c67110829126916e3a65e616191542448bccc`. PR216 is still an open candidate, not a new publication. Refresh all facts at Devin startup.

This session writes the takeover directive and current root/coordination instructions, and updates the assignment issues. These changes are documentation only. No application PR was merged, no PHP/schema/installer/workflow/version file changed, no application acceptance was rerun and no new software release or deployment occurred. No active Devin session or external-worker acknowledgement is claimed. The enclosing commit identifies the instruction revision.

## Devin's first executable milestone

1. Preserve local work; fetch current main, all relevant feature branches and paginated PR/run/review evidence. Check for concurrent writers.
2. Create or inspect a Devin-owned integration branch from current main. Build `docs/DEVIN_INTEGRATION_QUEUE.md` and a stable requirements tracker for the entire project.
3. Identify the first ready merge group across both workers. Fix specific mature blockers, including contacts fixture/payment-link corrections where still applicable. Preserve dependencies and current-main provider/billing hooks. Do not merge all open drafts blindly.
4. Run required combined acceptance/review and normal-merge ready slices into main. Preserve all old source/failed evidence. Continue an independent task while genuinely blocked hosted gates wait.
5. Complete exact-main acceptance and publish the next appropriate verified incremental release, with FULL/COMPLETE/UPDATE assets, migration notes and actual build screenshots. Download and verify every release asset.
6. Continue every remaining 20i and non-20i capability. Do not stop at a plan, one PR or one release, and do not wait on Codex to take action.

## Source and gate caveats

Read the takeover document's PR map. The open catalog/capture family, hosting commercial stack, nameserver/folder/runtime/subdomain/StackCP candidates and Devin mail/project/billing/backup/contact stacks are independent or stacked histories, not one accepted combined release. PR256 is a pure VPS component on an old foundation application tree: never deploy that tree. PR250 is a contacts test correction; PR253 is a payment-link privacy correction. Current acceptance must be checked before carrying forward old failures or old passes.

The prior 497-candidate cleanup disposition is recorded, not a job to restart. Preserve required original catalog/capture/application/release runs. Queue delay alone never authorizes duplicate dispatch or a passing claim.

Do not deploy live, issue real provider writes/charges/refunds/customer mail/SSH, buy resources or overwrite old tags/assets under this handoff. Record exact external prerequisites and continue unblocked implementation. Do not commit secrets or customer data.

## Prior full handoff and evidence

The complete earlier handoff, source pins, local evidence paths and dated run IDs remain at [pre-takeover SESSION_HANDOFF.md](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/SESSION_HANDOFF.md). Also read [current continuation evidence](docs/ROADMAP_CONTINUATION_2026_10_19.md), [release workflow](docs/RELEASE_WORKFLOW.md), [whole roadmap](docs/ROADMAP.md) and [20i requirements](docs/TWENTYI_REQUIREMENTS_EVIDENCE.md). Historical allocations do not override the new owner directive; historical results remain historical.
# 2026-10-28 licensing key-entropy handoff

The PR266 integration candidate corrects new license generation from 64-bit truncated keys to the intended 128-bit value, formatted as eight four-hex groups. Existing keys remain accepted and no migration rewrites stored identities. Run the exact refreshed native/browser/package matrix before merge.
# 2026-10-28 Devin integration candidate

`devin/licensing-integration-candidate` carries the full Codex correction stack merged onto feature `2d8d32d` in verified dependency order: guard-order (acceptance reorder + #268 financing + webhook lane), chain A #287→#289→#301 (reconcile atomicity), chain B #290→#303 (capture/attempts/stale-profile/payoff/receipts), c293 (incl. #285 domain lock), #286 (uncertain replacement), #288 (v1.26 rehearsal + key entropy). Union-merged log docs verified; the recurring-billing-native workflow was re-resolved as ledger-side plus chain-A's reconcile step (the naive union broke YAML).

Verifications this session: `license_financing_cases.py` gained `time.sleep(61)` before the unpaid-initial re-validate probe (same fixed-minute per-IP rate-bucket collision Codex fixed in 36721397; the `Independent active financing does not unlock the unpaid initial agreement` failure on run 40822597884 was that collision, not an auth defect — denied state verified locally through every runnable fixture op). StackCP snapshot native: 78/78 on the merged tree. Finance natives on merged tree: reconcile 8, capture-snapshot 141, attempt-bindings 27, payoff-retirement 291, replacement-payoff 139, initial-enrollment 208 — all passing. Inventory regenerated via `tools/build.py --refresh`.

Owner confirmed the `authorize-net-sandbox` GitHub environment (main-only restriction + three ANET secrets). Next: CI on the candidate, then the sandbox workflow lane (#271→#279→#283→#284, main-targeted) post-merge; keep production payments disabled.

## 2026-10-29 integration candidate — payoff retirement gateway guard

`a5a456c3` on `devin/licensing-integration-candidate` (PR #307): `license_financing_sync_by_invoice` reaches `license_finance_subscription_payoff_retire` for a paid-off offer with a `billing_suspended` gateway row while `software_gateway_mode` is `disabled` — `license_finance_anet_settings()` threw `Authorize.Net checkout is disabled.` (finance_authorize_net.php:17). The catch returned `review` but also `report_error`ed; the `CWP [ref]` log line is what the `Financing fixture payoff succeeds` check flagged. Fix: probe `license_finance_anet_settings()` before the adapter call in both `payoff_retire` and `replacement_retire`; return `review` quietly (a disabled/unprepared gateway is a configuration state, and the reserved `review` status is retried when settings return). Verified locally via reproduction (`sync_by_invoice` on a paid-off offer + `billing_suspended` sub + disabled gateway → no error line) and natives: payoff-retirement 138, replacement-payoff 138, enrollment-payoff 240.

## 2026-10-29 integration candidate — published-upgrade suite version literals

`b2fb53b3` on `devin/licensing-integration-candidate` (PR #307): the apache leg failed at `release_125_upgrade_cases.py` — `assert len(updates) == 1` on `dist/CajunWebPro-1.25.0-UPDATE.zip`. CWP_VERSION is 1.26.0 on the merged tree (Codex v1.26 rehearsal lane #288), so `tools/build.py` emits `CajunWebPro-1.26.0-UPDATE.zip` and stages it as `cwp-update-1.26.0/`. Hardcoded `1.25.0` literals in `release_125_upgrade_cases.py` (glob + updater path) and `release_125_upgrade_acceptance.py` (final-snapshot version, candidate_version report field) missed the package. Both files now read `VERSION`/`STAGE` from `tests/release_version.py` — the same binding `software_release_125_upgrade.py` already uses. The 1.24.0 published baseline and baseline SHA expectations stay literal.



## Historical source entries retained while integrating PR #162

## 2026-10-19 — backup manifest depth exclusions (non-20i)

Coordinator review correction landed on `devin/non20i-backups-foundation` (`66fe1439`): the manifest `FORBIDDEN` pattern anchored exact secrets at the path root, so a manifest could describe nested secrets such as `includes/config.php`. All protected-path rules (`config.php`, `proxy-config.php`, `.installed.php`, `.env`, `*.key`/`*.pem`/`*.p12`, any `storage/` component, `payment-master` names) now apply per path component at any depth. Collection still skips real forbidden files rather than failing. Nested-depth rejection cases added to `tests/backups/run.php`; the isolated suite passes 37 checks on PHP 8.4.25. This branch is foundation-only — the deployed `site/includes` mirror receives the same pattern on the descendant branches. No merge, deployment, provider action or release.
