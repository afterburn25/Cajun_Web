# SSH access foundation (A-10 contract layer)

Canonical component: `foundation/ssh-access/`. Implementation status: **Implemented (local/source)**. External gate: **Architecture/security validation pending** — no SSH transport, gateway, daemon or provider console exists here, and none may be implied.

This is the safe-work portion of [A-10](CLIENT_PORTAL_ROADMAP.md#a-10--vps-ssh-access-from-the-support-workspace): the access model, audit contract and isolated test harness the eventual gateway integrates against. It answers *who may open which terminal* — never *how bytes reach a server*.

## Model

| Class | Contract |
|---|---|
| `ServiceTarget` | An approved destination bound to a registered service id. Host must be an IP or DNS name; port in range; OS account is a short non-space identifier; service type is recorded (managed vs unmanaged differs in capability — the adapter supplies it, this model does not infer it). |
| `TargetRegistry` | Lookup interface the site adapter implements over the client-service table. `null` = not approved. Arbitrary request-supplied host/port input is never a target, so cross-client and internal-network connections are structurally excluded. |
| `AccessGrant` | A client's explicit authorization for one staff member on one service. Requires `clientAuthorized`; carries scopes, issuer and expiry; revocable. Portal-login permission is not terminal access — the grant is a separate record. |
| `LaunchToken` | One-time, short-lived (10–600s), bound to grant + staff session + service. Stores only the SHA-256 hash; the raw secret reaches the browser once and is revalidated at connection time. Consumption marks it — a replay can never open a second session. |
| `TerminalSession` | Lifecycle `active → closed | expired | terminated`, with enforced idle and absolute timeouts, fixed actor/target attribution and an internal history. Terminal states are final. |
| `AuditEvent` | Immutable record of staff id, client, service, action and reason — the shape the durable audit store must persist for every launch, denial, open and close. |
| `TerminalTransport` | The boundary a vetted gateway implements (`open(ServiceTarget, TerminalSession)`). **No implementation ships.** With `transport === null` the broker returns an honest `transport-unavailable` denial instead of simulating a terminal. |
| `SessionBroker` | The single launch gate. Order: approved target → usable grant bound to the service → terminal scope → token binding → token fresh/unconsumed → concurrent cap → transport open. Denials never touch the transport; transport failure terminates the session and audits `transport-unavailable`. |

## What the broker guarantees

- A stolen token fails: binding mismatch (wrong staff session/service) or `token-replayed`/`token-expired`/`token-invalid` denial.
- A denied launch never opens the wire — transport is invoked only after all checks pass.
- Idle-expired sessions free their concurrency slot (`refresh` settles them before counting).
- Every outcome yields `AuditEvent`s retaining the real staff identity — required by A-10's "audit must retain the real staff identity".

## Explicitly not here

No SSH client, terminal emulator, WebSocket gateway, provider console endpoint, host-key verification, key provisioning, or OS-account management. Those arrive only after the connection-design gate (secure gateway or provider handoff) is reviewed — and never as a hidden daemon/Node/root requirement of the shared-host install. Private keys must never reach browser markup, URLs or logs; the component has no field that could carry one.

## Integration contract

When the gate passes, the site adapter: implements `TargetRegistry` over registered client services; implements `TerminalTransport` against the reviewed gateway; creates grants only behind the staff `vps-terminal` permission and a recorded client authorization; persists `AuditEvent`s durably; and renders denial reasons as honest unavailable/denied states (not simulated terminals).

## Verification

`tests/ssh-access/run.php` — 65 synthetic checks under official PHP 8.2/8.4 images with `--network none` (`.github/workflows/ssh-access.yml`). Scope: grants, tokens, session lifecycle and broker decisions only.
