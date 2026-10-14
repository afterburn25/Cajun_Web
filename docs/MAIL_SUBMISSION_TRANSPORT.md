# Maintained SMTP session preparation

## Current SMTP increment — bounded socket and loopback TLS

From5375ade, isolated `SubmissionChannel` adds one public numeric destination pinned to the selected SMTP host/port, original-host certificate/SNI verification, TLS1.2/1.3 only, mandatory STARTTLS,5-second I/O/connect and30-second overall budgets, and finite byte/line/write caps. It reuses the established public-address policy without changing existing diagnostic channels. The caller still must validate the full DNS answer set and current authority before opening; no account lookup, credential decryption, database or application route is added.

Actual local PHP8.4.25/OpenSSL loopback fixtures pass398 named checks: implicit TLS/STARTTLS, exact MIME DATA, all recipients before DATA, PLAIN/LOGIN, trust/hostname/expiry failures, downgrade/injection refusal, malformed replies, body/final disconnects, explicit final rejection and uncertain final outcomes. Test-only namespace interception asserts production options before routing to synthetic loopback; production has no alternate CA/private-address switch. Local policy tests pass27 default and23 no-ini checks, the latter proving missing OpenSSL refusal before any socket. Attempting the full local no-ini TLS suite correctly could not run without that extension; no no-ini TLS acceptance is claimed. The isolated Docker workflow requires compiled OpenSSL and prepares both modes under `--network none`; matrix verification is pending.

Prior session-only [38528949541](https://github.com/afterburn25/Cajun_Web/actions/runs/38528949541) at5375ade has18 downloaded, inspected JSON reports: PHP8.2/8.4 default/no-ini each80 session,52 snapshot,29 MIME and168 parser checks, plus1 unavailable-runtime check per runtime. This does not cover the new channel. Independent submission review and durable exact-attempt storage/authorization remain required before application exposure. Draft1.21 exact-main application38527733738 is in Apache acceptance; Windows build and actual desktop/mobile visual review pass, and17 other exact-main workflows pass. Latest public release is still1.20.0; full roadmap active.

## Earlier session-only checkpoint

This isolated component is outside the deployment tree. It has no channel opener, DNS resolver, saved-account lookup, credential decryption, database, durable dispatch or Send route. A successful synthetic session is not TLS/provider/application acceptance. See the [submission contract](MAIL_SUBMISSION_CONTRACT.md) for the remaining gates.

`SubmissionTransport` uses the original pinned PHPMailer7.1.1 low-level SMTP operations through `SmtpSession`. The supplied internal `ProbeChannel` must enforce real TLS, original-host certificate verification, pinned public addressing and finite deadlines. The existing diagnostic `SecureChannel` has insufficient write limits for sending and is not used or changed here. A production-capable submission channel and real loopback TLS fixture are the next increment.

The driver requires one immutable prepared MIME snapshot and a canonical32-hex attempt id. Its caller must first persist and audit that attempt, then check current author, saved draft/account revisions and Send assignment. A required callback rechecks authority before credential writes, envelope commands, DATA and the terminator. Callback failure before the terminator aborts submission. This callback is an integration boundary, not a substitute for actual application permission checks.

Only implicit TLS or mandatory STARTTLS is supported. STARTTLS requires advertised support and a second EHLO; HELO fallback is disabled. Authentication selects advertised PLAIN or LOGIN explicitly. No debug output, proxy, fallback host, implicit retry or high-level PHPMailer `send()` is used. Username/password bounds match saved account limits. Every To/Cc/Bcc recipient must be accepted before DATA; one refusal closes the session without submitting to the earlier accepted recipients.

Prepared MIME is fed to the maintained DATA primitive after removing exactly one final CRLF. PHPMailer's line iterator adds a CRLF to every logical line, including the final empty element; this adjustment preserves the stored bytes exactly rather than adding a blank line. Named tests compare the full reconstructed DATA with saved MIME, including empty and maximum-length bodies and Bcc-only messages. Maintained dot stuffing is tested separately with literal dot-leading lines.

Session limits are1,100 writes,2,048 bytes per write,73,728 total written bytes,160 reply lines,32 lines per reply and65,536 received bytes. Reply lines are at most510 bytes before CRLF, have consistent reply codes and reject controls/malformed continuation. These are additional protocol bounds; they do not establish a wall-clock bound for a caller-supplied channel.

| Observed result | Meaning |
|---|---|
| `not_submitted` | This process did not attempt the DATA terminator; no completed message was submitted by this attempt. |
| `accepted` | A valid final250 was observed. The server accepted responsibility; recipient delivery is unproven. |
| `rejected` | A valid final4xx/5xx was observed after the terminator. |
| `unknown` | The terminator might have completed, but final acceptance/rejection is unproven. No automatic retry. |

The terminator is marked attempted before writing, because a thrown partial-write failure can occur after the peer has received all bytes. Missing, malformed, truncated or unexpected final replies remain unknown. Unexpected exceptions are sanitized just like known transport failures. Close errors cannot reverse a verified250. Closing avoids QUIT when the server may still be consuming message data.

Every finite result binds submission id, private snapshot fingerprint (including Bcc), MIME hash and exact attempt id. It retains no credentials, raw server text or recipient addresses. This in-memory object is not a signed receipt, authorization token, durable ledger or reconciliation mechanism. If the process dies or result persistence fails after a durable dispatch intent, that stored intent must remain unresolved even if the process observed acceptance.

Local PHP8.4.25 default and no-ini each pass80 named synthetic transcript checks. Tests cover recipient partial refusal, final positive/negative/unknown outcomes, lost writes, stale authority at each sensitive boundary, TLS-mode assertions and required re-EHLO, missing mechanisms, credential bounds, exact DATA, debug suppression and protocol caps. Expanded PHP8.2/8.4 matrix is prepared, not yet verified. Real sockets, certificate trust/hostname, deadline enforcement, DNS policy and durable application storage remain untested by this increment.
