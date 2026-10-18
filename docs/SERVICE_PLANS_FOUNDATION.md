# Service-plan foundation (P-04 contract layer)

Current 2026-10-15: Codex owns complete 20i P0. This retained component is being integrated as a necessary commercial dependency in `codex/twentyi-order-eligibility`; former allocation and acceptance below are historical. [Current integration and remaining eligibility](TWENTYI_COMMERCIAL_ELIGIBILITY.md). Missing established agreement tables now require original database restoration, not empty preparation. Combined native/browser/review evidence is pending.

## Preserved application import — unreleased

Codex service-plan-integration ordinarily merges388b80d into candidate21ebe69, preserving both canonical classes and supplied76 checks. Build-generated private mirrors and21 retained independent controls exercise the shipped copies; local default/no-ini total236 passes. Focused combined matrix/import review remain pending. There are still no persisted plans, client consent screens or recurring charges. Earlier contract statements below describe the canonical model, not a live subscription feature.

Canonical component: `foundation/service-plans/`. Implementation status: **Implemented (local/source)**. External gate: **Not required** for this contract layer — plan UI, real billing capture and the request workflow are application work owned by Codex.

This is the safe portion of [P-04](ROADMAP.md#p-04--maintenanceservice-plans): the plan-terms and consent rules a maintenance/service-plan feature must satisfy, as a pure model with no billing calls.

## Model

| Class | Contract |
|---|---|
| `ServicePlan` | Owner-authored plan: cadence (`one-time`/`monthly`/`quarterly`/`annually`), integer-minor-unit price + strict three-letter ISO currency, concrete inclusions/exclusions, typed non-negative integer `limits` under canonical lowercase snake_case names (`\z`-anchored — a trailing newline cannot slip into a retained meter name), and a required concrete `supportExpectation`. Validated caps are copied into a fresh reference-free array so caller-held PHP references can never mutate a stored limit or drift `termsHash()` after activation. Every term field is a dense list of bounded (≤1000-byte), non-blank, valid-UTF-8 plain strings — non-strings and `Stringable` objects are refused so `termsHash()` can never silently change or throw mid-use, and serialization failure throws explicitly. **Banned phrasing** ("unlimited", "unmetered", "forever", "guaranteed uptime") is rejected at construction so no plan can promise unbounded or guaranteed service. Lifecycle `draft → active → retired`; activation is the consent point — an active plan is immutable and `retired` plans refuse new subscriptions while existing ones continue. `termsHash()` binds the canonical customer-facing terms. |
| `PlanSubscription` | `pending-consent → active ⇄ paused → cancelled` (or `abandoned` while pending). The agreed price **and terms hash** are pinned at subscribe time into an immutable snapshot — later plan edits never silently reprice or re-term an existing subscription. `recordConsent` requires the consenting actor to be *the subscribing client* and the presented `termsHash` to equal the pinned snapshot — the model never converts an arbitrary actor string into that client's consent. Recurring plans cannot `activate()` without that consent. Every event requires a non-empty actor and an explicit UTC instant (`YYYY-MM-DDTHH:MM:SSZ`, `\z`-anchored — relative strings, date-only forms and trailing-newline variants refuse), and no event may be recorded before the previous one: history is monotonic, attributed and canonical; a failed validation leaves state and history unchanged. Terminal states are final. |

## Guarantees

- **No "unlimited support" promises** — banned absolutist phrasing is structurally rejected, and service expectations must be concrete.
- **Agreed prices** — subscriptions pin the price at start; terms change requires a new plan, not an in-place edit.
- **Immutable typed terms** — every customer-facing term is a bounded valid-UTF-8 plain string; terms hashes are canonical and stable.
- **Explicit recurring consent** — recurring cadences fail closed without a consent recorded by the subscribing client against the exact presented terms hash.
- **Genuine status** — subscription state is a real lifecycle, not a label; invalid transitions throw.
- Every mutation is actor-attributed with canonical UTC instants, and history never moves backward.

## Explicitly not here

No plan/term UI, invoice generation, payment capture, renewal scheduling, usage counters or request ticketing — all adapter/integration concerns. P-04's full acceptance (agreed prices displayed to clients, request workflow) still needs the integrated implementation.

## Integration contract

When integrated, the adapter: persists plans/subscriptions keyed by the opaque ids; renders the terms verbatim at consent time so `termsHash()` covers what the client saw; establishes current authenticated client authority and calls `recordConsent` only after an explicit client action by that client (not on page view, and never mapping an arbitrary actor string onto a client); supplies canonical UTC instants (`gmdate('Y-m-d\TH:i:s\Z')`); enforces owner-only plan authoring; and surfaces `history()` in admin audit views.

## Verification

`tests/service-plans/run.php` — 76 synthetic checks under official PHP 8.2/8.4 images with `--network none` (`.github/workflows/service-plans.yml`), including caller-reference detachment of retained limits (post-activation `termsHash` stability) and trailing-newline refusal for limit names and UTC instants. Scope: the plan/subscription contract only.
