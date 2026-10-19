# Versioned proposals foundation (P-02 contract layer)

Canonical component: `foundation/proposals/`. Implementation status: **Runtime integrated (local/source)** on `devin/non20i-proposals-runtime` — additive `proposals`/`proposal_versions`/`proposal_events`/`proposal_decisions` schema, `site/app/marketing/proposals.php` persistence that replays the durable record through this model for every transition, admin authoring/send/withdraw UI at `?view=proposals`, and a client portal decision surface at `?proposal=` that pins approvals to the exact version id. External gate: **Not required** for this contract layer (Codex owns the complete 20i integration only).

This is the safe portion of [P-02](ROADMAP.md#p-02--versioned-approvals-and-proposals): the versioned-approval rules a proposal feature must satisfy, as a pure model with no database, mail or signature assumptions.

## Model

| Class | Contract |
|---|---|
| `ProposalVersion` | Immutable snapshot: deliverables, exclusions, schedule notes and priced milestones, frozen at creation. `contentHash` is a sha256 over the canonical content the approver sees — later tamper-evidence checks can verify a stored version was not altered. `totalCents` sums milestones. |
| `ApprovalDecision` | One client decision (`approve` / `request-changes`) pinned to an exact version id with actor and timestamp. Change requests require a note. |
| `Proposal` | Guarded lifecycle `draft → sent ⇄ changes-requested → approved` plus `withdrawn` (staff, never from approved). Append-only numbered versions; every mutation carries an actor and appends to history. |

## Guarantees

- **No stale approvals** — `decide()` throws unless the decision targets the *current* version id. Terms that were replaced can never be approved.
- **No silent term changes** — versions are immutable; revision always creates a new numbered version and marks the prior superseded.
- **No stale re-send** — after change requests the proposal returns to draft and `send()` requires a `revise()` version first.
- **Approved is terminal** — an approved proposal cannot be withdrawn, revised or re-decided; corrections happen through a new proposal.
- **Full attribution** — history records actor + event + time for create/version/send/decision/withdraw.

## Explicitly not here

No persistence schema, PDF/document rendering, client notification, payment linkage or legal e-signature compliance (the roadmap requires an explicit separate review before claiming that). Milestone amounts are data only — charging is HC-11/P-03 territory and separately gated.

## Integration contract

When integrated, the adapter: persists proposals/versions/decisions durably (append-only version table — never UPDATE a version row); enforces staff authorship on draft/revise/send/withdraw and client identity on decide; renders `contentHash` coverage exactly as the client saw it; and labels approvals as commercial confirmation only, not legal signature.

## Verification

`tests/proposals/run.php` — 35 synthetic checks under official PHP 8.2/8.4 images with `--network none` (`.github/workflows/proposals.yml`). Scope: the version/decision/lifecycle contract only.
