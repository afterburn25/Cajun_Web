# Scheduling adapter contract — L-03

Written 2026-10-13 on `devin/scheduling-adapter` from main `b975915`. Counterpart to the estimator (#35) and review-request (#36) adapter contracts: how the live `consultation` lead route can adopt the scheduling foundation ([PR #32](https://github.com/afterburn25/Cajun_Web/pull/32)) without changing the honest request-then-confirm model. **No `site/` or schema changes proposed** — implementation is a separate reviewed step.

## Live semantics that must be preserved

`growth_submit`'s `consultation` branch (currently `site/app/growth.php`; moves to `site/app/marketing/growth.php` under Codex's directory refactor):

- `timezone` must be one of the form's fixed IANA list (America/Chicago default … UTC); `preferred_date` must be a real `Y-m-d` within today→+365 days **in that zone**; `period` ∈ `Morning|Afternoon|Evening|Flexible`.
- Saves a `leads` row (`service='Consultation request'`, detail records the request and states "Appointment is not confirmed until the studio replies").
- Token, honeypot, consent, ≥20-char message and rate limits apply as with every growth form.

## Component mapping

| Live field | Contract object | Notes |
|---|---|---|
| `preferred_date` + `period` + `timezone` | `TimeWindow` (the request's preferred range) | period → local band, converted to UTC |
| name/email | `Consultation(id, name, email, slot, timeZone)` | `id` = lead id; `status` starts `requested` |
| staff pick of exact slot | `ConsultationCalendar::reschedule()` to the exact slot, then `transition(CONFIRMED)` | narrows the preferred range to the appointment |
| availability settings | `WeeklyRule` list → `ConsultationCalendar` | staff-configured weekly windows |
| slot/buffer/notice settings | `SchedulePolicy` | admin-editable, defaults below |
| existing consultations | `ConsultationCalendar` bookings | `isBookable` enforces no overlap |

### Period → `TimeWindow` bands (studio timezone interpreted per requester's zone)

`Morning` = 09:00–12:00, `Afternoon` = 12:00–17:00, `Evening` = 17:00–19:00, `Flexible` = 09:00–19:00 — all in the requester's declared `timezone`, stored as UTC instants. These bands are presentation defaults in settings, not hard-coded truth; `SchedulePolicy::slotMinutes` (default 45), `bufferSeconds` (900) and `minNoticeSeconds` (86400) are admin-editable.

### Confirmation flow

1. Staff opens the lead → adapter shows `ConsultationCalendar::slots()` intersections with the preferred `TimeWindow` (respecting policy + existing bookings).
2. Staff picks a slot → `isBookable($slot, ignoreId: lead id, $now)`; on true, `reschedule($consultation, $slot, $now)` narrows the window (returning to `requested`), then `transition($updated, CONFIRMED)` — persisted atomically.
3. Decline → `transition(DECLINED)`; cancel → `transition(CANCELLED)`; reschedule → `reschedule()` returns status to `requested` with the new slot — re-confirmation is never skipped.
4. **Tracked-instance discipline** — `transition()`/`reschedule()` must be called with the instance the calendar currently tracks (the object returned by `request()` or the previous call). The calendar refuses unknown ids and stale copies whose status/slot no longer matches the stored record, and updates its tracked state on success — so a cancellation really frees the slot and a moved slot blocks immediately. The adapter must always act on the freshly-loaded object, never a cached copy.
5. Confirmation delivery (email/portal message) is a separate mechanism — the contract only records state; no mail is sent by the component.

## Persistence (adapter-owned, additive)

`consultations`: `lead_id` unique, `start_ts`, `end_ts` (UTC epoch seconds, indexed for overlap scans), `time_zone`, `status`, `requested_at`, `notes`. `ConsultationCalendar` is rebuilt per request from `WeeklyRule` settings + `SELECT … WHERE status IN ('requested','confirmed') AND end_ts > now` — no denormalized slot table, conflicts are computed, not trusted. Persist the instance **returned** by `transition()`/`reschedule()` (it replaces the calendar's tracked copy); persisting a pre-call object would store stale state.

**DST correctness:** `WeeklyRule::expand()` validates **both** endpoints of each weekly window against the requester's local gap — a window whose start *or* end falls in the spring-forward gap expands no slots that day; fall-back ambiguous times resolve to the first occurrence (PHP semantics). Requested `preferred_date`/`period` bands are requester-local converted to UTC, unaffected by this rule.

## What stays outside the adapter

Form rendering, CSRF/token/honeypot/rate limits and the `leads` insert remain in `growth_submit`. No calendar provider, no invite/mail sending, no public self-booking — self-serve slot display is a later phase only after this confirm flow is proven.

## Acceptance evidence required

- `tests/native.php`: request → preference window persists UTC correctly across a DST boundary (America/Chicago spring-forward week — including a window whose *end* falls in the gap); staff confirm narrows to a bookable slot; overlapping second request rejected by `isBookable`; decline/cancel/reschedule transitions persist and the returned instance is what gets stored; a stale caller copy is refused; illegal jumps rejected.
- `tests/browser_acceptance.py`: staff picks a slot and confirms; the conflicting slot disappears from offered availability.
- Input compatibility: every currently-accepted consultation submission still saves the same lead row; every currently-rejected input still fails.

## Open questions for Codex

- Keep the fixed timezone list in the form, or accept any IANA zone (component validates either way)?
- Availability defaults: which weekdays/hours should seed `sched_availability` on install — needs the owner's real working hours, not a guess.
