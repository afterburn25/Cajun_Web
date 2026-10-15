# Owner-reviewed 20i plan mappings

This increment connects a published CajunWebPro retail plan to a reusable, owner-reviewed 20i account/package type. It is not automatic provider catalog discovery, a provider quote or proof of entitlement. The published release remains 1.24.0; this branch is unreleased.

On a published **Service plan**, the owner can prepare mapping history, enter the exact reseller/type/location and known limits, and save an enabled or withdrawn review for 1–30 days. Package defaults must be reviewed in the provider account: welcome mail, automatic applications, SSH keys and Website Turbo can have effects beyond creating an empty site. The application sends no provider request from this form.

Provider cost is optional private reference evidence. An exact decimal string in **major currency units**, its explicit currency, whole-month period, tax treatment and per-package/shared-account basis are retained separately from the immutable client retail price in USD cents. Unknown cost stays unknown. No currency conversion, per-client allocation, margin, provider quote, invoice change or charge is inferred. Reference text must not contain credentials.

An eligible accepted hosting order offers **Prepare from saved mapping**. Submission binds the exact client-accepted retail plan, current mapping fingerprint, profile revision/credential digest, original reviewer authorization and unexpired review inside the same locked transaction that creates the existing local service association. Payment or explicit manual fulfillment and current owner password remain required. Different plans, replaced/withdrawn/expired reviews, retired retail plans, changed connections and revoked reviewers cannot supply a new preparation.

The association stores the immutable mapping ID and digest. Existing format-1 manual associations retain their original contract. New format-2 catalog associations validate their historical source; withdrawal or retirement preserves history without rewriting service terms. A mapping is checked when preparing a service. The existing separate, current fulfillment review still checks package defaults, costs and account availability before any provider setup; catalog selection is never dispatch authority.

Mapping revisions are append-only with predecessor hashes, strict setup/table/index/foreign-key checks, audit correlation and one-use request identity. Missing records, setup markers or tables cause refusal and require original database restoration; GET never prepares tables. Staff support sessions and ordinary clients cannot manage or view private catalog evidence. Client service progress remains the existing restricted projection.

## Source and limitations

Read 2026-10-15: the official [addWeb guide](https://docs.20i.com/api/provision-hosting-package-api) identifies `type` as a packageTypes ID. The [package type guide](https://docs.20i.com/reseller-hosting/create-hosting-package-types) documents limits and automatic package defaults. The [API documentation guide](https://docs.20i.com/api/20i-api-documentation) directs account documentation access through My20i. The retained owner-reference summary does not supply the packageTypes response schema; no schema, response, offering, price or account entitlement is invented here. Automatic discovery and authenticated account acceptance remain unfinished. The subsequent [contract intake](TWENTYI_DISCOVERY_CONTRACT_INTAKE.md) identifies the exact schema gaps and safe next implementation.

## Verification checkpoint

Branch `codex/twentyi-catalog-controls` starts at `0ebc46237fce565005b581fe5a96a2691b9840b5`. The enclosing implementation commit records its resulting SHA in Git history. Local PHP 8.4 default/no-INI each pass **51 named pure catalog document checks** in `tests/provider_plan_catalog_document.php`; every changed/new PHP source passes syntax checks. These checks make no network or database request.

`tests/provider_plan_catalog_cases.php` extends the real native recurring/order suite with additive setup, schema refusal, atomic audit rollback, replay/conflicts, owner/client/support isolation, profile/reviewer invalidation, expiry, mapping replacement/withdrawal, actual rendered preparation intent, original retail terms, immutable association provenance and missing-history recovery. It is pending Linux PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 execution at this checkpoint. The Windows private-key protection is preserved; no native pass is claimed locally. Actual HTTP/Chromium, independent review and exact packaged acceptance remain required. No live API read/write, payment, customer mail, SSH, deployment or release occurred.

## Review continuation 2026-10-15 — independent catalog review corrections and browser coverage

Starting24e75ca646f13852b54ea148966048ebdec66bec, PR141. Root independently found that rejected oversized/array catalog fields could throw again during redisplay, and malformed recovered association JSON could access format before validation. Corrected both: rendering uses a bounded nonthrowing UTF-8 projection, while association/catalog document validation normalizes malformed history. Added four native malformed-render cases and actual HTTP/Chromium setup, review, rejection, replay and catalog-backed existing fulfillment coverage. Parent native38818360818 has successful PHP8.2/MariaDB and PHP8.2/MySQL jobs; remaining jobs/artifact verification were pending when this entry was written. Local51 pure checks per PHP8.4 mode and changed PHP syntax pass; exact descendant native/browser verification remains required. All runs and previous releases are preserved.

### Verified parent native evidence

Run38818360818 at24e75ca6 succeeded in all four PHP8.2/8.4 × MariaDB10.11/MySQL8.4 combinations. Downloaded ZIP hashes/CRCs and every retained named report verify; each includes36 catalog cases within501 combined recurring/order cases (do not add overlapping report counts). Pure report files were missing because their output directory did not yet exist; the descendant creates it first and fails if a report cannot be written. No missing report is claimed verified.

- Artifact12012092044: SHA256 `76acf4f9d618761375ba4a6ba7ca2a6584e13efbaf534ae1600cdaf148e80d88`.
- Artifact12011927319: SHA256 `532218e5b4036a7ce57cdbe63c31f9da55d8e632af8382b65d5a3281f9e107e6`.
- Artifact12011582391: SHA256 `488d2c32a279f20d8d66d3686c689edf7075afbaba5f7c7ee6e52641e9554c3e`.
- Artifact12011367977: SHA256 `a1d65e844004a40aef623395f4822f1851d8a62a903400c143c05dd8be650596`.

The descendant fixes root’s independent redisplay/recovered-format findings, adds four native render regressions and actual HTTP/Chromium catalog-backed fulfillment tests, and enables this feature branch’s complete application workflow. Local pure51 checks per mode, changed PHP syntax, Python AST and documentation checks pass. Refresh95043 completed successfully; exact installer inventory verified. Descendant native/browser/package/review remains pending.

## Catalog withdrawal recovery correction — 2026-10-15

Starting41123e9b5b95f8bbe4244ee5db64a15eb3a85a58, PR141: source review found that losing a latest withdrawal record together with its matching audit could make an older enabled mapping appear current. Added an independently retained per-plan latest ID/digest pointer in settings, saved atomically with every review. Exact pointer/history checks and plan-family counts refuse missing/older review histories, including loss of both withdrawal and audit. Native fixtures cover paired loss, refusal of new approval, missing pointer and exact restoration. This strengthens recovery; it does not add provider execution. Local syntax and51 pure checks pass; current native/full acceptance remains pending. Preserve parent41123 full38819193594 and native38819193595; do not replace or cancel those runs.

## Exact catalog acceptance — 2026-10-15

Preserved source `8f7028dbbc23d9926d7d0ebde8d2f6b5e6861e98`, draft [PR 141](https://github.com/afterburn25/Cajun_Web/pull/141), completed [full application run 38819685543](https://github.com/afterburn25/Cajun_Web/actions/runs/38819685543) successfully. The fresh final artifact `12013629800` matches API SHA-256 `9ed044b20a7bc21860a3cef3d2ef4d768cbd428d59ce48d35b16cbaa99de25ef`; outer and embedded ZIP CRCs pass. All 64 terminal named reports pass, including 1,794 actual HTTP/Chromium checks, 289 unprivileged structure recovery checks and 1,827 Apache checks across rewrite modes. The additional Apache-progress report is an overlapping checkpoint, not extra acceptance coverage.

Actual catalog owner desktop/mobile and prepared-service screenshots were inspected; their bytes match the final artifact. The HTTP suite includes 20 catalog-specific checks. Installer/build and no-shipping-mutation gates passed. All 11 distribution checksums, three package manifests and directory lists agree with source: FULL-PROJECT 5,429 files, COMPLETE-UPLOAD 2,664 and UPDATE 2,651. Every ordinary package source member matches its exact Git blob; UPDATE additionally has its generated protected manifest and access file. These are test packages, not a newly published release.

| Exact 8f package | SHA-256 |
|---|---|
| FULL-PROJECT | `799d1fa4077d9b2fcc264418ef4a9a89a012b3f008aef19caec1c8fa46a250ca` |
| COMPLETE-UPLOAD | `0ed168d0838d147aaffd36bc21a54e3e597f1b673d4b1f9b06d41f6a85ff05e0` |
| UPDATE | `05b598d1d1b013e702fb2ba4d112669fc50dd2a47373b5b5fdc3cbdefea559a0` |

[Native matrix 38819685552](https://github.com/afterburn25/Cajun_Web/actions/runs/38819685552) succeeds for PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4. Each downloaded archive has 13 passing named JSON reports: 43 catalog checks within 508 combined recurring/order checks, plus 51 pure catalog checks in default and no-INI modes. Combined/component counts overlap and must not be added. Actual archive hashes match API metadata and ZIP CRCs pass.

| Combination | Artifact | SHA-256 |
|---|---|---|
| PHP 8.2 / MariaDB 10.11 | 12013540039 | `9df6dd216f8726c76c367406105abff14d25aca0f8fc73841d0923980b204d0d` |
| PHP 8.4 / MariaDB 10.11 | 12013225692 | `0ae74ad94a062b4711f9f79047669cb99402c05511731e58da75e57d496562a9` |
| PHP 8.2 / MySQL 8.4 | 12013035594 | `c8db88c816a4436dab3f2cbad281a7d6747647d97b464cfc7fc3e7f72c25e569` |
| PHP 8.4 / MySQL 8.4 | 12012886420 | `fb774dae0b5a2abe6fafa91c4f3d8a76a3d1a5fcb9ee0ddb17f839478d8cab47` |

Independent root and cloud-agent source reviews found no further blocking defect in the latest-pointer recovery correction after the documented inline-render/history fixes. Root normally integrated this source and reviews its mailbox/registrar/cloud combination separately. The newer combined candidate cannot inherit this full pass merely because it contains 8f. Earlier 24e75/41123 runs and immutable published assets are preserved. No account API read/write, charge, customer message, SSH or live deployment was performed.
