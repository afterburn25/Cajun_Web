# Comparison-facts foundation (C-01 contract layer)

Canonical component: `foundation/comparisons/`. Implementation status: **Implemented (local/source)** on `devin/non20i-comparisons-foundation`. External gate: **Not required** for this contract layer — the actual Wix/Squarespace comparison content, page markup and admin editing are application work remaining in Devin's non-20i scope (Codex owns the complete 20i integration only).

This is the sourced-claims portion of [C-01](ROADMAP.md#c-01--why-custom--wix-and-squarespace-comparisons): the roadmap acceptance requires "source/date/plan registry, no copied unsupported mockup claims, no guarantees of rankings/speed/conversion or universal superiority". This component makes those rules structural rather than editorial.

## Model

| Class | Contract |
|---|---|
| `ComparisonFact` | One sourced statement. Competitor claims require an `https:` source URL, `observedAt` date, `planScope`, evidence kind (`provider-doc`/`provider-terms`/`observed`) and a `reviewBy` refresh deadline — after which the fact is stale and cannot render. CajunWebPro deliverables (`subject=self`) must cite `internal-offer` evidence — our claims describe what we sell, not a competitor document. Absolutist wording ("guarantee", "universally superior", "rank first", …) is rejected at construction. |
| `ComparisonRegistry` | Register/lookup; `current()` vs `staleFacts()` so admin UI can surface content needing re-verification. Duplicate ids rejected. |
| `ComparisonSection` | A publishable block citing fact ids. `publishable()` is false when any citation is missing or stale; `renderableFacts()` throws on a missing citation rather than silently dropping the claim. |

## Guarantees

- A claim without an HTTPS primary source cannot exist in the model.
- Competitor facts cannot cite our own marketing as evidence, and our own deliverables cannot be dressed up as competitor documentation.
- Stale content fails closed: sections referencing expired facts are unpublishable, and `staleCitations()` names exactly what to refresh.
- Guarantees/superlatives are unpublishable by construction.

## Explicitly not here

No actual Wix/Squarespace facts — populating the registry requires current primary-source review per [RELEASE_WORKFLOW](RELEASE_WORKFLOW.md) honesty rules. No page markup, admin editing UI, structured-data output or migration content. No dates imply verified research; fixture URLs are `.invalid`.

## Integration contract

When integrated, the adapter: stores facts with their evidence fields; exposes stale-fact review to admins; renders sections only when `publishable()`; separates provider/plan facts from CajunWebPro deliverables visually; and includes a consultation CTA plus who-each-route-suits guidance per C-01 acceptance.

## Verification

`tests/comparisons/run.php` — 30 synthetic checks under official PHP 8.2/8.4 images with `--network none` (`.github/workflows/comparisons.yml`). Scope: the claim-registry contract only.
