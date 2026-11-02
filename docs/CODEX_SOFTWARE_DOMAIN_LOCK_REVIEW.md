# Customer domain lifecycle row-lock review — 2026-10-28

Parent: exact integration `3789bb50c90cb836530f2d5dc2400cee394763d0`. This focused branch preserves the integration writer's ownership; neither PR #266, the integration branch nor main is modified.

## Finding and correction

**P2: an in-flight customer domain mutation can commit after suspension.** `software_grant_register_domain()`, `software_grant_deactivate_domain()` and `software_grant_replace_domain()` checked active/expiry state before starting their transaction and waiting for the shared license row lock. A competing lifecycle transaction can suspend the license before the customer obtains the lock, after its check has already passed. Domain records then change while the license remains inactive. This is an unauthorized mutation after suspension, not a runtime validation or suspension bypass.

Move each existing lifecycle check immediately after `license_lock()` inside the existing transaction. A fresh consistent read then sees the lifecycle state committed by the lock holder; expiry is evaluated after the wait as well. Nested licensing transactions remain rejected. Ownership, entitlement classification, existing legacy paths, cooldowns, seat limits and validation rules are retained.

## Native evidence

The original service is byte-identical between the preserved execution archive and parent `3789bb50`. With separate native PDO connections, a controlled transaction holds the real license row lock and stages suspension. A child executes each actual customer service; the parent observes its `SELECT ... FOR UPDATE` wait before committing. All three operations mutate domains despite suspension on each of PHP 8.2.35/8.4.25 × MariaDB 10.11.19/MySQL 8.4.13: twelve reproduced cases. The license remains inactive in every case.

The new regression covers suspended and expired states across all three operations. Each case observes the held-lock wait, checks the exact denial, verifies unchanged domains and committed lifecycle state, and proves an active/unexpired positive control on the same recovered fixture. These 30 assertions pass on each of the four runtimes (120 executions). Existing rows in nine protected fixture tables are preserved. Both PHP versions use fresh grants in one preserved clone per database.

The complete native commerce suite, including the regression, passes **269 assertions on each runtime, 1,076 executions total**, in four separate fresh namespaces. Only the suite's two fixed table-prefix literals are adapted locally; reversing those substitutions restores the original fixture bytes. The helper is copied byte-for-byte. Expected injected notice-failure diagnostics are retained; unexpected diagnostics are empty. The targeted 120 overlap the full suite and are not additional unique requirements.

These are local real SQL/service tests with synthetic state and payments. They do not establish owner HTTP execution, Apache coverage or actual Authorize.net transactions. Exact combined hosted acceptance and independent review remain required. Sandbox credentials remain unavailable.

PHP lint passes on both supported versions for all three touched PHP files. Canonical build refresh completes full shipping PHP/JavaScript syntax and inventory checks; 69 build guards, 283 acceptance-artifact checks, all 99 workflow YAML parses and repository documentation/link checks pass. The tested service, native fixture and helper hashes match the exact staged Git bytes.

## Harness history and integration

Preserved initial harness failures: an assumed absent order-items table, missing explicit grant-creation transaction, and Windows process-pipe blocking that allowed a child lock timeout. Corrected table snapshots, explicit transactions and file-based child receipts resolved those test harness errors; no application guard was relaxed. Original failure logs and source copies remain in the independent review evidence.

`tests/software_commerce_native.php` runs the helper in the existing native suite; both focused native workflows include it in their path filters. No workflow timeouts, production authentication, database constraints or payment safeguards change. The canonical installer inventory is refreshed for the shipping correction. This remains implemented and locally tested on a correction branch, awaiting integration and exact combined hosted verification; no main merge, release or deployment is claimed.
