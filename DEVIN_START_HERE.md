# Devin - start here

Owner assignment updated 2026-10-16: **continue the remaining non-20i CajunWebPro site, including software-product licensing**. Codex and three agents own complete 20i, provider-facing support/SSH, shared integration and versioned releases. The earlier pause on unrelated development no longer applies to Devin's lane.

The full assignment is [docs/DEVIN_NON20I_SITE_COMPLETION.md](docs/DEVIN_NON20I_SITE_COMPLETION.md), also posted as [GitHub issue #157](https://github.com/afterburn25/Cajun_Web/issues/157). It covers project/support workflows, payment forms, admin mail/templates/bells, public-site/lead work, backup/recovery and licensing. Use separate `devin/non20i-*` branches and coordinate shared auth/billing/schema/portal/build changes. Start with one bounded, testable increment and continue.

## Licensing already exists on main

The absence of a branch named licensing does not mean the feature is absent. Main79d9e8e9 contains:

- [Implementation guide](docs/LICENSING_IMPLEMENTATION.md), including current scope and evidence.
- [License service](site/app/licenses/service.php), [fulfilment](site/app/licenses/fulfill.php), and [bundle creation](site/app/licenses/bundle.php).
- [Validation handler](site/api/handlers/license.php), [bundle download](site/api/bundle-download.php), and [admin interface](site/admin/views/studio/licenses.php).
- [Migration](site/database/migrations/license_migrate.php), [schema](site/database/schema/license_schema.php), [native tests](tests/license_native.php), and [functional bundle tests](tests/license_bundle_functional.php).

Read the [four licensing findings](docs/LICENSING_INTEGRATION_REVIEW.md). [PR #159](https://github.com/afterburn25/Cajun_Web/pull/159) now proposes their fixes; independent review and integration remain pending. Preserve the existing implementation/history; do not rebuild it or add an installation key to CajunWebPro.

## If the GitHub API is rate-limited

Normal Git fetch and local file reads provide the complete handoff; issue API access is unnecessary:

```sh
git fetch origin main
git show origin/main:DEVIN_START_HERE.md
git show origin/main:docs/DEVIN_NON20I_SITE_COMPLETION.md
git show origin/main:docs/LICENSING_INTEGRATION_REVIEW.md
```

Preserve any current local edits. Create a new `devin/non20i-*` branch from current `origin/main`, or continue your existing owned branch after inspecting its ancestry. Do not reset another worker's checkout, switch onto Codex's active branches, or make further20i changes.

Devin [acknowledged the assignment and reported work](https://github.com/afterburn25/Cajun_Web/issues/157#issuecomment-6217621738). Licensing PR #159, tickets #158 and project progress #160 are open; their proposed changes require independent acceptance and sequential integration. Licensing159 has [actionable review findings](https://github.com/afterburn25/Cajun_Web/pull/159#issuecomment-6218434878): unsupported sole-license invoice association, unmarked populated legacy upgrade, and stale installer inventory. Codex's active20i source/checks remain in [issue2](https://github.com/afterburn25/Cajun_Web/issues/2), with current acceptance source [cccf85bf](https://github.com/afterburn25/Cajun_Web/commit/cccf85bf6c3523bca8a0659b5c660915fd67e248) on `codex/twentyi-cloud-minimal-runtime`. Read the [current checkpoint](docs/TWENTYI_CURRENT_CHECKPOINT.md) for the new focused matrix, pending source acceptance and preserved predecessor failures/evidence. A branch count is not a completion measure.

Current integration priority: resolve the [licensing159 findings](https://github.com/afterburn25/Cajun_Web/pull/159#issuecomment-6218434878) and [mail161 findings](https://github.com/afterburn25/Cajun_Web/pull/161#issuecomment-6219105635), then finish existing end-to-end non-20i work. [Coordination update](https://github.com/afterburn25/Cajun_Web/issues/157#issuecomment-6219110174). Your PRs162–169 are contract foundations, and170 is stacked on160; opened PRs are not completed application acceptance.
