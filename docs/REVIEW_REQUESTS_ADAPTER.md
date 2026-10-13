# Review-request adapter contract — L-02

Written 2026-10-13 on `devin/review-adapter` from main `b975915`. This is the L-02 counterpart to the estimator adapter contract ([PR #35](https://github.com/afterburn25/Cajun_Web/pull/35)): how the live `website-review` lead route can adopt the review-request foundation ([PR #31](https://github.com/afterburn25/Cajun_Web/pull/31), pending integration) without changing visible intake behavior. **No `site/` or schema changes are proposed here** — implementation is a separate reviewed step.

## Live semantics that must be preserved

`growth_submit`'s `website-review` branch (currently `site/app/growth.php`; moves to `site/app/marketing/growth.php` under Codex's directory refactor) today:

- Requires `current_url` non-empty and passes it through `safe_url()` — an absolute `http`/`https` URL with no userinfo and no CR/LF. Bare hosts like `example.com` are currently **rejected**.
- Requires name, valid email, ≥20-char message, `consent=1`; honeypot + session token + 5/hour + 12/day rate limits apply before the lead is saved.
- Saves a `leads` row (`service='Website review'`, detail records the URL and "Human-reviewed request; no automated audit performed").

## Component mapping

| Live field | Contract object | Notes |
|---|---|---|
| `current_url` | `WebsiteAddress` | Normalizes bare hosts to `https://…` — see decision below |
| name/email + `consent` | `ReviewContact` | `consentToContact` must be `true`; live form already requires it |
| lead row | `ReviewRequest` | `stage` starts `received`; `id` = lead id as string |
| staff review notes | `ReviewFinding` | `ReviewFinding::EVIDENCE_HUMAN` (`human-review`) by default; `EVIDENCE_LAB`/`EVIDENCE_FIELD` (`lab-data`/`field-data`) require source + timestamp; `EVIDENCE_ABSENT` (`absent-data`) marks checked-but-missing |
| drafted report | `ReviewReport` | `delivered` requires the saved report with confirmed `deliveredAt` (via `withDelivery()`) AND contact consent — a drafted-but-unsent report can never establish delivery |
| lead queue | `ReviewInbox` | stage transitions, ordering, duplicate-id rejection |

### Decision: bare-host normalization widens accepted input

`new WebsiteAddress($raw)` accepts `example.com` and normalizes to `https://example.com` via `->url()`; `safe_url` rejects bare hosts. Options:

- **A (recommended):** construct `WebsiteAddress` on `current_url` and store `->url()`. Accepted input deliberately widens to include bare hosts — matching user expectation for a "website" field. This is an explicit, documented broadening, not a claim of identical behavior.
- **B:** keep `safe_url` first, then construct `WebsiteAddress` — zero input change, but no normalization benefit.

Either way, no URL is fetched; `WebsiteAddress::isFetchCandidate()` exists only to label whether a future fetcher may attempt it. Staff UI should surface non-candidate flags so internal/private addresses can't be fetched accidentally.

**Compatibility caveats (deliberate, not identical):** under option A, bare-host input that `safe_url` rejects becomes accepted — a broadening. In the other direction, `WebsiteAddress` rejects URL fragments (`#section`) that the current `safe_url` path may accept, and drops userinfo/credentials entirely rather than only rejecting some forms — a narrowing. The adapter must not promise every previously accepted/rejected input stays identical; the exact accepted set changes at both edges and that tradeoff is the documented decision.

## Persistence additions (adapter-owned, additive)

Two tables, both additive — no existing column changes:

- `review_stages` (`lead_id` unique, `stage`, `updated_at`) — durable stage machine feeding `ReviewInbox::transition()`. Alternatively a nullable `review_stage` column on `leads`; the table keeps the leads table untouched and lets non-review leads carry no stage.
- `review_reports` (`lead_id` unique, `findings` JSON of `ReviewFinding` fields, `summary`, `saved_at`, `delivered_at` nullable) — the saved-report gate for `delivered`; `delivered_at` is written only when staff confirm the report actually went out.

Transition writes wrap `ReviewInbox::transition($request, $to, $report)` in a transaction: load current stage, construct objects, call `transition()` (which rejects illegal jumps), persist on success — never trust client-supplied stage.

## Staff workflow mapping

- Lead detail gains stage controls for `service='Website review'` rows only: `received → in-review → report-drafted → delivered → closed`, surfaced as buttons that call the inbox transition. `delivered` stays disabled until `review_reports` has a row **with `delivered_at` recorded** and `consent=1` was captured — a saved draft alone must never enable the transition.
- Findings entry requires the evidence fields the contract demands: `human-review` needs only text; `lab-data`/`field-data` need source + observed timestamp; `absent-data` declares a checked-but-missing item.

## What stays outside the adapter

Form rendering, `check_csrf()`, token/honeypot, rate limits, the `leads` insert, and response handling remain in `growth_submit`. No URL fetching, screenshotting, mail send, or report delivery mechanism — `delivered` only records that delivery happened through whatever channel staff used.

## Acceptance evidence required

- `tests/native.php`: synthetic lead rows → `ReviewInbox::transition()` persists; illegal jumps (received→delivered, delivered with report lacking `deliveredAt`, delivered without consent) rejected; bare-host input normalizes (option A) or is rejected identically (option B).
- `tests/browser_acceptance.py`: staff advances a review request through the full pipeline in the admin UI; delivery control blocked until a report with confirmed delivery exists.
- **Input-compatibility evidence is enumerated, not blanket-claimed:** under option A, previously-rejected bare hosts become accepted (widening) and previously-accepted fragment/userinfo forms become rejected (narrowing); the test records each changed case explicitly rather than asserting identical behavior.

## Open question for Codex

Bare-host acceptance (decision A vs B) and whether stage lives on `leads.review_stage` or the side table — both are integration-review calls recorded here, not decided.
