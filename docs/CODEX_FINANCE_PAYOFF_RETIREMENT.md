# Verified recurring-billing retirement after financing payoff

Date: 2026-10-28 (America/Chicago). Branch `codex/finance-payoff-subscription-retirement`, based on frozen [PR #298](https://github.com/afterburn25/Cajun_Web/pull/298) `4405433dc1dc97b9addd0a9b7967318df082eb82`. [Verified finding and ownership claim](https://github.com/afterburn25/Cajun_Web/pull/268#issuecomment-6486115622). All verification below is producer self-review; independent approval remains required.

## Problem and correction

Reservation-time admission cannot cover payments committed while ARB creation is in flight. The original opaque/profile finalizers retained pending recurring billing after actual owner ledger operations had already recorded permanent payoff. Owner-recorded payoff of an existing subscription also left billing active, and the scheduler excluded paid-off agreements from further work.

Initial finalization now checks permanent payoff and authoritative signed-period ledger evidence under the account mutex. It durably retains the returned gateway ID and exact contract in review before cancellation. A shared payoff-retirement path checks current agreement/license/project ownership and payoff, reads the matching gateway contract, cancels active or suspended billing, and requires a terminal readback. It commits the local paid-off billing state only if the retained row still matches the reserved snapshot. Uncertain transport, rejected cancellation, nonterminal readback, changed customer ownership or newer local data retain review/evidence for reconciliation.

Committed manual payments and final verified recurring receipts use the same retirement path. Scheduler selection includes paid-off agreements with unresolved billing; owner reconciliation can retire the retained ID. Reconciliation repeats current owner authentication inside its reservation and final transaction. No retry creates an ARB subscription. Cancellation failure cannot roll back a recorded receipt or the permanent financing unlock.

The core `license_financing_sync()` and its permanent-payoff early return are unchanged, as are domain/installation/owner controls and resale/distribution rights. All existing Authorize.Net adapter functions and production activation gates are byte-equivalent at the function-body boundary. Replacement retirement keeps its separate stricter rule refusing an active prior subscription. No schema or version change is introduced.

[Authorize.Net's recurring-billing reference](https://developer.authorize.net/api/reference/features/recurring-billing.html) documents active scheduling and canceled/expired/terminated states. This code requires verified terminal evidence and does not assume that a transport timeout proves cancellation. Real merchant sandbox validation remains a release gate.

## Evidence

- Frozen parent: 57 failures among 270 assertions; original 2/6 probe and 48/236 reproduction are preserved.
- Corrected retirement: 290 assertions per PHP 8.2.35/8.4.25 × MariaDB 10.11.19/MySQL 8.4.13 lane. The 27 lifecycle scenarios exercise both initial callers and manual payoff, including cancellation timeout, lost response after cancellation, rejected cancellation, active readback, mismatched contract and existing terminal states. Five additional native controls cover unpaid/state-only evidence, changed customer ownership, incorrect password and a separate committed writer changing the retained subscription after readback.
- Revoked owner: five assertions in an isolated PHP process per lane verify the actual 303 login redirect, no provider request, and preserved subscription/payment/payoff records. The original in-process redirect exits are retained as incomplete evidence, not passing tests.
- Final recurring settlement: 41 assertions per lane cover actual ledger and gateway-transaction mapping commits, payoff, uncertain cancellation, wrong-amount rejection and delayed duplicate refusal.
- Existing initial-payment/admission tests: 240 + 207 assertions per lane remain green. Total native executions are **3,132**, including **1,344** new assertions across the four lanes. These are execution counts, not distinct feature counts.
- Handler/lifecycle/sandbox/source/replacement contract checks: **272** across both PHP versions. Canonical installer refresh, touched PHP syntax, workflow YAML/bash and exact staged bytes are verified separately.

Native schema, signed schedules, ownership/session checks, ledger commits, row locks, subscription persistence, gateway readback validation and cancellation orchestration are real application source. Provider transport/mode and future-period invoices are synthetic. Each main retirement lane performs 81 actual owner `ledger_receive()` commits against fixtures and one separate PDO writer for the stale-snapshot case; historical initial receipts are explicitly seeded. Expected sanitized diagnostics are 50 per retirement lane, one per owner-denial process, seven per settlement lane, 38 per existing admission lane and 54 per existing initial-payment lane. [Named assertions, original failures, report hashes and exact source](evidence/finance-payoff-retirement.json).

## Integration status

Implemented on a separate branch, awaiting hosted exact-head acceptance and independent review. Main remains `fdf40a25be3b9a7c2e154a1fad17ab4c7ae55827`; primary #266 remains `d062bd5c3b78f3ec265ffc8a404e253013cfa924`; integration #268 remains `3789bb50c90cb836530f2d5dc2400cee394763d0`. Their owned branches were not modified. #298 has all 39 component checks green.

The single full cloud-follow-up run [40802174745](https://github.com/afterburn25/Cajun_Web/actions/runs/40802174745), on frozen #294 `0ad3df4d`, has passed build, recovery and Apache at 02:45 UTC; native/browser remains running. It excludes this later financing work. The existing four-lane recurring workflow retains the new reports; no duplicate full workflow was dispatched.

Remaining gates include review of the unchanged replacement-creation boundary, final combined installed/browser and populated-v1.25 upgrade acceptance, PHP/database/Apache matrix, exact combined packages, unavailable Authorize.Net sandbox credentials, independent approvals, normal main integration and post-merge acceptance. Earlier local combined `640049eb` packages remain bounded historical evidence and predate this correction. No live activation, customer charge, deployment, merge or release is authorized or claimed by this evidence.
