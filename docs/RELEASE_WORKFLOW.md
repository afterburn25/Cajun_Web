# GitHub testing, incremental releases and agent continuity

## Current directory and package contract — 1.11.0 candidate

The owner directory correction supersedes all earlier flat-root/overwrite-update instructions below. See [Directory structure](DIRECTORY_STRUCTURE.md), [migration and recovery](STRUCTURE_MIGRATION.md), and [START-HERE](../START-HERE.txt). Maintain thin root entries, organized controllers/views/business/database modules, categorized images and protected storage/upload scaffolds. FULL-PROJECT contains docs/tests/tools/brand/foundation/CI; COMPLETE-UPLOAD contains organized production files without a wrapper; UPDATE stages a verified owner-authenticated migration instead of blindly overwriting live files. Existing config/keys/private data and other agents' branches remain preserved.

Candidate `9e8dedf` in [PR #38](https://github.com/afterburn25/Cajun_Web/pull/38) passes 618 native, 782 HTTP/Chromium, 41 filesystem/recovery, 203 Apache and 16 package-builder checks. [Acceptance record](TEST_REPORT_1.11.0.md); [Devin handoff](DEVIN_HANDOFF_1.11.0.md). Final cleanup/docs acceptance, merge, exact-main tests and immutable publication remain mandatory. Latest published release remains 1.10.0. No live deployment.

## Owner priority override — directory structure and packaging, 2026-10-13

The owner requires an actual structural refactor and three separately verified downloads before any unrelated roadmap work resumes. This supersedes earlier constraints that kept substantial root PHP files or interpreted a no-wrapper ZIP as a flat application. Work on `codex/priority-directory-refactor` from main `b975915` preserves integrated portfolio/estimator work and the tested browser correction. Latest published version is **1.10.0**; the 1.11.0 portfolio-only release was stopped before draft/tag creation. The unused 1.11.0 candidate will be corrected before release. Do not publish the old-layout candidate.

Required meaningful areas: admin, portal, pages, includes, business app modules, API/webhooks/media, install, database, config, assets/css and assets/js, categorized images, protected storage/upload scaffolds. Root entry points must be thin. FULL-PROJECT includes docs/tests/tools/brand/foundation/CI; COMPLETE-UPLOAD extracts organized site contents directly; UPDATE includes tested compatibility/migration support. Keep private configured data locations, exact brand bytes, one-page keyless setup, all existing behavior and no-rewrite/root/subfolder support. [Refactor contract](DIRECTORY_REFACTOR_CONTRACT.md). No live deployment is authorized.

Devin PRs #31/#32 are separate independent components, preserved and paused for integration while paths change. Supply the exact mapping and corrected baseline before further application adapters. Next action is structure implementation, migration/recovery and Apache/native/browser/package acceptance—not another roadmap feature.

The first v1.1.0 publication completed this workflow: exact-main tests, deterministic packages, draft verification, publication, clean download verification and retained evidence. See [release register](RELEASES.md) and [test report](TEST_REPORT_1.1.0.md).

Owner direction recorded 2026-10-12: use GitHub for testing, deliver incremental versions, retain copies of released versions on GitHub, and document the project so other agents can resume without reconstructing chat history. This is standing authorization for ordinary repository updates, isolated CI tests and publishing verified project release packages. It does not itself activate production deployments, live charges, customer mail or paid provider operations.

## Current implementation

- [Repository checks](../.github/workflows/repository-checks.yml) runs on pull requests, pushes to `main` and manual dispatch. It uses read-only repository permissions and no production credentials.
- [Documentation checker](../tools/check_docs.py) verifies required continuity files and inline relative Markdown links, including heading targets. Use `python tools/check_docs.py` locally. External URLs, reference-style links, HTML links and application behavior are outside this check's scope.
- [Application acceptance](../.github/workflows/application-tests.yml) verifies exact source/package bytes, native MariaDB and actual HTTP/Chromium workflows, plus fresh/update package behavior. It retains reports and screenshots. Final combined-main acceptance and downloaded release verification are required; documentation checks alone are insufficient.
- The independent [access-policy workflow](../.github/workflows/access-policy.yml) now tests isolated PHP permission decisions on 8.2/8.4. This is component verification only; application CI/build/release gates still await 1.1.0 integration. Independent foundation code is excluded from deployment packages until adapted and accepted.
- [Release register](RELEASES.md) distinguishes owner-reported versions, verified source and published deployment packages. No placeholder application release or version bump is justified by this documentation change.

## Work in reviewable increments

The [staff-invitation job](../.github/workflows/staff-invitations.yml) adds native MariaDB component tests with synthetic fixtures and concurrent PHP processes. A successful run establishes only that component's persistence/locking behavior, not the current application's migration, invitation UI, email delivery or release readiness. Retain that distinction alongside the access-policy and documentation jobs.

1. Fetch GitHub and read the current status, handoff, rules and relevant roadmap before editing. Inspect open PRs and claimed tasks to avoid conflicting work.
2. Start from current `main` or an explicitly documented active branch. Give each feature or fix a bounded acceptance target, identify its roadmap IDs, and preserve existing behavior and customer data.
3. Commit implementation, relevant tests and continuity documentation together. Use a PR with the exact checks run, limitations, migrations and intended release scope. Fix failed checks before merging; never bypass required repository protections.
4. Merge ready, verified increments into `main` so new agents see the current state. Record unfinished work and branch/PR links at the top of the handoff. Documentation-only updates use commits without inventing an application version.
5. Publish an incremental application release when its acceptance and package gates pass. A release can deliver a small useful subset of the roadmap; it must list deferred capabilities clearly.

## Version policy

- The owner originally identified **1.1.0** as current. It was recovered and released, and the verified **1.2.0** administrator increment now follows it. Inspect current main and the release register before selecting another version. Preserve older published versions; never relabel historical packages.
- Use `MAJOR.MINOR.PATCH`: compatible fixes increment patch; compatible features increment minor; incompatible changes increment major and include migration guidance. Illustrative next versions could be `1.1.1` for a fix or `1.2.0` for features; these are not reserved or promised builds.
- Use explicit prerelease tags such as `v1.2.0-rc.1` for builds intended for testing. Mark them as prereleases and do not advertise them as the latest stable version.
- Version labels in application code, installer, manifest, package filename, changelog and Git tag must agree. A tag names an exact source commit; test that source and the exact attached package.
- Never move a published release tag or silently replace its ZIP. Correct it with a new version, explain the issue, and retain prior versions with appropriate known-issue notes. Do not retain leaked secrets or customer data as historical release material.

## GitHub testing after source recovery

Add runnable checks based on the inspected application, not invented paths or guessed behavior. Development/CI tooling can use GitHub runners without adding command-line requirements to the customer installation.

| Gate | Evidence required |
|---|---|
| Source checks | PHP and JavaScript syntax; relevant behavior tests; dependency and secret checks suited to actual source |
| Database and installation | Isolated MySQL/MariaDB; fresh install and supported upgrades; failure/retry paths; retained config/media/data; one-page keyless installer and installed-site guards |
| Permissions and browser flows | Actual PHP pages; client/staff separation, support-session attribution, CSRF/session handling, public/admin mail isolation; representative desktop/mobile acceptance |
| Provider integrations | Explicitly identified mocks for failure cases plus separate sandbox results for payments, mail and 20i where available; no invented passing result for a skipped or unavailable integration |
| Distribution | Organized FULL-PROJECT, COMPLETE-UPLOAD and staged UPDATE, per-artifact manifests and SHA-256; exact version alignment, regenerated installer dependency hashes, ZIP integrity, clean extraction and installation from that ZIP |
| Upgrade and release | Supported starting versions, migration/data compatibility, backup and recovery evidence, known issues and target-host acceptance when claimed |

Use synthetic data and restricted test accounts. Keep secrets in approved GitHub secret/environment storage when needed, never in source, logs, reports or public assets. A mock result, skipped job and real sandbox test must remain distinguishable. GitHub CI does not replace actual shared-host checks or prove production is deployed.

## Permanent version copies on GitHub

For each verified application version, create a GitHub Release attached to its exact `vX.Y.Z` tag. Prepare a draft, attach and verify all intended files, then publish. Keep prior releases accessible.

Required public assets:

- `CajunWebPro-X.Y.Z-FULL-PROJECT.zip`: sanitized source, docs/tests/tools/brand/foundation/CI for development.
- `CajunWebPro-X.Y.Z-UPDATE.zip`: independently tested owner-authenticated staged migration for existing sites.
- Per-artifact `.manifest.json` and `.directories.txt`, START-HERE, test evidence/report and migration instructions.
- `CajunWebPro-X.Y.Z-COMPLETE-UPLOAD.zip`: organized deployable website contents without an enclosing wrapper, meeting [the package contract](INSTALLATION_AND_UPDATES.md#package-contract).
- `SHA256SUMS.txt`: hashes of the attached ZIP and other delivered files.
- `manifest.json`: version, source commit, package layout and shipping-file hashes, without private build paths or credentials.
- `TEST_REPORT.md`: exact tested revision/artifact, environment, checks/results, known failures, untested areas and supported installation/upgrade paths.

Release notes explain delivered features/fixes, prerequisites, upgrade instructions, data compatibility and known limitations, with links to relevant PRs and CI runs. Also update [the release register](RELEASES.md), changelog, status and handoff. An update-only ZIP is optional and must be independently tested for explicit supported versions; it never replaces the full package.

GitHub's automatic source-code ZIP is a source snapshot, not our complete upload package. Actions logs and temporary build artifacts are useful evidence, but release assets are the retained download copies. Do not place ZIP copies of every release in ordinary Git history. Client-specific website deliveries, databases and credentials stay in authorized private client storage, outside this public repository's releases.

Verify publication by reading the release back from GitHub, downloading the attached deployment ZIP and comparing its hash and archive integrity with the tested package. Confirm the tag still resolves to the recorded source commit. Only then report a working release download link. Publishing a package does not establish host deployment or a tested downgrade path.

## Handoff contract for every agent

Keep [PROJECT_STATUS.md](../PROJECT_STATUS.md) and [SESSION_HANDOFF.md](../SESSION_HANDOFF.md) concise at the top: current source version/commit, latest published version and URL, what works, what is planned, current branch/PR, test evidence, blockers and the next concrete action. Date older findings and make superseding instructions explicit.

Append an entry to [SESSION_LOG.md](SESSION_LOG.md), update [CHANGELOG.md](../CHANGELOG.md), and synchronize affected roadmap/architecture/decision documents. State whether any source, package or target host changed. Never convert planning text into a completion claim. Do not rely on another agent having access to this chat, local temporary files or credentials.

## Official references

Reviewed 2026-10-12: [GitHub Actions workflow syntax](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax), [managing releases](https://docs.github.com/en/repositories/releasing-projects-on-github/managing-releases-in-a-repository), and [source-code archives](https://docs.github.com/en/repositories/working-with-files/using-files/downloading-source-code-archives).

The [estimator workflow](../.github/workflows/estimator.yml) verifies only standalone decisions on PHP 8.2/8.4. It does not test the current estimator form, quantity pricing, database or notification delivery; the component remains excluded from deployment.
