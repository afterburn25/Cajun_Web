# Devin - sole-owner start here

## Owner override - 2026-10-19

Devin now owns the ENTIRE CajunWebPro project: all 20i/provider work, all non-20i work, review, shared-file integration, main merges, testing, packaging and GitHub releases. The owner's first priority is to merge completed work into main safely, then continue every remaining roadmap item independently. Do not wait for Codex to perform integration or finish its previous lane.

Read and follow [the complete takeover directive](docs/DEVIN_FULL_SITE_TAKEOVER.md) before acting. It supersedes old lane restrictions in issues #2/#157 and historical documentation, but not security, data preservation, review, testing or live-operation gates. The latest publication observed at handoff was v1.25.0; fetch current GitHub state before assuming it is still latest.

## Start in this order

1. Preserve current local edits and fetch main, all relevant feature refs, PR dependencies/reviews and actual run attempts. Confirm concurrent workers are not writing the same integration branch.
2. Build a source-bound integration queue for BOTH Codex and Devin work. Identify already-included ancestors, useful ready slices, correction/acceptance blockers and incomplete foundations.
3. Use a Devin-owned integration branch from current main. Fix blockers in mature slices, preserve all shared-file behavior, test/review the exact combined source and normal-merge accepted work to main in dependency order. Do not blanket-merge drafts or wait for every unrelated branch.
4. Publish a verified incremental release with FULL-PROJECT, COMPLETE-UPLOAD, UPDATE, actual screenshots and the required reports/manifests. Verify downloaded public assets. Do not overwrite an existing version.
5. Continue ALL remaining 20i and non-20i items. Keep a stable requirements-based tracker and explicit installed/released/live status. Do not stop at the first release.

Read [AGENTS.md](AGENTS.md), [status](PROJECT_STATUS.md), [handoff](SESSION_HANDOFF.md), [coordination](docs/AGENT_COORDINATION.md), [whole roadmap](docs/ROADMAP.md), [hosting/billing](docs/HOSTING_AND_BILLING_ROADMAP.md), [client portal](docs/CLIENT_PORTAL_ROADMAP.md), [admin mail](docs/ADMIN_MAIL_ROADMAP.md), [20i evidence](docs/TWENTYI_REQUIREMENTS_EVIDENCE.md) and [release workflow](docs/RELEASE_WORKFLOW.md). The takeover directive maps the known pending PR families and concrete contacts/payment-link/VPS caveats; discover newer work rather than treating that list as exhaustive.

## Preserve existing licensing and product architecture

Licensing already exists. Inspect [the implementation guide](docs/LICENSING_IMPLEMENTATION.md), [service](site/app/licenses/service.php), [fulfilment](site/app/licenses/fulfill.php), [bundles](site/app/licenses/bundle.php), [validation](site/api/handlers/license.php), [migration](site/database/migrations/license_migrate.php), [native tests](tests/license_native.php) and [release fixes](docs/TEST_REPORT_1.25.0.md). Do not rebuild it or add a license key to CajunWebPro's own installer.

Keep the approved navy/gold design, organized shared-host PHP/MySQL application, one-page keyless installer, customer data/keys and previous downloads. A component based on an old tree is not a deployable replacement. Repository publication does not authorize production deployment, live charges/provider changes, customer mail or SSH.

## API rate limits

Use normal Git fetch and local files while backing off unavailable API calls. For example:

```sh
git fetch origin main
git show origin/main:DEVIN_START_HERE.md
git show origin/main:docs/DEVIN_FULL_SITE_TAKEOVER.md
```

Do not reset another worker's checkout, force-push or redispatch a queued run. Continue unblocked code/review work and record any narrow external dependency.

First response: acknowledge sole end-to-end ownership, state actual main and integration branch, identify the first ready merge group and exact blockers, then begin execution. This file is an instruction, not evidence that a Devin session has started.

[Previous start guide and historical repairs](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/DEVIN_START_HERE.md).
