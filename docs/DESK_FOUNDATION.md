# Service-desk foundation (HC-12 contract layer)

Canonical contract classes for the roadmap's admin-operations and
service-desk slice: typed trace links, a recoverable fulfillment-job
queue model, role-based approvals for irreversible operations, and
internal support tickets with staff-only notes.

This is a **model layer only**. It owns no job runner, persistence,
mail, payments or UI. Real queue workers, provider reconciliation,
admin screens and ticket views are the integration gate.

## Files

| File | Role |
| --- | --- |
| `foundation/desk/TraceLink.php` | Typed `kind:id` reference over an allowlisted record-kind set — order/invoice/service/client/ticket chains staff can follow without raw database access. Unknown kinds and malformed keys are refused. |
| `foundation/desk/FulfillmentJob.php` | One unit of provisioning work: `queued → running → succeeded | failed → queued (backoff) | dead_letter`. Lease-expired runs go `stalled` for explicit `requeue()` recovery (which clears stale backoff). Required `idempotencyKey` for replay detection. Bounded exponential backoff (`base × 2^attempts`); exhaustion is honest `dead_letter`, never fabricated success. Irreversible kinds (`deprovision`/`refund`/`purge`) refuse `run()` without a **live approved `ApprovalRequest` of the same kind whose subject is one of the job's trace links** — a bare id or stale/denied request cannot release it. |
| `foundation/desk/ApprovalRequest.php` | `requested → approved | denied | expired`. The requester can never decide; the approver must hold the required role (default `owner`); denials need a reason; decisions expire. |
| `foundation/desk/TicketMessage.php` | Immutable ticket message: `client`/`staff`/`system` author kinds, plain-text body. `internal` is staff-only — the flag is fixed at construction and `clientVisible()` answers it structurally. |
| `foundation/desk/SupportTicket.php` | Ticket lifecycle `open → pending → resolved → closed`, with honest `resolved → open` reopen. Explicit staff assignment, attributed history, trace links, and `clientMessages()`/`allMessages()` separation so internal notes never reach a client view. `unreadFor($reader, $since, $includeInternal)` counts internal notes for staff only. |

## Invariants

- Staff can trace order → provider operation → invoice/service/client
  through typed links; unrecognized kinds never enter a chain.
- An interrupted job is `stalled` and recoverable, not lost or silently
  re-run; a timed-out provider call never auto-retries (HC-08's
  `OperationResult` handles the transport side).
- Irreversible work cannot run without a live two-party approval.
- Internal staff notes are structurally invisible to clients — in
  listings and in unread counts.
- Closed tickets are terminal; posts to them are refused.
- No mail envelopes here: tickets are internal records, deliberately
  separate from A-06 mailbox mail and P-07 notification delivery.

## Not included (separate gates)

- Job runner/cron, persistence schema, queue locking, provider calls,
  admin ticket/search/report UI, mail compose, export.

## Tests

`tests/desk/run.php` — 52 synthetic checks covering link grammar,
retry/backoff/dead-letter, stall recovery, approval gates (role,
self-approval, expiry, denial reasons), ticket lifecycle, internal-note
isolation and unread counting. Fixtures only; `php -n` clean. CI:
`.github/workflows/desk.yml` on official PHP 8.2/8.4 with
`--network none`.
