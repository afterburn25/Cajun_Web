# Mail-bell adapter contract (A-08)

How the application wires unread-mail bells: bounded A-06 inbox reads
drive P-07 staff-audience notifications whose deep links open the
protected message view. This document is the contract for that bridge —
**documentation only**. The integration itself (observed-state
persistence, feed filtering, public-page bell rendering) is Codex's
lane.

Dependencies: `foundation/mail-receiving` + `site/app/mail/` (the
shipped v1.17.0 receiving path — explicit `mail_inbox` newest-20 header
reads and `mail_message_read` exact-message reads; **there is no
background sync job and no persistent unread state**), and
`foundation/notifications` (PR #57 — still an isolated component
pending integration). Neither canonical contract changes beyond the
recorded extensions below. The superseded `devin/mail-transport`
`InboundSync` model (PR #68) is a preserved reference only — its
`MailMessage`/`UnifiedInbox`/`markSeen`/`isUnread` surface is **not**
what ships and must not be named as the producer. Server flags are
never written (`BODY.PEEK`); "read" state for bells is application-side
observation, not server truth.

## Required canonical extensions

Two additive changes the integration must make before bridging:

1. **New notification kind.** `Notification::KINDS` gains
   `'mail-message'`. The kind is staff-scoped by construction — the
   adapter only ever creates `AUDIENCE_STAFF` records with it.
2. **Message link ids bound to a saved generation.** A message's stable
   identity is the RFC 9051 §2.3.1.1 tuple `(account, folder,
   uidValidity, uid)` **plus the saved account revision that produced
   the observation** — the same revision gate the inbox/message routes
   enforce. `Notification::$link` only accepts
   `[a-z][a-z0-9-]*:[A-Za-z0-9_-]+`, so the adapter links to a
   deterministic **sync id** derived from the full bound identity:

   ```
   link = 'webmail-message:' . substr(hash('sha256',
          account_id . "\0" . account_revision . "\0" .
          folder . "\0" . uidvalidity . "\0" . uid), 0, 24)
   ```

   Binding the saved generation into the digest means an account
   settings change produces new links — old bells are structurally dead
   rather than re-resolvable under changed credentials. Lowercase hex
   satisfies the link grammar. The link embeds no tuple fields, so a
   copied link can't smuggle an arbitrary mailbox path.

## Observed-state store

One additive store keyed by the **full** bound identity
`(account_id, account_revision, folder, uidvalidity, uid)` →
`{sync_id, observed_unseen, first_seen_at, last_seen_at}`:

- Rows are written only inside a completed read request's authority
  window (`staff_locked` + `mail_inbox_authority`/`mail_message_authority`
  — current session, global `mail.read`, active temporal assignment,
  enabled account, saved revision). A header read refreshes the window;
  an exact-message read marks that tuple observed-seen.
- The store holds **message identity and flag observations only** —
  never subject, sender, headers or body (per the receiving path's
  no-retention rule; bell titles must therefore not carry subject text
  unless the platform accepts title-level leakage — default: title is
  "New message in {mailbox label}", sender/subject rendered only inside
  the webmail view behind the full authority chain).

## Corrected rules (Codex review requirements)

- **Current-grant filtering before feed metadata and counts.** The
  notification feed and badge must exclude every `mail-message` row
  whose account lacks a *currently active* `MailboxAssignment` for that
  staff member — filtered in the feed query (join on grants with
  temporal predicates: `enabled`, `issued_at <= now`,
  `expires_at IS NULL OR expires_at > now`, account `enabled`, plus the
  recipient's global `mail.read`) **before** titles or counts are
  computed. Revocation makes the bell disappear, not merely fail at
  open time; a revoked grant can never contribute to a badge or leak
  mailbox activity into metadata.
- **Saved-generation binding.** Resolution requires the stored
  `account_revision` to equal the account's current saved revision.
  A mismatched generation suppresses the row entirely — stale
  generation bells are dead, not "unavailable" links.
- **Full identity verification for truncated digests.** The 24-char
  sync id is a lookup handle, never an identity proof. Resolving a link
  must load the store row by sync id **and** compare every bound field
  `(account_id, account_revision, folder, uidvalidity, uid)`
  field-by-field against the request context; a digest collision or any
  field mismatch refuses rather than resolves to a different message.
- **Honest bounded-window semantics.** Badge and feed state derive only
  from tuples *actually observed* inside the newest-20 window of a
  completed header read. They must never be labeled or totaled as
  "all unread" — a mailbox with no completed read contributes nothing
  (`untested` is not zero-with-confidence), and copy must say "recent
  unread" or equivalent, not a mailbox-wide count.
- **Application-side read state.** Because the transport never writes
  server flags, a message read elsewhere stays "unread" in bell state
  until the next header request observes `\Seen`; marking a bell read
  records *opened in CajunWebPro*, not *marked read on the server*.
  Both directions must stay honest in copy and audit text.

## Unchanged rules

- **Staff audience only.** `mail-message` notifications are created
  with `AUDIENCE_STAFF`; `assertClientVisible()` makes leaking one to a
  client feed a hard error, not a display choice.
- **Grant-checked at open.** The destination view re-runs the full
  authority chain — staff session, `mail.read`, active
  `MailboxAssignment`, enabled account, saved revision — before
  rendering. A copied link or stale session grants nothing.
- **Public-page bells are authenticated staff only.** The partial
  renders only for a staff member with an active read grant on at least
  one mailbox; anonymous and client sessions get nothing, and a
  response carrying a bell must not be publicly cacheable.
- **No sending, no content.** Bells link to the message view; they
  never send, compose, forward, or carry body/header text.

## Explicitly out of scope

- Per-mailbox notification preferences beyond P-07's per-kind opt-out
  (`mail-message` is one opt-out kind; granular per-mailbox muting is a
  later increment if wanted).
- Automatic/background synchronization or polling — bells track
  explicit reads only until a separately reviewed sync path exists.
- Ticketing/service-desk mail (internal ticket messages stay in
  `foundation/desk`, per HC-12's separation note).
- Client-facing mail notifications — A-08 is admin/staff bells only.

## Integration checklist (for the Codex-side PR)

1. Add `'mail-message'` to `Notification::KINDS` (canonical, one line).
2. Add the observed-state store keyed by the full bound identity;
   populate it only inside completed `mail_inbox`/`mail_message_read`
   authority windows. Never persist subject/sender/header/body.
3. Feed: filter `mail-message` rows by the recipient's current active
   assignment + global permission before counts/titles; suppress
   revision-mismatched rows; resolve links only after full-tuple
   comparison.
4. Bell partial gated on staff auth + active read capability;
   `Cache-Control: private` (or better) when rendered.
5. Native tests: grant-revoked rows absent from feed *and* badge;
   generation-mismatch suppression; truncated-digest collision refusal;
   newest-window honesty (no all-unread claim); cross-account reads
   can't touch another staffer's bells; anonymous page carries no bell
   markup; internal-note isolation analog.
