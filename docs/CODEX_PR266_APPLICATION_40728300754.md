# PR #266 Application run 40728300754 review

Date: 2026-10-28

## Exact source and result

- PR #266 head: `d062bd5c3b78f3ec265ffc8a404e253013cfa924`.
- Application run: `40728300754`.
- Failed job: `122971621925` (`native-and-browser`).
- Build, recovery, Apache and the native database phase passed.
- The installed browser phase recorded 2,997 passing checks before its first exception.

The first exception was Playwright's unchanged 30-second navigation limit while loading:

`/existing/admin/index.php?view=provider-settings&stackcp_page=1&stackcp_access_page=1`

The exact browser checkpoint artifact is `12850372335`; its downloaded archive digest is `8d125b4d5d76e7eec26402d64a8c52e123322b5bbd7b8cfd25d020a2ef79f7d4`.

## First cause

The retained PHP server log shows the focused StackCP URL repeatedly finishing in approximately 27–31 seconds. The final request was accepted at 20:40:13 UTC and did not finish before the browser process ended at 20:40:44 UTC. This is not an artifact-upload or runner-startup failure.

The provider settings view treated StackCP pagination as ordinary query state and still rendered every unrelated provider workspace. On the populated cumulative acceptance database, those workspaces reproject and validate their own retained histories before the two StackCP projections run. The response therefore reached the real browser timeout even though the requested page and screenshots concerned only StackCP setup/access.

## Compatible correction

The independent integration candidate now treats either bounded `stackcp_page` or `stackcp_access_page` as an explicit focused provider view. It retains the saved provider account and hosting inventory needed to create and verify StackCP bindings, renders both StackCP setup and access workspaces, and omits unrelated secondary provider workspaces. A visible link returns to the complete 20i settings page.

StackCP setup and access redirects retain both bounded page selectors. The two installed suites start on the focused URL and assert that both StackCP sections are present while unrelated Force SSL and directory-listing control sections are absent. No authentication, CSRF, owner-only boundary, provider-read contract, timeout, immutable-history validation or production gateway safeguard is weakened.

## Local evidence and limits

- Python syntax for both changed installed suites: pass.
- Build rejection guards: 69/69 pass.
- Artifact transfer/provenance helpers: 283/283 pass.
- StackCP isolated HTTP handler contract: 21/21 pass.
- Canonical installer inventory refreshed for the three changed deployable PHP files; its recorded hashes match the source bytes.
- Documentation continuity: 12 required documents, 293 Markdown files and 1,911 local links pass.
- Workflow syntax: all 99 YAML files parse.
- `git diff --check`: pass.
- PHP is unavailable in this workspace, so the complete build command stops at its PHP lint phase after refreshing the inventory; exact hosted PHP/database/HTTP/Chromium execution is required.
- This correction performs no Authorize.net transaction and supplies no real sandbox evidence.
- The existing session-isolation correction remains required for the late software-commerce and financing suites after this earlier performance blocker is cleared.

## Remaining gates

Fresh exact-candidate Application acceptance, all required package/security workflows, independent review, real owner-authorized Authorize.net sandbox evidence, a real populated v1.25-to-next-version upgrade, exact merged-main acceptance, approvals and release verification remain mandatory. No merge, release, deployment or live payment is authorized by this report.
