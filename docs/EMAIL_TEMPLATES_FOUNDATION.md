# Email-template foundation (A-07 contract layer)

The corrected model is integrated in published [1.12.0](TEST_REPORT_1.12.0.md). The [administrator library](ADMIN_EMAIL_TEMPLATES.md) supplies saved draft editing, lifecycle, previews and history. Component-only scope below describes this model; compose insertion and sending remain later work.

Canonical component: `foundation/email-templates/`. Implementation status: **Implemented (local/source)**. External gate: **Not required** for this contract layer — compose UI, persistence and sending are application work owned by Codex.

This is the safe portion of [A-07](ROADMAP.md#a-07--reusable-email-templates): the template/placeholder rules a reusable-email-template feature must satisfy, as a pure model that never sends.

## Model

| Class | Contract |
|---|---|
| `PlaceholderRegistry` | The allowlist of merge fields — `lower_snake` names, each with a required human description, sorted for stable UI. Empty allowlists rejected. |
| `EmailTemplate` | Subject + body with `{{placeholder}}` merge fields. **Every placeholder must be registered in the allowlist** — unknown names are rejected at construction and on edit, so no template can smuggle an unresolved or unsafe merge field. A **strict double-brace grammar** applies at construction/edit: any `{{`/`}}` that is not a complete `{{lower_snake}}` token (uppercase, spaces, dots, unmatched or stray braces) is rejected, so malformed placeholders cannot masquerade as resolved text; ordinary single braces stay usable. Lifecycle `draft → active → archived`: edits return a new draft (active copy never mutates), `duplicate()` clones as a personal draft under a new identity, `archive()` retains the record but removes it from compose. `transactional` templates are **never** insertable into ad-hoc compose. `preview()` resolves values in a **single pass over the original text** — merge values are literal data and are never re-scanned, so a value containing `{{other_field}}` stays literal — and returns `unresolved` names rather than emitting raw `{{name}}` as if it were content. Merge values must be strings; anything else throws. |

## Guarantees

- **Allowlisted placeholders are structural** — a template containing an unregistered merge field cannot exist.
- **Strict grammar** — malformed double-brace tokens are construction/edit errors, not silently-literal text.
- **Preview is honest and literal-safe** — single-pass substitution means supplied values cannot inject further placeholders; missing values stay unresolved and are named, never substituted with junk or left ambiguous.
- **Preview output is plain text** — a UI must escape it for HTML display, and a mail adapter must reject header line breaks before sending. The model does not send.
- **Visibility scoping** — `shared` templates insertable by any staff; `personal` only by the owner; `transactional` excluded from compose insertion entirely.
- **No silent history loss** — archive retains records; edits produce a new draft state, not an in-place mutation of an active template.
- Immutable value object — `edit`/`activate`/`archive`/`duplicate` all return new instances.

## Explicitly not here

This pure component provides no compose UI, placeholder insertion UX, sending, signatures, account selection or persistence. Published 1.12.0 supplies the separate draft editing, storage, preview and lifecycle adapter; compose insertion and resolved preview before actual sending still need their own integration acceptance.

## Integration contract

When integrated, the adapter: persists templates keyed by the opaque ids; populates `PlaceholderRegistry` from the actual fields the app can supply (client name, project name, portal links — nothing sensitive); calls `preview()` with real values before showing a draft; gates compose insertion through `insertableBy()`; and keeps transactional templates (receipts, resets) on fixed server-side flows only.

## Verification

`tests/email-templates/run.php` — 50 synthetic checks under official PHP 8.2/8.4 images with `--network none` (`.github/workflows/email-templates.yml`), including the Codex-review regressions for single-pass literal substitution, strict double-brace grammar and runtime string validation. Scope: the template/placeholder contract only.
