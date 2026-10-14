# Mail transport (A-06 bounded transport layer)

Bounded, **read-only** IMAP4rev1 transport for the webmail model in
[`foundation/webmail/](../foundation/webmail). It connects, authenticates,
reads unseen header summaries, and logs out — nothing else. Sits between
the released contract layer and any future application adapter: the
adapter supplies a socket factory and credentials, this component owns
protocol framing, and the A-06 model owns identity/authorization.

## Files

| File | Role |
| --- | --- |
| `foundation/mail-transport/MailSocket.php` | Injectable byte-stream contract (`readLine`, `readBytes`, `write`, `startTls`, `close`, `isOpen`). Tests inject scripted sockets; no session code opens a connection. |
| `foundation/mail-transport/MailTransportException.php` | Classified failure (`connect`/`tls`/`auth`/`refused`/`closed`/`timeout`/`protocol`). Kind is machine-readable; messages are fixed strings — never credentials or payload. |
| `foundation/mail-transport/TlsMailSocket.php` | Production socket over PHP streams. Peer verification mandatory and never disabled (`verify_peer`, `verify_peer_name`, no self-signed, TLS 1.2/1.3 only). Bounded: 1–60 s connect/read timeout, 8192-byte line cap, 16 MiB literal cap. Never logs server data. |
| `foundation/mail-transport/ImapParser.php` | IMAP response grammar: atoms, quoted strings with escapes, `{n}` literals **including mid-response literals** (the literal bytes continue on the next physical line), parenthesized lists, `NIL`. Malformed input, over-large literals (4 MiB), and >100k-token responses all throw `protocol`. |
| `foundation/mail-transport/ImapSession.php` | Session driver: `greeting` → `capability` → `startTls` → `login` → `examine`/`searchUnseen`/`fetchSummaries` → `logout`. Read-only by construction — uses `EXAMINE` (never `SELECT`), fetches only `UID FLAGS ENVELOPE INTERNALDATE`, never `STORE`/`EXPUNGE`. |
| `foundation/mail-transport/InboundSync.php` | Bridge to the released model: given a `MailboxProfile`, credentials, and an injected socket factory, returns `{result: ConnectionResult, messages: list<MailMessage>}`. `EXAMINE`s each requested folder and maps unseen UIDs to `MailMessage` under the profile's identity. |

## Boundaries enforced

- **Encryption only.** `ssl` connects encrypted at-connect; `starttls`
  requires the server to advertise `STARTTLS` and upgrades before login.
  `login()` throws `refused` on an unencrypted connection — plaintext
  credentials can never be sent.
- **Read-only.** `EXAMINE` + `UID SEARCH UNSEEN` +
  `UID FETCH (UID FLAGS ENVELOPE INTERNALDATE)`; no body parts, no flags
  written, nothing deleted. SMTP send is out of scope entirely.
- **Bounded sync.** Each folder contributes at most
  `InboundSync::MAX_MESSAGES_PER_FOLDER` (200) unseen summaries per check
  — the *newest* UIDs when more exist — so a flooded mailbox cannot
  produce unbounded work or responses.
- **Honest outcomes.** `checkMailbox` always returns a `ConnectionResult`;
  a disabled profile is `untested` (never dialed), failures name a
  classified error, and `success` is only returned after the server
  confirms each step. Tag mismatches, `BYE`, `NO`/`BAD`, missing
  `UIDVALIDITY`, and incomplete transcripts are errors, not data.
- **Credential hygiene.** Login arguments are RFC 3501 quoted (control
  characters rejected outright so no command injection), passwords are
  `#[\SensitiveParameter]`, and no exception or result ever carries
  credential or message-body bytes.
- **Untrusted envelope.** `from`/`subject` pass through unescaped into
  `MailMessage` — the same trust rule as the A-06 model: every renderer
  must escape.
- **Authorization unchanged.** This layer performs no access decision;
  the caller still gates each mailbox through `PermissionPolicy` +
  active `MailboxAssignment` before invoking `checkMailbox`.

## Not included (separate gates)

- Real-network dialing (the socket factory is adapter-owned), credential
  decryption, persistence of fetched messages, folder lists beyond the
  requested set, MIME decoding, body fetch/rendering, sending, flag
  updates, polling/daemons, UI.

## Tests

`tests/mail-transport/run.php` — 34 scripted-socket checks covering
parser grammar (quoted escapes, lists, mid-line literals, malformed and
oversized responses), session sequencing (capability, STARTTLS
negotiation/refusal, plaintext-login refusal, tag mismatch, `BYE`,
missing `UIDVALIDITY`), ENVELOPE mapping, auth-refusal classification,
and end-to-end `checkMailbox` mapping to `MailMessage` with honest
`untested`/`failed`/`success` outcomes. Runs under `php -n`.
CI: `.github/workflows/mail-transport.yml` on official PHP 8.2/8.4
with `--network none`.
