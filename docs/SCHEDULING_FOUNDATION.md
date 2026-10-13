# Scheduling foundation — L-03 isolated component

Status: **isolated component — not integrated into the application.** This is
the L-03 "consultation scheduling" contract from `docs/ROADMAP.md`, delivered
as a framework-independent PHP component like the portfolio, estimator and
review-request foundations. It has no database, request, session, mail, or
provider dependencies, and it performs no network access.

## What it is

`foundation/scheduling/` — namespace `CajunWebPro\Foundation\Scheduling`:

- **`TimeWindow`** — a half-open UTC interval `[startsAt, endsAt)` with
  `contains`, `overlaps`, and `withMargin` (for buffer checks). All calendar
  math uses these, so conflict checks are exact.
- **`WeeklyRule`** — a recurring weekly availability window in a real IANA
  timezone (`Tuesdays 09:00–12:00 America/Chicago`). `expand()` produces
  concrete UTC windows using real timezone rules: DST transitions shift the
  UTC instant while the configured local time stays put, and occurrences
  swallowed by a spring-forward gap at either endpoint are skipped rather
  than silently moved or stretched; ambiguous fall-back times resolve to
  the first occurrence. Unknown timezone identifiers are rejected, never
  guessed.
- **`SchedulePolicy`** — adapter-supplied constraints: slot length (1–480
  minutes), buffer seconds between bookings, and minimum-notice seconds.
- **`Consultation`** — immutable request record: id, name, validated email,
  slot, the contact's validated display timezone, status, adapter-supplied
  `requestedAt`, optional notes. Statuses: `requested`, `confirmed`,
  `declined`, `cancelled`. Terminal states cannot reopen; a `requested` state
  is the only one a visitor-facing path can reach — nothing is ever created
  "booked".
- **`ConsultationCalendar`** — availability windows + policy + existing
  consultations. `slots()` enumerates bookable starts on absolute policy
  boundaries inside availability, honoring minimum notice. `isBookable()`
  requires the slot to be policy-sized, boundary-aligned, fully inside an
  availability window, and clear of every active consultation including its
  buffer. `request()` verifies bookability and returns the consultation still
  `requested`; `transition()` applies only legal moves and `reschedule()`
  requires a bookable new slot (ignoring the consultation's own slot),
  returning it to `requested` — a moved meeting is not confirmed until
  confirmed again. Both lifecycle operations require the instance currently
  tracked by the calendar, refuse stale or untracked copies, and store their
  result, so sequential operations on one calendar never keep stale
  conflicts or leave a newly occupied time bookable.

## What it deliberately does not do

- No schema, table, or persistence — the adapter owns storage and must
  re-verify `isBookable()` inside the same transaction that writes a booking,
  since two concurrent requests can race on this pure component.
- No form rendering, CSRF, spam controls, or rate limits.
- No mail — confirmation/cancellation delivery is the application's job.
- No calendar-provider integration — a future provider adapter is a
  configuration decision, not an existing connection; the contract's
  `confirmed` status must only be set after the provider confirms when one is
  configured.
- No "instant booking" claim — the honest lifecycle is request first,
  confirmed later, matching the roadmap requirement that nothing be described
  as booked before persistence/provider confirmation.

## Adapter contract (future integration)

1. Form input maps to `Consultation`; invalid input surfaces inline errors
   preserving entered values.
2. Admin-configured availability becomes `WeeklyRule`s (staff timezone) or
   concrete `TimeWindow`s; policy comes from admin settings.
3. The booking write wraps `isBookable()` + insert in one transaction with a
   uniqueness guard on overlapping active slots, then transitions to
   `confirmed` only after the row exists (and after any provider call
   succeeds when a provider is configured).
4. `requestedAt` and all timestamps come from the adapter, never `time()`
   inside the component.

## Verification

`tests/scheduling/run.php` — 64 checks with synthetic `fx-*` fixtures: window
math, weekly-rule expansion including a real DST spring-forward (local time
constant, UTC shifted, gap occurrences skipped), policy validation, contact/
consultation validation, the full transition map including terminal states,
slot enumeration under minimum notice, overlap and buffer conflicts,
cancelled-slot release, and the request→confirm→reschedule→cancel lifecycle.
Runs on PHP 8.2/8.4 via `.github/workflows/scheduling.yml`. Component checks
only — form, persistence, admin workflow, confirmations, mail and provider
integration remain untested here.
