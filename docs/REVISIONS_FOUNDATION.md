# Content revision-history foundation (A-02 contract layer)

Canonical component: `foundation/revisions/`. Implementation status: **Implemented (local/source)**. External gate: **Not required** for this contract layer — persistence, permissions wiring and admin compare/restore UI are application-integration work owned by Codex.

This is the safe portion of [A-02](ROADMAP.md#a-02--content-revision-history): the version/restore/approval rules a content-history feature must satisfy, as a pure model with no storage assumptions.

## Model

| Class | Contract |
|---|---|
| `ContentRevision` | Immutable numbered snapshot: subject, content, author, timestamp, note, optional `restoredFrom` link (restores are new revisions — history is never rewritten). `contentHash()` for integrity checks. |
| `ApprovalContext` | A retained approval pinned to a specific published revision — a separate event, not a flag on content, so it survives later drafts unchanged. |
| `RevisionDiff` | Deterministic line-level comparison (added/removed sets, `identical()`, `changedLineCount()`). The UI adapter may render richer diffs; this guarantees an honest baseline. |
| `ContentDocument` | One subject's append-only history with a published pointer and a draft pointer, enforcing the rules below. |

## Guarantees

- **Only meaningful versions** — saves identical to the pending draft or published revision are rejected.
- **Draft vs publish separated** — `save()` creates a draft; `publish()` is a distinct explicit action.
- **No silent overwrite** — `restoreAsDraft()` produces a *new* draft revision linked to its source; the published revision is untouched, and it refuses to discard a pending draft without an explicit `discardDraft()`.
- **Pinned approvals** — `approve()` only accepts the currently published revision id, and the approval record persists independently of later drafts.
- **Full attribution** — every save/publish/restore/discard/approval records actor + time in history.

## Explicitly not here

No persistence schema, per-field permissions, rich diff rendering, scheduled publishing or cross-document policies. Subjects are opaque strings — the adapter decides granularity (page, block, template).

## Integration contract

When integrated, the adapter: stores revisions append-only (never UPDATE a revision row); checks staff edit permission on save/restore/discard and the approval permission on approve; renders `RevisionDiff` or a richer equivalent; and keeps `published`/`draft` pointers consistent under concurrent editing (the model rejects races only logically — the schema needs a uniqueness/version guard).

## Verification

`tests/revisions/run.php` — 30 synthetic checks under official PHP 8.2/8.4 images with `--network none` (`.github/workflows/revisions.yml`). Scope: the revision/diff/approval contract only.
