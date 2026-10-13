# Staff invitation persistence and current-source alignment

**Application integration — 1.2.0 candidate:** [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10) supplies the [staff adapter and UI](ADMIN_ACCOUNTS.md), explicit migration, account/reset/session lifecycle and last-owner serialization. These canonical classes are bundled verbatim by the release builder. Original independent-component history below is retained; statements about absent application integration describe that earlier checkpoint. Mailbox connections and grants remain deferred.

2026-10-13, A-05 increment on `codex/platform-foundation`. This is an independent, database-backed component. It is not yet connected to the administrators UI, current application account mutations, invitation delivery or a release package.

## Source inspected

The separate 1.1.0 worker has recovered application source under `site/` on `codex/finish-1.1.0`. Inspected snapshot: `4a8b4c4`, including `site/app/core.php`, `site/app/schema.php`, `site/admin.php` and `site/app/workspace.php`. This is a draft branch, not a released version. No files on that branch were changed here.

The staff `users.auth_version` column is VARCHAR(64), and login/reset/account-change paths generate random 32-character hex strings and compare them with `hash_equals`. The prior foundation's integer revision assumption was therefore unsuitable. [AuthorizationVersion](../foundation/access/AuthorizationVersion.php) now validates opaque 32/64-character lowercase hex tokens and compares them without numeric coercion. AccessContext version inputs are strings; preserve their exact values. Do not migrate existing random strings into integer counters or map every existing administrator to an owner.

The draft has existing staff password resets and separate client invitation logic. This new component covers additional **staff setup invitations**, not a replacement for either existing flow. The final account lifecycle/migration must reconcile those systems and revoke all applicable credentials consistently.

## Implemented behavior

[StaffInvitationStore](../foundation/invitations/StaffInvitationStore.php) uses native PDO MySQL/MariaDB with exception mode and an existing InnoDB table. It rejects unsafe table identifiers and nontransactional/missing tables. `createTableSql()` returns explicit additive DDL; constructing the store never runs a migration.

- `issue(subjectId, authorizationVersion, ttlSeconds)` generates 32 cryptographic random bytes encoded as 64 hex characters. Only a purpose-separated SHA-256 digest is stored, bound to the staff subject and current authorization token. Default expiry is 24 hours; allowed lifetime is 60–86,400 seconds. One row per staff subject means resending replaces the previous link.
- `redeem(token, activate)` locks the invitation row in its own transaction, checks current expiry/consumption, invokes the trusted activation callback and consumes the link only when the callback returns exactly `true`. False or exceptions roll back callback writes and leave an unexpired link available for a legitimate retry. Expiry is rechecked after lock acquisition and after activation. Two successful redemptions of the same link are prevented by the row lock and consumed state.
- `revoke(subjectId)` removes the invitation and may participate in the account service's own transaction.
- [IssuedInvitation](../foundation/invitations/IssuedInvitation.php) returns the raw token once to the trusted caller for delivery. Debug/JSON views redact it. Do not log, serialize, store or expose the raw property outside its intended setup link; redacted views are not a substitute for handling secrets carefully.

Redemption refuses an already-open caller transaction. The activation callback must use the supplied PDO connection and transactional account tables, return a boolean, and never commit, roll back, run DDL or perform external side effects. The store can roll back transactional database writes, not email, network actions or a callback's forbidden explicit commit.

## Required application adapter

These conditions remain required before shipping the feature:

1. Authorize the requesting owner/staff member through existing authentication and current permission data before issuance/reissue/revocation. The persistence class itself is not an authorization boundary or HTTP endpoint. Validate CSRF, rates, target staff identity, role/grant limits and last-owner invariants in the account service.
2. Apply an additive migration after reviewing the final 1.1.0 schema. The isolated component accepts a configured table name; no speculative table was added to the application's schema or installer.
3. In the activation callback, lock/reload the actual staff row using the invitation's subject, confirm that account is eligible/active and its exact current authorization token matches the one bound to the invitation, validate and hash the new password using the existing policy, and rotate the authorization token. Return false for missing, disabled or superseded accounts. Do not trust account ID, role or permissions supplied alongside the link by a browser.
4. Keep a consistent lock order: invitation row first, then account row when an operation touches both. Deactivation and relevant permission/account changes must rotate the account token and revoke setup/reset tokens in the same transaction. Use bounded deadlock retries at the account-service boundary. Do not automatically retry an activation callback with external side effects.
5. Deliver a generated link only after its issuance transaction commits. Construct it from the configured trusted HTTPS origin, never a request Host header. Do not consume it during GET/preview or email-link scanning; consume only a validated explicit setup submission. Do not automatically sign the recipient in after activation.
6. Use generic failure responses, request rate limits, `Referrer-Policy: no-referrer`, no-store pages and no third-party resources on token-bearing setup pages. Never log tokens, passwords or the full setup URL. Return truthful mail-send status; provide owner-only copying if SMTP is unavailable. No mail is sent by this component.
7. Review source/installer inventory, package hashes, upgrade behavior and actual browser/native acceptance before enabling the UI or adding files to a release.

Last-owner concurrency, staff grant delegation, live session invalidation, existing reset-token revocation, SMTP delivery and setup forms are not implemented by this store. A-05 remains incomplete.

## Native verification

[tests/invitations/native.php](../tests/invitations/native.php) requires an explicitly authorized disposable `cwp_invitation_test` MySQL/MariaDB database. It creates uniquely named synthetic InnoDB fixtures and drops only its own tables. No production schema is used. The fixture activation callback loads current synthetic staff state and rotates its opaque authorization token in the transaction.

Cases cover digest-only storage, bounded expiry, redaction, malformed/unknown/reused links, resend/revoke, inactive/stale accounts, callback failure/exception rollback, expiry during activation, nested transaction rejection, rolled-back issuance, nontransactional-table rejection, and two simultaneous PHP processes redeeming the same invitation. The concurrency test requires exactly one accepted result and one committed activation.

[Staff invitation CI](../.github/workflows/staff-invitations.yml) supplies an isolated MariaDB 11.4 service and official PHP 8.2/8.4 containers with PDO MySQL. The fixture test credentials are synthetic and apply only to those ephemeral services. Local syntax/access checks are separate from the native database result; record CI run links before claiming database acceptance.

Verified source commit `718dbc4867ff7fe8cf214d3ce10410a9a41570a0` in [PR #6](https://github.com/afterburn25/Cajun_Web/pull/6): [native branch run](https://github.com/afterburn25/Cajun_Web/actions/runs/38351494316) passed 146 access checks and 46 native invitation checks on each of PHP 8.2.34 and 8.4.26 against MariaDB 11.4.13. This includes two-process redemption and rollback tests. The [native PR merge-ref run](https://github.com/afterburn25/Cajun_Web/actions/runs/38351532791), [access checks](https://github.com/afterburn25/Cajun_Web/actions/runs/38351532555) and [documentation checks](https://github.com/afterburn25/Cajun_Web/actions/runs/38351532658) also passed. This proves isolated component persistence, not the pending application adapter, installation, browser flows or deployment.

## References

Reviewed 2026-10-13: PHP [cryptographic random bytes](https://www.php.net/manual/en/function.random-bytes.php), [timing-safe comparison](https://www.php.net/manual/en/function.hash-equals.php), MariaDB [transactional row locking](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update), and OWASP [single-use account-recovery token guidance](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html). The account-setup design applies these token principles; it does not certify the unintegrated application.
