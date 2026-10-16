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

## Current priority: repair existing features

1. [Tickets158 review](https://github.com/afterburn25/Cajun_Web/pull/158#issuecomment-6219612932): register tickets.css in the asset map, cover actual admin/client layout rendering, refresh inventories and complete browser acceptance. Preserve dependent175.
2. [Licensing159 review](https://github.com/afterburn25/Cajun_Web/pull/159#issuecomment-6218434878): repair unsupported legacy invoice association, populated unmarked migration and stale installer inventory.
3. [Mail161 review](https://github.com/afterburn25/Cajun_Web/pull/161#issuecomment-6219105635): recheck current source-mailbox read permission for copied saved content/history, fix mixed pagination and the failing SMTP fixture.

Then continue complete non-20i workflows from the full assignment. Preserve stacked160->170->171->174 (projects/planning/design payments/recurring billing),169->173 (service plans), and158->175 (ticket captures);172 proposes the billing calendar. Foundation PRs162–169 and new runtime proposals are unaccepted work until independently tested and integrated. Coordinate shared billing/auth/schema/portal/installer/CI changes; do not replace current Codex files with old whole-file versions.

Devin [acknowledged the assignment](https://github.com/afterburn25/Cajun_Web/issues/157#issuecomment-6217621738). Codex's current separate20i candidate is [3fa72d7b](https://github.com/afterburn25/Cajun_Web/commit/3fa72d7b3b447fa3d281cc981ed861c508f850f2) on codex/twentyi-stackcp-read-snapshot, with first focused38933590118 active. [Current checkpoint](docs/TWENTYI_CURRENT_CHECKPOINT.md) records exact evidence; [issue2](https://github.com/afterburn25/Cajun_Web/issues/2) tracks20i. Branch counts are not a completion measure.
