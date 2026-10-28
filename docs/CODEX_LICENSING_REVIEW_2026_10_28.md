# Independent licensing integration review — 2026-10-28

## Ownership and source

The owner assigned this review to support the existing PR #266 implementation. After discovering a second active chat, the owner explicitly kept **Complete Licensing Integration** as the integration writer. This correction uses `codex/licensing-webhook-isolation`; it does not write `codex/licensing-integration-review`, Devin's feature branch, or `main`.

- Frozen review/build source: `6045b801fc7653125da5d11c0a8cd956ce58a26c`.
- Refreshed PR #266 head: `5ba2a37da0bf8aa22b1162acb7f6dca142da969a`. The only intervening change is the financing browser fixture's `RecurringBillingTerms` import; it does not change shipping code.
- Main: `fdf40a25be3b9a7c2e154a1fad17ab4c7ae55827`.
- Latest published release: [v1.25.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.25.0), source `b79c67110829126916e3a65e616191542448bccc`.
- PR #266 remains a draft; the review-submission API returned no submitted reviews at this checkpoint. This report is not a full security approval or merge authorization.

## Reproduced defects and focused correction

**P1 — optional financing prevents software capture handling.** `software_webhook_process()` checks financing before software one-time capture handling in both sandbox and production. `license_finance_charge_webhook_review()` previously queried `license_finance_charges` without checking preparation. A software-only installation therefore raised a missing-table error and returned HTTP 503 for an otherwise valid notification. The correction returns the existing unmatched result before querying unprepared finance storage, allowing the software handler to continue. It does not fulfill, credit, or ignore a matched financing payment.

**P1 — independent finance activation prevents software reversal handling.** With finance storage prepared but financing production activation disabled, `license_finance_payment_reversal_review()` previously loaded finance gateway settings before identifying the original transaction. This caused unrelated software refunds and voids to fail on the finance production gate. The correction validates the transaction and identifies financing ownership first. A matched finance reversal still requires the existing production gate; software reversals continue to their own verified handler.

The two service changes are deliberately narrow. No schema, customer identity, gateway credentials, production activation, ledger math, signature verification, or paid-transition policy changes. Installer hashes are regenerated for both changed files.

`tests/finance_webhook_isolation.php` calls the real PHP services with strict read-only database doubles. On the frozen source it reproduces the absent-table query and both unrelated reversal failures. With the correction all 10 cases pass on local PHP 8.4.25, both normal configuration and `php -n`. Cases include recognized capture replay, amount mismatch, required finance production activation and exact reversal identity. The focused CI workflow runs this regression on PHP 8.2 and 8.4 with container networking disabled. Its results must be inspected independently; local doubles do not establish native SQL or actual gateway acceptance.

## Executed local evidence

### Follow-up: delayed financing lifecycle events

**P1 — delayed lifecycle events can clear financing safeguards.** On `5ba2a37d` and routing correction `f6d49812`, the real lifecycle service accepts `cancelled + failed -> payment_failed`, `billing_suspended + failed -> payment_failed`, `reversal_recovery + suspended -> billing_suspended` and `replacing + suspended -> billing_suspended`. The first two remove catch-up eligibility. The latter two clear states that prohibit ordinary settlement; replacement finalization also rejects the overwritten reservation, and its exception handler only fences a row that is still `replacing`.

The follow-up on `codex/licensing-lifecycle-ordering` preserves cancellation, reversal recovery and in-flight replacement states, alongside the existing review/payoff holds. Confirmed suspension survives a delayed failure. Normal active/failure transitions to suspension, cancellation and review still occur with the same transaction, lock and audit. No new state, schema, gateway call or ledger rule is introduced. This branch is stacked on [#269](https://github.com/afterburn25/Cajun_Web/pull/269) to keep the follow-up diff focused.

`tests/finance_subscription_lifecycle.php` exercises the actual service with strict in-memory SQL doubles. Seventeen of 45 cases fail before the fix; all 45 pass after it, under PHP 8.4.25 normal and no-INI execution. Coverage includes all five lifecycle events against review/payoff/cancelled/reversal/replacement states, normal progression, duplicate delivery, archived/unknown IDs, unprepared storage, retained recovery eligibility and settlement fences. This is deterministic interleaving coverage, not a real concurrent database or gateway run.

### Routing correction packages

The focused [#269 CI run 40650392930](https://github.com/afterburn25/Cajun_Web/actions/runs/40650392930) passed both PHP 8.2 and 8.4 service-test jobs, including evidence uploads. Repository checks also passed at `f6d49812`. This result covers the routing correction; the lifecycle follow-up requires its own run.

Exact [#269](https://github.com/afterburn25/Cajun_Web/pull/269) commit `f6d49812f5aafb03a85e4b0a3dc20f5bfbabbed5` built successfully. All three archives passed every-member SHA-256, manifest/CRC, path/collision, no-nested-ZIP and direct-extraction checks. Version remains the development label 1.25.0; these are not replacement published-release assets.

| Correction artifact | Members | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD | 2,956 | `e282a81a99cd1e1d205e0d647fa2aaa361bc450ae625155e921c0a8d00b35cf7` |
| FULL-PROJECT | 6,101 | `2f0d7ea7787481153fb257721417b60404261f40789f7256b511594784c3b5be` |
| UPDATE | 2,943 | `bbb583ab323c728ef9788d854e880843aa79b59984634bc274326a8cfddffbe8` |

These hashes precede the lifecycle follow-up and must not be used to certify it.

### Baseline and routing tests

- The combined corrections also pass all 55 new actual-service cases and the 12 existing pure/source suites using PHP 8.2.35 with `-n`. The isolated Windows runtime came from the [official PHP release index](https://downloads.php.net/~windows/releases/releases.json); the archive SHA-256 was verified as `ebe75134c03869826cb1714d31fd5eb96afefa6d9fd35d786971c03020e7bce2`. This complements local PHP 8.4.25 and does not substitute for the Linux/database matrix.
- PR #266 advanced to `937bec7f10ac24ddf4c60d8209aec2a6f9a05d4f` after the reviewed `5ba2a37d`: only the financing browser test's stale recovery-link wording changed. No shipping code changed. The first failure in run 40649494078 was that wording assertion after the entitlement checks; the newer run must be assessed separately.
- On `937bec7f`, [finance run 40650664410](https://github.com/afterburn25/Cajun_Web/actions/runs/40650664410) now passes recovery-link generation/opening and support denial of agreement viewing, then fails `Staff support session cannot trigger financing payment actions`. The action controller rejects support mutations, but the finance renderer emits its own HTTP 403 message before rendering the controller error. The test checks the controller's message; response/audit evidence must confirm the denial without weakening the assertion. This diagnosis was handed to the existing fixture writer. Catch-up/replacement/payoff browser cases after this assertion have not executed in that run.

- Twelve existing suites passed: software license policy; commerce contract; production gate; gateway amounts; payment CSP; payment reversal; subscription events, gateway and periods; signed webhooks; financing policy; financing source contract.
- The payment-link view cases are an included native suite, not a standalone entry point. Direct invocation returned its expected CLI/bootstrap guard exit 2; it is **not** counted as passed.
- Changed PHP files and the new regression pass syntax checks. The new workflow parses. Documentation continuity and local links pass.
- Frozen source `6045b801` built successfully using `python tools/build.py --commit ...`, including all shipping PHP and JavaScript syntax checks and canonical dependency validation.
- All three baseline packages passed CRC, exact manifest inventory, every member SHA-256, case-collision/path checks, no-nested-ZIP checks and direct extraction. These are development artifacts labeled 1.25.0, not replacement release assets.

| Baseline artifact | Members | SHA-256 |
|---|---:|---|
| COMPLETE-UPLOAD | 2,956 | `d84600f92895cd88a55b67a02831fc302fbd37cf175db6d77390941bb212437d` |
| FULL-PROJECT | 6,098 | `be94f49f21149f6d1091ca26b6844c6ea8a0bf169bf7c9d6108be54fd10a12f1` |
| UPDATE | 2,943 | `24580d5a3c9b507d3640a73342f606a854c37474c8a31ceb0bdff270886c4622` |

These hashes describe the baseline, **not** the corrected source. Correction and final integration artifacts need their own exact-commit evidence.

## Historical failure classification

Complete logs were fetched for the failing jobs below. Later results must remain tied to their own commits.

| Historical run | First cause / observed result |
|---|---|
| [40615027920](https://github.com/afterburn25/Cajun_Web/actions/runs/40615027920) | Native: `Enable sandbox trials first.` after 69 sandbox checks. Apache: `rewrite 1.23.0 recurring upgrade requires explicit owner preparation`. Build and recovery jobs succeeded; browser was skipped after native failure. |
| [40615027741](https://github.com/afterburn25/Cajun_Web/actions/runs/40615027741) | Renewal dispatch reported failure after six checks; preceding 588 recurring checks passed. The complete job log does not name the failing dispatch assertion; retained JSON evidence is needed to attribute that exact failure. |
| [40615027779](https://github.com/afterburn25/Cajun_Web/actions/runs/40615027779) | All four native test steps passed. PHP 8.2/MariaDB failed later because GitHub's CreateArtifact service timed out after five attempts. This is evidence-upload infrastructure failure, not a WordPress assertion failure. |
| [40615027824](https://github.com/afterburn25/Cajun_Web/actions/runs/40615027824) | Native: `Enable payment preparation first.` Schema-v2 fixture corrections are present in the reviewed source; historical failure is retained. |

At `6045b801`, [finance run 40648391263](https://github.com/afterburn25/Cajun_Web/actions/runs/40648391263) passed agreement privacy, another-client denial, signature evidence, zero-down activation, domain replacement, owned bundle and project-release checks, then failed in the `late` fixture: `RecurringBillingTerms` was not imported. That exact cause was communicated on PR #266 and subsequently corrected by `5ba2a37d`. It does not prove the remaining finance cases passed.

At refreshed head `5ba2a37d`, [finance run 40649494078](https://github.com/afterburn25/Cajun_Web/actions/runs/40649494078) is failed. Application [40649500122](https://github.com/afterburn25/Cajun_Web/actions/runs/40649500122) is pending, recurring [40649493682](https://github.com/afterburn25/Cajun_Web/actions/runs/40649493682) is in progress and renewal dispatch [40649500111](https://github.com/afterburn25/Cajun_Web/actions/runs/40649500111) is queued. Those are not passing acceptance results. No duplicate runs were dispatched for queue delay.

## Integration matrix and remaining gates

| Area | Evidence available from this review | Still required |
|---|---|---|
| Master/standard/trial/temporary, expiry, domains, dependencies, installation/update entitlements | Local pure policy and source-contract suites passed | Final native and installed authorization/download coverage |
| One-time and recurring payment contracts, signatures, amounts, reversals, production safeguards | Local pure/source suites; two reproduced routing corrections | Native concurrency/replay and real sandbox execution |
| Customer-specific finance agreements, signature evidence, zero-down, active downloads | Named installed checks passed before the frozen run's later failure | Entire overdue/grace/suspend/catch-up/payoff and independent-agreement run |
| PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 | Both PHP versions pass the 55 isolated cases and 12 pure/source suites locally; no Docker/WSL/database service on this host | Complete current native integration matrix; do not transfer older passes |
| Apache root/subfolder, rewrite/no rewrite | Historical Apache failure classified; package extraction checked | Passing final installed lanes |
| Fresh install and populated v1.25 upgrade/recovery | Baseline package build and extraction passed | Real populated published-v1.25 → newly versioned candidate, including interrupted recovery |
| Portal/identity, existing billing, domains/20i, email, installer | Corrections reuse existing services; no replacement subsystem | Full combined application acceptance and email retry evidence |
| Security | Focused signature/amount/activation and module-routing review; no broad approval | Complete independent review of final combined source, including ZIP publication, tenant boundaries, concurrency and delayed lifecycle events |

The green [published-v1.25 gate 40649500013](https://github.com/afterburn25/Cajun_Web/actions/runs/40649500013) is deferred while `VERSION` remains 1.25.0. The workflow skips the real install/update steps until the candidate is greater than 1.25.0. Do not count this as a real upgrade pass or bump/release the version merely to remove a status label.

Repository secret-name metadata lists `TWENTYI_API_KEY` only. The manual sandbox workflows have no environment selection and require `ANET_SANDBOX_API_LOGIN_ID`, `ANET_SANDBOX_TRANSACTION_KEY` and `ANET_SANDBOX_PUBLIC_CLIENT_KEY` from approved secret storage. No values were retrieved or requested in comments. Actual Accept.js, charge/readback, ARB enrollment/cancellation/replacement, reversals and recovery remain unverified. Source-contract checks are not gateway transactions.

The current PR also changes protected application-acceptance cancellation to supersede licensing runs, while AGENTS.md still requires that workflow to retain non-canceling evidence. The integration writer should reconcile this policy discrepancy without discarding required acceptance evidence; this focused payment correction does not change shared CI ownership.

## Handoff

Review/integrate the focused correction PR, rerun required acceptance on the combined candidate and retain both original failures. The integration writer owns current-head refresh, remaining financing failure diagnosis, full security review, version assignment, populated upgrade/recovery, final artifacts, approval, main merge and post-merge verification. Production deployment and live payments remain unauthorized. No merge, publication, deployment, customer message or live gateway operation was performed by this review.
