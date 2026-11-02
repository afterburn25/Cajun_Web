# Notification settings: committed owner choices and request caches

## Source and delivery state

This correction starts at published Account/Notifications candidate [`3cc10c144e2cd4e78ff5952cee9f60710a07a9ff`](https://github.com/afterburn25/Cajun_Web/commit/3cc10c144e2cd4e78ff5952cee9f60710a07a9ff), tree `e93332563bc365ef15d0e6676ad644c466302d43`. Work is isolated on `review/notification-pause-guard-20261102`. The enclosing commit identifies the corrected source. Draft [PR #323](https://github.com/afterburn25/Cajun_Web/pull/323), PR #317 and main are unchanged at this local verification checkpoint.

The published parent's [focused workflow 41365339461](https://github.com/afterburn25/Cajun_Web/actions/runs/41365339461) passed all four PHP 8.2/8.4 and MySQL 8.4/MariaDB 10.11 combinations. Each reported 47 account and 65 notification native checks; both PHP 8.4 combinations also passed 54 installed browser checks. Independent downloaded-artifact review and exact 6,785-file source-archive review are preserved as the **pre-fix checkpoint**. Those results did not cover stale request-cache settings and do not certify this correction. [Retained hosted review](evidence/notification-settings-freshness-2026-11-02/pre-fix-focused-review.json); [source artifact review](evidence/notification-settings-freshness-2026-11-02/pre-fix-source-archive-review.json).

## Defects and bounded correction

The application caches settings in `$GLOBALS['cwp_settings']` for the current PHP request. A second owner's request can commit a pause without invalidating the first request's memory.

The original dispatcher primed the cache before acquiring the account mutex and reused it inside `notification_outbox_one()`. A pause or outbound `email_mode=disabled` committed before acquisition could therefore still reach transport, create a claim audit, increment attempts and mark the delivery sent. The corrected dispatcher clears the request cache immediately after acquiring that mutex, before its owner, schema and configuration checks. Its existing claim, audit, retry, uncertain-result and final-write logic is unchanged.

The original emitter also used the request cache when deciding whether to create a future email projection. A completed owner pause could leave a later event with an unwanted pending email row. The corrected `notification_emit()` reads `notification_email_enabled` directly from the settings table inside its existing transaction. It keeps the portal/feed insertion, identity/replay checks and recipient preference behavior. It does not add an account mutex inside that transaction or change the transaction lock order.

The correction concerns settings committed before the dispatch admission or emission read boundary. It does not promise to cancel transport that has already begun, or totally order the generic outbound-settings writer with every later transport operation. That writer does not acquire the account mutex. No real mail transport was used during this work.

Shipping scope is exactly `site/app/notifications/feed.php`, `site/app/notifications/outbox.php` and the canonical installer inventory. The settings store itself, account code, other notification behavior, historical builders and every workflow remain unchanged.

## Regression and original failure evidence

Two independently retained actual-function probes first reproduced the issue using synthetic persistence and request-cache contexts. The dispatcher probe invokes the actual pump/dispatcher and changes stored settings at its synthetic mutex boundary. The emitter probe completes actual audited `notification_email_configure(false)` before beginning emission. Their source hashes, controls and original outputs remain byte-identical. They are explicitly **not SQL or installed acceptance**:

- [Original dispatcher probe](evidence/notification-settings-freshness-2026-11-02/original-dispatch-probe.php) and [results](evidence/notification-settings-freshness-2026-11-02/original-dispatch-probe.json).
- [Original emitter probe](evidence/notification-settings-freshness-2026-11-02/original-emitter-probe.php) and [results](evidence/notification-settings-freshness-2026-11-02/original-emitter-probe.json).

The new native regression runs through the existing `tests/notification_center_native.php` entry point. Its only changes to that file are an include and a final call; removing those two additions reconstructs the complete original file byte-for-byte. All 65 original assertions stay intact and execute first. The retained include adds 24 checks with real PDO queries, the actual account mutex, and independent PHP worker processes:

- Parent dispatch requests first cache enabled/smtp settings. A separate process commits an actual owner pause, or an outbound-disable transaction. The parent proves the stored value changed while its own cached value stayed old. Dispatch must return `paused`, make zero synthetic callback invocations, preserve every feed/preference/delivery record, retain attempts and claim audit rows exactly, and leave no open transaction. Explicit resume must send that retained pending item once.
- Parent emission requests first cache the old owner choice. A separate process completes actual audited pause or resume. Emission must retain its private portal/feed projection and create an email row only for the current enabled choice. All earlier records and claim audit rows remain unchanged.

The same regression was first run with the exact original application bytes. The original 65 checks passed, as did the new cache and separate-process setup assertions. It then failed at **“Committed pause refuses dispatch before synthetic transport.”** The report contains 68 passing rows plus the failed assertion and its captured exception. Direct SQL after that failure showed stored enable flag `0`, delivery `sent`, attempts `1`, and one claim audit. The failure stops the native suite before its later disable/emission cases; their original behavior is separately evidenced by the actual-function probes above.

With the correction, the complete native suite passes **89/89**, exit 0, with empty stderr on PHP **8.3.6** and MariaDB **10.11.14**. It runs as unprivileged UID 65534 in an exact hashed copy of the source, using a newly initialized disposable database on loopback port **33309**. Ports 33306/33307 and the frozen primary acceptance workspace are untouched. Transport is a recording synthetic callback; no provider, payment, customer-message or live-service operation occurs.

| Native evidence | Result |
| --- | --- |
| [Original native report](evidence/notification-settings-freshness-2026-11-02/original-native.json) and [execution/source hashes](evidence/notification-settings-freshness-2026-11-02/original-native-execution.json) | Expected regression failure after all original 65 checks |
| [Original persisted post-failure state](evidence/notification-settings-freshness-2026-11-02/original-postfailure-observation.json) | Pause stored, but synthetic delivery was sent once |
| [Corrected native report](evidence/notification-settings-freshness-2026-11-02/corrected-native.json) and [execution/source hashes](evidence/notification-settings-freshness-2026-11-02/corrected-native-execution.json) | 89/89, exit 0, empty stderr |

An initial seed attempt could not traverse the private workspace parent as the unprivileged runner and failed before application assertions. Its [stderr is retained](evidence/notification-settings-freshness-2026-11-02/initial-seed-permission.stderr.log). A verified `/tmp` runtime copy resolved that environment issue without changing shared directory permissions. Private database dumps, generated keys and runtime configuration stay outside Git.

## Preserve the historical source proofs

The new [bounded receipt](NOTIFICATION_SETTINGS_FRESHNESS_CORRECTION.json) and [verifier](../tests/notification_settings_freshness_provenance.py) pin actual published parent hashes for both modules, inventory, native entry and the source-contract adapter. Exact reversible edits reconstruct every predecessor and compare it to its actual `git show 3cc...` blob. The regression include is separately hashed. The guard rejects broader or untracked shipping changes and verifies that original receipts/builders and focused/full workflow bodies remain byte-identical.

The small `account_notification_contract.py` adapter verifies this current correction first, then supplies exact reconstructed predecessor bytes only to the historical source comparison. Actual native/browser acceptance continues to execute the current application and retained tests. No historical receipt is rewritten, and no historical application implementation substitutes for the current runtime.

The new proof passes **43 checks**, including **28 negative cases**. The existing workflow proof passes 56, fixture combination 36, core navigation 50, provider provenance 241, navigation helper 54, account policy 49 in each default/minimal run, administrator policy 35, and maintained acceptance helpers 575. The account/notification, account, notification, ticket and combined-runtime source contracts also pass. These counts overlap and are not added together. Touched PHP syntax and the canonical refresh are recorded with the [source-check evidence](evidence/notification-settings-freshness-2026-11-02/source-checks.json).

The local evidence-capture wrapper initially expected a list where the helper suite reports an integer count. All underlying output files were retained; only that helper command was rechecked for a separately captured exit status before the pending canonical refresh continued. The [capture failure record](evidence/notification-settings-freshness-2026-11-02/capture-runner-failure.json) distinguishes this bookkeeping failure from an application assertion failure.

## Remaining gates and handoff

This checkpoint is **implemented and locally verified; separate review and publication are pending**. Current-source proof commands were run against the uncommitted correction. Clean immutable-commit admission, the corrected hosted PHP/database matrix and installed browser checks are still required. The unchanged current-source workflow invokes the expanded native suite through its existing command; no workflow body or minimum was weakened.

After separate review, the integration owner should commit these exact bytes, run clean source admission, and update the existing draft PR #323 branch through its normal push event. Preserve the successful 3cc hosted checkpoint and all original failures. Full combined acceptance, reviewed main integration, exact-main verification and a verified release remain distinct gates. P-07 is not promoted to integrated, released or live by this correction.
