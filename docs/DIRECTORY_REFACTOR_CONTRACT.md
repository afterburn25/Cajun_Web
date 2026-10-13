# Priority directory refactor contract — 2026-10-13

Owner's pasted override is authoritative. Start: main `b975915` after PR #33's test-only correction; actual latest published package is 1.10.0 at `4649a91`, SHA-256 `a9003b041552c6759ade64a471fbfb940e192dcb5ad6d8bcb4d610902f154885`. The unpublished 1.11.0 portfolio candidate is preserved in current source but its old-layout publication is stopped. Codex owns `codex/priority-directory-refactor` and all shared path changes until acceptance/release. No unrelated roadmap development or automatic deployment.

## Inspected baseline

Published 1.10.0 root: `.htaccess`, `activity.php`, `admin.php`, `api.php`, `favicon.ico`, `index.php`, `install.php`, `media.php`, `portal.php`, `robots.php`, `sandbox-notifications.php`, `sitemap.php`. Subdirectories: `app/` (business, templates, foundations and bootstrap mixed), `assets/` (CSS, JS and images mixed). Historical 1.0.8 uses the same app/assets convention with fewer root endpoints. No old `upload/` wrapper occurs in these inspected archives. No live host inspected.

Current source inventory is recorded in [baseline JSON](STRUCTURE_BASELINE_1.11-CANDIDATE.json). Largest mixed files: installer about 96 KB (embedded bootstrap, inventory, storage/database actions, UI and scripts), admin 55 KB (auth/actions/all views), studio-admin 23 KB (actions/rendering), core 19 KB (paths/security/session/database/filesystem/media), workspace 17 KB (schema/client/project/files). Split responsibilities rather than just relocating these files.

Config remains the installed `config.php` contract unless separately tested; root installation guards, external `config()['storage']`, payment master key, guarded media/client documents and immutable delivery ZIPs stay in place. The old package excludes all runtime storage/configuration, docs/tests/tools and canonical source folders. New FULL-PROJECT corrects the development-download omission without exposing tests publicly.

## Shared path and compatibility contract

- Central `includes/paths.php` defines the real site root independently of the executing endpoint. URL helpers retain configured root/subfolder bases and existing cookie/session identity. Bootstrap composes canonical focused security/auth/database/settings/media/filesystem modules; no competing functions file.
- Real admin/portal/controllers/views, public page templates, API handlers, signed webhook endpoint, authenticated media serving, install support and database schema/migration modules move to their named areas. Reuse bundled canonical foundation components.
- CSS/JS move to `assets/css/` and `assets/js/`; original images move byte-identically to images/branding, icons, backgrounds and portfolio. Existing database cover identifiers stay valid via the centralized asset mapping; private media is never relocated into public images.
- COMPLETE uses a small root allowlist. The staged owner migration in UPDATE generates enumerated tiny legacy PHP endpoint shims after verifying/backing up recognized old entries where no-rewrite hosts need old bookmarks, POST actions and signed callbacks preserved; they invoke canonical handlers directly, never generic redirects. Document their retirement window. Internal modules and runtime scaffolds deny direct HTTP at both server and PHP boundaries.
- Protected `storage/` describes the configured persistent private store; protected `upload/` describes request staging beneath that same store, never a second upload database. Only exact scaffold metadata ships. Prefer outside-document-root storage, preserve existing selections, verify required operations as an unprivileged worker, and test local fallback access denial on Apache with/without rewriting.

## Bounded migration and recovery

Owner-authenticated structure migration requires current password, explicit recoverable-backup confirmation, recognized starting files/installed guard, verified replacement inventory and a single retained lock. Only enumerated known historical file hashes may be retired, with protected rollback copies and a durable journal before removal. Missing/modified/unknown files are preserved and reported; never recursively delete directories. New shims must not expose the old privileged implementation. Interruption/retry and denied operations are explicit states. Recovery restores journaled recognized bytes only after hash checks, with original backups and database/private storage preserved. No public repair installer.

## Setup and build contract

One-page keyless installer UI remains. Root installer is a small guarded bootstrap with useful missing-support fallback. Generated self-contained setup bootstrap derives from canonical modules, checked by builder; organized actions/view and inline script source remain maintainable. Cleanup has a fixed setup-only file list; installed guards protect any files whose removal fails. Never remove shared code or runtime data.

Build from a clean recorded commit. Produce FULL-PROJECT (sanitized Git-owned development sources, docs/tests/tools/brand/foundation/CI), COMPLETE-UPLOAD (organized site contents, no wrapper), and independently tested UPDATE. Use explicit runtime-scaffold allowlists, canonical-component checks, installer hashes, per-artifact manifests/checksums/directory inventories, no secrets/symlinks/duplicates/unsafe paths. Include a START-HERE guide. Previous releases/tags/assets remain immutable.

## Acceptance and coordination

Retain all native/database/access/HTTP/Chromium assertions, adapting current-source references without changing historical fixture bytes. Add real Apache 2.4 tests with rewrite enabled/disabled, root/subfolder routes and cookie scope, direct internal/private denial, safe uploads, legacy POST/signature compatibility, missing-installer support and cleanup, exact fresh packages, actual published 1.10.0 upgrade, older paths, customized-file preservation, migration interruption/locks/retry/rollback, root/module/reference/build guards and screenshot comparisons.

Devin PR #31 (`devin/review-requests`) and #32 (`devin/consultation-scheduling`) are independent nonshipping components. Preserve their histories and avoid integration while shared paths change. Send the precise old/new mapping and accepted corrected baseline through issue #2 after verified release. Record actual checks, limitations, PR/main/release IDs and fresh downloaded-back package verification before reporting completion.

Implementation refinement: UPDATE extracts only a protected version-specific staging directory. Owner authentication and backup confirmation precede bounded activation. Unknown/customized conflicting targets abort without overwrite; root server policy temporarily protects all application paths while the owner recovery page remains available. See STRUCTURE_MIGRATION.md for the actual workflow.
