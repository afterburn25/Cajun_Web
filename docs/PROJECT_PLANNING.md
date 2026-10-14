# Structured project planning

Implemented on `codex/project-planning-screens`, unreleased. Contracts: [public plan](PROJECT_PLANNING_CONTRACT.md), [native history](PROJECT_PLANNING_STORAGE_CONTRACT.md), [forms and activity](PROJECT_PLANNING_SCREENS_CONTRACT.md). This increment builds on PR1125e44a6b; contract77f92db preceded code. Complete20i stays with Devin under [his acceptance](https://github.com/afterburn25/Cajun_Web/issues/2#issuecomment-6171594708).

## Using a project plan

An owner backs up the database, opens Version & database and enables Project planning using their current password. Preparation preserves the old project description, stage, free-text milestones, approvals, conversations, invoices and files. Planning does not require history tracking of those older fields to be enabled. Existing projects start with no fabricated structured plan.

Authorized project managers open an existing client project and use Edit project plan. Scope, studio contact label, target calendar date, client action and blockers are all visible to that client. Add ordered milestones with a title, details, explicit status, responsible party, optional date and up to eight prerequisites. There can be at most100 retained milestones. Complete prerequisites before marking a dependent milestone completed. Cancel previously saved milestones rather than removing them. A studio contact label does not grant account access. Dates are targets, not guarantees; no completion percentage is invented.

JavaScript provides the milestone editor and submits one bounded serialized value, avoiding shared-host input-count truncation. It is a normal local asset; no Node service or host build step is required. Without JavaScript the other fields can still be saved while milestone bytes are preserved. Save project plan commits the full version atomically. Concurrent changes refuse a stale draft, preserve the submitted version/request and show the saved plan separately. Use the explicit reload link to begin a new edit after reviewing it. An original successful retry returns its original event without reverting later changes.

Clients open their own project to see Scope & next steps and Planning history. The history shows ten recorded revisions per page, exact UTC times and expandable saved snapshots. The first revision is honestly labeled Plan created. Existing conversation and file/review/delivery controls remain available under established permissions. Authorized scoped support can read public planning with actual staff audit; it does not become a client editor.

## Notifications and privacy

When workspace activity is enabled, initial and later plans appear as personal updates. Staff do not receive their own saves. Each link revalidates current project ownership and the complete bounded retained history before opening the exact revision, including old pages. Explicit read/unread belongs to the viewing person; opening a plan does not mark it read. Bells preserve idle expiry. Inconsistent history refuses the notice; the underlying saved plan is independent of activity rendering. This increment sends no email and includes no private staff notes in client plans.

## Evidence and remaining acceptance

Local PHP8.4.25/MariaDB10.11.16:154 native assertions under128MiB, including28 new activity/mode/permission checks and preserved4,096-event/nearly32MiB fixtures. The79 form/render assertions pass both default and no-ini modes;211 planning records and155/57 progress records/forms also pass both modes, totaling1,158 PHP executions. Tests are in `tests/project-planning/forms.php`, `tests/project_planning_screen_native_cases.php` and the existing native suites.

The actual `tests/project_planning_cases.py` module passed39 HTTP/Edge behaviors plus one no-runtime-diagnostics check in an isolated local installation, including Unicode separators, mobile320px, keyboard and no-JavaScript behavior. All six original staff/client/conflict desktop/mobile screenshots were inspected. Initial attempts exposed two fixture setup problems (support preparation and local observation connection charset); fresh synthetic datasets passed after those helpers were corrected. Neither was worked around by weakening authorization or changing canonical records.

Current GitHub PHP8.2/8.4 evidence and full application acceptance are pending. `tests/project_planning_upgrade_cases.py` adds published1.23 populated setup, exact snapshot links/read state, original replay and two recovery/reapplication cycles preserving earlier records/private keys. These new package/Apache cases are implemented but not yet claimed as passed. Independent parent/combined review and exact-main acceptance are required before publication. Published1.23 and frozen108 remain unchanged. Configurable stages, additional contact permissions, financial links and outbound notification preferences/delivery remain broader P-05/P-07 work.
