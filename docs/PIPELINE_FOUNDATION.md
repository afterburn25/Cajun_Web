# Lead-pipeline foundation (A-01 contract layer)

Canonical component: `foundation/pipeline/`. Implementation status: **Implemented (local/source)**. External gate: **Not required** for this contract layer — persistence, permissions and the visual board UI (including non-drag controls) are application-integration work owned by Codex.

This is the safe portion of [A-01](ROADMAP.md#a-01--visual-lead-pipeline): the stage/transition/audit rules for extending inquiries into a lead pipeline.

## Model

| Class | Contract |
|---|---|
| `PipelineStage` | Named ordered stage with an explicit `next` transition list (empty = terminal) and won/lost markers. |
| `PipelineBoard` | The stage set plus transition rules, validated at construction (duplicate ids, dangling references, missing initial stage are build errors). `canTransition`/`allowedMoves` are the single authority both drag UI and alternative controls consult. |
| `LeadRecord` | A lead pinned to a board: starts at the initial stage; `moveTo` only applies configured transitions; links to briefs/quotes/projects are typed opaque references (`brief:12`); follow-up dates and notes are validated and attributed. |

## Guarantees

- **Validated transitions** — a card can never land in an unconfigured stage, from UI or otherwise.
- **Terminal won state** — won leads cannot move; `not-proceeding` may re-open to `new` only if the board configures it.
- **Append-only attribution** — every create/move/link/follow-up/note records actor + time in history.
- **Accessibility contract** — the rules live in the board, not the drag widget, so a dropdown/keyboard control satisfies the same validation (roadmap: dragging must not be the only accessible control).

## Explicitly not here

No persistence schema, permission checks, notifications, search/filter UI or board rendering. Lead/contact privacy handling and export are integration concerns.

## Integration contract

When integrated, the adapter: persists leads/transitions durably; enforces staff edit permission on all mutations; renders the board from `PipelineBoard::stages()` with at least one non-drag control calling `moveTo`; maps won/not-proceeding outcomes to inquiry/client records per existing schema; and keeps `history` entries in the audit trail.

## Verification

`tests/pipeline/run.php` — 31 synthetic checks under official PHP 8.2/8.4 images with `--network none` (`.github/workflows/pipeline.yml`). Scope: the board/lead contract only.
