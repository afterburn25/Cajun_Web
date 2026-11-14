# REL-01 — 1.27.0 planning checkpoint

Status: planning only on `codex/blocked-backlog-audit-20261111` from verified main `ebcb7a1e52982c742996e10e2042c69764890fed` (tree `7ccd5cd2384b5adecbd66fd98b7719a92a38887e`). This document does not change the application version, create a release candidate or tag, generate final assets, publish, deploy, or authorize any live operation.

PR #388 is frozen at proof head `d489b27504d65c24457a6cf134e64ba5ba8bb309`, implementation source `1dd1784ef3146576b62d8f80e9676907d0b4b45a`. Its payment, gateway-receipt and fulfillment changes are prospective only. They must not appear in source, packages, release claims or acceptance for this plan unless that exact candidate later earns hosted acceptance, normally merges, and its resulting main is verified. Any such merge requires a fresh 1.27.0 source freeze and complete requalification.

## Version decision

`1.27.0` remains the correct next version. Published `v1.26.1` is `f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391`; current main is 191 commits and 16 first-parent merges later, with 188 changed paths. The retained changes are compatible additions across identity, catalog/licensing, administration, provider intake and storefront workflows. Under the repository's semantic-version policy this is a minor release, not a patch. No incompatible removal or forced destructive migration was identified.

Refresh tags, releases and main immediately before final source selection. If another version consumes 1.27.0 first, select the next available compatible minor version and update all surfaces together.

## Meaningful current-main changes since v1.26.1

Customer-facing capabilities:

- PRs #367–#369: catalog/image home, guided software onboarding and customer-license issuance.
- PR #370: shared master client-portal shell and authorized client routing.
- PR #375: unified version/database readiness dashboard and schema registry.
- PR #383: durable cloud-site intake records and owner/client views.
- PRs #384/#385: public registration, mailbox verification, neutral resend/expiry, password recovery and closed checkout resume, plus its diagnostic successor.
- PR #386: classified Shared/VPS storefront, public hosting navigation, catalog publication mapping and checkout preparation.

Qualification and integrity work:

- PRs #362–#366: screenshot, finance-readiness, diagnostic, RC exact-source and packaging corrections.
- PRs #376/#377: administrator scanner and continuity package proof.
- PR #387: exact-main installed-browser stability correction and resulting-main qualification.

These integrity changes support the release but should not be inflated into separate customer features.

Prospective PR #388 additions are excluded until accepted on main. They include ordinary hosting payments, Authorize.Net receipt evidence, settlement/reconciliation, fulfillment and activation evidence.

## Draft release notes

### Customer accounts and portal

- Customers can register without an invitation, verify their mailbox, request a neutral resend, recover their password and resume a closed checkout destination.
- Administrators can enter the shared client portal with an explicit review banner while customer authority and actions remain distinct.

### Catalog, software and licensing

- Catalog and licensing tasks are grouped into a coherent workspace with explicit readiness states and honest missing-artwork handling.
- Browser software upload can reconcile the product/SKU, place private release bytes and prepare licensing policy.
- Customer-license issuance remains separate from software registration and runtime enforcement.

### Hosting storefront and provider intake

- Public Shared Hosting and VPS Hosting navigation uses reviewed categories and configured publications.
- Shared/VPS classification and checkout preparation are present; unavailable or request-only offers stay explicit.
- Cloud-site requests are recorded for owner review without executing a provider operation.

### Administration and release integrity

- Settings → Version & database reports feature-schema readiness and applies supported additive migrations in dependency order.
- Exact-source packaging, diagnostics, source composition, administrator scanner and browser-stability checks were strengthened.

## Migration and data-preservation notes

The release is additive. Preserve `config.php`, database records, encryption keys, private storage, customer uploads/media, recovery journals and prior release assets.

Expected schema markers include:

- `software_onboarding_version=1`
- `client_identity_schema_version=1`
- `hosting_storefront_version=1`
- `provider_cloud_site_request_schema_version=1`

Upgrade order: install the accepted UPDATE files, then use Settings → Version & database to review and apply required schema steps in dependency order. Repeated or interrupted execution must remain recoverable. Optional preparation must remain distinguishable from required migration and must not activate payment, provider, mail or provisioning operations.

Public identity must not silently adopt or overwrite legacy accounts. Storefront preparation must not fabricate an available VPS offer or a successful payment/fulfillment result.

## Known limitations for the planned release

- No production deployment or target-host live verification is claimed by repository acceptance.
- No live charge/refund, paid provisioning, customer message, provider mutation, SSH/support session or destructive operation is authorized.
- Without an accepted #388 merge, current main does not include ordinary hosting charge, gateway-receipt, settlement or paid-fulfillment behavior.
- VPS remains deliberately unavailable/unprovisioned unless a reviewed offer and fulfillment path explicitly establish otherwise.
- Cloud intake records a request; it does not execute the provider operation.
- Public registration does not automatically adopt a legacy customer account.

## Version and generated-source inventory

Direct source updates required only when REL-01 implementation begins:

- `VERSION`
- `site/config/defaults.php` (`CWP_VERSION`)
- `site/install.php` (`CWP_INSTALLER_BUILD`)
- `START-HERE.txt`: heading, archive names, `cwp-update-1.27.0/`, owner-update path/URL, confirmation version and `structure-1.27.0`

Builder-refreshed/generated surfaces:

- `site/install/bootstrap.generated.php`
- `site/install/inventory.generated.php`
- derived names and metadata emitted by `tools/build.py`
- three versioned ZIPs, three package manifests, three directory inventories, aggregate `manifest.json`, `SHA256SUMS.txt` and packaged `START-HERE.txt`

Release documentation:

- new current entry in `CHANGELOG.md`
- current-state prefixes in `PROJECT_STATUS.md`, `SESSION_HANDOFF.md` and `docs/RELEASES.md`
- `docs/MIGRATION-INSTRUCTIONS-1.27.0.md`
- `docs/TEST_REPORT_1.27.0.md`
- `docs/RELEASE_SCOPE_1.27.0.md`
- after verified publication, `docs/RELEASE_VERIFICATION_1.27.0.json`
- generic release attachments `MIGRATION-INSTRUCTIONS.md`, `TEST_REPORT.md` and `DIRECTORY-STRUCTURE.md`

Do not globally replace historical 1.26.1 references, old workflow evidence, reports or handoffs.

## Published-1.26.1 baseline prerequisite

Release machinery must add published 1.26.1 as a distinct immutable upgrade baseline while retaining the 1.26.0 RC lineage:

- `tools/legacy-release-files.json` currently ends at 1.26.0 and labels it a deployed release candidate.
- `tests/prepare_legacy.py` prepares published releases only through 1.25.
- `tools/acceptance_artifacts.py` has `VERSIONS` only through 1.25.0 and a `PUBLISHED_BASELINE_126` name that currently resolves to the 1.25.0 package.
- Coordinated updates will be required in `tests/acceptance_artifacts_test.py`, `tests/organized_update_cases.py`, the finite source-composition proof and its receipt.

The authoritative published 1.26.1 COMPLETE asset has SHA-256 `7d7117a25fab556cb3f8dfce7f0b859d072cfc992b98fc91026d96b490e1dad3`; its manifest SHA-256 is `a903b2850ab4ff25ed94ceddd1794370341865183ee515a72bd816cd5324d0ee`, with 3,072 members. Re-read the release and freshly download the immutable inputs during implementation rather than relying only on these planning values.

## Acceptance matrix

Source and review:

- select one clean exact full commit and prove every version label agrees;
- refresh canonical build outputs with no unexplained tracked or untracked drift;
- pass build guards, exact source composition, mutation regressions and genuine shallow-clone proof;
- complete required independent review without treating same-agent self-review as approval.

Runtime and installation:

- PHP 8.2 and 8.4 across MariaDB 10.11 and MySQL 8.4;
- root and subfolder installs, rewrite and no-rewrite behavior, Apache, filesystem and recovery gates;
- fresh COMPLETE-UPLOAD installation;
- actual published 1.26.1 → candidate UPDATE, interrupted recovery, reapplication and retained-data verification;
- preserve the distinct 1.26.0 RC upgrade lineage;
- verify schema dependencies, configuration, keys, private storage, media/customer records and recovery evidence.

Application evidence:

- complete Application and Diagnostic artifacts from the exact candidate;
- independently download and replay final/partition registries and source bindings;
- inspect required desktop/mobile captures and diagnostics;
- retain every prior failure, skipped stage and first-attempt provenance honestly.

Artifacts and packages:

- verify all 12 builder outputs;
- validate outer ZIP hashes, CRC, member size/hash, portable case-unique paths, direct extraction, no wrapper, no nested shipping ZIP and no private data;
- validate source commit/version in every manifest and Windows/Linux agreement;
- prepare the release-layer asset set, freshly download every draft/public asset and compare with accepted bytes;
- prove the immutable v1.26.1 tag and assets did not change.

Resulting main and publication:

- normally merge only reviewed accepted source;
- record resulting-main commit, tree and version;
- require every expected main workflow to be present and terminal-success on that exact commit;
- independently replay resulting-main Application/Diagnostic artifacts, captures and diagnostics;
- rebuild exact main and compare packages before creating the tag or publishing;
- tag/publish only after resulting-main qualification; production deployment remains a distinct authorization boundary.

## Candidate invalidation rules

- A queued, skipped, missing, cancelled, stale-source or retried-away result is not acceptance.
- A branch package is not a release and a published release is not a deployment.
- Any main advance, including a future #388 merge, requires a fresh source comparison and may require rebuilding and requalifying the candidate.
- Never weaken a test, source proof, package rule or first-attempt policy to preserve a planned date or version number.
