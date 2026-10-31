# Financing reconciliation snapshot review — 2026-10-28

Parent: integration `3789bb50c90cb836530f2d5dc2400cee394763d0`. This correction is independent of #285 and #286 and leaves both implementation owners' branches untouched.

## Reproduced defect

**P1: delayed owner readback can overwrite a newer recurring subscription identity.** `license_finance_subscription_reconcile()` reads the local subscription, performs a remote readback outside its database lock, and later acquires the lock to persist a non-active provider status. That update previously did not compare the locked row with the pre-readback snapshot. A replacement completed during the remote request could consequently have its new authoritative ID overwritten with the old ID. Concurrent paid-off, cancelled, replacement and reversal-recovery subscription states could also be overwritten. The active/replacing branch likewise could move a different replacement identity into review.

This is a subscription bookkeeping/identity race. The separate `license_financing.paid_off_at` one-way license protection is not shown to fail, and the reproduction does not demonstrate duplicate provider charges.

## Correction

Immediately after owner reauthentication and the existing row lock, both affected mutation blocks compare the row ID, gateway identity and lifecycle state with the snapshot used for readback. A change raises an explicit retry-reconciliation error before writing. Unchanged rows retain the existing behavior. Provider traffic remains outside the database lock, and sandbox verification remains read-only. Existing initial and replacement finalizers keep their own guards.

## Verification and limits

The regression executes the actual owner reconciliation function copied verbatim into a temporary module, with strict synthetic database/gateway dependencies. A deterministic concurrent change is injected at the remote-read boundary. It exercises 28 cases / 112 assertions: suspended, expired and cancelled replies against concurrent lifecycle changes; replacement identity changes; active/replacing handling; unchanged positive controls; and read-only sandbox verification.

The identical fixture fails the original integration service with 67 failed assertions and passes all 112 after correction on PHP 8.2.35 and 8.4.25 (`php -n`). Original-source execution changes only one reversible fixture source path. Both versions also pass existing webhook isolation, delayed lifecycle, financing policy, source-contract and prior-ARB retirement tests, plus touched PHP syntax checks. The existing isolated CI matrix runs this fixture with networking disabled and preserves its report.

These are deterministic orchestration checks, not native PDO concurrency, installed HTTP or real sandbox transactions. The correction requires independent review, incorporation by the integration writer, and combined acceptance. It has not reached main or a release. Actual sandbox credentials remain unavailable; no release approval is bypassed.
