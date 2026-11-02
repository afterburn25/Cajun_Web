# 1.26.1 admin / 20i integration checkpoint

Owner-requested work: direct plan catalog import, discoverable backend products, explicit hosting offer publication/front-end browsing, no repeated administrator password, clearer 20i workspaces.

## Source and actual evidence

- Draft PR315, branch `chatgpt/admin-20i-integration-20261030`, is based on the preserved RC branch, NOT merged into main.
- Initial expanded ordinary source: `ebf8aad02a890f192946e737c5803a72d8361506`. Its assembler successfully verified the saved candidate before/after hashes and preserved existing payment-security workflows.
- Run `41113805727`: assembly and portable jobs passed. Downloaded portable artifact `13005206842` has SHA256 `7a26e84889197d1c93ad0e4c23df91c340288928fe74903cb264ea7f5690a958`; the report contains 28 passing suites and 1,441 named executions. These are portable checks, not SQL/browser acceptance.
- The four SQL/browser matrix jobs were still running/queued at this checkpoint. Their final reports, rather than process exit status alone, must be present, non-empty and passing.
- Local real file-updater rehearsal passed 17 checks against the exact deployed RC: verified identity, interrupted apply, maintenance/journal, resume, exact replacement bytes, config/keys/private uploads preservation, idempotent retry, rollback, customized-file refusal, corrupt-upload refusal. It does not test SQL migrations or owner-authenticated HTTP updates.
- This follow-up adds 17 modeled owner-updater policy/source checks. All 29 focused suites (1,458 named checks) pass locally with that change; exact-head hosted results are required separately.

## Exact deployed RC identity

Run `41077725683`, artifact `12988737538`, ZIP SHA256 `1694d5c94dc88e7d5493b89831d79f4c88c85ee09db3d199fee4a33496f46e44`. The sorted compact JSON map of all 2,956 actual site file hashes (excluding the extra RC notice) has SHA256 `4f0a9b3363c5ade78614a79c4752dae6233234a196fa8765eba34c420effd037`.

The updater history previously ended at 1.25.0. The new baseline tool reconstructs the exact RC from fixed Git source/overlays, regenerates its canonical files, and requires that whole-map digest before recording 1.26.0 as a **deployed release candidate**, not a published release. It refuses a conflicting existing entry and retains older history. No arbitrary same-version source or locally customized files are accepted.

## Remaining release blockers

Full populated SQL upgrades from the exact deployed RC and published 1.25; historical full-suite contract reconciliation (retaining denial assertions); review of session-only account/key/payment boundaries; installed owner update/rollback; independent review; exact-main integration/acceptance; clean-source package build and downloaded archive verification. Focused component success cannot replace these gates.

The standalone owner updater now uses the active owner session too, retaining its actual initial sign-in password when signed out. Seventeen modeled-policy/source checks pass for owner/expired/revoked/disabled/support boundaries and retained initial-login/CSRF/backup guards; installed owner-update HTTP/SQL acceptance remains pending. No production installation or account credentials were changed.

No production deploy, live provider requests, product publication, charges, customer messages or main merge. Do not rerun the old RC packaging workflow and claim these new changes are included. No installable update is approved by this checkpoint.
