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

# CajunWebPro current status

<<<<<<< HEAD
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

||||||| 3789bb50
=======
## 2026-10-28 — retain unresolved financing replacement outcomes for review

Independent review of frozen integration `3789bb50` reproduced unresolved ARB creation responses restoring `billing_suspended` and admitting another create. The focused correction keeps unknown/malformed/duplicate/internal outcomes in `review`; only explicit recognized request-validation rejection without a candidate identity permits retry. The same 100 assertions fail original source (42 failures) and pass corrected source on PHP 8.2/8.4; five existing financing/isolation suites also pass. These are exact function bodies with strict synthetic dependencies, not real gateway transactions or native SQL. Protected Application run `40767087342` is preserved, and the integration writer retains branch ownership. [Finding, evidence and limitations](docs/CODEX_FINANCE_REPLACEMENT_OUTCOME_REVIEW.md).

>>>>>>> refs/codex-286
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
