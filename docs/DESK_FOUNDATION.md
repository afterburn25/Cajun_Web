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
| `foundation/desk/FulfillmentJob.php` | One unit of provisioning work: `queued → running → succeeded | failed → queued (backoff) | dead_letter`. Lease-expired runs go `stalled` for explicit recovery. Every `run()` claim mints an immutable attempt token (`id#n`) that `succeed()`/`fail()` must echo exactly — a worker reporting for an expired claim can never settle a replacement attempt, and the token is released for settlement when the lease expires. **A stalled attempt is uncertain** — the expired worker may already have run the external operation — so `requeue()` is fenced behind `resolveStalled(token, outcome, evidence)`: an explicit verified resolution bound to the exact stalled attempt token, with non-empty evidence. `completed` settles the job terminally with no resend; `not_executed` is the only outcome that releases a retry through `requeue()` (which also clears stale backoff). `unknown` is an **append-only observation**, never final — it keeps the job blocked without preventing a later conclusive, attempt-bound resolution, and the inconclusive evidence stays recorded. Conclusive resolutions are immutable per token — an identical replay returns quietly even after the job leaves `stalled` (e.g. a `completed` verdict already settled it or a `not_executed` was consumed by requeue), a conflicting conclusive outcome is refused, and absent/mismatched evidence never releases a retry. Required `idempotencyKey` for replay detection. Bounded exponential backoff (`base × 2^attempts`); exhaustion is honest `dead_letter`, never fabricated success. Irreversible kinds (`deprovision`/`refund`/`purge`) refuse `run()` without a **live approved `ApprovalRequest` of the same kind whose subject is one of the job's trace links** — a bare id or stale/denied request cannot release it, and expiry is rechecked at every claim including a verified retry. |
| `foundation/desk/ApprovalRequest.php` | `requested → approved | denied | expired`. The requester can never decide; the approver must hold the required role (default `owner`); denials need a reason; decisions expire. Expiry bounds **approved** decisions as well as pending ones — `approved($now)` is true only between the recorded decision instant and `expiresAt`, so a stale approval can never release a later attempt. All decision checks run before any field mutation, so a refused decision cannot rewrite the retained decider or reason. |
| `foundation/desk/TicketMessage.php` | Immutable ticket message: `client`/`staff`/`system` author kinds, plain-text body. `internal` is staff-only — the flag is fixed at construction and `clientVisible()` answers it structurally. |
| `foundation/desk/SupportTicket.php` | Ticket lifecycle `open → pending → resolved → closed`, with honest `resolved → open` reopen. Explicit staff assignment, attributed history, trace links, and `clientMessages()`/`allMessages()` separation so internal notes never reach a client view. `unreadFor($reader, $since, $includeInternal)` counts internal notes for staff only. |

## Invariants

- Staff can trace order → provider operation → invoice/service/client
  through typed links; unrecognized kinds never enter a chain.
- An interrupted job is `stalled` and recoverable, not lost or silently
  re-run; a timed-out provider call never auto-retries (HC-08's
  `OperationResult` handles the transport side).
- Stalled-attempt recovery requires verified, attempt-bound evidence:
  an operator's requeue click and the job's idempotency string are not
  proof the provider did not execute. Only a recorded `not_executed`
  resolution for the exact stalled token releases a retry; `completed`
  is terminal and `unknown` stays blocked.
- Irreversible work cannot run without a live two-party approval, and
  an approval's authority ends at `expiresAt` even after it was granted.
- Completion/failure results are bound to the exact attempt token minted
  by the claim; an expired lease releases its token so no later or
  replayed worker result can settle a replacement attempt.
- A refused approval decision mutates nothing: decider, decision
  instant and reason are untouched by a rejected call.
- Internal staff notes are structurally invisible to clients — in
  listings and in unread counts.
- Closed tickets are terminal; posts to them are refused.
- No mail envelopes here: tickets are internal records, deliberately
  separate from A-06 mailbox mail and P-07 notification delivery.

## Not included (separate gates)

- Job runner/cron, persistence schema, queue locking, provider calls,
  admin ticket/search/report UI, mail compose, export.

The persistence adapter must re-check the approval inside the job-claim
transaction with a **current** read: under InnoDB REPEATABLE READ a plain
`SELECT` earlier in the transaction pins the snapshot at a pre-lock
instant, so the approval gate must run after the queue lock (or use
`SELECT … FOR UPDATE`) — same requirement recorded in
`SCHEDULING_ADAPTER.md` after the L-03 integration race. An approval that
expired or was denied between snapshot and lock must never release an
irreversible job.

## Tests

`tests/desk/run.php` — 114 synthetic checks covering link grammar,
retry/backoff/dead-letter, stall recovery, attempt-token binding
(including stale-worker and same-worker-token-reuse refusals),
attempt-bound stall resolutions (missing/wrong-attempt/empty/conflicting
evidence, `unknown` append-only observations and later conclusive
resolution, `completed` terminality with identical replay after success,
`not_executed` fenced retry and replay after reclaim, per-attempt
re-verification, approval expiry rechecked at the replacement claim), approval gates (role, self-approval, expiry of
pending **and** approved decisions, pre-decision authority, denial
reasons, refused-decision immutability), ticket lifecycle, internal-note
isolation and unread counting. Fixtures only; `php -n` clean. CI:
`.github/workflows/desk.yml` on official PHP 8.2/8.4 with
`--network none`.
