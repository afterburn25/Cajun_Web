# Project history and dated updates

This is an unreleased increment on `codex/project-progress-screens`, based on preserved native PR106 / `c41cc6d`. Contract commit `19191b4` precedes the screen implementation. Published 1.23 is unchanged. [Contract](PROJECT_PROGRESS_CONTRACT.md) · [Client portal roadmap](CLIENT_PORTAL_ROADMAP.md).

## Enable history

An owner opens **Version & database**, confirms a current database backup and their current password, and selects **Enable project history**. Preparation is additive. It retains existing projects and does not invent earlier changes. Until this explicit step, existing project editing remains available and the screen says history is not enabled. Unknown storage versions or retained records without an activation marker require review; the editor cannot silently bypass them.

## Save and review changes

Use the existing client workspace to create a project or edit its title, summary, stage and milestones. These fields and saved changes are client-visible. Existing project ownership stays fixed. The first real change to an existing project records what was on file when tracking began, then the change itself. Later changes append dated entries. The history shows the actual fields changed and the saved details at that point, in UTC. It does not infer a completion percentage or turn payment status into production progress.

If another editor saves first, the rejected form retains its bounded unsaved text and original request/version. Current saved details appear separately. Use **Reload saved details and start a new edit** to review the latest version before applying a fresh change. Retrying the old accepted request returns its original result without replacing newer details or creating a duplicate project. Malformed or oversized values are explicitly identified rather than silently truncated.

Staff and the owning active client see history in their existing project detail screen. The newest page opens by default; older/newer controls show at most 30 updates per page. Activity links open the precise update even on an older page. Existing designs, proposals, conversations, files, deliveries and invoices remain available. A scoped support session may read the permitted client's public history with real staff attribution; it cannot edit progress or mark the client's activity read.

## Activity and permissions

Actual updates appear in the existing personal activity center and private workspace bell. Tracking baselines do not create notifications. Staff activity excludes their own updates. Opening a link does not mark it read; explicit read controls remain per person. Same-second updates retain numeric revision order and pagination does not depend on random IDs. Passive counts preserve idle-session expiry. These are local workspace events, not automatically sent email.

Writes recheck current `clients.manage`, current project ownership and active client under the shared staff lock. History reads, activity counts, destinations and read-state changes validate the retained native chain and independent head anchor. Project fields, event, original request, head and audit commit together. The bounded adapter retains at most 4,096 events per project and refuses further changes at capacity without pruning existing history. Client markup excludes staff identifiers and native hashes. Private handlers are guarded against direct HTTP access; project pages use private cache/referrer headers.

## Current evidence and remaining acceptance

Local PHP 8.4.25 / MariaDB 10.11.16 verifies **139 named native assertions**, including the parent's 109 and 30 activity/read-state cases. Pure form and actual HTML-render tests pass **57 named assertions in each default/no-ini mode**. The portable actual HTTP/Edge module passes **43 behavior assertions**, plus one local-only check for PHP diagnostics. Six actual staff/client/conflict desktop and 320px screenshots were inspected over their full height. All 2,610 installer dependency hashes match the 2,611 shipping files; the source refresh also checks PHP and JavaScript syntax. Canonical project/desk models and provider implementation match the preserved base.

The first local browser launcher failed before testing because its Python environment lacked `requests`; the existing browser-test environment corrected that. Visual review then caught mobile screenshots taken during the sidebar close transition despite successful width checks. The test now checks open/Escape-close behavior and waits for closure before final captures; all final cases pass. Earlier captures remain separate evidence, and no shipping CSS workaround was needed. The local source comparison differs only in the installer inventory refreshed after the runtime copy. A verifier initially compared Windows separators with POSIX separators; normalization corrected the evidence helper, without changing application code.

Latest-1.23 populated project update/recovery/reapplication cases are implemented and syntax-checked. They verify history, original requests, head anchors, personal reads, private keys and pre-existing records through real updater cycles, including throttled refusal. They are **not yet executed as packaged Apache acceptance**. Exact committed PHP 8.2/8.4 matrices, full combined application tests, Linux/Windows packages, final browser artifacts and independent review remain required. This increment is not a feature release or target-host verification.

Full P-05/P-07 remains partial: structured milestone ownership, due dates/dependencies, blockers, broader notification preferences/dispatch and service monitoring require further work. Devin owns complete 20i implementation under [his accepted assignment](https://github.com/afterburn25/Cajun_Web/issues/2#issuecomment-6171594708). Codex owns this non-20i increment and final integration/release acceptance. No live provider operation, customer message, charge, SSH session or production deployment was performed.
