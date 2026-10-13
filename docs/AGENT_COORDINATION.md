# Codex and Devin work allocation

Owner direction, 2026-10-12: split work between Codex and Devin on separate branches, then integrate finished pieces. Codex coordinates integration, shared interfaces, testing and releases. This assignment is not evidence that Devin has started or received a message.

## Current gate and assignments

Both implementation streams require recovery and inspection of the owner's **1.1.0** source. The repository currently holds documentation and documentation CI. Do not rebuild the application from these descriptions or begin from historical 1.0.8. After importing verified source into `main`, update both branches to that same baseline before coding.

| Owner | Branch | Initial deliverable | Boundary |
|---|---|---|---|
| Codex | `codex/platform-foundation` | Audit/import current source, then a bounded A-05 staff-account/permission increment supporting M1 | Own shared identity/session/permission contracts, migrations and security foundations; A-09 support sessions follow in a separately tested increment |
| Devin | `devin/public-experience` | W-01 portfolio improvements using the existing public site and verified content | Own portfolio presentation and scoped assets; request shared backend changes through an agreed contract |

Branch names are assignments until their existence is verified on GitHub. A branch does not mean its worker is running. The owner selected a GitHub assignment to paste into Devin; Codex is preparing that handoff and has not dispatched Devin directly. Record the assignment issue and actual Devin session/PR when available.

After the first portfolio increment, Devin can take W-02 accessible motion and scoped public lead/estimator work, with persisted-data/API contracts agreed first. Codex proceeds through identity, payments and 20i foundations in the accepted milestone order. Assign webmail and client-project interfaces after shared permission/data contracts exist. Do not independently design competing account, invoice, mail or project schemas.

## Integration rules

1. Use separate branches and PRs. Never push into the other agent's branch or force-push shared history. Record starting commit, roadmap IDs, owned files and dependencies before implementation.
2. Agree on exact file ownership after source inspection. For a shared template, route, schema or settings module, land a small shared change first or designate one owner. Do not duplicate a subsystem to avoid coordination.
3. Commit shared contracts before dependent work: fields, routes, permissions, errors and fixtures. Identify mocks explicitly and keep them consistent with the contract.
4. Every PR includes actual tests, implementation screenshots when relevant, limitations and required documentation. Devin proposes its session/status/handoff updates; Codex resolves shared-document conflicts while integrating.
5. Integrate finished PRs into `main` one at a time after review and required checks. Reconcile against current `main`, test the combined changes, and re-run relevant checks after material conflict resolution. Separate branch checks do not prove integration works.
6. Release only after combined acceptance and exact-package verification under [the release workflow](RELEASE_WORKFLOW.md). Keep incomplete features disabled or outside the package. No automatic production deployment.

## Devin handoff

Repository: [afterburn25/Cajun_Web](https://github.com/afterburn25/Cajun_Web). Read `AGENTS.md`, `README.md`, `PROJECT_STATUS.md`, `SESSION_HANDOFF.md`, `docs/PROJECT_DIRECTION.md`, `docs/ROADMAP.md`, this document and `docs/RELEASE_WORKFLOW.md`. Inspect current `main`, open PRs and the verified source version.

Your first task is **W-01: portfolio improvements**, on `devin/public-experience`, coordinated with Codex's `codex/platform-foundation`. First confirm that the owner's actual 1.1.0 application source has been recovered and verified. If unavailable, report the blocker; do not invent a replacement or begin from 1.0.8.

Inspect the existing portfolio/data/admin structure, identify precise owned files and deliver a small working increment: responsive portfolio browsing/filtering and case-study presentation using existing content. Include before/after interaction only where actual paired assets exist. Preserve the original navy/gold horizontal logo, exact favicon, PHP/shared-host deployment, app/assets layout and one-page keyless installer. Do not invent client work, testimonials or performance results; label concepts honestly.

Keep identity, billing, provider adapters, installer, shared migrations and global permission changes with Codex. If new storage/admin fields are needed, document the dependency and agree the shared change before implementing it. Avoid sweeping CSS/template rewrites.

Verify keyboard/touch use, desktop/mobile layouts, relevant reduced-motion behavior, actual PHP rendering and changed routes/data paths. Identify fixtures and mocks. Open a PR with roadmap IDs, starting commit, changed files, tests/evidence, actual implementation screenshots, dependencies and limitations. Update continuity documentation. Leave integration, releases and deployment to Codex; do not merge or deploy independently.
