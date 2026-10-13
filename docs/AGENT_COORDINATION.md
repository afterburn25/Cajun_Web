# Codex and Devin work allocation

**1.2.0 administrator-account candidate — 2026-10-13:** [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10), `codex/admin-accounts`. Core A-05 now integrates owner-controlled accounts, selected website/client access, private setup/recovery links, revocation and concurrent last-owner protection. [Administrator guide](ADMIN_ACCOUNTS.md). Starting main `ffdcef3`; latest published release remains immutable v1.1.0. Candidate `53cf246` passed [application acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38361215151): 50 original + 31 staff native checks and 212 real HTTP/Chromium checks. Desktop/320px captures were reviewed. Final explicit pending-password login guard, extra assigned-access tests and documentation require the next exact-head run before merge/publication. No live deployment/provider actions. Devin PR #7 remains unchanged at `55d6067`; no work has been pushed to its branch.


**Release gate satisfied, 2026-10-13:** [v1.1.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.1.0) is published at `3703783e979abab38e8d72e9092c51dc835833b3` and all downloaded assets verified. Read the current exact-release handoff at [DEVIN_HANDOFF_1.1.0.md](DEVIN_HANDOFF_1.1.0.md). Earlier waiting/recovery assignments below are historical. Codex finishes publication records, the authorized issue #2 handoff and issue #4 closure, then stops. Devin's existing work is preserved; notification/receipt/running status must not be inferred from a GitHub comment.

**Owner priority override, 2026-10-13:** finish and verify the existing 1.1.0 website/client workspace, merge actual source, publish and download-verify its GitHub Release, then hand off issue #2 and stop. Completion branch `codex/release-1.1.0` preserves application `90ba4f5`, main `b28a67c` and Devin PR #7 at `55d6067`. Codex owns release fixes/tests/integration/publication; original branches remain intact. Later roadmap work is paused. The user authorized repository changes, isolated tests, PRs, checked merges, publication and the GitHub handoff, not live hosting/customer/provider operations. This overrides earlier parallel-work timing below.

Owner direction, 2026-10-12: split work between Codex and Devin on separate branches, then integrate finished pieces. Codex coordinates integration, shared interfaces, testing and releases. This assignment is not evidence that Devin has started or received a message.

## Current gate and assignments

Owner clarification on 2026-10-13: another worker is finishing **1.1.0** and will publish it to GitHub; start independent work now. Components, explicit contracts and synthetic unit tests may proceed outside the deployment tree. Application integration requires inspecting the published current source. Do not rebuild the application from descriptions or begin from historical 1.0.8. Bring both branches to the same verified source baseline before connecting them to routes, schema or templates.

| Owner | Branch | Initial deliverable | Boundary |
|---|---|---|---|
| Existing 1.1.0 worker | `codex/finish-1.1.0` | Finish and verify current application source, native acceptance and release preparation | Do not overwrite or duplicate this worker's source/installer work |
| Codex in this stream | `codex/platform-foundation` | A-05/A-06 permissions and staff-invitation components; adapt to final 1.1.0 | Own `foundation/access/`, `foundation/invitations/`, their test folders/workflows, permission contracts and later shared identity/migration integration |
| Devin | `devin/public-experience` | Independent W-01 portfolio data/filtering contract and tests first; existing-site presentation after source inspection | Own `foundation/portfolio/`, `tests/portfolio/`, portfolio documentation and a scoped portfolio CI job; request shared backend changes through an agreed contract |

Both branches were created on GitHub from merged-main checkpoint `633f5a1e5c27f0ad626276f2bae75cbc2b88ea66`. Refresh them after verified source import. A branch does not mean its worker is running. The owner selected [GitHub assignment #2](https://github.com/afterburn25/Cajun_Web/issues/2) to paste into Devin; Codex has not dispatched Devin directly. Record the actual Devin session/PR when available.

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

Your first task is **W-01: portfolio improvements**, on `devin/public-experience`, coordinated with Codex's `codex/platform-foundation`. Another worker is finishing 1.1.0. If its source is not yet in GitHub, begin the independent component below; do not invent a replacement site or begin from 1.0.8. Record application integration as pending, not the whole assignment as blocked.

First increment: define a small framework-independent PHP 8.2+ portfolio input/output contract and implement deterministic category filtering and stable ordering in `foundation/portfolio/`. Put synthetic fixtures/tests in `tests/portfolio/` and document fields as adapter inputs, not assumptions about the current database. Preserve explicit concept/client-work labels. Missing/unknown filters, unpublished records, empty results and deterministic ordering need defined behavior and meaningful tests. Only explicitly public/published records may appear in public output. Do not add SQL migrations, routes, placeholder customer projects or a new authentication system. Provide a separate read-only PHP CI job if useful, without changing the existing workers' workflows. This component is excluded from deployment until integrated.

After 1.1.0 is published, inspect the existing portfolio/data/admin structure before adapting the component. Identify precise owned files and deliver responsive portfolio browsing/filtering and case-study presentation using existing content. Include before/after interaction only where actual paired assets exist. Preserve the original navy/gold horizontal logo, exact favicon, PHP/shared-host deployment, app/assets layout and one-page keyless installer. Do not invent client work, testimonials or performance results; label concepts honestly.

Keep identity, billing, provider adapters, installer, shared migrations and global permission changes with Codex. If new storage/admin fields are needed, document the dependency and agree the shared change before implementing it. Avoid sweeping CSS/template rewrites.

For the standalone increment, verify its contract/filtering and state that no UI/application integration exists. For the later UI increment, verify keyboard/touch use, desktop/mobile layouts, relevant reduced-motion behavior, actual PHP rendering and changed routes/data paths. Identify fixtures and mocks. Open small PRs with roadmap IDs, starting commit, changed files, tests/evidence, actual UI screenshots when applicable, dependencies and limitations. Update continuity documentation. Leave integration, releases and deployment to Codex; do not merge or deploy independently.
