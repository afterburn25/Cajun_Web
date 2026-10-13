# Email-template foundation (A-07 contract layer)

Canonical component: `foundation/email-templates/`. Implementation status: **Implemented (local/source)**. External gate: **Not required** for this contract layer — compose UI, persistence and sending are application work owned by Codex.

This is the safe portion of [A-07](ROADMAP.md#a-07--reusable-email-templates): the template/placeholder rules a reusable-email-template feature must satisfy, as a pure model that never sends.

## Model

| Class | Contract |
|---|---|
| `PlaceholderRegistry` | The allowlist of merge fields — `lower_snake` names, each with a required human description, sorted for stable UI. Empty allowlists rejected. |
| `EmailTemplate` | Subject + body with `{{placeholder}}` merge fields. **Every placeholder must be registered in the allowlist** — unknown names are rejected at construction and on edit, so no template can smuggle an unresolved or unsafe merge field. Lifecycle `draft → active → archived`: edits return a new draft (active copy never mutates), `duplicate()` clones as a personal draft under a new identity, `archive()` retains the record but removes it from compose. `transactional` templates are **never** insertable into ad-hoc compose. `preview()` resolves values and returns `unresolved` names — it never silently emits raw `{{name}}` as if it were content. |

## Guarantees

- **Allowlisted placeholders are structural** — a template containing an unregistered merge field cannot exist.
- **Preview is honest** — missing values stay unresolved and are named, never substituted with junk or left ambiguous.
- **Visibility scoping** — `shared` templates insertable by any staff; `personal` only by the owner; `transactional` excluded from compose insertion entirely.
- **No silent history loss** — archive retains records; edits produce a new draft state, not an in-place mutation of an active template.
- Immutable value object — `edit`/`activate`/`archive`/`duplicate` all return new instances.

## Explicitly not here

No compose UI, placeholder insertion UX, draft editing surface, sending, signatures, account selection, or persistence — all adapter concerns. A-07's full acceptance (create/edit/duplicate/preview/archive workflow, compose insertion, resolved preview before send) still needs the integrated implementation.

## Integration contract

When integrated, the adapter: persists templates keyed by the opaque ids; populates `PlaceholderRegistry` from the actual fields the app can supply (client name, project name, portal links — nothing sensitive); calls `preview()` with real values before showing a draft; gates compose insertion through `insertableBy()`; and keeps transactional templates (receipts, resets) on fixed server-side flows only.

## Verification

`tests/email-templates/run.php` — 36 synthetic checks under official PHP 8.2/8.4 images with `--network none` (`.github/workflows/email-templates.yml`). Scope: the template/placeholder contract only.
