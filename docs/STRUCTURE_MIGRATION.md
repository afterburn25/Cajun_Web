# Structure migration, installation and recovery

This procedure replaces the historical “extract UPDATE over live files” instructions introduced with the 1.11.0 directory correction and retained for later organized updates. Do not run the fresh installer on an existing website. No automatic hosting deployment is performed.

## Choose the correct artifact

| Download | Purpose | Where its contents go |
| --- | --- | --- |
| FULL-PROJECT | Development, audit and agent handoff; includes docs/tests/tools/brand/foundation/CI | A private development workspace, never wholesale into the public website |
| COMPLETE-UPLOAD | Fresh installation, organized production application | Directly into the intended website directory, with its internal folders intact |
| UPDATE | Authenticated migration of a recognized existing installation | Into the existing website directory; it creates only cwp-update-1.14.0/ staging |

All three come from the same clean source commit. Each has its own manifest, directory inventory and checksum. [START-HERE](../START-HERE.txt) is the concise download guide. The complete production package has no enclosing site/, version/ or upload/ wrapper.

## Fresh installation

1. Verify the downloaded COMPLETE-UPLOAD checksum against the release. Extract its contents directly into your intended HTTPS website directory. Keep admin/, includes/, images/, storage/, upload/ and the other organized directories together.
2. Open install.php. All database, owner, website and storage fields remain on one page. Fill the form and select Install once; errors and progress remain inline. No installation key, wizard, customer shell, Composer installation or Node service is required.
3. Setup verifies the package before database changes; selects and tests persistent private storage; creates the database/account; commits configuration and permanent guards; and removes the fixed setup-only PHP files. Configured private storage remains authoritative. If physical deletion is denied, the installed guard still blocks setup and the response lists files requiring host assistance.
4. Open admin/index.php. Inspect the site, email delivery settings, publication status and private workspace. Payment/provider capabilities retain their existing disabled or sandbox-only gates.

A missing setup support file gives a useful restore-the-complete-package message instead of an empty fatal page. Missing ordinary application dependencies leave the full form visible with the mismatched file list. Never remove config.php, .installed.php or private install locks to make setup run again.

## Supported existing installations

Known immutable packages: 1.0.8 and releases 1.1.0 through 1.13.0. Exact archive and file hashes are retained in [the release inventory](../tools/legacy-release-files.json). 1.10.0 is the latest old layout; 1.11.0 is the first published organized layout. An unknown or modified bootstrap/version module is not silently adopted. Customized conflicts require review before activation. The unused pre-publication 1.11.0 source is preserved in Git, but is not a supported published starting point.

The file migration does not create, reset or import database tables. Existing older feature upgrades remain explicit under Version & database and their owner-controlled administration sections after files are updated. No live checkout, provider provisioning, webmail or SSH is activated by the refactor.

## Owner update procedure

1. Take a recoverable backup of the current website files, database and the actual configured private storage, including media/client documents, master key and delivery ZIPs. Verify how you would restore it through your hosting panel. The updater requires your explicit backup confirmation; its code rollback copies are additional protection, not a replacement for this backup.
2. Verify and extract UPDATE into the existing website directory. It creates cwp-update-1.14.0/ containing a protected code payload, manifest and owner page. **Do not manually copy its site/ payload over the installed website.** Do not overwrite an unrelated or different staging directory.
3. Open cwp-update-1.14.0/owner-update.php on the existing HTTPS domain/subfolder. Use an existing owner session or authenticate with the owner's email/current password. Every inspection, application or recovery POST requires a valid session token, current owner password and backup confirmation. Nonowners cannot perform it. This page cannot create an installation or reset credentials.
4. Select Verify and inspect. Missing or changed payload files are reported before application loading/switching; restore the same UPDATE package before retrying. Recognized old file hashes and all replacement hashes are checked. Any customized conflicting destination or privileged old endpoint stops activation and remains intact. Unknown files outside that bounded plan remain untouched.
5. Select Apply or retry update. A retained lock prevents overlapping updates/recovery. The updater writes protected rollback copies and a durable journal before changing application files. A temporary Apache 2.4/LiteSpeed-compatible maintenance policy blocks public/privileged application endpoints during replacement while the authenticated staged owner page remains reachable. Replacement files are written beside their targets and atomically renamed. Only enumerated known-hash old release files are retired; no directory tree is recursively deleted.
6. On success, the canonical protection policy is restored and the organized application becomes active. On old-layout upgrades, six tiny compatibility entries preserve existing admin/client/contact/activity/media/callback URLs by directly invoking the new handler, preserving POST bodies and signatures. Fresh organized sites do not receive those entries on update; existing recognized shims from a prior structure migration remain in place. The fresh package does not contain those entries. Their reasons and retirement window are in [Directory structure](DIRECTORY_STRUCTURE.md#legacy-routes-and-retirement).
7. Verify public pages, administrator/client sign-in, role restrictions, invitations, private files/ZIP downloads and registered callbacks. Keep staging until this review completes. Then remove the exact cwp-update-1.14.0 directory using your hosting panel. Keep protected recovery records with your backup. Do not delete configuration, permanent guards or the configured store.

Root config.php, proxy settings, installation guards, encryption keys, current storage selection and all business data are preserved. Existing image identifiers are mapped through the centralized asset helper; immutable client-delivery records are not rewritten. Directory ownership is not seized; permission failures name the bounded operation requiring host correction.

## Interrupted update and recovery

Keep the staged owner page and original UPDATE package. A browser/network interruption does not mean the update stopped immediately; inspect its state first. A still-held lock means another request is running. Retry after it finishes rather than launching competing changes.

If an upload was incomplete, restore the exact staging files and reopen the owner page. If an application filesystem operation was denied, correct only the named ownership/permission/quota problem and select Apply or retry. Completed hash-matching operations are recognized on retry; the recovery journal remains in the configured private store under structure-1.14.0/.

Select Recover original files with your current owner password and backup confirmation to undo a backed-up attempt. Recovery verifies original backup hashes and current replacement hashes, restores recognized original files, and removes only unchanged newly introduced files. It never recursively removes directories, resets a database or touches private customer files. Configuration and runtime data remain in place. Changed application files or server policy are preserved and reported; maintenance protection remains until the owner reconciles the conflict and retries. Preparation failures before complete backups require retrying preparation first because no application switch occurred.

The permanent mutex stays at structure-1.11.0/mutex.php in the configured private store so old and new staged owner tools cannot overlap. Never delete or replace that lock. The 1.14.0 journal and backups use structure-1.14.0/; earlier journals/backups are preserved. Root storage/upload scaffolds and their contents are preserved, including owner-customized protection files.

A recovered attempt and its original backup copies remain retained. A subsequent owner-confirmed apply creates a new plan/backup identity and archives the recovered journal. Recovery is a bounded code rollback, not a substitute for restoring a matched website/database/storage backup set after unrelated later changes.

## Hosting protection

Apache 2.4 rules use Require all denied for internal and scaffold directories plus Options -Indexes; mod_rewrite is optional. Explicit PHP routes work on root and subfolder installations without rewriting. PHP direct-entry guards and guarded private blobs add another layer. Do not claim nginx/IIS or a particular live 20i account has been accepted without equivalent server-rule tests. Current automated acceptance uses real Apache/mod_php and unprivileged filesystem operations, alongside the native database and PHP/Chromium suites; it does not deploy to or inspect the owner's hosting account.
