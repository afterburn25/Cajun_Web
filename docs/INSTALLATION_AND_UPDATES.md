# Installation, packaging and updates

**1.8.0 candidate:** one additive `invoice_payments` table holds external receipts, legacy balances and append-only recording reversals, separate from sandbox tables. After a current file/database/private-key backup and UPDATE upload, owners enable Invoice payments after Payment preparation. Fresh setup includes empty enabled history. Original invoice amounts and retained credentials/trials/notifications remain intact. Client receipts are private HTML views suitable for browser Print/Save as PDF. [Ledger guide](INVOICE_LEDGER.md). No runtime CLI/daemon or provider connection added; publication pending.

## 2026-10-13 — Verified 1.7.0 sandbox notification increment

Signed sandbox receipt, durable minimal inbox and explicit owner-triggered current reporting are delivered in [v1.7.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.7.0) / `1fe0670142755f86aee235fabe1847e5d118ee52`. Receiver defaults off; schema is additive, invoice settlement remains disabled and no runtime daemon is needed. Original 1.6.0 UPDATE preserves attempts/events and all earlier records/keys. 358 native / 451 HTTP/Chromium checks pass; signatures/reporting remain synthetic. Actual merchant acceptance, production/client checkout and automatic processing are pending. [Guide](SANDBOX_NOTIFICATIONS.md) · [report](TEST_REPORT_1.7.0.md).

## 2026-10-13 — Verified 1.6.0 sandbox increment

Owner-only tokenized sandbox trials, durable intent/events, transaction-detail reconciliation and shared manual/catalog/request locking are delivered in [v1.6.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.6.0) / `e5d4c17456faff0522429ab3f58093a06a523bd5`. Sandbox never pays real invoices. Production/client checkout, webhooks, receipts/refunds and recurring billing remain pending. Original 1.5.0 UPDATE preserves credentials/key, requests and existing records. 296 native / 421 HTTP/Chromium checks pass; gateway and browser tokenization fixtures remain synthetic. Back up and enable Sandbox trials after UPDATE. [Guide](SANDBOX_PAYMENTS.md) · [report](TEST_REPORT_1.6.0.md).


Released 1.5.0 adds [payment preparation](PAYMENT_PREPARATION.md): two additive tables, owner-only external-key credential protection and reviewed existing-invoice requests. Charges and production connections stay disabled. 227 native / 401 HTTP-browser checks passed; [report](TEST_REPORT_1.5.0.md). Complete/update: 58/57 files. After backup and UPDATE merge, owners enable Payment preparation; private gateway storage must be outside the document root. Existing manual billing remains unchanged.


Released 1.4.0: after a full backup and UPDATE merge, owners enable **Service catalog** with their current password. Three additive catalog tables preserve clients, support policy, staff access and existing invoices. Fresh installation initializes an empty catalog. No sample prices publish. Read [Service catalog](SERVICE_CATALOG.md); publication evidence remains in the release register. Complete/update inventory: 55/54 files.


Current verified release: [v1.7.0](RELEASES.md#v170--published-13-october-2026). [1.7.0 test report](TEST_REPORT_1.7.0.md). Earlier version instructions below are retained for their supported upgrade steps.

Released 1.3.0 adds [client support sessions](CLIENT_SUPPORT.md). From 1.2.0, merge the verified UPDATE package after backup, then explicitly enable Client support with the owner password and allow individual clients under the support agreement. Existing roles and customer credentials are retained. From earlier versions, complete the existing workspace/administrator setup first. Fresh setup remains one-page; support policy still requires explicit confirmation. Package inventory: 52 complete / 51 update files. Publication and evidence are tracked in the release register.


v1.2.0 complete/update deployment assets are published and downloaded-back verified; v1.1.0 remains unchanged. Use the [release register](RELEASES.md) for exact URLs, hashes and tested source; read the [1.2.0 test report](TEST_REPORT_1.2.0.md).

## 1.2.0 administrator-account upgrade

Use only a published, verified version from the release register. Candidate source or GitHub automatic source archives are not deployment releases. 1.2.0 extends the 1.1.0 website with [administrator accounts](ADMIN_ACCOUNTS.md).

**From 1.1.0:** make a complete host backup, then merge the version's UPDATE ZIP into the existing directory, preserving `config.php`, installed guards, private storage and unrelated files. Sign in with the existing administrator. Open **Administrators**, select the initial owner, confirm the backup and enter your current password. This adds two account tables and preserves existing identities/passwords. Other existing administrators retain website and client-workspace permissions; owner-only settings become restricted. Verify the owner sign-in and assigned staff access before creating more accounts. Never rerun `install.php` on an installed site.

**From 1.0.8:** use the same complete UPDATE ZIP; run **Version & database** first for the client-workspace migration, then enable **Administrators** as above. Both steps are additive. The release suite checks the retained populated 1.0.8 fixture and a checksum-pinned copy of the actual published 1.1.0 package. Earlier versions or custom schemas need separate review.

**Fresh 1.2.0:** use COMPLETE-UPLOAD and the one-page keyless installer as described below. It creates the owner and account tables during installation; no separate role upgrade is needed. The full package contains 50 files, including the six canonical access/invitation classes under `app/foundation/`; UPDATE has 49, omitting only `install.php`. Do not omit hidden protection files or upload a mixed set of versions.

Finish any interrupted file merge before database actions. A marker/table mismatch or unknown staff records causes an explicit error rather than replacement. Restore the complete validated host backup if recovery is needed; a code-only downgrade to 1.1.0 is unsupported because the older code does not enforce roles. Existing live hosting/HTTPS/SMTP configuration still needs host verification.

## 1.1.0 installation and supported upgrade

Runtime: PHP 8.2+ source target, PDO MySQL, OpenSSL, Fileinfo and GD, with an InnoDB database. Full application acceptance runs on **PHP 8.3 / MariaDB 10.11**. Independent PHP 8.2/8.4 component checks do not certify the application on those runtimes. Apache 2.4/LiteSpeed protection files are included; target-host HTTPS, PHP mapping and permissions need checking. Customers need no Node service, root access, SSH, Composer or command-line installation.

**Fresh website:** download the verified COMPLETE-UPLOAD ZIP and compare its SHA-256. Extract directly into the intended domain/subfolder, including hidden `.htaccess` files. Open `install.php` over HTTPS. Fill database, owner, website URL, optional permanent private-storage parent and confirmation in the single visible form, then Install. Pricing starts unpublished and email disabled; concept portfolio examples are optional. Check the reported permanent guards and actual installer removal. Follow any cleanup warning precisely; preserve config/media/locks.

**Existing 1.0.8:** first back up the database, website, configuration and private storage through the host. Test a copy before the live site. Merge the UPDATE ZIP into the existing folder, overwriting matching application files only. It excludes fresh setup, config, storage and locks. Sign in using the existing administrator and open **Version & database**; confirm the backup and apply the additive update. Verify public pages, saved inquiries, media and client access. Repeat database verification is safe; incompatible workspace tables are rejected. Do not rerun fresh setup or delete installed markers.

The update path is tested against the fingerprinted populated 1.0.8 fixture. Earlier versions and custom schemas require separate review. Finish an interrupted file merge before applying the database update. Ask the host to invalidate stale opcode caches if required. On failure, preserve the report and restore the complete host backup; a code-only downgrade is not a verified recovery procedure. This is not an automatic application-file updater or a full backup/restore service.

**Developer build:** from a clean checkout with canonical LF bytes, Python 3, PHP CLI and Node syntax checking, run `python tools/build.py --commit <full-sha>`. After shipping edits run `python tools/build.py --refresh`, commit source plus installer hashes, then verify again against that commit. Only `site/` enters flat deterministic archives. Tests, original brand sources and development tools are excluded. Access/invitation foundation sources are bundled verbatim into `site/app/foundation` by `--refresh`; verification rejects divergence. The builder also verifies embedded installer/core equality.

## Continuing installation requirements

These are release requirements, not a declaration that the target host is fixed. Actual application source is now in `site/`; see the release register for publication status.

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
