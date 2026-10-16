# Support-access foundation (A-09 contract layer)

Canonical component: `foundation/support-access/`. Implementation status: **Implemented (local/source)** on `devin/non20i-support-access-foundation`. External gate: **Not required** for this contract layer — portal session issuance, the banner UI and permission wiring are application work remaining in Devin's non-20i scope (Codex owns the complete 20i integration only).

This is the safe portion of [A-09](ROADMAP.md#a-09--one-click-client-portal-support-access): the authorization and audit rules for staff opening a client's portal without the client password, as a pure model with no real sessions.

## Model

| Class | Contract |
|---|---|
| `SupportGrant` | Owner-issued authorization binding one `staffId` to one `clientId` — identical identities are rejected so staff and customer identities stay separate. Bounded session TTL (60s–8h), issuer attribution, one-shot revocation. `permitsSensitive` is a **separate flag** — portal access never implies permission for sensitive actions. |
| `SupportSession` | A launched session carrying both identities, start/expiry, and `bannerText()` the UI must render. `perform()` logs every action with the staff actor and returns `false` for disallowed actions (expired/closed session, or sensitive action without the grant flag) — denials are still logged. `close()` is the Return-to-admin path. |
| `SupportBroker` | Single launch gate: grant validity → per-client and per-staff concurrency caps → session issuance. Every outcome (`granted`/`denied`/`closed`/`revoked`) lands in an audit list. Denials throw `SupportDenied` with a stable machine-readable reason — honest refusal, never a simulated session. Revoking a grant immediately kills its live sessions. |

## Guarantees

- **Identity separation** — grants refuse `staffId === clientId`; sessions always carry the staff actor, and every action is attributed to staff, not the client.
- **No silent access** — every launch attempt is audited including denials; there is no path to a session without a valid grant.
- **Expiry and revocation** — sessions die at TTL, on close, or instantly on grant revocation.
- **Sensitive actions are separately gated** — `permitsSensitive` must be explicit on the grant.
- **Concurrency bounded** — per-client (default 1) and per-staff (default 3) caps prevent parallel-session floods.

## Explicitly not here

No portal session cookies/middleware, banner rendering, Return-to-admin route, client-data scoping queries, or real permission checks — all adapter concerns. A-09's full acceptance (banner, banner visibility, exact audit surface) still needs integration.

## Integration contract

When integrated, the adapter: issues grants only via an owner/staff-permission check; calls `launch()` at the moment staff click "Open client portal"; renders `bannerText()` persistently and wires Return-to-admin to `close()`; maps `perform()` to a middleware that wraps every request in the session and flags sensitive routes; shows `activeSessionsFor()` on the customer record; and persists `auditLog()` durably.

## Verification

`tests/support-access/run.php` — 37 synthetic checks under official PHP 8.2/8.4 images with `--network none` (`.github/workflows/support-access.yml`). Scope: the grant/session/broker contract only.
