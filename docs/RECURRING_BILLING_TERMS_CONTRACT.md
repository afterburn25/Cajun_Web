# Exact recurring invoice terms

Codex owns this HC-06/P-03/P-04 increment on `codex/recurring-billing-terms`, starting from calendar PR 117 source `8f8ea1c8dad4cdae612da9157494c15bdaf871f4`. Devin retains complete 20i implementation. This contract precedes the pure terms document; all shipping files and live financial acceptance sources stay unchanged.

## Product purpose and fixed boundaries

A maintenance billing schedule must retain the exact client, project, service-agreement revision, amount, calendar and due-date rules the client reviews. A calendar alone cannot establish those obligations. Build a bounded immutable terms document and deterministic period facts first, then add native validation, explicit consent, unique invoices and actual staff/client screens under their next integration contract. This increment does not itself authenticate a person, establish debt, create an invoice, authorize recurring payments or grant access to another record.

Use the existing service-plan recurring cadences and USD invoice amount range. The later native adapter must resolve and validate the retained source subscription revision and matching client/project/plan terms under current authority; copying an ID or checksum into this pure document is never evidence of those facts. Existing service-agreement acceptance does not become consent to a new billing anchor, timezone or due rule. The client must explicitly review/accept the exact billing document before invoices may be generated, and a support session cannot do that for them.

## Canonical document

Format 1 has exactly these fields, in the canonical order below. Missing/extra fields, coerced types, unknown formats and oversized input are rejected. Canonical JSON uses unescaped Unicode/slashes, no extra whitespace, and a maximum 32,768 bytes. `fromArray` copies validated data into that order; `fromJson` accepts only the exact canonical representation, refusing duplicate JSON keys and alternate encodings/order. Fingerprints are SHA-256 of those exact bytes.

| Field | Required value |
|---|---|
| `format` | Integer 1 |
| `id` | Exactly 32 lowercase hexadecimal characters; permanent schedule identity |
| `client_id`, `project_id` | Integers 1–2147483647 |
| `source_subscription` | Exactly `id` (32 lowercase hex), `revision` (integer 1–512), `sha256` (64 lowercase hex); a retained accepted source revision, not a mutable latest pointer |
| `title` | Nonblank UTF-8 string, at most 120 bytes |
| `terms` | Nonblank literal UTF-8 string, at most 16,384 bytes; no control bytes except LF/TAB |
| `currency` | Exactly `USD` |
| `amount_cents` | Integer 0–99999999, copied from approved source terms; no float or tax/discount computation |
| `cadence` | Exactly `monthly`, `quarterly` or `annually` |
| `anchor` | Exact calendar date accepted by the existing billing calendar; its initial whole period must fit |
| `alignment` | Explicit `day-of-month` or `month-end`, with the calendar's original-anchor rules |
| `timezone` | Explicit `UTC` or canonical identifier in PHP's built-in IANA `ALL` timezone list; no numeric offsets, abbreviations or aliases accepted outside that list |
| `due_after_days` | Integer 0–60, calendar days after a period's start; no inferred default |
| `collection` | Exactly `invoice-only`; never a card, bank or automatic-payment mandate |

Titles also reject every control byte. Whitespace is never silently trimmed or normalized; terms remain literal text for later escaped presentation. There is no HTML/template execution. A zero-price period is represented honestly; native invoice rules for zero amounts remain explicit and must not invent a charge.

## Deterministic operations

Expose `data`, `json`, `fingerprint`, `period(index)`, `billingDate(epoch)` and `periodAt(epoch)` on an immutable `RecurringBillingTerms` value. Data returned to callers must be detached copies; later caller edits and failed calls cannot alter stored terms.

`period` uses the existing calendar and returns exactly `key`, `index`, `start`, exclusive `end`, `due`, `timezone`, `currency`, and `amount_cents`. `key` is the permanent schedule ID, a colon and canonical decimal period index. Compute `due` by adding the explicit whole calendar-day offset to `start`; DST does not add/remove a calendar day. Reject any unrepresentable period/end/due date rather than clamp or return partial facts. These are proposed period facts, never a billability flag or an issued invoice.

`billingDate` receives an explicit integer UTC Unix epoch in 0–253402300799 and converts it to the stored IANA timezone. No process clock/default timezone is read. Reject local dates outside the supported four-digit year range. `periodAt` resolves that local date to the original calendar period, or null before the anchor; an exact local midnight boundary belongs to the new period. It returns the same facts as `period`. Test DST and positive/negative UTC offsets without treating 24 hours as a universal local day.

## Required native follow-on

Native storage must validate the actual source history/current client and project binding, persist immutable offered terms and explicit current-client consent with request replay/audit, and preserve prior terms through later source changes. Define effective activation/pause/resume/end and prospective replacements before issuing recurring invoices. Use a transactional unique constraint on `(schedule_id, period_index)` plus a current-authorized whole-period check; a new request ID or document hash must never duplicate an existing period invoice. Also prevent overlapping live schedules for the same source agreement under the agreed replacement policy. Reuse existing invoices/ledger and preserve their unrelated payment state. No gateway or provider operation is implied.

This pure increment owns only `foundation/recurring-billing/`, `tests/recurring-billing/`, its focused workflow and continuity documentation. No deployed mirror, schema, route, installer or version change. Obtain independent calendar/terms review before native billing integration; define that exact storage/authority contract before implementation. Real merchant acceptance and production activation retain their existing gates.

## Acceptance

Named checks must cover exact JSON/data/fingerprint identity, all bound context fields, strict types/limits, unsupported formats and duplicate/noncanonical JSON, literal escaped-later text, caller mutation, calendar alignment/leap/century transitions, independent due-date examples across months/years/leap days, zero/maximum amounts, stable period identity, direct lookup, invalid/overflow epochs, local midnight/DST/date-line conversions and process-timezone independence. Run PHP 8.2/8.4 default/no-ini in isolated network-disabled CI, inspect actual reports/logs and preserve the frozen parent evidence. This proves pure terms/facts only; the full recurring billing workflow remains incomplete.
