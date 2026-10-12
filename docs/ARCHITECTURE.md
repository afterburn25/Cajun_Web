# Architecture notes

Checkpoint: 2026-10-12. This is an inventory of the supplied 1.0.8 distribution and a guide for extensions. **The distribution is not yet imported into this Git tree.**

## Deployment model

Existing application: PHP with PDO MySQL/MariaDB, normal browser requests, local CSS/JavaScript/images, and persistent private file storage. PHP source targets 8.2 or newer. Required extensions are checked by the installer. Shared-host deployment must not require a Node service, command-line build, root access, or a long-running worker.

The repo's documentation tree is not the website document root. Decide and document the source directory at import. Build the deployment ZIP from the deployable files only, without a source-directory/version wrapper. Preserve the existing app/assets grouping and root entry-point behavior.

## Inspected source map

| Distribution path | Responsibility indicated by source/package |
|---|---|
| `index.php` | Public page routing/rendering |
| `app/home.php`, `app/view.php` | Homepage and common public presentation |
| `admin.php`, `app/admin-view.php` | Administration workflows and views |
| `api.php` | Public form/API handling |
| `app/core.php` | Common configuration/session/database/security/filesystem helpers; version constant |
| `app/schema.php` | Initial table definitions and seed/default content |
| `app/mail.php` | Mail delivery helpers |
| `media.php` | Media-serving entry point |
| `robots.php`, `sitemap.php` | Search-crawler endpoints |
| `install.php` | Embedded-bootstrap, one-page setup and package inventory |
| `.htaccess`, `app/.htaccess`, `app/index.php` | Existing server/application access protections |
| `assets/` | Logo, emblem, existing favicon, site/admin CSS/JS and decorative/project artwork |

These responsibilities do not substitute for a full code review. Native database and service behavior were not exercised in the documentation session.

## Initial schema observed

Ten named definitions in `app/schema.php`: settings, users, projects, services, testimonials, leads, media, rate_limits, resets, audit. Definitions use a validated table prefix and InnoDB/utf8mb4 declarations.

Existing lead records include a request/reference, contact/project requirements, state/notes, and notification status. Existing public project records include publication/demo/featured state and case-study fields. Do not confuse these public portfolio projects with a complete multi-client project-management/approval/invoicing model.

## Runtime state to preserve

Configuration and installation guards are created at installation. Private media and other persistent data are not release payload. Protected `config.php`, installed markers, storage, media and DB data must survive application updates. Do not publish account-specific proxy configuration, credentials, private diagnostic paths, or operational backups in Git.

The installer contains an exact dependent-file inventory. Altering CSS, JavaScript, PHP, logo/icon files, or the set of deployable files requires regenerating that inventory; otherwise the correct application change may be rejected as a mixed upload.

## Extension design constraints

- Add reviewed schema migrations for new capabilities; do not use fresh setup to upgrade an installed site or overwrite existing tables.
- Distinguish portfolio records, leads, client organizations/accounts, client projects, proposal versions, approvals, files, messages and financial records as appropriate after source review.
- Enforce authorization server-side for each resource; hiding a link is not client isolation.
- Keep estimator calculations and business validation server-side, regardless of live UI previews.
- Use hosted payments and verified notifications; never store raw card data.
- Keep customer uploads outside public reach and validate type, size, authorization and retrieval paths.
- Provide accessible non-drag/non-animation controls and preserve root/subfolder deployment.
- Background integrations must fit real hosting capabilities; do not silently depend on a daemon or a cron job that has not been configured.

Architecture choices for these extensions remain to be implemented and tested. Update this file with actual routes, classes/modules, schema migrations, provider selections and data flows when they land.
