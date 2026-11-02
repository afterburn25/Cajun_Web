# Core browser fixtures and bounded recurring-billing runtime — 2026-11-01

## Source and scope

Owner-requested continuation, self-reviewed by ChatGPT; not an independent audit. PR317 remains a development candidate, not an accepted release. Parent combined source8ead3459fd458725c32f4a29dc36deefbce085ae. Reviewed fixture source10a61b35376dafc1446f27d96fa2bbc87be26b4a, tree f6919cadc594014ffc58a15a5924c609e9ebb3da. The enclosing commit adds only the recurring job time budget and this handoff. Main, deployment, payment mode and delivered1.26.1 packages are unchanged.

## Core browser correction verification

Run41329499688 completed successfully. Downloaded artifact13092353945, SHA256 c6c806df2f4e86c042d1863ca9f90e2e9b50b4c8645fe32835a3c511d5580601. Verified ZIP CRC, exact source/tree identity and empty uncommitted status. All named report checks pass:333 retained acceptance helpers,60 updater/form orchestration,42 installed authority helpers and45 new original-source/negative-receipt checks. These are source/portable/helper checks, NOT newly executed installed SQL/HTTP/Chromium acceptance.

Compared all5,443 shipping/foundation/build/update/version paths byte-for-byte with8ead345: unchanged. The complete browser_acceptance.py driver remains unchanged. Ten fixture families now match current application behavior: support, catalog, payments, attempts, notifications, ledger, delivery, activity, provider catalog import and staff. Obsolete repeated-password checks are replaced by actual same-owner permission revocation with full database/schema/private-file retention. CSRF, initial login, customer credential changes, tenant isolation, consent, stale/replay and review checks remain. Restricted staff cannot review an unapproved client; the owner uses the audited Owner account review context without approving the client. Saved-package inventory is requested explicitly instead of relying on the new available-plan default. The SQL-seeded historical staff fixture receives a synthetic installation marker only after its actual migration.

Exact reversible changes are in CORE_BROWSER_FIXTURE_REPAIRS.json and the extended BROWSER_OWNER_FIXTURE_REPAIRS.json. Derived harness receipts reconstruct original historical assertions; no unconditional pass or discarded failure was introduced.

## Recurring workflow diagnosis and narrow correction

Earlier broad summaries of run41326627401 did not identify its cancellation stage reliably. The raw Actions jobs endpoint and downloaded report are the evidence used here. PHP8.4 MariaDB job124907832483 started16:33:19Z. Its initial-financing step ran16:33:49Z–16:45:19Z and SUCCEEDED; the next native-consent step was cancelled at16:46:02Z. PHP8.2 MariaDB job124907832547 also exhausted approximately12 minutes. The configured whole-job limit was12 minutes, including preparation and all suites. These timings strongly indicate budget exhaustion, not proof of a particular database deadlock or application defect. Direct timeout annotations were not available through the connector.

Downloaded PHP8.4 MariaDB artifact13091489753 and verified SHA256 464ef85780c6ca0e811b1c78b870c5c075bb3b953451eb714e5e34d3a1fc5f61 and ZIP CRC. All16 present named reports contain2,054 passing assertions, including the complete358-check initial-receipts suite. Remaining recurring reports are absent, so the aggregate remains CANCELLED/INCOMPLETE, not successful. Both MySQL lanes completed successfully according to the raw job metadata; no claim of independent reinspection of their current artifacts is made here.

The single canonical workflow edit increases the bounded whole-job timeout from12 to30 minutes. All commands, tests, matrix entries, failure handling, report collection, permissions, triggers and concurrency are byte-identical. Original workflow SHA25647b2a99181ba2574b582f80745c9ff60072230f95cda16ff8a785a77d738322b; revised SHA2569d07cf3775f742b55783e577f9d2bf2d308fc1c048bac5964680abf627af04c4, Git blob29c1f260b2a8e106536d3851084fa3a02515ac66. Parsed YAML equality was verified after removing only the timeout value. This is a runtime-budget correction, not a pass claim. The unchanged complete matrix must finish on the new candidate.

## Required next actions

1. Advance only PR317's development branch by normal fast-forward after rechecking its expected8ead345 head; do not change main or another worker's branch. Existing protected Application acceptance is non-cancelling and must retain its evidence.
2. Complete current full installed HTTP/Chromium, native SQL, Apache, populated published-release update/recovery and ordinary PR checks. Newly corrected source does not inherit a complete installed pass from old reports. Inspect actual named reports/screenshots and preserve every failure.
3. Verify the complete recurring matrix under the new bounded budget. If it still fails or times out, diagnose the exact failing suite rather than extending indefinitely or hiding it.
4. Continue the recorded feature integration inventory, including expanded contacts/client accounts, broader notifications/retries, enhanced mail and remaining hosting/control-panel work. Deliver accepted increments rather than claiming the entire roadmap is complete.
5. Normal main integration, exact-main verification and a NEW immutable versioned installation/update package remain separate outstanding steps. No production deployment, real charge, customer email, provider operation or private-account mutation is authorized by this checkpoint.
