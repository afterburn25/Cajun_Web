# Webmail foundation (A-06 contract layer)

Canonical contract classes for the roadmap's multi-account admin webmail:
configured mailbox profiles, per-staff mailbox grants, honest connection
probes, listing-level message summaries, and a structurally private
combined inbox.

This is a **model layer only**. It owns no IMAP/SMTP transport, no
credential storage, no persistence, no compose/reply UI and no message
bodies. Integration (real connections, encrypted credential store,
folder sync, rendering, sending) is a separate gate.

## Files

| File | Role |
| --- | --- |
| `foundation/webmail/MailboxProfile.php` | One account profile: labelled, owner-attributed, validated host/port per protocol, **`ssl`/`starttls` only** — plaintext transports refused. `credentialRef` is an opaque id into the app's encrypted credential store; the model can never hold a usable secret. Explicit `configured`/`disabled` status. |
| `foundation/webmail/MailboxGrant.php` | Staff ↔ mailbox authorization with issuer attribution, optional bounded expiry and revocation — mailbox access is granted, never implied by staff status. |
| `foundation/webmail/ConnectionResult.php` | Recorded outcome of a *real* probe: `success`/`failed` (named error required)/`untested`. The model can only report what the adapter actually observed — no simulated success. |
| `foundation/webmail/MailMessage.php` | Immutable listing summary: uid, folder, envelope fields, flag set (`seen`/`answered`/`flagged`/`draft`/`attachment`). No body — content fetching/safe rendering is a separate concern. Explicit `markSeen`/`markUnread`/`withFlag`. |
| `foundation/webmail/UnifiedInbox.php` | Combined-inbox view for one staff id: messages from any mailbox without an **active** grant are construction-time errors — expired/revoked/other-staff grants authorize nothing. Newest-first paging, global and per-mailbox unread counts. |

## Invariants

- Plaintext mail transport can never be configured (`none`/`plain` rejected).
- A profile carries no password — only an opaque credential reference.
- Connection state is honest: `untested` is distinct from `failed`, and a
  failure must name its error.
- Combined inbox can only ever contain messages from mailboxes the actor
  holds an active grant for — privacy by construction, not by filter.
- Message listings carry no body, so unescaped message HTML can never
  reach the UI through this layer.

## Not included (separate gates)

- Real IMAP/SMTP connections and probes, credential encryption at rest,
  persistence schema, folder/message sync, compose/reply/forward,
  attachments, search indexes, admin UI.
- Send/sync failure *handling* lives with the transport adapter; this
  layer only models its honest result.

## Tests

`tests/webmail/run.php` — 46 synthetic checks covering profile
validation, grant lifecycle, honest connection results, flag semantics,
and combined-inbox grant enforcement. Fixture records only; runs clean
under `php -n` (no optional extensions). CI:
`.github/workflows/webmail.yml` on official PHP 8.2/8.4 with
`--network none`.
