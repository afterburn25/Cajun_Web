# Application 40767087342: cloud assignment navigation

Date: 2026-10-28 (America/Chicago).

## Source and failure evidence

PR #268 head `3789bb50c90cb836530f2d5dc2400cee394763d0` remains owned by the integration session. This isolated correction starts at that head on `codex/cloud-assignment-focused-view`. Devin's PR #266 remains `d062bd5c3b78f3ec265ffc8a404e253013cfa924`; main remains `fdf40a25be3b9a7c2e154a1fad17ab4c7ae55827` at the recovered checkpoint.

[Application 40767087342](https://github.com/afterburn25/Cajun_Web/actions/runs/40767087342) is terminal failure. Build, recovery, Apache and the native database phase passed. Native/browser job `123099199495` recorded 3,218 passing browser checks, then Playwright's unchanged 30-second navigation limit expired loading `/existing/admin/index.php?view=provider-settings` for the mobile cloud assignment capture. The last completed assertion was `Cloud-Assignment-Current-Owner Desktop fits viewport`.

Artifact `12863227633` has SHA-256 `bfd04193a4ceed4edde360164fe6af5112ba4b4b3b93dcb014408907d085e45a`. Its inner partition has SHA-256 `934740095d3a4f3d4e2457c69f15cf06c1493356c6ec20ba610df4ed5f03030e`. CRC and all 569 member lengths/hashes were checked. Identity binds CI checkout `2f5835a018c985af661d97dfe63c9b1ba136b8a0` to source `3789bb50` and tree `1e78ab8e01a7d2602c82fcf0f5c8751630497cb1`.

The retained server log independently confirms the preceding desktop request accepted at 00:34:16 UTC and completed at 00:34:46; the mobile request was accepted at 00:34:51 and did not complete before timeout. The full settings renderer projects every provider workspace's accumulated history. The assignment capture needs the cloud observation and assignment history, so unrelated projections exhaust its navigation budget. No failed individual assertion, provider authentication failure or artifact-upload error preceded this exception.

## Correction

An explicit `focus=cloud-assignment` URL renders the existing cloud inventory and assignment templates, their refresh/assignment history, owner forms, recovery messages and return navigation. It skips unrelated provider workspaces and cloud website creation controls. The ordinary settings page exposes a link to this focused workspace and retains its existing content. Cloud refresh, history preparation and assignment mutation redirects preserve the selector when present. The owner controller gate remains unchanged.

The installed assignment suite uses this workspace, verifies return navigation and exclusion of unrelated controls, and checks that successful redirects retain focus. All existing current/historical/recovery, CSRF/password/stale observation, role/support, tenant isolation and immutable-history assertions remain. Viewports, browser timeouts, provider requests and payment settings are unchanged.

## Verification and limits

- Old actual providers template fails the focused-render regression at the unrelated projection sentinel. Corrected real templates pass 15 checks on PHP 8.2.35 and 8.4.25, default and no INI. Dependencies supplying saved projections and basic HTML helpers are synthetic; this is not routing/auth/browser evidence.
- Existing cloud fixture suite passes 14 checks. Changed Python parses; touched PHP files lint on both runtimes; workflow YAML parses.
- Nine added native assertions exercise the actual templates against native current and missing-head history, owner/support gates, protected forms and preserved rows/network counts. The existing native workflow will execute these plus the new template checks on its four Linux PHP/database lanes.
- Local native setup stopped before any assertion because Windows could not retain the required private-file permissions (`0600`). The original failure is retained; no permission control was bypassed and no local native pass is claimed.
- Full installed browser acceptance is still required. This branch has not been merged, independently approved or deployed. No duplicate full workflow was dispatched.

Machine-readable local source/failure receipts: [review JSON](evidence/pr266-cloud-focus-review.json). Mandatory licensing/security corrections, exact combined acceptance, independent review, real sandbox validation, final main acceptance and release approvals remain separate gates.
