# Replacement recurring billing when financing pays off in flight

Date: 2026-10-28 (America/Chicago). Branch `codex/finance-replacement-payoff-guard`, based on [PR #299](https://github.com/afterburn25/Cajun_Web/pull/299) `fa404e59b740b300a04c66cc870a6768352479a1`. [Replacement finding](https://github.com/afterburn25/Cajun_Web/pull/268#issuecomment-6486610355) and [shared-reader finding](https://github.com/afterburn25/Cajun_Web/pull/299#issuecomment-6486717744). All evidence here is producer self-review, pending independent approval.

## Verified problems and correction

A customer can pay off financing after the old recurring subscription is retired but while the provider creates its replacement. The actual ledger payment sync marks the old local row paid off. Previously the replacement finalizer then rejected that changed reservation, leaving the returned new subscription active and discoverable only in an audit message. The license correctly stayed paid off, but another automatic charge remained possible.

The correction synchronizes verified financing after old-subscription retirement and avoids creating a replacement when payoff is already known. For payoff during creation or first readback, it validates the original replacement contract against its signed period offset, retains the new ID and predecessor history, and then invokes the existing verified payoff retirement. Failed or uncertain readback/cancellation keeps the known candidate in review for owner/scheduler reconciliation. A successful return requires terminal provider readback. No retry creates another subscription; recorded payments and permanent payoff stay intact.

The expanded tests also exposed a #299 regression: ordinary owner reconciliation called the shared payoff reader outside a database transaction, while its unpaid-period validation requires one. The reader now takes a fresh locked offer and opens the existing staff transaction when needed, and reuses an existing caller transaction. Paid-off early-return tests had not exercised that unpaid path. No authentication or transaction assertion was bypassed.

Three existing function bodies change and one signed-contract reader is added. The financing core, domain/installation controls, ownership, resale/distribution restrictions, gateway adapter, schema and production activation gates are unchanged. The fix covers full payoff with a known returned replacement ID; unknown create outcomes and partial/noncontiguous future receipts remain separate audit items.

## Verification

- Expanded frozen parent: **40 failures among 138 assertions**, with no fixture exception. The original 2/6 probe and first-correction transaction failure are preserved.
- Corrected replacement suite: **138 assertions per lane**, covering normal replacement, payoff before create/during create/during first readback, readback timeout/mismatch, cancellation error/timeout/lost reply/still-active outcomes, six owner-reconciliation retries, duplicate-create refusal and ordinary unpaid owner reconciliation.
- Retained payoff retirement, revoked-owner and settlement tests: **290 + 5 + 41 assertions per lane**.
- PHP 8.2.35/8.4.25 with MariaDB 10.11.19/MySQL 8.4.13: **1,896 native executions**, all passing. These are executions, not distinct requirements. The first passing PHP 8.4/MariaDB replacement report was reused only after matching exact source and test hashes.
- Existing initial-handler/lifecycle/sandbox/source/replacement-contract checks: **272 passing executions** across both PHP versions. Workflow YAML and all eight shell steps parse. Canonical inventory refresh, touched PHP lint and exact staged-byte verification are recorded separately.

The new suite uses the actual enrollment/finalizer/readback/cancellation/reconciliation source, native schema/transactions, signed schedules and 27 real owner `ledger_receive()` commits per lane. Transport, provider mode, future invoice fixtures and historical initial receipts are synthetic. Expected sanitized diagnostics are 18 per replacement lane, 50 for prior retirement, one for owner denial and seven for settlement. [Named assertions, source hashes and original failures](evidence/finance-replacement-payoff.json).

## Integration gates

Implemented on an independent branch, awaiting hosted exact-head checks and independent review. Do not treat #299's 39 green component checks as closure of the two defects documented here. Its downloaded PHP 8.4/MySQL artifact was verified against frozen #299 (23 members, SHA-256 `444a1d29f5f80d2b3ecf9436b08f287c304371f4a2ee6baa64a8c40f32ae6e00`); those reports precede this correction.

The single [full acceptance run 40802174745](https://github.com/afterburn25/Cajun_Web/actions/runs/40802174745) remains on frozen #294 `0ad3df4d`: build, Apache and recovery passed; native/browser was running at 03:17 UTC October 29. It excludes the later financing corrections. Local combined candidate `cc62bcc1` contains work through #299 and has canonical/source-combination verification only; it also predates this fix.

Main remains `fdf40a25be3b9a7c2e154a1fad17ab4c7ae55827`, #266 `d062bd5c3b78f3ec265ffc8a404e253013cfa924`, and #268 `3789bb50c90cb836530f2d5dc2400cee394763d0` at the last ownership check. Neither owned branch was modified. Final combined application/security/upgrade/package acceptance, unavailable Authorize.Net sandbox credentials, independent approvals, protected integration and exact post-merge main acceptance remain required. No production activation, customer charge, deployment, main merge or release is claimed.
