# CI queue health

How to diagnose GitHub Actions backlog for this repository.

## Reading the report

Run `python tools/ci_health.py` (requires the `gh` CLI authenticated to
`afterburn25/Cajun_Web`) or open the latest `CI queue health` workflow
run's step summary / `ci-health-*` artifact. The report covers:

- queued vs in-progress run counts,
- the oldest queued run's age,
- per-branch and per-workflow backlog distribution,
- recently canceled/failed/succeeded runs and >60-minute legs,
- the observed effective runner concurrency (in-progress count while a
  backlog exists approximates the account cap).

## Diagnosing a backlog

| Symptom | Likely cause | Action |
|---|---|---|
| queued >> running; jobs sit with no `runner_name` for >30 min | Backlog ahead of account concurrency | Find which branch dominates; check for repeated pushes that enqueue superseded runs |
| One branch produces most queued runs | Its workflows lack working per-PR/per-branch cancellation | Verify that workflow's `concurrency:` block (see rules below) |
| queued > 0 with zero in progress, GitHub status green | Account-level Actions concurrency or billing spend cap | Check account Settings → Billing → Actions; check for runs marked `waiting` |
| Many `cancelled` conclusions on superseded runs | Working as intended — obsolete dev runs were superseded | Investigate only if the *newest* run for a branch/PR was also canceled |
| Long-running legs hold all slots | A large acceptance/native matrix is in flight | Expected during acceptance; confirm the runs are progressing, not stalled |

## Test tiers

CI is organized in three levels; membership is determined by workflow
cost and purpose, not by file naming.

- **Tier 1 — fast development checks** (~2–8 min): `repository-checks`
  (documentation/links, always runs), `mail-receiving`, `mail-transport`,
  and every `*-contract`/`provider-*`/`domain-*`/`hosting-*`/`twentyi-*`
  native check. These carry the superseding concurrency group and run on
  relevant pushes and pull requests only (each is path-filtered to its
  component).
- **Tier 2 — integration**: the same native checks against the database
  matrix (MariaDB 10.11 / MySQL 8.4) plus `application-tests` in
  checkpoint mode when explicitly dispatched. Triggered by relevant pull
  requests, controlled checkpoints and manual requests.
- **Tier 3 — release certification**: `application-tests` (full mode),
  `release-licensing`, `release-upgrade-diagnostic`. These run only for
  approved release candidates or explicit requests, are never
  auto-canceled, and are never treated as satisfied by a partial suite.

A pull request whose changes match no workflow's `paths` filter runs only
`repository-checks` — documentation-only changes therefore never enqueue
expensive native or acceptance jobs.

## Concurrency rules in this repository

Every development check workflow must carry:

```yaml
concurrency:
  group: <workflow-key>-${{ github.event.pull_request.number || github.ref }}
  cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}
```

- The group is per pull request (or per branch for pushes); a newer push
  or PR sync cancels queued and running superseded runs of that workflow
  for that ref only — never other branches or other workflows.
- `main` pushes are exempt from cancellation (`cancel-in-progress` is
  false there) so every main commit keeps its evidence trail; runs for
  the same workflow on `main` serialize in order.
- Never add `github.sha`/`run_id`/`run_attempt` to a development check's
  group — a per-commit group makes `cancel-in-progress` a no-op and is
  the exact pattern that produced the ~1000-run backlog on 2026-10-26.

Protected workflows that must never be converted to newest-wins
cancellation:

- `application-tests.yml` — the sealed acceptance pipeline; every
  dispatched run is preserved evidence (`cancel-in-progress: false`,
  group includes ref+sha+mode+php+database).
- `release-licensing.yml`, `release-upgrade-diagnostic.yml`,
  `stackcp-access-read-diagnostic.yml` — release/diagnostic evidence,
  per-run unique groups.
- `import-preserved-source.yml`, `twentyi-catalog-schema.yml`,
  `twentyi-cloud-root.yml` — fixed serialization groups for explicit
  historical/read-only operations on their dedicated branches.
- The six `codex/finish-1.1.0`-scoped recovery workflows — inert
  historical-source tooling, trigger only when their own file changes.

## Dispatch discipline

- Do not redispatch a queued run; queued is not failed.
- Do not dispatch a duplicate run for a commit whose run is pending.
- On a shared account, keep ad-hoc polling at ≥10-minute intervals; the
  REST rate limit (5000/hr) is shared across every session using the
  account token.

## Known follow-up

The native check workflows build `tests/invitations/Dockerfile`
(`php:<ver>-cli` + `pdo_mysql`) per matrix leg with no cross-run layer
cache. If runtimes become the bottleneck after the queue is fixed,
`docker/setup-buildx-action` with `type=gha` cache is the candidate
optimization — evaluate measured build times first.
