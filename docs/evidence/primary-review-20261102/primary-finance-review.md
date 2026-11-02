# PR317 bounded finance, payment and licensing implementation review

**Disposition: do not clear the financing slice. Two retained P1 defects remain reachable in the exact published PR317 source.** This separate read-only implementation review does not approve all of PR317, its release, a provider activation, or deployment.

Reviewed target: `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd` against main `1c44ca120e7768998fafa720b3e1c4c85990c330`, on 2026-11-02 UTC. The mutable integration checkout contains later work and was not used as the source of application truth. Reads used immutable Git blobs and isolated exact84 files. No application source, index, branch, database, service, provider account or remote state was changed. No tests, provider requests, payments, mail, Actions queries or release operations were performed by this reviewer.

The complete machine-readable receipt, with exact Git blob IDs, SHA-256, byte counts, coverage levels, evidence comparisons and 25 individual subscription-function hashes, is [primary-finance-review.json](primary-finance-review.json). The fetched original comments are retained separately as [primary-finance-blocking-comments.json](primary-finance-blocking-comments.json).

## FIN-01 — P1: payoff can strand an unknown replacement subscription

**An unresolved replacement create is not durably distinct from the old subscription that payoff retires.** If the provider creates a replacement while an actual owner receipt completes payoff, and the response is lost or lacks the new ID, the application can leave the predecessor row `paid_off` and reject recovery of the active successor.

Exact target trace in [finance_subscription.php](https://github.com/afterburn25/Cajun_Web/blob/84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd/site/app/billing/finance_subscription.php):

1. `license_finance_subscription_replacement_reserve()` changes the one retained predecessor row to `replacing` (328–340). `reenroll_opaque()` retires the predecessor and then dispatches creation outside the transaction (452–476).
2. Manual ledger payoff calls `license_financing_sync_by_invoice()`, which invokes `payoff_retire()` once the financing plan is permanently paid off. That helper operates on the currently retained gateway ID. It changes that predecessor row to review, verifies its terminal status and then writes `paid_off` (154–189). It has no independent unresolved-successor record to preserve.
3. When creation returns no usable ID or throws before assigning `$newGatewayId`, `reenroll_opaque()` calls `replacement_review()` (478–499 or 512–530). `replacement_review()` only changes a matching row whose status is still `replacing` (342–350). It therefore does nothing to the now-`paid_off` predecessor row.
4. Owner reconciliation with the recovered new ID takes the `$replacement` branch and rejects any local state outside `replacing/review` (545–557). Even if a row remains review, the next check requires the financing plan to be `active/grace`, and the subsequent contract reader requires unpaid future periods (558–562). A permanently paid-off agreement cannot pass those conditions.
5. The scheduler excludes a paid-off finance plan whose retained subscription status is already `paid_off` ([finance_installments.php](https://github.com/afterburn25/Cajun_Web/blob/84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd/site/app/licenses/finance_installments.php), 117–138). It cannot discover the unretained successor.

The license payoff and existing receipts remain preserved; the defect concerns remaining automatic collection and recovery. No actual overcharge or live provider outcome is claimed by this review.

### Retained reproduction and exact-source limits

[Original PR268 comment6487669759](https://github.com/afterburn25/Cajun_Web/pull/268#issuecomment-6487669759), retrieved read-only by the parent review lane, records **4 failures among 8 assertions**, six real owner ledger receipts, synthetic provider transport/mode, and an original intermediate subscription source hash `ac883dedd0564162f1b4d06f9f7238b83b3210acda3d644750b1034b11608d89`. The final [CODEX_FINANCE_ENROLLMENT_RECEIPTS.md](https://github.com/afterburn25/Cajun_Web/blob/84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd/docs/CODEX_FINANCE_ENROLLMENT_RECEIPTS.md), line32, explicitly carries this separate blocker forward on the later frozen receipt candidate.

The raw executable and JSON report for the separate 4/8 probe were not supplied by that comment and were not recovered in this review. The source conclusion above is a fresh independent trace of exact84, not a claim that the old probe was rerun or that every intermediate source byte matches.

**Retained executable extension points:** [finance_replacement_payoff_native.php](https://github.com/afterburn25/Cajun_Web/blob/84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd/tests/finance_replacement_payoff_native.php), scenario list67 and payoff callback82–96; [finance_replacement_payoff_boundaries.php](https://github.com/afterburn25/Cajun_Web/blob/84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd/tests/finance_replacement_payoff_boundaries.php), synthetic create11–14 and verbatim-source extraction34–53. The current replacement boundary always returns the created ID and its scenario list does not contain lost-create-response or missing-create-ID cases. Thus its passing retained 138-assertion matrix does not close FIN-01. Initial-enrollment unknown-response cases in `finance_initial_receipts_native.php` and its boundary helper exercise a different state path.

### Required correction invariants

- Commit a durable replacement attempt, predecessor identity, original signed remaining contract and relevant provider identity before dispatch. Retiring the predecessor must not erase or imply completion of an unresolved successor.
- Preserve an unresolved successor hold across payoff, delayed lifecycle events, audit failure and scheduler retries; no second create may be admitted.
- Allow current-owner recovery of the verified existing successor after payoff by validating the originally reserved contract. Do not recompute a new unpaid-period contract or reopen the paid-off license to make recovery possible.
- Retain a known successor identity and predecessor history atomically before cancellation. Verify its exact contract, request cancellation when needed, and require terminal readback. Failed, rejected, lost or nonterminal results remain visible recovery obligations.
- Preserve every committed receipt, license/key/domain record, signed term, rights record and permanent `paid_off_at` boundary. Add controlled missing-ID/lost-response interleavings around real committed payoff, not merely assertions that no second create occurred.

## FIN-02 — P1: receipts after completed enrollment leave the original recurring amount active

**The receipt guard covers reservation and finalization, but not the established recurring contract after enrollment has returned.** A legitimate manual receipt for a covered installment can reduce or satisfy that obligation while the provider still retains the original full recurring amount.

Exact target trace:

1. [ledger.php](https://github.com/afterburn25/Cajun_Web/blob/84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd/site/app/billing/ledger.php), 33–59, commits a valid manual receipt, preserves partial balances and then invokes `license_financing_sync_by_invoice()` as a separate best-effort synchronization.
2. [financing.php](https://github.com/afterburn25/Cajun_Web/blob/84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd/site/app/licenses/financing.php), 323–373, retires automatic billing only when the whole financing plan becomes `paid_off`. For an otherwise `active/grace` plan it changes subscription state only for the dedicated `reversal_recovery` case. Ordinary `pending_first_payment` or `active` rows survive partial first-period and partial/full later-period receipts unchanged.
3. The scheduler only invokes retirement for whole-plan payoff or an already marked `retirement_pending` row (`finance_installments.php`, 128–157). The ordinary manual receipt path creates neither condition.
4. The eventual recurring settlement (`finance_subscription.php`, 729–788) requires an issued invoice with **zero prior payment and the entire configured ARB amount due**, at 767–769. It will refuse the reduced installment after an external provider can already have collected its scheduled full amount. Refusing local settlement preserves ledger integrity, but does not retire the existing collection instruction.

### Retained reproduction and exact-source limits

[Original PR268 comment6487933023](https://github.com/afterburn25/Cajun_Web/pull/268#issuecomment-6487933023), retrieved read-only, identifies final receipt source SHA-256 `e3b05ecaaec95653ae8a9b63e68e11eb1769d11b9db6e37e451d6e7b0450b8e1` and reports **6 failures among 30 assertions**, with six actual owner ledger commits preserved. The three scenarios are partial first period, full later period and partial later period. Three cases use the real returned `pending_first_payment` state; three use an explicitly synthetic historical `active` projection. Provider transport/mode and future invoice timing are synthetic. This is not evidence of six live established merchant subscriptions.

The raw separate 6/30 probe executable/report was not included in the comment and was not recovered here. It is explicitly excluded from the adjacent passing matrices by [finance-enrollment-receipts.json](https://github.com/afterburn25/Cajun_Web/blob/84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd/docs/evidence/finance-enrollment-receipts.json), `limitations`.

**Retained executable extension point:** [finance_replacement_receipts_native.php](https://github.com/afterburn25/Cajun_Web/blob/84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd/tests/finance_replacement_receipts_native.php), 67–102, supplies actual ledger receipt fixtures and timings `before`, `reservation`, `after_retirement`, `during_create`, `during_readback`. It deliberately marks the predecessor `billing_suspended` and then exercises replacement. It does **not** exercise an ordinary manual payment after successful initial enrollment has returned with the retained subscription still active/pending. The 230 passing assertions for that fixture cannot certify this missing case.

### Required correction invariants

- Detect every external/manual receipt that changes an installment still covered by a retained recurring contract, including partial first periods and nonconsecutive later periods.
- Commit a durable retirement/reconciliation obligation under the existing financial serialization. Preserve the real receipt even if the gateway is disabled or cancellation fails. A later page visit must not be the only opportunity to retain this obligation.
- Verify and retire the exact original subscription, with terminal readback, before admitting another automatic contract or a competing collection path. Keep retries idempotent and prevent duplicate creates.
- Re-evaluate the entire remaining signed contract. A fully paid consecutive prefix may support a later adjusted contract; partial or nonconsecutive payments cannot silently retain the original full amounts.
- Preserve permanent payoff, immutable signed terms, license/key/domain/rights records and gateway history. If the provider charge arrives after manual payment, retain it for explicit accounting reconciliation rather than count a second full local settlement or discard the external outcome.
- Add tests using the genuine returned pending-first-payment state and a separately established active state, unchanged positive controls, cancellation error/timeout/lost reply/nonterminal readback, disabled gateway, concurrent receipts and scheduler/owner recovery. Retain the original failures.

## Preserve the later owned follow-up before implementing a correction

A later retrieved [PR268 comment6488739430](https://github.com/afterburn25/Cajun_Web/pull/268#issuecomment-6488739430), dated2026-10-29 05:24 UTC, reports that the unknown-replacement/payoff recovery work remained on the author's owned branch and its expanded **454-check suite passed all four PHP/database combinations**; security and compatibility coverage was still completing. It separately says the post-enrollment overlap remained claimed.

This is a concrete pointer to preserved successor work. **Locate and reuse that source and its evidence before rebuilding FIN-01.** Root assigned a separate discovery lane. This review establishes that exact84 still contains the defect; it does not say nobody fixed it on another branch. The later comment does not itself identify the successor's exact accepted head or supply the suite files/report artifacts, and its passing claim was not independently executed here. Its full original body and metadata are in [primary-finance-blocking-comments-extended.json](primary-finance-blocking-comments-extended.json); the original two-comment receipt remains preserved.

## Exact relationship to the frozen receipt source

The historical `ebe20e9f8ee606f167116edae07840e89b9f58d5:site/app/billing/finance_subscription.php` blob was hydrated read-only by the parent. Git object: `f3da6575ed161a4795d0af94f589f5d95af062c4`; SHA-256: `e3b05ecaaec95653ae8a9b63e68e11eb1769d11b9db6e37e451d6e7b0450b8e1`; 56,745 bytes. The exact84 file is Git object `74cae87c` (full identity in JSON), SHA-256 `eae98b777fb8da6325c9ba92a5ca54c7bbe0eac01a575f01094eb5c3c05c19a9`.

Twenty-one of its 25 full function declarations are byte-identical under the declared extraction method. The changed functions and their effect are:

| Changed function | Actual later change | Why it does not close these findings |
| --- | --- | --- |
| `license_finance_subscription_payoff_retire` | Returns review when gateway settings cannot be loaded. | With available settings it still retires only the retained predecessor and can record `paid_off` while successor creation is unresolved. |
| `license_finance_subscription_replacement_retire` | Adds the same settings-availability check. | It operates only on a known retained `retirement_pending` identity. |
| `license_finance_subscription_reenroll_opaque` | Unknown/malformed creation replies become review; only whitelisted validation errors allow retry. | `replacement_review` still only changes a row currently marked `replacing`; payoff can already have changed that row. Lost-response handling is unchanged. |
| `license_finance_subscription_reconcile` | Adds stale-snapshot fences and moves two audit writes inside their transaction. | The earlier different-ID and active/grace admission guards remain unchanged. |

`replacement_review`, `replacement_finalize`, `payoff_due`, initial enrollment/finalization and recurring settlement are among the 21 unchanged declarations. `finance_installments.php`, `finance_payment_review.php`, `financing_policy.php` and the finance portal still match their recorded final receipt hashes. `financing.php` matches the payoff-retirement receipt hash exactly. This comparison reuses evidence only at its matching boundary; it does not transform the producer matrices into independent approval.

## OBS-03 — bounded provider-account provenance concern

The new software/finance schemas retain numeric gateway IDs and contract fields but not their original environment, merchant/account identity or credential revision. `software_anet_execute()` and `license_finance_anet_execute()` read current settings for each request. `payments_save()` permits changing the saved sandbox account. `software_subscription_cancel()` admits a `sandbox_test` row and sends cancellation before performing its later exact contract readback.

The source therefore permits an operation retained under account A to send a lookup/cancellation using current account B credentials after credential replacement. Existing `attempts.php` demonstrates a stronger retained `environment/account_hash/gateway_revision` binding and rejects a different account before reconciliation.

**Limits:** the current admin mode selector explicitly allows only `disabled/sandbox`; production additionally has code and owner-review gates. No current-UI sandbox-to-production transition is claimed. This review did not establish cross-merchant ID collisions, provider global-ID semantics, an actual wrong-customer cancellation or any live loss. Treat this as an unresolved original-account/transport-context requirement, not a third proven live P1. Before enabling production, bind operations to the original provider context, refuse mismatched accounts before I/O, use a consistent context through create/readback/settlement, and test credential changes with synthetic boundaries. Existing state may need an explicit reconciliation policy because its original account identity was never stored.

## Existing review evidence: what is reusable

| Evidence family | Classification and source binding at84 |
| --- | --- |
| `CODEX_FINANCE_*` correction documents and associated JSON | Most explicitly label producer self-review or pending independent approval. Valuable reproduction, function and native SQL receipts; no broad independent clearance. The machine-readable map records every matching/mismatching path hash. |
| `finance-sandbox-profile-review.json` | `finance_charge.php` and its recorded response/binding fixtures still match exact hashes. Reuse for the stated attempt-key and stale sandbox continuation boundaries only. |
| `finance-enrollment-receipts.json` | Four application paths still match; `finance_subscription.php`, admin license view, portal action file and some tests/workflow changed. Use the 21-function comparison and explicit changed-function analysis; do not reuse its whole-file source claim. Both FIN-01/02 are listed limitations, not passing cases. |
| `CODEX_SOFTWARE_PRODUCT_VALIDATION_REVIEW.md` | Exact84 API license handler blob equals reviewed PR281 `14008290...`. The grant service differs, so the source identity does not cover its whole present file. |
| `CODEX_SOFTWARE_DOWNLOAD_SOURCE_REVIEW.md` | Portal software renderer, software download endpoint and asset entitlement helper blobs are identical to the named `220cab22...` execution source. Retained focused HTTP/SQL and source-selection evidence can be reused at those paths. It is not full Apache/gateway/release acceptance. |
| `CODEX_SOFTWARE_SEAT_ACCEPTANCE_REVIEW.md` | Portal software renderer and download endpoint match named `0bf2c5d...` source. Preserve its scoped UI/support assertions; it does not authorize unrelated changes. |
| `CODEX_SOFTWARE_DOMAIN_LOCK_REVIEW.md` | Present grant file differs from the document's enclosing `81ae93c...` source. Current post-lock active/expiry checks were inspected directly. No wholesale historical source equivalence is asserted. |
| `CODEX_LICENSING_REVIEW_2026_10_28.md` | Focused module-routing/lifecycle and integration evidence, explicitly no full approval; addenda say approvals remained unrecorded. Later source and source-bound receipt comparison remain necessary. |
| `LICENSING_INTEGRATION_REVIEW.md` | The top section explicitly distinguishes historical migration/master-delivery findings from separately reviewed1.25 corrections. Those old issues were not reopened merely because the history is retained. |
| `OWNER_RELEASE_REVIEW_1.26.1.md` | Explicitly same-agent review. It cannot substitute for independent review of this whole finance/application delta. |

## Coverage and remaining work

The JSON receipt inventories57 application/dependency paths with exact hashes and a coverage description for each. Full source reads covered the finite finance subscription/charge/Authorize.Net adapters, financing state and installment scheduler, software charge reservation/orchestration/enrollment/cancellation, software asset entitlement/download endpoint, software schema/migration and finance gateway schema. Partial source coverage is precisely recorded for offers/signature/payment preparation, software grants, the payment/ledger/recurring invoice seams, selected admin activation clauses and portal finance/software actions.

This bounded pass ends once concrete non-clearance is established so a separate correction lane can proceed. It does **not** complete every originally enumerated software asset publication/product/reversal/settlement/webhook/notices path, all licensing bundle/legacy/fulfillment behavior, all finance/recurring migrations or every shared admin form. Those are identified as inventoried-only or partial coverage, not silently cleared. The recurring foundation/checkout schema and migration union also needs the parent/hosting review or follow-up. The exact original separate4/8 and6/30 raw probes remain missing from the retrieved portable evidence; preserve the comments and adjacent executable fixtures while locating the owned follow-up work.

The already-active original84 acceptance remains evidence for84 only. It cannot close an omitted scenario, validate a later correction or serve as release approval. After reusing or correcting the existing owned work, require separate implementation review of the actual delta, the relevant native SQL/concurrency and installed behavior gates, exact combined acceptance, protected integration and exact-main/release acceptance as applicable. No payment or production gate should be loosened to obtain test success.
