# Recurring billing calendar contract

Devin owns this HC-06/P-03/P-04 increment on `devin/non20i-billing-calendar`, continuing preserved `codex/billing-calendar` from frozen notification source `f053dacac8a949a113a295a6b3fd68543ced3452`. Codex retains complete 20i implementation. This contract precedes the calendar implementation. The existing financial, service-agreement and provider source remains unchanged.

## Required behavior

Provide a pure PHP 8.2+ calendar for the existing recurring plan cadences: `monthly`, `quarterly` and `annually`. It receives an explicit original anchor date and alignment rule. It never reads the clock or a server timezone, infers an invoice obligation, moves money, activates a subscription or changes a service. One-time plans do not have recurring periods and must be rejected by this API.

Dates are canonical Gregorian `YYYY-MM-DD`, with years 1000 through 9999 and real month/day validation. These are calendar dates, not timestamps. Later integration must explicitly record the billing timezone and convert its current instant to the agreed billing date; this component does not invent a timezone, payment due date, grace period or proration policy.

Two explicit alignment rules are supported:

- `day-of-month`: retain the original anchor day. Clamp it only in a shorter month. January 31 becomes February 28 (29 in a leap year), then March 31; April 30 becomes May 30. Do not advance from a previously clamped date.
- `month-end`: the original anchor must itself be the last day of its month. Every later boundary is the last day of the target month. April 30 becomes May 31. Never silently interpret a normal date as month-end.

Period index zero starts at the original anchor. Each period is half-open: `start <= date < end`; its end is the next boundary. Monthly, quarterly and annual periods advance one, three and twelve months from the original anchor respectively. Periods must remain adjacent and strictly increasing, including leap-day, century and year transitions. Refuse any request whose end exceeds the supported date range rather than wrap, clamp the year or return a partial result.

Expose three deterministic operations on an immutable calendar:

1. `period(index)` returns exactly `index`, `start`, and exclusive `end`.
2. `periodAt(date)` returns that same period for a supported date at/after the anchor, or null before the anchor. A date equal to a boundary belongs to the following period. Calculate the index directly, without iterating across elapsed periods.
3. `window(first, count)` returns the requested adjacent periods; count is 1–120. Validate the complete requested range before returning it. A bounded index (0–119999) prevents untrusted arithmetic overflow; supported-date bounds apply independently. No partial results or silent truncation.

Only exact known cadence/alignment strings, integer indices/counts and canonical dates are accepted. Returned arrays cannot alter the immutable original anchor. No extension, filesystem, database, network or process-clock dependency is allowed; both normal PHP and `php -n` must behave identically.

## Application integration remains required

This component prepares the date calculation needed for recurring maintenance billing. It is not a scheduler, invoice, service agreement or automatic-payment authorization. Existing service-agreement acceptance does not become an automatic-payment mandate. Later native work must bind the explicit anchor, alignment, timezone, currency/amount and consented terms to an immutable schedule; define effective pause/resume/end and prospective changes; prevent duplicate period invoices transactionally; and expose actual next-period/renewal information to authorized staff and clients. Payment, tax, credits/proration, delivery/retry and production activation retain their own requirements.

Own `foundation/billing-calendar/`, `tests/billing-calendar/`, its focused CI workflow and continuity documentation only. Do not add a deployed mirror or wire application routes/schema/installer in this increment. Keep the current financial notification acceptance source/run frozen. Integration must follow its own committed contract and acceptance; the full recurring billing feature remains incomplete.

## Acceptance

Use named literal date examples and independent adjacency/containment checks covering all cadences and both alignments, February 28/29, days 29–31, leap centuries (2000/2400) and non-leap centuries (1900/2100), year transitions, pre-anchor dates, exact boundaries and distant lookup. Reject malformed/relative/normalized/Unicode dates, unknown or padded parameters, invalid month-end anchors, negative/oversized indices, unbounded windows and end-date overflow. Demonstrate that server timezone changes cannot change output, input/returned mutations do not alter the calendar, and invalid calls leave later valid calls unchanged. Run exact PHP 8.2/8.4 default/no-ini CI and inspect named artifacts. Obtain independent review before native billing integration or release claims.

## Implementation evidence

Contract31fe0dd precedes `foundation/billing-calendar/BillingCalendar.php`, its named `tests/billing-calendar/calendar.php` cases and focused PHP8.2/8.4 workflow. Local PHP8.4 default and no-ini each pass203 identical named checks; all syntax checks pass. Literal examples and independent core Gregorian comparisons cover exact boundaries, original anchors, all cadences/alignments, leap centuries, bounded distant lookup, invalid inputs, immutability and timezone independence. No failed test is omitted. Focused committed CI and independent review remain pending. No deployed mirror, route, schema, installer inventory, version or provider source changed. Native schedule and invoice integration remains required; this is not complete recurring billing.
