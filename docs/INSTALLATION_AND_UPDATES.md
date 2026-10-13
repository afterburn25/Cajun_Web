# Installation, packaging and updates

These are release requirements, not a declaration that the target host is fixed. The repository currently contains documentation, not a deployable source import.

Use [the GitHub release workflow](RELEASE_WORKFLOW.md) for incremental versioning, CI evidence and retained complete deployment ZIPs. Track real source/package versions in [the release register](RELEASES.md). GitHub's automatic source ZIP and temporary CI artifacts are not substitutes for the tested complete upload release.

## One-page means one visible form

All database, owner, website, private-storage and confirmation fields must be visible within the same installer document. One Install action submits the configuration. Optional preflight, progress, errors and completion render inline. No Next/Back screens, mandatory tabs, access-key input, activation key, or separate setup/repair filename.

Fields stay editable during appropriate preliminary checks and retain values after errors; do not store raw setup passwords in diagnostics or persistent wizard sessions. Missing app files or failing checks must not crash/hide the form. They must block unsafe installation and list the problem on that page.

The owner repeatedly saw version 1.0.5 despite later deliveries. Verify the actual deployed entry point and version when diagnosing this. Do not say 'fixed on the server' merely because a new ZIP exists. Do not assert that upload nesting, cache, PHP OPcache or routing is the cause without evidence.

## Shared-host storage

Use a permanent, site-specific private directory created by the real PHP worker, preferably outside the public document root. Verify the actual create/read/append/rename/delete operations required by the application. Save the selected location in runtime configuration.

When external storage is not available, an exactly recognized empty bundled storage skeleton may be preserved under a backup name and replaced only where the parent directory permits it. Unexpected files, media, symbolic links and installed-site guards must stop destructive adoption. Never use `/tmp` for permanent content or recursively change the hosting account's permissions.

The reported failure was storage owned by UID/GID 0:0 with mode 0755 and a missing media directory. PHP cannot grant itself root ownership. If no safe writable location or necessary writable parent exists, report the real worker identity and affected paths for targeted hosting correction. Do not use 0777 or claim chmod alone changes ownership.

Configuration, mutex, locking and installer-deletion access also need checking; solving media storage does not prove those writes will work.

## Fresh installation and post-install cleanup

Use HTTPS, CSRF protection, validated input, a database user with required scoped privileges, secure password hashing, and concurrency protection. Do not replace existing tables or ignore installed configuration/locks. There is intentionally no application installation key; finish setup promptly or use optional hosting-level restriction while provisioning.

After actual successful installation, activate permanent guards and attempt physical deletion of only the known installer/legacy-installer files. Check each deletion and show truthful failures. Preserve configuration, installed markers, media, app code, and unrelated files. An inability to delete must not silently leave setup reusable.

## Package contract

1. One complete distribution, not a patch chain. Required entry points such as `index.php` and `install.php` extract directly into the intended website directory.
2. No enclosing `CajunWebPro-version/` or `upload/` folder in the customer deployment ZIP. The source repo can have a separate layout; the release cannot recreate that extraction ambiguity.
3. Preserve `app/`, `assets/`, hidden protection files, and all required dependencies. Build from an explicit inventory.
4. No real config, passwords, customer data, DB dumps, private storage, or installed markers in a fresh release.
5. Keep one canonical installer source. Any direct installer download must be byte-identical to the packaged installer of the same build.
6. Regenerate embedded installer hashes whenever any shipping dependency changes or is added. Do not freeze the historical 32-dependent-file count after adding features.
7. Test ZIP integrity and final extracted paths, relative includes/assets, PHP/JS syntax, version labels, hashes, and completeness on the actual artifact.
8. Document commit, version, archive SHA-256, content manifest, test scope, limitations, and whether the package is fresh-install or update-only.

Do not tell users to delete an existing site. A fresh release is not automatically a safe updater. Future installed-site updates require data preservation, migration compatibility, backup/recovery, and explicit upgrade behavior.

## Acceptance matrix for future releases

Verify a complete native MySQL/MariaDB install and failures/retries, database-backed admin/public operations, GD upload/read, SMTP receipt and reset flow, real HTTPS sessions/CSRF/forms, subfolder deployment, unprivileged ownership cases, retained user data, concurrency, installed-site blocking, and installer cleanup. Distinguish real server tests from offline UI renders and mocked fetch responses.

For visual changes, render the actual PHP pages at representative desktop and mobile sizes and compare them with the approved direction. Do not deliver a concept screenshot as evidence the application matches.

## Public documentation hygiene

The site domain is `cajunwebpro.com`. Hosting examples should use placeholders such as `/home/<account>/domains/cajunwebpro.com/public/`. Keep actual account-private diagnostics out of this public repository. Never put database or hosting credentials in a commit, issue, screenshot, or handoff.
