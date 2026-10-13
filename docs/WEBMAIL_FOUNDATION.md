# Webmail foundation (A-06 contract layer)

Canonical contract classes for the roadmap's multi-account admin webmail:
configured mailbox profiles, temporal per-staff mailbox assignments,
honest connection probes, listing-level message summaries, and a
structurally private combined inbox.

This is a **model layer only**. It owns no IMAP/SMTP transport, no
credential storage, no persistence, no compose/reply UI and no message
bodies. Integration (real connections, encrypted credential store,
folder sync, rendering, sending) is a separate gate.

## Files

| File | Role |
| --- | --- |
| `foundation/webmail/MailboxProfile.php` | One account profile: labelled, owner-attributed, validated host/port per protocol, **`ssl`/`starttls` only** — plaintext transports refused. `credentialRef` is an *intended-opaque* handle into the app's encrypted credential store; persistence-layer rules, not this object, keep secrets out of it. Explicit `configured`/`disabled` status. |
| `foundation/webmail/MailboxAssignment.php` | Temporal record assigning one mailbox to one staff member: issuer attribution, non-negative times, active only at/after `issuedAt`, optional bounded expiry, revocation. This is the *lifecycle* half of authorization — see the bridge note below. |
| `foundation/webmail/ConnectionResult.php` | Recorded outcome of a *real* probe: `success`/`failed` (named error required)/`untested`. `success()` records what the caller asserts — the factory cannot prove a connection happened; that obligation is adapter-side. |
| `foundation/webmail/MailMessage.php` | Immutable listing summary: uid, `uidValidity` generation, folder, envelope fields, flag set (`seen`/`answered`/`flagged`/`draft`/`attachment`). `identity()` is mailbox + folder + uidValidity + uid per RFC 9051 §2.3.1.1 — safe to key caches and notification links. No body. |
| `foundation/webmail/UnifiedInbox.php` | Combined-inbox snapshot for one staff id: messages from any mailbox without an **active** assignment are construction-time errors; duplicate full identities are rejected rather than double-counted. Newest-first paging, global and per-mailbox unread counts. |

## Authorization bridge

The canonical per-request authority remains
`CajunWebPro\Foundation\Access\PermissionPolicy` with server-loaded
`Access\MailboxGrant` capabilities (`canRead`/`canSend`/`enabled`) —
this layer does not introduce a competing authority. The bridge: an
`MailboxAssignment` that is `active($now)` may *produce* read/send
capabilities for the current staff session; an inactive assignment
produces none, and an assignment can never widen a denied request.
`UnifiedInbox` enforces assignment scope only — the adapter must still
gate every request through `PermissionPolicy` for `mail.read`/`mail.send`
plus the capability grant.

## Invariants

- Plaintext mail transport can never be configured (`none`/`plain` rejected).
- A profile carries no password field; `credentialRef` is an identifier
  whose secrecy discipline is enforced by the credential store.
- Connection state is honest: `untested` is distinct from `failed`, a
  failure must name its error, and `success` records a caller-asserted
  probe outcome.
- Message identity is generation-aware: the same uid under a new
  `uidValidity` is a different message.
- An inbox is a **snapshot** — it freezes the assignments and messages
  given at construction and must be rebuilt after assignment changes or
  a new sync; a stale snapshot never silently widens access.
- Duplicate message identities are rejected, never double-counted.
- Envelope text (`from`, `subject`, `folder`) remains **untrusted**:
  this model does not escape it — every renderer must.
- Message listings carry no body; content fetching/safe rendering is a
  separate adapter concern.

## Not included (separate gates)

- Real IMAP/SMTP connections and probes, credential encryption at rest,
  persistence schema, folder/message sync, compose/reply/forward,
  attachments, search indexes, admin UI.
- Send/sync failure *handling* lives with the transport adapter; this
  layer only models its honest result.

## Tests

`tests/webmail/run.php` — 62 synthetic checks covering profile
validation, assignment lifecycle (pre-issuance, exact boundaries,
negative times, revocation ordering), honest connection results,
generation-aware identity and flag semantics, and combined-inbox
privacy/dedup. Fixture records only; runs clean under `php -n` (no
optional extensions). CI: `.github/workflows/webmail.yml` on official
PHP 8.2/8.4 with `--network none`.
