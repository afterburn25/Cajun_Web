# Mail-bell adapter contract (A-08)

How the application wires unread-mail bells: A-06 webmail unread state
drives P-07 staff-audience notifications whose deep links open the
protected webmail message. This document is the contract for that
bridge — **documentation only**. The integration itself (sync,
persistence, public-page bell rendering) is Codex's lane.

Dependencies: `foundation/webmail` (PR #59 — integrated into the
application in v1.13.0; mirrors live at `site/includes/foundation/webmail/`),
`foundation/notifications` (PR #57 — still an isolated component pending
integration). Neither canonical contract changes beyond the two recorded
extensions below. The "webmail sync job" referenced below is the receiving
transport: `devin/mail-transport` ([PR #68](https://github.com/afterburn25/Cajun_Web/pull/68))
provides `InboundSync::checkMailbox()`, which yields the `MailMessage`
values this store keys on; Codex's connection-checks increment
([PR #67](https://github.com/afterburn25/Cajun_Web/pull/67)) is the
separate diagnostics path and does not deliver messages.

## Required canonical extensions

Two additive changes the integration must make before bridging:

1. **New notification kind.** `Notification::KINDS` gains
   `'mail-message'`. The kind is staff-scoped by construction — the
   adapter only ever creates `AUDIENCE_STAFF` records with it.
2. **Message link ids.** A webmail message's stable identity is the
   RFC 9051 §2.3.1.1 tuple `(mailboxId, folder, uidValidity, uid)`,
   exposed by `MailMessage::identity()` as an unambiguous
   length-prefixed encoding — but `Notification::$link` only accepts
   `[a-z][a-z0-9-]*:[A-Za-z0-9_-]+`. The adapter therefore links to a
   deterministic **sync id** derived from the canonical identity:

   ```
   link = 'webmail-message:' . substr(hash('sha256',
          $message->identity()), 0, 24)
   ```

   Deriving from `identity()` — never re-joining the raw fields — keeps
   the link stable across a folder's `uidValidity` rollover (a new
   generation is a new link, so a stale bell can never open a different
   message) and inherits the collision-free tuple encoding. Lowercase
   hex satisfies the link grammar. The webmail view resolves the sync
   id through its own `synced_messages` store (the full identity is the
   unique key); the notification never embeds the tuple directly, so a
   link can't smuggle an arbitrary mailbox path.

## Mapping

| Webmail (A-06) | Notification (P-07) |
| --- | --- |
| `MailMessage` where `isUnread()` | one `mail-message` notification per staff recipient holding an active `MailboxAssignment` (which yields the `Access\MailboxGrant` read capability) for the message's mailbox |
| `UnifiedInbox::unreadCount()` | the bell badge — derived, never a stored counter that can drift |
| `MailMessage::markSeen()` | marks the corresponding notification read via `NotificationFeed` (recipient-only) |
| Mailbox assignment revoked/expired | existing notifications stay but their link resolves to "message unavailable" — the `Access\MailboxGrant` capability is re-checked at view time, never at render-cache time |

## Rules

- **Staff audience only.** `mail-message` notifications are created
  with `AUDIENCE_STAFF`; `assertClientVisible()` makes leaking one to a
  client feed a hard error, not a display choice.
- **Grant-checked at open.** Clicking the bell opens the webmail view,
  which re-runs `Access\PermissionPolicy` — staff session, `mail.read`,
  and the `Access\MailboxGrant` capability an active
  `MailboxAssignment` produces — for that mailbox before rendering.
  A copied link or a stale session grants nothing.
- **No message content in the notification.** Title is the subject
  line and `from` — never body text. Bodies stay behind the webmail
  permission check (and `MailMessage` carries no body anyway).
- **Public-page bells are authenticated staff only.** The public bell
  partial renders only when the session resolves to a staff member
  passing `Access\PermissionPolicy` with the `mail.read` capability for
  at least one mailbox (produced by an active `MailboxAssignment`);
  anonymous and client sessions get nothing, and the response must not
  be publicly cacheable when a bell is rendered.
- **Honest empty state.** Zero unread = no badge; a mailbox that was
  never synced contributes nothing (the A-06 `ConnectionResult`
  `untested` state is not a count of zero-with-confidence claims).
- **No sending.** Notifications link to messages; they never send,
  compose or forward.

## Explicitly out of scope

- Per-mailbox notification preferences beyond P-07's existing per-kind
  opt-out (`mail-message` is one opt-out kind — granular per-mailbox
  muting is a later increment if wanted).
- Ticketing/service-desk mail (internal ticket messages stay in
  `foundation/desk`, per HC-12's separation note).
- Client-facing mail notifications — A-08 is admin/staff bells only.

## Integration checklist (for the Codex-side PR)

1. Add `'mail-message'` to `Notification::KINDS` (canonical, one line).
2. `synced_messages` store keyed by the canonical `identity()` tuple
   `(mailbox_id, folder, uid_validity, uid)` → sync id; populated by
   the webmail sync job.
3. Bell adapter: on sync, create `mail-message` notifications for each
   staff member with an active `MailboxAssignment` (i.e. holding the
   read capability); on `markSeen`, mark the linked notification read
   through `NotificationFeed`.
4. Public/admin bell partial gated on staff auth + active read
   capabilities;
   `Cache-Control: private` (or better) when rendered.
5. Native tests: two-mailbox unread split, grant-revoked link refusal,
   internal-note isolation analog, anonymous public page carries no
   bell markup.
