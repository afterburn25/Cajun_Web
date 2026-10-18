# Recurring invoice consent and whole-period lifecycle

Current integration: Codex owns the complete 20i P0 under the [owner takeover](TWENTYI_CODEX_TAKEOVER.md). This preserved contract comes from #120 at `66f7760b686c91ca88b8395d350e083cd1ef2516`; its older Devin allocation and checkpoint claims below are historical. Only the necessary recurring consent/invoice dependency is reused. New combined native, interface, package and review evidence remain required.

Codex owns this pure HC-06/P-03/P-04 increment on `codex/recurring-billing-lifecycle`, starting from terms PR 118/`4b99e646191aac8d2b3b5b927959516bad764756`. Devin retains complete 20i implementation. This contract precedes code. The component stays outside deployment and does not replace the required native records, current authority, invoice uniqueness or actual staff/client interfaces.

## Purpose

Retain explicit client acceptance of the exact immutable invoice terms and a bounded, replayable history of future whole-period activation, pauses, resumes and termination. A date and amount alone must not make a period eligible. Preserve past whole-period decisions and never let a later pause rewrite a period that has already started. No prorating, refund, payment mandate, service entitlement or automatic collection is inferred. Native offers must clearly present these lifecycle rules alongside the exact terms before consent; this first policy issues whole-period invoices only. Partial-period changes and invoice adjustments require their own explicit flow.

## Document and command contract

`RecurringBillingSchedule` is immutable. Canonical format 1 contains exactly `format`, `terms` (the existing canonical `RecurringBillingTerms` data), `created_by` (staff integer 1–2147483647), `created_at` (explicit UTC epoch 0–253402300799), and `events` (a list, maximum 256). Maximum canonical JSON size is 524,288 bytes. Use unescaped Unicode/slashes with no whitespace. `fromJson` rejects duplicate keys, alternate representations, extra/missing fields, unsupported formats and invalid histories. There is no mutable terms replacement: changing price/context/calendar requires a new offered schedule and new client agreement under the native replacement policy.

Each event has exactly `id` (32 lowercase hex), `command`, `actor`, `at`, `arguments`. IDs are unique. Actor is exactly `staff:N` or `client:N`, where N is an integer identity 1–2147483647 without padding. Client actions must match the document's client. The pure document validates only declared identity/context, never current authentication or permission. Event epochs are explicit integers in the supported range and never precede creation or the previous event. The stored timezone must represent each event's date. Equal timestamps retain list order.

| Command | Actor and prior phase | Exact arguments and result |
|---|---|---|
| `accept` | Matching client; offered | `terms_sha256` equal to the exact terms fingerprint; phase accepted |
| `decline` | Matching client; offered | Nonblank literal `reason`, maximum 1,000 UTF-8 bytes; phase declined, terminal |
| `withdraw` | Staff; offered or accepted | Same bounded reason; phase withdrawn, terminal |
| `activate` | Staff; accepted | `period_index` integer accepted by the terms calendar; phase running |
| `pause` | Staff; running | `period_index`, bounded reason; phase paused |
| `resume` | Staff; paused | `period_index`, bounded reason; phase running |
| `end` | Staff or matching client; running or paused | `period_index`, bounded reason; phase ended, terminal |

Reasons reject control bytes except LF/TAB and are preserved without trimming or HTML execution. All lifecycle boundary commands use an entire representable period, including its due date. The boundary's local start date must be strictly later than the command's local date: no backdated or already-started-period change. Activation can skip elapsed original-calendar periods; it never shifts the agreed anchor or bills skipped periods. Activation/pause/resume boundaries are nondecreasing and never precede activation. Multiple such future changes may target the same boundary; the last recorded decision wins there, while every event remains retained. Termination is an explicit exception: a future end boundary may precede any planned activation, pause or resume and overrides all periods from that boundary onward. A previously scheduled later action must never prevent the client ending future billing. Earlier planned events remain in history; elapsed periods cannot be rewritten. At the 256-event limit, reserve the final slot for `end` while running/paused so a full history cannot prevent termination. No command is allowed after decline, withdrawal or end, apart from an exact event replay.

Appending an already-used event ID with the exact canonical event is an idempotent no-op even after later actions. Reusing the ID with different actor/time/arguments/command refuses. A malformed or refused append leaves the original document unchanged. No invoice-issued events are added to this bounded decision history; invoices belong in the native ledger.

## API and assessment

Expose `create(terms, createdBy, createdAt)`, `fromJson`, `append(id, command, actor, at, arguments)`, detached `data`, `json`, `fingerprint`, `revision` and `phase`. Phase is the last recorded lifecycle decision, including scheduled future decisions; it is not a claim about the current instant.

`assessment(periodIndex, asOfEpoch)` returns exactly `period` (the original terms' complete facts), `eligible` (boolean), and `reason`. It must reject an as-of time before the latest retained decision rather than pretend to reconstruct a historical snapshot. Evaluate boundaries in list order. The reasons are: `awaiting-consent`, `declined`, `withdrawn`, `not-activated`, `before-activation`, `paused`, `ended`, `not-started`, `zero-price`, or `eligible`. A period is eligible only after acceptance and activation, inside a running interval, after its local start date has arrived, and with a positive amount. A paused/ended future interval must not erase a preceding running period's eligibility. A zero-price period remains zero and is not a charge; native handling must record any zero-price fulfillment separately. No system clock/default timezone is read and the original calendar determines every boundary.

Eligibility is a pure history projection, not proof of authenticated consent, current authority, source validity, unpaid balance or invoice issuance. The native adapter must validate actual source history/current clients/projects, verify fresh client consent outside support sessions, enforce current authority, handle changes under transaction/locks, prevent overlapping live schedules, and create at most one invoice per permanent `(schedule_id, period_index)` under a unique constraint. It must reuse the existing invoice ledger and preserve all prior invoices/payment state. No provider or merchant operation follows from this component.

## Acceptance and integration

Named tests cover exact canonical identity, literal history, valid acceptance/activation/pause/resume/end sequences, same-boundary precedence, future-only whole periods, skipped initial periods, positive/zero prices, terminal/replay conflicts, strict actor/fields/types/limits, monotonic epochs, local-date/DST boundaries, period/epoch overflow, byte/event bounds, reserved termination, caller immutability and process-timezone independence. Run PHP 8.2/8.4 default/no-ini in network-disabled CI and inspect actual named reports/logs.

Own only `foundation/recurring-billing/RecurringBillingSchedule.php`, `tests/recurring-billing/schedule.php`, a focused workflow and continuity documentation. Preserve parent terms/calendar, shipping files, schemas, routes, installer and version. Independent calendar/terms/lifecycle review and an exact native storage/authority/invoice contract are required before deploying this behavior. The complete recurring billing workflow remains unfinished until native integration, interfaces and payment-specific acceptance are implemented.

## Implementation and local evidence

Contracts `de68460`/`857395a` precede `foundation/recurring-billing/RecurringBillingSchedule.php`. `tests/recurring-billing/schedule.php` passes 321 named checks per local PHP 8.4 default/no-ini mode, with identical reports and no PHP diagnostics. The command/actor matrix is checked against the agreed contract; literal date/period facts, immutable hash-bound consent, future cancellation overriding later planned changes, replay conflicts, canonical/strict limits, DST and date-line timing, and final-slot termination all have named assertions. A decimal-format test initially encoded its fixture to an integer; the corrected test passes actual `1.0` JSON and verifies refusal. Initial failed evidence is retained locally.

Run `php tests/recurring-billing/schedule.php` and `php -n tests/recurring-billing/schedule.php`. The focused workflow uses network-disabled official PHP 8.2/8.4 containers with read-only source. Committed CI and independent review remain pending at this checkpoint. All native records, real authenticated consent, service/source authority, unique invoice writes and actual interfaces remain required; no deployed behavior changes.
