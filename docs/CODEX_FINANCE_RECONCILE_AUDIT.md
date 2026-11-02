# Financing reconciliation audit atomicity

## Independent correction — 2026-10-28

This focused branch starts from PR #287 commit `4f368547b667c3c0a0ce713f557f428e79e1ad5e`. It retains that correction's snapshot fences and does not include #285, #286 or the provisional #288 upgrade rehearsal. Devin and the Complete Licensing Integration chat retain their branches.

The owner reconciliation function's two direct subscription mutation blocks committed before its common licensing audit call. Rejecting that audit insert therefore returned an error after committing an unaudited status/profile change. Both blocks now write the same owner-attributed audit event inside their existing account mutex and transaction, then return without emitting a second event. An audit failure rolls back the associated mutation. Gateway reads remain outside the lock, and current-owner reauthentication and stale-snapshot checks remain inside it.

## Regression evidence

The new `tests/license_finance_reconcile_native.php` executes the verbatim reconciliation and subscription-row functions, actual account mutex/transaction functions, and actual licensing audit function. It uses the canonical subscription DDL including its indexes/foreign key, the canonical audit DDL expression, a minimal synthetic referenced-offer table, and a separate PHP process that commits competing SQL changes during the synthetic provider-read boundary. A database trigger rejects the two selected audit inserts. No foreign-key checks or transaction guarantees are disabled.

On parent #287, the active/replacing and non-active audit rejection cases leave changed rows committed on every combination of PHP 8.2.35/8.4.25 and MariaDB 10.11.19/MySQL 8.4.13: eight reproduced failures. The corrected source passes all 75 named assertions on each combination, 300 executions total. Checks include preservation of newer subscription IDs and paid-off states, expected positive outcomes, read-only sandbox subscription behavior with its intended audit event, mutex/transaction release, separate committed writer connections, and audit-failure rollback. These assertions supplement the retained 112-check synthetic snapshot regression; the counts are execution counts, not unique business requirements.

The first local test setup had Windows argument/name-resolution errors; original logs were preserved and the fresh isolated servers were corrected without deleting data. The first audit harness also incorrectly expected sandbox verification to produce no audit event and simulated a PHP exception after an audit insert. Those failures were preserved. The final fixture uses the real audit implementation and a database-level insert rejection; the state rollback assertions were retained and pass only after the product repair.

The existing recurring-billing native workflow runs this regression in its four PHP/database lanes and retains its JSON report even on failure. Run locally only with explicit disposable `CWP_TEST_DATABASE=cwp_test`, loopback `CWP_TEST_HOST`, port, user and password, using a new `fq_..._` prefix. Tables and original results remain preserved; a used prefix is not reset.

This is native SQL/concurrency evidence with synthetic owner/offer/provider boundaries, not installed HTTP, actual gateway transactions, independent approval, or complete integration acceptance. Actual Authorize.net sandbox credentials remain unavailable. Required combined-source, populated-upgrade, security review and exact-main gates remain outstanding. No production activation, customer charge, deployment or release is authorized by this work.

[Exact source hashes and matrix results](evidence/finance-reconcile-audit-native.json).
