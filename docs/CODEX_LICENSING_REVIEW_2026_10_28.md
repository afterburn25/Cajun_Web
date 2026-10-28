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
| PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 | Existing workflows specify the matrix; this host has PHP 8.4, no Docker/WSL/database service | Complete current integration matrix; do not transfer older passes |
| Apache root/subfolder, rewrite/no rewrite | Historical Apache failure classified; package extraction checked | Passing final installed lanes |
| Fresh install and populated v1.25 upgrade/recovery | Baseline package build and extraction passed | Real populated published-v1.25 → newly versioned candidate, including interrupted recovery |
| Portal/identity, existing billing, domains/20i, email, installer | Corrections reuse existing services; no replacement subsystem | Full combined application acceptance and email retry evidence |
| Security | Focused signature/amount/activation and module-routing review; no broad approval | Complete independent review of final combined source, including ZIP publication, tenant boundaries, concurrency and delayed lifecycle events |

The green [published-v1.25 gate 40649500013](https://github.com/afterburn25/Cajun_Web/actions/runs/40649500013) is deferred while `VERSION` remains 1.25.0. The workflow skips the real install/update steps until the candidate is greater than 1.25.0. Do not count this as a real upgrade pass or bump/release the version merely to remove a status label.

Repository secret-name metadata lists `TWENTYI_API_KEY` only. The manual sandbox workflows have no environment selection and require `ANET_SANDBOX_API_LOGIN_ID`, `ANET_SANDBOX_TRANSACTION_KEY` and `ANET_SANDBOX_PUBLIC_CLIENT_KEY` from approved secret storage. No values were retrieved or requested in comments. Actual Accept.js, charge/readback, ARB enrollment/cancellation/replacement, reversals and recovery remain unverified. Source-contract checks are not gateway transactions.

The current PR also changes protected application-acceptance cancellation to supersede licensing runs, while AGENTS.md still requires that workflow to retain non-canceling evidence. The integration writer should reconcile this policy discrepancy without discarding required acceptance evidence; this focused payment correction does not change shared CI ownership.

## Handoff

Review/integrate the focused correction PR, rerun required acceptance on the combined candidate and retain both original failures. The integration writer owns current-head refresh, remaining financing failure diagnosis, full security review, version assignment, populated upgrade/recovery, final artifacts, approval, main merge and post-merge verification. Production deployment and live payments remain unauthorized. No merge, publication, deployment, customer message or live gateway operation was performed by this review.
