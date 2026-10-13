# Review-request foundation — L-02 isolated component

Status: **isolated component — not integrated into the application.** This is the
L-02 "website-review requests" contract from `docs/ROADMAP.md`, delivered as a
framework-independent PHP component the same way the portfolio and estimator
foundations were. It has no database, request, session, mail, or admin
dependencies, and it performs **no network access**.

## What it is

`foundation/review-requests/` — namespace `CajunWebPro\Foundation\ReviewRequests`:

- **`WebsiteAddress`** — normalizes the visitor's existing-website input. Bare
  hosts (`example.com`) become `https://example.com/`; schemes/hosts are
  lowercased, default ports dropped, fragments and embedded credentials
  rejected, and only `http`/`https` are allowed. `isFetchCandidate()` rejects
  literal private/reserved IP addresses, IPv4 `224.0.0.0/4` and IPv6 `ff00::/8`
  multicast (which PHP's reserved-range flag does not cover), and well-known
  internal names
  (`localhost`, `.local`, `.internal`, `.test`, `.example`, `.invalid`).
- **`ReviewContact`** — validated name, email, optional phone, and an explicit
  `consentToContact` flag (defaults to `false`).
- **`ReviewStage`** — lifecycle constants and the legal transition map:
  `received → in-review → report-drafted → delivered → closed`, with
  `closed` terminal and `report-drafted` able to return to `in-review`.
- **`ReviewRequest`** — immutable request value object: id, website, contact,
  goals (required), status, adapter-supplied `submittedAt` timestamp, optional
  notes.
- **`ReviewFinding`** — one reported finding with explicit evidence kind:
  `human-review`, `lab-data`, `field-data`, or `absent-data`. Non-human
  evidence requires both an observation timestamp and a source label, so
  measured data is never presented as opinion and missing data is declared.
- **`ReviewReport`** — the saved human review: belongs to one request id,
  requires a named reviewer and at least one finding, and tracks `deliveredAt`
  which can never predate the report.
- **`ReviewInbox`** — ordered collection and pipeline. Requests are
  de-duplicated by id and ordered deterministically by `submittedAt` then id,
  independent of input order. `transition()` applies only legal stage moves;
  `delivered` additionally requires the saved report for that request with
  confirmed `deliveredAt` (a drafted-but-unsent report cannot establish
  delivery) **and** the contact's recorded consent.

## What it deliberately does not do

- No schema, table, or persistence — the adapter owns storage.
- No form rendering, CSRF, spam controls, or rate limits — those live in the
  application layer.
- No URL fetching — automated performance data is a later optional increment;
  when it arrives, the fetching adapter must resolve DNS and re-check resolved
  addresses per request (literal checks here are a first line, not SSRF
  protection on their own), follow no redirects into internal space, and label
  every finding with source and observation date via `ReviewFinding`.
- No mail or notification — `delivered` is a record of delivery the adapter
  confirms, never a send side effect.
- No invented findings, metrics, or client data.

## Adapter contract (future integration)

When the application form and admin inbox are built:

1. Form input maps to `WebsiteAddress`/`ReviewContact`/`ReviewRequest`; invalid
   input surfaces inline errors and preserves entered values — the component's
   `InvalidArgumentException` messages are safe to show.
2. Persistence rows map back to the same value objects; `submittedAt` comes
   from the row, never `time()` inside the component.
3. Admin stage changes go through `ReviewInbox::transition()`; marking
   delivered must pass the saved `ReviewReport` row.
4. If automated measurement is added later, findings created from tool output
   must use `lab-data`/`field-data` with the tool name and observation time;
   unavailable data uses `absent-data` rather than silence.

## Verification

`tests/review-requests/run.php` — 80 checks with synthetic `fx-*` fixtures:
URL normalization/rejection, SSRF-readiness flags, contact/request/finding/
report validation, the full transition map including the delivery guards, and
deterministic inbox ordering. Runs on PHP 8.2/8.4 via
`.github/workflows/review-requests.yml`. These are component checks only —
application form, persistence, admin workflow, and delivery are untested here.
