# Mail-bell adapter contract (A-08)

How the application wires unread-mail bells: A-06 webmail unread state
drives P-07 staff-audience notifications whose deep links open the
protected webmail message. This document is the contract for that
bridge — **documentation only**. The integration itself (sync,
persistence, public-page bell rendering) is Codex's lane.

Dependencies: `foundation/webmail` (PR #59), `foundation/notifications`
(PR #57). Both are contract layers; nothing below changes them beyond
the two recorded canonical extensions.

## Required canonical extensions

Two additive changes the integration must make before bridging:

1. **New notification kind.** `Notification::KINDS` gains
   `'mail-message'`. The kind is staff-scoped by construction — the
   adapter only ever creates `AUDIENCE_STAFF` records with it.
2. **Message link ids.** A webmail message's natural identity is
   `(mailboxId, folder, uid)`, but `Notification::$link` only accepts
   `[a-z][a-z0-9-]*:[A-Za-z0-9_-]+`. The adapter therefore links to a
   deterministic **sync id**:

   ```
   link = 'webmail-message:' . substr(hash('sha256',
          $mailboxId . '|' . $folder . '|' . $uid), 0, 24)
   ```

   Lowercase hex satisfies the link grammar. The webmail view resolves
   the sync id through its own `synced_messages` store (the
   mailbox/folder/uid triple is the unique key); the notification never
   embeds the triple directly, so a link can't smuggle an arbitrary
   mailbox path.

## Mapping

| Webmail (A-06) | Notification (P-07) |
| --- | --- |
| `MailMessage` where `isUnread()` | one `mail-message` notification per staff recipient holding an active `MailboxGrant` for the message's mailbox |
| `UnifiedInbox::unreadCount()` | the bell badge — derived, never a stored counter that can drift |
| `MailMessage::markSeen()` | marks the corresponding notification read via `NotificationFeed` (recipient-only) |
| Mailbox grant revoked/expired | existing notifications stay but their link resolves to "message unavailable" — the grant is re-checked at view time, never at render-cache time |

## Rules

- **Staff audience only.** `mail-message` notifications are created
  with `AUDIENCE_STAFF`; `assertClientVisible()` makes leaking one to a
  client feed a hard error, not a display choice.
- **Grant-checked at open.** Clicking the bell opens the webmail view,
  which re-verifies the reader's `MailboxGrant` for that mailbox before
  rendering — a copied link or a stale session grants nothing.
- **No message content in the notification.** Title is the subject
  line and `from` — never body text. Bodies stay behind the webmail
  permission check (and `MailMessage` carries no body anyway).
- **Public-page bells are authenticated-admins only.** The public bell
  partial renders only when `support_current()`/`admin_user()` resolves
  to a staff member with `mail.templates.manage`-equivalent mailbox
  grants; anonymous and client sessions get nothing, and the response
  must not be publicly cacheable when a bell is rendered.
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
2. `synced_messages` store keyed by `(mailbox_id, folder, uid)` → sync
   id; populated by the webmail sync job.
3. Bell adapter: on sync, create `mail-message` notifications for each
   active grant holder; on `markSeen`, mark the linked notification
   read through `NotificationFeed`.
4. Public/admin bell partial gated on staff auth + active grants;
   `Cache-Control: private` (or better) when rendered.
5. Native tests: two-mailbox unread split, grant-revoked link refusal,
   internal-note isolation analog, anonymous public page carries no
   bell markup.
