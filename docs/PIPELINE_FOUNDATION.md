# Lead-pipeline foundation (A-01 contract layer)

Canonical component: `foundation/pipeline/`. Implementation status: **Implemented (local/source)** on `devin/non20i-pipeline-foundation`. External gate: **Not required** for this contract layer — persistence, permissions and the visual board UI (including non-drag controls) are application-integration work remaining in Devin's non-20i scope (Codex owns the complete 20i integration only).

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

## Runtime integration (devin/non20i-pipeline-runtime)

`app/marketing/pipeline.php` defines the canonical `pipeline_board()` over the existing seven inquiry stages (`new → contacted → qualified → proposal → won`; `closed`/`spam` reopen to `new`; `won` is terminal) and `pipeline_transition()` routes both the pipeline card save and the inquiry detail save through `canTransition`. Stage controls render `pipeline_choices()` so forbidden moves are not even offered. `database/migrations/pipeline_migrate.php` enables the append-only `lead_stage_history` table (owner-authorized); once enabled, every real transition records from/to stage, actor and time and is shown on the inquiry detail screen. Transitions remain legal before history is enabled — the board validation is unconditional; only durable per-lead history waits for setup.
