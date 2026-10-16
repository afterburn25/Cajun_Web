# Devin - start here

Owner assignment updated 2026-10-16: **continue the remaining non-20i CajunWebPro site, including software-product licensing**. Codex and three agents own complete 20i, provider-facing support/SSH, shared integration and versioned releases. The earlier pause on unrelated development no longer applies to Devin's lane.

The full assignment is [docs/DEVIN_NON20I_SITE_COMPLETION.md](docs/DEVIN_NON20I_SITE_COMPLETION.md), also posted as [GitHub issue #157](https://github.com/afterburn25/Cajun_Web/issues/157). It covers project/support workflows, payment forms, admin mail/templates/bells, public-site/lead work, backup/recovery and licensing. Use separate `devin/non20i-*` branches and coordinate shared auth/billing/schema/portal/build changes. Start with one bounded, testable increment and continue.

## Licensing already exists on main

The absence of a branch named licensing does not mean the feature is absent. Main79d9e8e9 contains:

- [Implementation guide](docs/LICENSING_IMPLEMENTATION.md), including current scope and evidence.
- [License service](site/app/licenses/service.php), [fulfilment](site/app/licenses/fulfill.php), and [bundle creation](site/app/licenses/bundle.php).
- [Validation handler](site/api/handlers/license.php), [bundle download](site/api/bundle-download.php), and [admin interface](site/admin/views/studio/licenses.php).
- [Migration](site/database/migrations/license_migrate.php), [schema](site/database/schema/license_schema.php), [native tests](tests/license_native.php), and [functional bundle tests](tests/license_bundle_functional.php).

Read the [four unresolved licensing findings](docs/LICENSING_INTEGRATION_REVIEW.md) and fix those on your own branch before extending the feature. Preserve the existing implementation/history; do not rebuild it or add an installation key to CajunWebPro.

## If the GitHub API is rate-limited

Normal Git fetch and local file reads provide the complete handoff; issue API access is unnecessary:

```sh
git fetch origin main
git show origin/main:DEVIN_START_HERE.md
git show origin/main:docs/DEVIN_NON20I_SITE_COMPLETION.md
git show origin/main:docs/LICENSING_INTEGRATION_REVIEW.md
```

Preserve any current local edits. Create a new `devin/non20i-*` branch from current `origin/main`, or continue your existing owned branch after inspecting its ancestry. Do not reset another worker's checkout, switch onto Codex's active branches, or make further20i changes.

Acknowledge issue157 when API access returns, giving your branch/SHA, next increment and shared files; meanwhile the owner has already authorized the non20i implementation. An acknowledgement is useful coordination, not a reason to remain idle. Codex's active20i source/checks remain in [issue2](https://github.com/afterburn25/Cajun_Web/issues/2), with corrected source [819c3ea3](https://github.com/afterburn25/Cajun_Web/commit/819c3ea39b4ebbef8f37280dc5ede59cf06fd01c) on `codex/twentyi-stackcp-access-observations`. Read the [current checkpoint](docs/TWENTYI_CURRENT_CHECKPOINT.md) for the independently verified focused archives and Windows packages, first full 819c run in progress and preserved original 9779 full acceptance. A branch count is not a completion measure.
