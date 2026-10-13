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
| staff review notes | `ReviewFinding` | `human` evidence by default; lab/field require source + timestamp |
| drafted report | `ReviewReport` | `delivered` requires saved report AND contact consent |
| lead queue | `ReviewInbox` | stage transitions, ordering, duplicate-id rejection |

### Decision: bare-host normalization widens accepted input

`WebsiteAddress::parse` accepts `example.com` and normalizes to `https://example.com`; `safe_url` rejects it. Options:

- **A (recommended):** run `WebsiteAddress::parse` on `current_url`, store `->normalized()`. Accepted input widens, matching user expectation for a "website" field; every previously-valid input still parses identically.
- **B:** keep `safe_url` first, then construct `WebsiteAddress` — zero input change, but no normalization benefit.

Either way, no URL is fetched; `WebsiteAddress::isFetchCandidate()` exists only to label whether a future fetcher may attempt it. Staff UI should surface non-candidate flags so internal/private addresses can't be fetched accidentally.

## Persistence additions (adapter-owned, additive)

Two tables, both additive — no existing column changes:

- `review_stages` (`lead_id` unique, `stage`, `updated_at`) — durable stage machine for `ReviewInbox::record()`. Alternatively a nullable `review_stage` column on `leads`; the table keeps the leads table untouched and lets non-review leads carry no stage.
- `review_reports` (`lead_id` unique, `findings` JSON of `ReviewFinding` fields, `summary`, `saved_at`, `delivered_at` nullable) — the saved-report gate for `delivered`.

Transition writes wrap `ReviewInbox::record()` in a transaction: load current stage, construct objects, call `record()` (which rejects illegal jumps), persist on success — never trust client-supplied stage.

## Staff workflow mapping

- Lead detail gains stage controls for `service='Website review'` rows only: `received → in-review → report-drafted → delivered → closed`, surfaced as buttons that call the inbox transition. `delivered` stays disabled until `review_reports` has a row and `consent=1` was captured.
- Findings entry requires the evidence fields the contract demands: `human` needs only text; `lab`/`field` need source + observed timestamp; `absent` declares a checked-but-missing item.

## What stays outside the adapter

Form rendering, `check_csrf()`, token/honeypot, rate limits, the `leads` insert, and response handling remain in `growth_submit`. No URL fetching, screenshotting, mail send, or report delivery mechanism — `delivered` only records that delivery happened through whatever channel staff used.

## Acceptance evidence required

- `tests/native.php`: synthetic lead rows → `ReviewInbox` transitions persist; illegal jumps (received→delivered, delivered without report, delivered without consent) rejected; bare-host input normalizes (option A) or is rejected identically (option B).
- `tests/browser_acceptance.py`: staff advances a review request through the full pipeline in the admin UI; delivery button blocked pre-report.
- All input previously rejected by `safe_url` still rejected; all previously accepted inputs still save identical lead rows.

## Open question for Codex

Bare-host acceptance (decision A vs B) and whether stage lives on `leads.review_stage` or the side table — both are integration-review calls recorded here, not decided.
