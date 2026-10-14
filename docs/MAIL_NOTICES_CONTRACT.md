# Bounded A-08 application contract — recent email notices

Prepared against merged message-reader source `91550d956c404585545dbd766c96f36f58ae0623` while exact-main 1.18.0 release acceptance runs. Design only; nothing below is implemented yet. Full A-08 still includes synchronized provider unread state, previews and provider flag changes; this increment must be labeled **recent email notices** and must not claim that complete capability.

## Ownership and baseline

Codex owns application/schema/UI/acceptance/build/release integration after 1.18.0 publication. Preserve Devin #62 and its review 6157794515 as contract input; preserve #68 independently. Do not merge the custom parser or substitute a generic activity model. Existing workspace activity remains unchanged and requires clients.manage; the mail projection uses current mail.read plus exact account Read assignment. Mail-only staff gain no client-project access.

## Data and storage

Three additive InnoDB tables, prefix-safe and verified by a dedicated exact column/unique-key validator plus explicit relationships/collations:

- `mail_notice_windows`: primary account_id, current saved account_revision, literal INBOX, UIDVALIDITY, successful inbox request ID, checked_at. One row per account and only the last fully accepted header window. FK account → accounts RESTRICT and successful-read ID → inbox_reads RESTRICT; application requires the corresponding successful terminal result.
- `mail_notice_items`: random 128-bit lowercase hex handle, account/revision/folder/epoch/UID complete identity, slot 0–19, last actually observed server_seen, flags_observed_at. Full tuple unique independently of handle; account foreign key → window RESTRICT. All identifiers have ASCII binary comparison. At most 20 current rows per window. Persist no subject, sender, dates extracted from messages, headers, plaintext, HTML or raw MIME.
- `mail_notice_states`: item handle plus actor_id composite primary key, local opened_at or dismissed_at. Item foreign key CASCADE; user foreign key RESTRICT. These values describe this person's local interaction, never IMAP flags. No all-staff read marker.

Migration requires the current owner password, staff lock, ready email/inbox/message storage, strict schema and FK compatibility, strict audit, and rejection of nonempty unrecognized storage. Setup performs no mailbox work. Fresh installs create empty ready storage. Recovery leaves added records intact and reapplies safely, retaining the private mail key and earlier histories.

## Atomic observation

Only `mail_inbox_record` with a successful bounded batch can replace a window, inside the same transaction as its terminal result and strict audit and under the existing current-authority staff lock. Validate all identities and maximum 20 records before mutating storage. No received subject/from/date is passed to INSERT. Existing unchanged complete tuples keep their handle/local states; delete absent tuples explicitly (cascade local state), then add newly observed tuples and update the window. An empty successful window clears items. New revision or epoch clears the old window. Insert a new handle only after checking it is unused; a collision with a different complete tuple is refused and the entire result transaction rolls back. No upsert may silently repoint an existing handle. Tests inject a collision to prove refusal.

Failure/interruption records its normal request result without changing the last successful window. UI reports the prior checked time and latest failed/incomplete read where applicable. Passive display never pretends this is live provider state. Account edit, disable or grant revocation immediately filters old rows even before another observation.

`mail_message_record` may update an existing exact item with the server_seen flag actually returned by that message read and mark only its actor opened, atomically with successful terminal result/audit. It never invents a seen=true flag. It never adds a tuple to the newest-20 window or advances the window check time. A successful message outside the active window changes no notice. Failed reads change no local state. A changed-epoch outcome may invalidate an old matching window under its strict terminal transaction; it must not erase a newer window from another successful read.

## Current authority and private projection

Every page/count/action requires `mail_accounts_actor()` (excluding support, clients, anonymous, expired/revoked administrators) and current global mail.read. Owner status does not bypass the exact temporal Read assignment. Query predicates require enabled account, nonempty credentials, enabled grant, issued_at <= now, expires_at absent or > now, can_read and current saved account revision. Use current session and database reads, not an old serialized authorization result.

Apply those filters before counts, labels or rows leave persistence. Per-item POST resolution additionally calls the canonical `mail_accounts_can('mail.read', account_id)` and verifies the exact full tuple against the current window. Queries select content-free identifiers, configured account label/address, observed times, actual server flag and only the current actor's local state. Do not select ciphertext. The newest-unseen-and-not-locally-opened/dismissed count is capped at 100 (99+ UI) and is explicitly a count of recent notices, never all unread mail. Provide a bounded list of eligible accounts/check status separately, so unrefreshed/failed and a checked empty inbox are distinguishable. Offset/keyset pagination is bounded and reauthorizes each page.

## UI and routing

Separate accessible “Email notices” bell in admin and public header. Anonymous public HTML contains only a neutral hidden mount and static script URL; no identity/count/account/CSRF embedded by that mount. Authenticated same-origin private/no-store API returns the count and a fixed internal email-notices URL. Polling reads local metadata only, restores session idle timestamps, pauses when hidden, avoids concurrent requests, and clears UI on denial/unavailable/logout. Workspace bell remains independently labeled.

The private email-notices page shows account, “Recent message”, last observed time, observed server flag and this person's local opened/dismissed state. Explain “Based on the newest 20 messages from your last inbox check. Opening here does not change your mail app's read flags.” Link to the authorized inbox for explicit refresh; no network on page GET or polling. No sender/subject preview is claimed.

Each item provides a CSRF POST Open message. Bind both the opaque handle and the complete tuple in the form/action; resolve handle and require all bound fields to match the current stored tuple/window. This independent binding also rejects any accidental reuse of a retired handle for a different identity. Reuse the exact message operation's current authority, identity, rate, strict result/audit and rendering gates. Opening the bell/page changes no state. POST dismiss changes only the current actor's local marker with strict audit and reauthorization; no bulk all-mail action. A stale tuple, absent window, changed revision/epoch, or unknown handle fails generically without connecting. No arbitrary return URL.

## Required evidence

Native database checks: migration compatibility/owner password/idempotence; 20-item bound; empty/failed/interrupted windows; stable unchanged handles and local state; eviction/epoch/revision behavior; synthetic handle collision rollback; full tuple route mismatch; two staff sharing an account; current global/grant expiry/issued-at/revocation/disabled/support/session denial; mail-only staff; current filtered counts/no labels before access; no contents/ciphertext persisted or returned; audit/result rollback; real concurrent refresh/open/dismiss; session idle unchanged by passive count; exact message out-of-window does not repopulate notices.

Actual packaged HTTP/Chromium checks: private endpoint headers and GET-only behavior, anonymous/client/public cached placeholder safety, mail-only and two-staff behavior, separate accessible admin/public bells, narrow mobile layout/keyboard access, correct exact-message POST, stale/edited/denied and count clearing. Instrument fixture transport to prove all GET/poll/dismiss operations perform zero IMAP, while explicit inbox/message POST uses the intended account/epoch/UID. Ensure original workspace permissions and behavior remain intact.

Release gates: all named native/browser suites, dependency matrices, protected internal roots, immutable prior 1.18.0 upgrade/recovery with nonempty notice state and original key retained, actual screenshots, exact-main tests, Windows/Linux byte equality, draft/public fresh-download verification, updated roadmap/status/owner instructions/Devin handoff. Real mailbox/deployment remains separate acceptance. Full A-08, automatic synchronization, server flags, previews and sending remain pending after this bounded increment.
