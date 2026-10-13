# Directory structure — current source contract

## Candidate 1.17.0 — acceptance pending

[PR #69](https://github.com/afterburn25/Cajun_Web/pull/69) adds explicit assigned-staff INBOX header reading, protected bundled dependencies, strict request auditing and an additive upgrade setup. See the [receiving contract](MAIL_INBOX_READER.md). New native/HTTP/TLS/browser/Apache and 1.16.0 upgrade/recovery acceptance is pending. Latest published version remains 1.16.0; publication summaries below describe their recorded release, not the active candidate. The permanent organized structure remains in force.


This organized layout supersedes the historical flat-root conventions. A ZIP without a wrapper still contains normal application subdirectories. Do not add root business logic, restore app/core.php, or put public images back beside scripts and styles.

## Before

The inspected published 1.10.0 COMPLETE-UPLOAD contained substantial root `admin.php`, `portal.php`, `api.php`, `activity.php`, `media.php` and `sandbox-notifications.php`; a 96 KB root installer; mixed business code and templates in `app/`; and mixed CSS, JavaScript and images in `assets/`. No enclosing upload wrapper was present. No live host was inspected.

```text
website/
  index.php, admin.php, portal.php, api.php, activity.php
  media.php, sandbox-notifications.php, install.php
  robots.php, sitemap.php, favicon.ico, .htaccess
  app/        business + schema + shared helpers + page rendering
  assets/     CSS + JavaScript + images
```

## Current production tree

```text
website/
  index.php             public front controller bootstrap
  install.php           temporary guarded setup bootstrap and missing-file fallback
  robots.php            thin crawler entry for hosts without rewrite support
  sitemap.php           thin crawler entry for hosts without rewrite support
  favicon.ico, .htaccess
  admin/
    index.php
    controllers/        auth, dispatcher, CRUD and module actions
    views/              individual administration screens and studio views
  portal/
    index.php
    controllers/        support context, client auth, actions and route dispatch
    views/              client auth and workspace rendering
  pages/
    controller.php
    home/, portfolio/, services/, comparisons/, contact/, marketing/
    about.php, process.php, privacy.php
  includes/
    bootstrap.php, paths.php, urls.php, database.php, runtime.php
    auth/, security/, filesystem/, layout/, foundation/
  app/
    clients/, projects/, portfolio/, billing/, activity/
    staff/, support/, settings/, marketing/, mail/, media/, updates/
  api/
    index.php, activity.php, robots.php, sitemap.php
    handlers/           guarded request handlers
  webhooks/authorize-net/
    index.php, receive.php
  media/
    index.php, serve.php
  install/
    actions/, views/, controller.php
    bootstrap.generated.php, inventory.generated.php
  database/
    schema/, migrations/
  config/
    defaults.php, example.php
  assets/css/, assets/js/
  images/branding/, images/icons/, images/backgrounds/, images/portfolio/
  storage/              protected metadata only in Git and packages
  upload/               protected metadata only in Git and packages
```

The repository and FULL-PROJECT additionally retain `.github/`, `docs/`, `tests/`, `tools/`, `brand/`, `foundation/`, README, AGENTS, current status, handoff and changelog. Tests and development tools are never deployed by COMPLETE-UPLOAD or the UPDATE payload.

## Responsibilities and dependencies

`includes/bootstrap.php` composes focused modules; it is not a replacement monolithic functions file. `CWP_ROOT` is the installation identity and permanent root for configuration/guards. `CWP_CODE_ROOT` is the immutable code location (different only while the owner runs a staged update). URL helpers use the installed `site_url`, preserving root/subfolder cookie scope and the original session-name hash. Before setup, only the real root installer supplies the base path. HTTPS enforcement preserves request method using 307.

The former core's input, HTTP headers, CSRF, crypto, rate limits, session/admin identity, database, settings/audit, private filesystem, setup cleanup and media functions have separate owners. The former root admin actions are split by area, with authentication before the privileged action gate; each screen has a guarded view. Portal actions/auth and rendering are separate. Public comparisons, contact forms, service/package/industry pages and portfolio screens have templates. Domain schema definitions and additive migrations are under database/. Existing canonical foundation sources remain verbatim in includes/foundation; web entry guards were added to both canonical and bundled copies.

Installer rendering remains one visible form and one Install submission. Maintain its modules; regenerate its self-contained bootstrap/inventory with `python tools/build.py --refresh`. Never hand-edit the generated copies. After successful setup the fixed cleanup allowlist removes all setup-only PHP source; install/.htaccess remains to deny the now-empty directories. Failed removal leaves guards active and an explicit warning.

Business modules should not emit full screens. New public endpoints belong in their relevant route directory; root additions require an explicit owner-approved contract change and corresponding root-allowlist tests. Add future webmail, provider or SSH implementations only when actually implemented; no placeholder feature folders are shipped.

## Private storage and uploads

The installed root `config.php` remains the permanent configuration contract. Its `storage` setting is authoritative. Existing guarded media, client files, payment master key and immutable delivery ZIPs are never moved into images/ or renamed to match code paths. Fresh setup prefers a persistent private directory outside the document root, creates owner-only directories and actually checks create/read/append/rename/delete as the PHP worker. A readable root-owned unused bundled scaffold stays untouched when an external location is selected. No ownership takeover or world-writable permissions are used.

Packaged storage/ and upload/ contain only exact `.htaccess` denial and PHP 404 metadata. The real staging directory is **config()['storage']/upload**, beneath the same private store. Validated public-image uploads use guarded short-lived staging files there and remove them in finally. It is not a second permanent upload store and is not a package wrapper. Runtime media/documents/delivery ZIPs retain their existing configured locations. Supported in-document-root storage relies on the supplied Apache 2.4/LiteSpeed-compatible denial rules; PHP guards protect application-owned blobs as a second layer. Directory listings are disabled independently of mod_rewrite. Other server configurations must enforce equivalent denial before deployment.

## Legacy routes and retirement

Fresh COMPLETE-UPLOAD includes none of the six legacy privileged root routes. After a successful old-layout migration, UPDATE writes these tiny direct-dispatch shims:

| Legacy entry | Canonical handler | Why retained |
| --- | --- | --- |
| admin.php | admin/index.php | Existing admin bookmarks, password links and form actions |
| portal.php | portal/index.php | Invitations, client/support bookmarks and private download links |
| api.php | api/index.php | In-flight or cached contact-form POSTs |
| activity.php | api/activity.php | Cached private polling clients |
| media.php | media/index.php | Existing public media references |
| sandbox-notifications.php | webhooks/authorize-net/index.php | Registered signed sandbox callback URLs |

These include the canonical entry directly, retaining query strings, method, request body, cookies and signatures; they are not generic redirects. Keep them through the next two minor releases at minimum and until callback registrations, retained invitations and external links have been migrated. A future owner-reviewed cleanup may remove exact known shim bytes only after usage review. Never replace customized entries silently. robots.php and sitemap.php remain intentional thin crawler routes in fresh packages because rewrite is optional.

## Exact path migration map

The machine-readable authoritative map is [tools/directory-map.json](../tools/directory-map.json). Core, workspace, schema and render split records name subdivisions as well as the primary destination. Original image bytes are checked against the recorded pre-refactor inventory.

| Old path | Primary new path |
| --- | --- |
| `app/core.php` | `includes/bootstrap.php` |
| `app/view.php` | `includes/layout/public.php` |
| `app/admin-view.php` | `includes/layout/admin.php` |
| `app/home.php` | `pages/home/body.php` |
| `app/schema.php` | `database/schema/base.php` |
| `app/mail.php` | `app/mail/transport.php` |
| `app/workspace.php` | `app/clients/workspace.php` |
| `app/growth.php` | `app/marketing/growth.php` |
| `app/growth-pages.php` | `pages/marketing/controller.php` |
| `app/portfolio.php` | `app/portfolio/browse.php` |
| `app/staff.php` | `app/staff/accounts.php` |
| `app/support.php` | `app/support/sessions.php` |
| `app/catalog.php` | `app/billing/catalog.php` |
| `app/catalog-view.php` | `includes/layout/catalog.php` |
| `app/payments.php` | `app/billing/payments.php` |
| `app/authorize.php` | `app/billing/authorize.php` |
| `app/payment-attempts.php` | `app/billing/attempts.php` |
| `app/payment-notifications.php` | `app/billing/notifications.php` |
| `app/invoice-ledger.php` | `app/billing/ledger.php` |
| `app/deliveries.php` | `app/projects/deliveries.php` |
| `app/delivery-archive.php` | `app/projects/archive.php` |
| `app/activity.php` | `app/activity/events.php` |
| `app/studio-admin.php` | `admin/controllers/studio.php` |
| `app/staff-admin.php` | `admin/controllers/staff.php` |
| `app/support-admin.php` | `admin/controllers/support.php` |
| `app/catalog-admin.php` | `admin/controllers/catalog.php` |
| `app/payments-admin.php` | `admin/controllers/payments.php` |
| `app/payment-trial-view.php` | `admin/views/payment-trials.php` |
| `app/payment-notification-view.php` | `admin/views/payment-notifications.php` |
| `app/invoice-ledger-view.php` | `includes/layout/ledger.php` |
| `app/delivery-view.php` | `includes/layout/deliveries.php` |
| `app/activity-view.php` | `includes/layout/activity.php` |
| `admin.php` | `admin/controllers/router.php` |
| `portal.php` | `portal/controllers/router.php` |
| `index.php` | `pages/controller.php` |
| `api.php` | `api/handlers/inquiry.php` |
| `activity.php` | `api/handlers/activity.php` |
| `media.php` | `media/serve.php` |
| `sandbox-notifications.php` | `webhooks/authorize-net/receive.php` |
| `robots.php` | `api/handlers/robots.php` |
| `sitemap.php` | `api/handlers/sitemap.php` |
| `app/foundation/access/AccessContext.php` | `includes/foundation/access/AccessContext.php` |
| `app/foundation/access/AuthorizationVersion.php` | `includes/foundation/access/AuthorizationVersion.php` |
| `app/foundation/access/MailboxGrant.php` | `includes/foundation/access/MailboxGrant.php` |
| `app/foundation/access/PermissionPolicy.php` | `includes/foundation/access/PermissionPolicy.php` |
| `app/foundation/invitations/IssuedInvitation.php` | `includes/foundation/invitations/IssuedInvitation.php` |
| `app/foundation/invitations/StaffInvitationStore.php` | `includes/foundation/invitations/StaffInvitationStore.php` |
| `app/foundation/portfolio/BeforeAfterPair.php` | `includes/foundation/portfolio/BeforeAfterPair.php` |
| `app/foundation/portfolio/CaseStudy.php` | `includes/foundation/portfolio/CaseStudy.php` |
| `app/foundation/portfolio/PortfolioCatalog.php` | `includes/foundation/portfolio/PortfolioCatalog.php` |
| `app/foundation/portfolio/PortfolioItem.php` | `includes/foundation/portfolio/PortfolioItem.php` |
| `app/foundation/portfolio/PortfolioMedia.php` | `includes/foundation/portfolio/PortfolioMedia.php` |
| `app/foundation/portfolio/PortfolioQuery.php` | `includes/foundation/portfolio/PortfolioQuery.php` |
| `assets/activity.js` | `assets/js/activity.js` |
| `assets/admin.css` | `assets/css/admin.css` |
| `assets/admin.js` | `assets/js/admin.js` |
| `assets/bayou-detail.webp` | `images/backgrounds/bayou-detail.webp` |
| `assets/bayou.webp` | `images/backgrounds/bayou.webp` |
| `assets/emblem.png` | `images/branding/emblem.png` |
| `assets/favicon.png` | `images/icons/favicon.png` |
| `assets/growth.css` | `assets/css/growth.css` |
| `assets/growth.js` | `assets/js/growth.js` |
| `assets/hero-detail.webp` | `images/backgrounds/hero-detail.webp` |
| `assets/hero.webp` | `images/backgrounds/hero.webp` |
| `assets/logo.png` | `images/branding/logo.png` |
| `assets/micrograin.png` | `images/backgrounds/micrograin.png` |
| `assets/payment-trial.js` | `assets/js/payment-trial.js` |
| `assets/premium.css` | `assets/css/premium.css` |
| `assets/project-construction.webp` | `images/portfolio/project-construction.webp` |
| `assets/project-placeholder.svg` | `images/portfolio/project-placeholder.svg` |
| `assets/project-restaurant.webp` | `images/portfolio/project-restaurant.webp` |
| `assets/project-wellness.webp` | `images/portfolio/project-wellness.webp` |
| `assets/site.css` | `assets/css/site.css` |
| `assets/site.js` | `assets/js/site.js` |
| `assets/staff-setup.js` | `assets/js/staff-setup.js` |

## Existing-site migration and recovery

Use [START-HERE](../START-HERE.txt) and [migration guide](STRUCTURE_MIGRATION.md). UPDATE is a staged owner workflow, not a blind overwrite of live files. Keep legacy runtime data and custom code intact; do not recursively delete old directories to make them look clean.
