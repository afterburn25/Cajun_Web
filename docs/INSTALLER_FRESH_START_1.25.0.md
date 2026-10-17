# Installer correction for 1.25.0

Status: implemented on `codex/installer-layout-1.25.0` from parent `bc5ec277580aa681f43aaf2c9b97df730f6954f1`; release acceptance and publication pending. The enclosing commit identifies the correction without a self-referential hash.

## Owner-requested behavior

- Desktop server checks appear left of the setup fields. Mobile stacks the same single form. Website details immediately follows Website files.
- The Exact hosting-permission report panel and redundant One form/No wizard sentence are removed. Relevant individual failures remain visible with all field values preserved.
- Administrator creation, activation, password reset and account changes accept at least 12 Unicode codepoints, reject NUL/invalid UTF-8, and retain the 72-byte hash-input maximum. Clients retain the prior 14-byte policy. Existing passwords are not rewritten.
- Uploading website code over an installed website preserves its configuration, database settings, keys and private storage. No installer is forced. Missing configuration plus retained local installation evidence shows recovery guidance.
- Explicit **Start a fresh website**, off by default, uses a session-bound new table prefix and a separate private folder outside the public document root. It does not drop, rename or rewrite old tables/storage, and cannot bypass config.php, .installed.php or local storage/install.lock.php. Database collisions at the new prefix still fail closed. The token names resources; it is not an ownership credential.

This offers a fresh active site while retaining previous data separately. It is not destructive database overwrite or a promise that deleted original configuration/encryption keys can be recovered. Fresh mode requires a writable permanent private parent and deliberately has no arbitrary public-directory fallback.

## Bounded evidence and remaining acceptance

Actual local PHP8.4.25 default/no-ini password checks cover ASCII/Unicode 11-versus-12 boundaries, 72-byte limits, malformed UTF-8/NUL, intentional spaces and unchanged client behavior. The native staff fixture imports those checks and exercises 12-character activation/reset/account changes; native execution remains pending.

Isolated localhost PHP8.4.25 / Chrome155.0.8059.79 completed 67 checks at five viewport widths (1440,1024,768,390,320). All 2,886 installer inventory entries matched. Real checks POSTs, field retention, checkbox default/toggle/prefix restoration, browser password boundaries, installed-config403 and missing-config-marker503 guards passed. Six initial/explicitly mocked long-error images were inspected. A missing space in the new checkbox label was then corrected. Windows directory permission failures are retained honestly; no database test or installation POST was sent by this bounded harness. Result SHA256 `774c584de5730458b17b9b256b9270cb05bb985ecbf21d3bef70ed326d8f47a3`; separate review `f8d06850a5933904d806d4816446c6e6f7aceb7cfc4d1a1449c4298b4a86daa6`.

The no-database fresh-input fixture passes 58 named checks in each of default/no-ini PHP8.4.25, covering explicit intent, invalid inputs, stable resource naming, existing-lock precedence and unchanged retained-file hashes. Candidate path selection uses labeled read-only seams; actual private-directory allocation is not claimed. Root independently repeated both modes. All 18 touched PHP files passed syntax checks, the 215 acceptance-helper checks and 69 builder rejection checks passed, and documentation links passed.

The full HTTP/browser fixture adds retained external-storage and existing-prefix refusal, explicit fresh installation with a 12-character administrator, old-table/private-file preservation, ordinary COMPLETE package overwrite and subsequent administrator login. Default fresh installation coverage remains in the original browser driver. Missing-config marker recovery is tested at public/admin/client entrypoints. These native Linux/database flows have not yet run for the new source.

Required next: independent final source review; exact source Windows/four supported PHP/database combinations and maintained compatibility; all 328 actual release images; normal main merge; fresh complete exact-main acceptance; all 18 release assets; publication and fresh download verification. No live deployment or target-host repair is represented by these local results.
