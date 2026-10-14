# Provider transport acceptance contract

Bounded requirements for the concrete `HttpTransport` implementation that serves the fixed 20i connection probe. This contract specifies **what must be proven and how it may be proven synthetically**; it does not implement a transport, authorize a provider call, or relax any existing gate. The interface invariants in [HttpTransport.php](../foundation/provider/HttpTransport.php) and the adapter rules in [TWENTYI_CONNECTION_PROBE.md](https://github.com/afterburn25/Cajun_Web/blob/codex/provider-settings/docs/TWENTYI_CONNECTION_PROBE.md) remain authoritative; this document is the acceptance checklist Codex's transport increment can be reviewed against.

## Hard invariants

1. **Fixed destination.** The transport accepts a caller `TransportRequest` but the probe path only ever presents `GET https://api.20i.com/package`. The transport must not accept a proxy URL, `CONNECT` target, or redirect-follow that changes the effective destination. `CURLOPT_FOLLOWLOCATION` stays off; a 3xx is a returned `TransportResponse`, never silently chased.
2. **Validated DNS before connect.** Resolve the hostname explicitly, reject every resolved address that is not public global unicast (loopback, RFC1918, link-local, CGNAT 100.64/10, IPv6 ULA/link-local, unspecified, multicast, documentation ranges), then pin the surviving address for the connection — e.g. `CURLOPT_RESOLVE` — so validation and connect cannot race a second lookup. Zero surviving addresses is a pre-send `unavailable` fault.
3. **Verified TLS on the original hostname.** `CURLOPT_SSL_VERIFYPEER`/`VERIFYHOST=2` against the system trust store; SNI and HTTP `Host` must carry the original hostname even when the connect target is the pinned address. TLS handshake failure is `tls`, demonstrably before the request was sent.
4. **No proxy, no redirect, no ambient config.** `CURLOPT_PROXY=''` (defeats `http_proxy`/`https_proxy`/`all_proxy` env vars) and `CURLOPT_NOPROXY='*'`. No `.curlrc`-style ambient configuration may alter the request.
5. **Bounded deadlines and body.** Finite connect and total deadlines plus a low-speed floor; response body capped at `TwentyIAdapter::MAX_BODY_BYTES` via a counting write callback that aborts — the adapter's own post-hoc length check is backstop, not the cap.
6. **Honest phase classification.** Faults demonstrably before the request left the client (`connect`, `tls`, `unavailable`) map to `unreachable`; anything that can fire after bytes may have been written (`timeout`, `protocol`, `unknown`) maps to `unknown`. A cap-abort or deadline fire is never relabelled `connect`.
7. **No disclosure.** `TransportException` still carries only its finite `kind` — no cURL error text, resolved addresses, headers, URL, or credential material. The `Authorization` header is unrepresentable in `TransportResponse` and in every thrown path.

## Synthetic acceptance

No real provider endpoint is required or authorized for CI. Acceptable proof uses local fixtures only:

- A loopback-controlled **fake DNS resolver / socket harness** (injected resolver or `CURLOPT_RESOLVE`-equivalent seam) returning scripted address sets: all-private → `unavailable` with zero connects attempted; mixed private+public → connect only to the public address; empty → `unavailable`.
- A **local TLS fixture** (the repo already uses isolated-TLS fixtures in mail diagnostics) presenting a self-signed or wrong-host certificate → `tls`; a correct local CA → success, proving hostname verification is real, not pinned-open.
- **Proxy-environment fixtures**: run with `http_proxy`/`https_proxy`/`all_proxy` set to a listening canary — the canary must observe zero connections.
- **Deadline fixtures**: a server that accepts but stalls → `timeout` (post-send kind, not `connect`); a write-callback cap fixture returning an oversized body → transport-level abort, and the adapter still yields `unknown`/`response_limit`.
- **Disclosure sweep**: a credential canary string placed in the Authorization header must not appear in any exception message, response object, or log line under any failure path.
- Named results, not assertion counts: each invariant above maps to named checks in the transport suite, runnable under `php` and `php -n` where the cURL extension permits; `php -n` execution may skip cURL-dependent checks only if named-skipped and still green.

## Explicit non-goals

No retries at the transport layer (bounded policy is a gateway concern). No background polling, keep-alive reuse across requests, response caching, or connection pooling beyond a single send. No inventory, ownership inference, or mutation — the transport is read-path plumbing for a single pinned endpoint; its existence proves nothing about entitlement.
