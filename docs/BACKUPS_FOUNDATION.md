# Backup/restore foundation (A-03 contract layer)

Canonical component: `foundation/backups/`. Implementation status: **Implemented (local/source)** on `devin/non20i-backups-foundation`. External gate: **Not required** for this contract layer — archive creation/extraction, storage and the restore drill are later application work in Devin's remaining non-20i scope (Codex owns the complete 20i integration only).

This is the safe portion of [A-03](ROADMAP.md#a-03--backup-and-restore): the manifest integrity and deliberate-authorization rules a backup/restore feature must satisfy, as a pure model with no filesystem access.

## Model

| Class | Contract |
|---|---|
| `BackupManifest` | Scoped item map (`identifier => sha256`) with creator, timestamps, `retainUntil` and `manifestHash()` binding the whole scope. Construction **rejects** items matching private paths — `config.php`, `proxy-config.php`, `.installed.php`, `.env`, `*.key`/`*.pem`/`*.p12`, `storage/` — so a manifest can never describe a backup containing secrets. `verifyItem()` checks a stored artifact against its recorded hash. |
| `RestorePlan` | `preview → authorized → applied` (or `abandoned`). Preview classifies each item `missing`/`identical`/`differs` against caller-supplied current hashes and refuses backup formats newer than the build supports. `authorize()` requires a non-empty actor and an exact confirmation phrase, and refuses expired backups. `applied()` is reachable only from authorized. `changedItems()` feeds the impact screen. |

## Guarantees

- **Secrets exclusion is structural** — private paths cannot enter a backup scope at all.
- **Retention is never born expired** — `retainUntil` must parse and
  extend past `createdAt`; pruning can only happen on real elapsed
  time.
- **Deliberate authorization** — a typed phrase + named actor, never a bare click; expired backups cannot be authorized.
- **Honest impact** — the preview reflects real hash comparisons, so "what will change" is computed, not asserted.
- **No unauthorized application** — `applied()` throws unless authorized first.
- A manifest hash proves the scope itself was not tampered with.

## Explicitly not here

No archive format, compression, filesystem extraction, database dump/restore, storage backend, scheduling, or download packaging — all adapter concerns. A successful restore drill in an isolated environment (the roadmap's acceptance) still needs the integrated implementation.

## Integration contract

When integrated, the adapter: generates manifests at backup time from real item bytes; persists manifests durably; renders `changedItems()` as the impact preview; collects the confirmation phrase before calling `authorize()`; applies item writes atomically per item-class and reports `applied()`/`abandon()`; enforces owner-only permission; and runs the isolated restore-drill acceptance before claiming the feature.

## Verification

`tests/backups/run.php` — 33 synthetic checks (verified locally on PHP 8.4 on this branch) and under official PHP 8.2/8.4 images with `--network none` in CI (`.github/workflows/backups.yml`). Scope: the manifest/restore-plan contract only.
