# Service-plan foundation (P-04 contract layer)

Canonical component: `foundation/service-plans/`. Implementation status: **Implemented (local/source)**. External gate: **Not required** for this contract layer — plan UI, real billing capture and the request workflow are application work owned by Codex.

This is the safe portion of [P-04](ROADMAP.md#p-04--maintenanceservice-plans): the plan-terms and consent rules a maintenance/service-plan feature must satisfy, as a pure model with no billing calls.

## Model

| Class | Contract |
|---|---|
| `ServicePlan` | Owner-authored plan: cadence (`one-time`/`monthly`/`quarterly`/`annually`), integer-minor-unit price + ISO currency, concrete inclusions/exclusions, typed non-negative integer `limits`, and a required concrete `supportExpectation`. **Banned phrasing** ("unlimited", "unmetered", "forever", "guaranteed uptime") is rejected at construction so no plan can promise unbounded or guaranteed service. Lifecycle `draft → active → retired`; activation is the consent point — an active plan is immutable and `retired` plans refuse new subscriptions while existing ones continue. `termsHash()` binds the canonical customer-facing terms. |
| `PlanSubscription` | `pending-consent → active ⇄ paused → cancelled` (or `abandoned` while pending). The agreed price is **pinned at subscribe time** — later plan edits never silently reprice an existing subscription. Recurring plans cannot `activate()` without a recorded `ConsentRecord`; consent pins `termsHash()` so a client always consents to the exact terms offered, and a hash mismatch blocks activation. Every transition is actor-attributed in append-only history; terminal states are final. |

## Guarantees

- **No "unlimited support" promises** — banned absolutist phrasing is structurally rejected, and service expectations must be concrete.
- **Agreed prices** — subscriptions pin the price at start; terms change requires a new plan, not an in-place edit.
- **Explicit recurring consent** — recurring cadences fail closed without a consent record bound to the exact terms.
- **Genuine status** — subscription state is a real lifecycle, not a label; invalid transitions throw.
- Every mutation is actor-attributed for audit.

## Explicitly not here

No plan/term UI, invoice generation, payment capture, renewal scheduling, usage counters or request ticketing — all adapter/integration concerns. P-04's full acceptance (agreed prices displayed to clients, request workflow) still needs the integrated implementation.

## Integration contract

When integrated, the adapter: persists plans/subscriptions keyed by the opaque ids; renders the terms verbatim at consent time so `termsHash()` covers what the client saw; calls `recordConsent` only after an explicit client action (not on page view); enforces owner-only plan authoring; and surfaces `history()` in admin audit views.

## Verification

`tests/service-plans/run.php` — 46 synthetic checks under official PHP 8.2/8.4 images with `--network none` (`.github/workflows/service-plans.yml`). Scope: the plan/subscription contract only.
