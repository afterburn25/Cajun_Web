## Unreleased — customer domain cooldown event ordering

- Prevent a replace/deactivate/add sequence within one database-second from bypassing the configured customer domain-move cooldown.
- Use serialized audit IDs to distinguish replacement-internal deactivation from a later standalone deactivation while retaining timestamp-based expiry, same-domain recovery and owner overrides.
- Add 12 native service regressions and a PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 focused workflow.

## Unreleased — refresh current PR266 financing portal behavior

- Merge feature head `726fc7db` into the separate integration lane without changing Devin's branch.
- Resolve missing or foreign customer financing agreements before layout output so the intended HTTP 404 is retained without a headers-already-sent warning.
- Require installed tenant-isolation acceptance to observe the private 404, no-store cache policy and no-referrer policy; regenerate the combined installer inventory and require fresh exact-head acceptance.

## Unreleased — recover interrupted financing setup

- Record audited pending markers before financed-license, finance-agreement and finance-gateway DDL, then safely resume only recognized empty partial setups.
- Keep unmarked or populated partial storage fail-closed and clear each marker only with its verified schema-version commit.
- Add native and source-contract regressions for interruption after the first table creation in all three setup layers.

## Unreleased — canonical customer software ZIP members

- Reject drive-qualified, dot-segment, empty-segment, control-character, Windows-reserved/trailing-dot and case-colliding software archive members before publication, in addition to traversal, nested ZIP and special-file checks.
- Add native regressions for portable canonical member names and case-insensitive extraction collisions.

## Unreleased — preserve owner control after financing payoff

- Keep permanent payoff limited to financing-based suspension; an explicit owner deactivation remains authoritative and validation never silently reactivates it.
- Retain the same paid-off plan, key, domain history and no-resale policy while allowing deliberate owner reactivation.
- Add native and installed-browser regressions for the owner-deactivate/reactivate boundary after payoff.

## Unreleased — delayed financing lifecycle events

- Add 40 native SQL regressions covering the webhook corrections, transaction rollback, serialized second-session delivery and unchanged licensing/ledger data across the existing PHP/database matrix.
- Preserve cancellation, reversal recovery and replacement reservations against delayed subscription events.
- Keep confirmed suspension and its catch-up option when a delayed payment-failure event arrives.
- Add 45 actual-service lifecycle regressions to the focused PHP 8.2/8.4 CI lane.

## Unreleased — optional financing webhook isolation

- Keep software captures usable before optional financing storage is prepared.
- Route unrelated software refunds/voids without requiring financing production activation; preserve the finance gate for matched transactions.
- Add actual-service isolated regression coverage and a focused PHP 8.2/8.4 workflow. Full integration/release acceptance remains pending.

## Unreleased — financing reconciliation authorization

- Reauthenticate owner-initiated one-time, subscription and replacement reconciliation inside their serialized commit transactions.
- Require current owner credentials before recurring reconciliation may clear review.
- Add native service-boundary rejection cases for stale credentials.

## Unreleased — financed billing replacement corrections

- Allow caught-up replacement enrollment after the original agreement start while retaining exact future remaining periods.
- Require verified permanent retirement of the prior ARB before replacement billing, preserving its terminal state in history.
- Align installed acceptance with the current outstanding-installment link and preserve Devin’s fixture class fix.

## Unreleased — PR266 integration acceptance corrections

- Preserve Devin’s separate-project financing fixture repair and verify independent paid/unpaid project access through installed endpoints.
- Replace the document-roots native CI fixed host database port with its assigned mapping, retaining the complete runtime/database matrix.
- Add narrowly filtered financing acceptance for companion PRs targeting the licensing feature branch.

## Unreleased — CI queue hygiene

- Fixed development-check workflow concurrency: per-`github.sha` groups (which made `cancel-in-progress` a no-op) replaced with per-PR/per-branch groups; superseded dev runs now cancel automatically while `main` pushes serialize.
- Added `tools/ci_health.py`, a weekly `ci-health` snapshot workflow, and `docs/CI_HEALTH.md` diagnosis rules.
- Release certification, acceptance and diagnostic workflows remain non-canceling.

## Unreleased — 20i password-reset email template

- Added owner-only reading, replacement review, password-confirmed save and separate readback for the documented StackCP reset email template.
- Preserved exact reviewed text, encrypted local history and one-use requests; uncertain writes cannot automatically resend.
- Added offline contracts and installed SQL/browser regressions. Full acceptance and publication remain pending.

## Unreleased documentation — verified remainder and candidate handoff

Record completion of the 139-run obsolete CI remainder, exact PR249 package verification and the separate PR250 contacts-fixture correction. Preserve every original acceptance gate, historical failure and published 1.25 asset. This commit changes documentation only; hosted feature acceptance remains pending.

## Unreleased — final component PR filters and verified cleanup

Limit service-plan and staff-invitation PR checks to their actual dependencies while preserving all execution settings. Confirm the remaining103 cleanup outcomes:327 total terminal cancellations,1,297 unexecuted jobs,31 preserved candidates and139 untouched. Keep the partial evidence and original failures. No application, installer, version or released package changes.

## Unreleased — remaining pure-suite PR filters

Add family-scoped PR filters to access policy, provider contracts and scheduling. Preserve all tests, matrices and push/manual runs. Source/dependency/inverse review passes; no application, installer or released bytes change.

## Unreleased documentation — queue cleanup checkpoint

Record327 acknowledged superseded component cancellation requests,224 independently verified terminal outcomes,103 unresolved verifications,31 preserved candidates and139 untouched candidates. Retain both interrupted batches and the later explicit API rate-limit failure. [Evidence and continuation](docs/CI_SUPERSEDED_QUEUE_CLEANUP.md). Application and released package bytes are unchanged.

## Unreleased documentation — original catalog runtime evidence

Record four original 1.26 runtime producer sets and successful unchanged local aggregations, authenticated artifacts, identical source-matched packages, 16 populated upgrade scopes and known capture limits. Hosted final acceptance and the corrected capture candidate remain pending. [Evidence and next gates](docs/RELEASE_1.26_ORIGINAL_RUNTIME_REVIEW.md). No application, package or release bytes change.

## Unreleased — isolated pure-suite pull-request filters

Limit estimator, email-template, portfolio, review-request and webmail pure-suite PR runs to their source/test directories and workflow files. Preserve every job and push/manual trigger. Source/dependency checks and independent review passed; this affects future scheduling only. No application or release version changes.
## 2026-10-18 — client cloud website journey

From main `e397d9a6e293850e8dff61a593e3ba39ee178400`, branch `codex/twentyi-client-cloud-websites` implements primary-client request/cancel, owner decline/explicit creation, retained outcomes and exact returned-package confirmation navigation. Client dispatch and the existing shared grant commit atomically; replay never sends again. The current intake requires a complete cloud observation within15 minutes. [Behavior, authority, evidence and remaining gates](docs/TWENTYI_CLIENT_CLOUD_WEBSITES.md).

Local evidence:141 document and98 modeled UI checks per PHP mode; four actual module cold loads;34 legacy-context checks per mode;271 final artifact-helper checks;35 portable installed-update guards;19 actual published-baseline negative checks. Original1.24 and1.25 ZIP bytes validate against their retained member inventories, with all27 earlier release maps unchanged. Independent service, browser, installed-update and shared-wiring review is recorded in the feature guide. Native SQL/concurrency, packaged browser pixels, installed Linux/Apache execution and full combined acceptance remain pending. No live operation, new release or deployment; published1.25 and all separate original CI handles remain unchanged.

## Unreleased — reduce unrelated pull-request test runs

Add path filters to ten focused workflows, preserving their full test jobs and shared dependency coverage. Documentation and unrelated UI changes no longer start those ten workflows automatically; relevant changes and manual dispatch remain supported. Complete application and release acceptance are unchanged. This changes future CI admission only, with no application, installer, release asset or live-site changes.

## Unreleased — record observed cloud null response

Retain the exact safe result of separate original run39090973425:42 offline guards and the one-GET diagnostic passed, observing JSON null. No cloud catalog/import semantics follow; the earlier two-operation failure stays preserved. Hosting catalog implementation continues separately. No released bytes or production behavior changed.

## Unreleased — cloud metadata response-type follow-up

Add a separate single-endpoint diagnostic for the original catalog read's missing cloud root kind. The original failure and hosting shape remain intact; no production importer, provider write, image download or release bytes change.

## 2026-10-18 — retain first catalog metadata outcome

Record original [catalog diagnostic run 39090394774](https://github.com/afterburn25/Cajun_Web/actions/runs/39090394774), attempt 1, from `1159648f`: 39 offline tests passed, the live step failed with overall `incomplete`, and the safe shape artifact was retained. Package types expose the observed `id`/`label`/`platform` spellings and integer/string/string types; cloud `unexpected_root` does not disclose its actual root kind. Add the exact safe JSON and [interpretation limits](docs/TWENTYI_CATALOG_SCHEMA_DIAGNOSTIC.md#original-outcome--2026-10-18). This documentation update changes no importer, transport, workflow or released 1.25.0 bytes and claims no full catalog/image acceptance.

## Unreleased — bounded catalog response discovery

Add a separate, owner-scoped two-GET 20i catalog shape diagnostic and offline transport/disclosure tests. It reports only closed field-name/type observations and finite errors; all values and unknown key names remain withheld. It does not change shipped hosting imports, request provider writes or fetch artwork. Execution and actual response evidence are separate from source preparation. Verified 1.25.0 and its assets remain unchanged.

## 1.25.0 — bounded mature increment

### Verified publication — 2026-10-18

[CajunWebPro 1.25.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.25.0) is published and freshly download-verified from immutable source `b79c67110829126916e3a65e616191542448bccc`, tree `f862cc9205e073513f419cdf5ff24cacd1f801df`, after the normal [PR180](https://github.com/afterburn25/Cajun_Web/pull/180) merge. All 18 release assets match their accepted bytes; 1.24.0 is unchanged. This is a GitHub release, not a live deployment.

The accepted increment includes the mature bounded 20i scope, release-only licensing repairs, installer layout and 12-character administrator passwords, explicit non-destructive fresh start/recovery, grouped navigation and saved-inventory hosting drafts with owner-uploaded images. Full provider catalogs, automatic provider images and held lifecycle capabilities remain incomplete. [Publication evidence and limits](docs/RELEASE_1.25.0_PUBLICATION.md).

Earlier dated candidate and branch checkpoints below retain their original evidence and failures. Their pending-work statements do not supersede this publication record.

## 2026-10-17 — scope delivery manifest browser clicks

Correct two browser selectors to target the delivery file manifest after adding collapsible admin navigation. Preserve all existing assertions/captures and add the fixture's exact acceptance pin. No shipping behavior changes. Original default de97 acceptance failed and is retained;221 helper checks plus12 exact-renderer/synthetic Chromium checks pass locally. Complete corrected candidate/main acceptance and publication remain pending.

## 2026-10-17 — combined owner additions ready for acceptance

Release branch `codex/release-1.25.0` normally integrates installer `345b335e` ([PR206](https://github.com/afterburn25/Cajun_Web/pull/206)), navigation `e8ccefc9` ([PR207](https://github.com/afterburn25/Cajun_Web/pull/207)), and observed hosting import `ecf52ee4` ([PR208](https://github.com/afterburn25/Cajun_Web/pull/208)). Integration commit `4b4ce250064036b228747db7550f7c14d3a2bcb0` has exactly the reviewed importer tree, including its installer and navigation ancestors. This enclosing documentation checkpoint records that integration before the fresh full acceptance dispatch. [Release PR180](https://github.com/afterburn25/Cajun_Web/pull/180) remains draft.

Actual local verification includes the shipping PHP/JS build refresh, all 2,894 installer hashes, 221 offline acceptance-helper checks, and the documented installer/navigation/import checks. A native-test-only missing studio form helper was corrected and independently verified with the actual PHP include graph; no application behavior was changed by that correction. Native database, multipart HTTP, combined browser images and full release acceptance remain pending. New expected report/image totals are 208 and 330, retaining all prior assertions and adding the import cases. Earlier parent results cannot certify this combined source. Main remains `d7153b07`, 1.25.0 is unpublished, 1.24.0 remains immutable, and production was not changed. Next: full combined candidate tests, then the documented main and publication gates.

## 2026-10-17 — observed hosting draft imports and product images

The owner requested admin import of 20i products including images. This bounded increment starts from reviewed navigation source `e8ccefc931829aa9245a71d4ba0d310db2d0add6` on `codex/twentyi-product-import-1.25.0`. It adds an owner-only catalog panel to create hosting drafts from named types in the latest saved 20i inventory, with editable local details and image upload/replacement. Complete provider catalogs, provider pricing/images, hosting publication and ordering remain unfinished. See [scope, setup and evidence](docs/TWENTYI_PRODUCT_IMPORT.md).

Current owner, saved-observation and credential checks protect preview/import; repeated requests preserve the existing draft. The import source is retained, family changes cannot bypass draft restrictions, and media cleanup shares the import lock. Uploaded images retain the existing public-URL behavior, explained in the form. Public catalog and invoice projections remain unchanged.

Actual local checks: 48 document cases in each of default/minimal PHP 8.4.25, changed-source syntax, and 221 offline helper checks with the exact reviewed CI wiring, now applied. Independent owned-source and wiring reviews are clear. Native database, genuine multipart uploads, browser pixels and combined release acceptance remain pending. The source-derived expectations are 65 new native and 24 HTTP checks; these are not passing results. Original 149 native recipes, all earlier reports/assertions and 328 release images are retained; 3 commands/reports and 2 images are additive. Published 1.24.0 is unchanged, 1.25 is unpublished, and no live deployment occurred.

## 2026-10-17 — grouped administration and public service navigation

The owner-requested navigation increment starts from installer correction `345b335eeea8331bba5800aa926a3bc1c7507100` on `codex/admin-navigation-1.25.0`. It groups all 36 existing administrator destinations into collapsible categories with current-account permissions, active-page opening and mobile keyboard controls. The public menu adds Hosting, Web Design and Products; Hosting is consultation-only and Products preserves the existing published service allowlist. See [behavior, evidence and remaining gates](docs/NAVIGATION_1.25.0.md).

Local actual-render PHP/Chromium checks passed: 134 admin and 91 public checks with explicitly synthetic data seams. Native database, combined candidate and exact-main release acceptance remain pending. Installer correction is committed in [PR206](https://github.com/afterburn25/Cajun_Web/pull/206); bounded 20i product import continues separately. Main remains `d7153b076e91b524ff2f99a9a7aa5c910551fc5e`; 1.25 is unpublished and no live deployment occurred.

## 2026-10-17 — installer layout, administrator passwords and explicit fresh start

Improve the one-page installer layout and remove redundant permission/process copy. Require at least 12 characters for new administrator passwords. Preserve existing-site configuration on ordinary uploads, show recovery for missing configuration with retained markers, and offer an explicit fresh start with separate tables/private storage. Existing data and install locks are preserved. This correction is awaiting release acceptance; it is not a published update.

## 2026-10-17 - complete acceptance job allowance

Allow four hours for the complete combined native/browser CI job after the preserved original three-hour cancellation near the final scheduler flow. Keep all149 commands, state continuity, named checks, application timeouts and328 image requirements. Shipping application code is unchanged. Fresh corrected-source acceptance and publication remain pending.

## 2026-10-17 - verified bounded StackCP correction integrated for full acceptance

Integrate the reviewed single-call StackCP access snapshot and exact scheduler-backoff fixture correction after the original bounded native/packaged-browser diagnostic passed. Add the native comparison to full acceptance while retaining every earlier recipe, image and guard. Complete corrected-candidate/main acceptance and verified publication remain pending; no new provider capability or production deployment.

## 2026-10-17 - StackCP access read performance correction under verification

Starting source: `60c053b5c9f5a5fded943930500ea57cddd20ff8`. Original full-browser runs expose a slow prepared StackCP access section; the required PHP 8.2/MySQL run also exposes a latency-sensitive scheduled-backoff assertion. Preserve the original failures and all completed evidence. The [diagnostic record](docs/STACKCP_ACCESS_READ_DIAGNOSTIC.md) explains the observed behavior, bounded correction and limits.

An isolated release-blocker branch prepares one synchronous access read snapshot under the owning mutex and transaction. All original validation bodies, current package/client authority, public mutation paths and displayed hosting links remain. Root and independent source reviews passed, along with 798 actual local PHP 8.4.25 contract/renderer checks in default/minimal modes. The scheduler fixture now requires an exact 1,800-second delay from a finish event within its request window; 18 modeled predicate/inverse checks and independent review passed. Independent diagnostic source review passed 63 checks; all 208 acceptance-helper regressions also passed. Native work measurements, exact output equivalence and extracted-package HTTP/Chromium remain pending. Do not count earlier source results as acceptance of this correction. No timeouts or release gates are relaxed, and no new20i scope is added.

Next: validate the bounded correction, integrate it into the canonical candidate, then run complete candidate/maintained acceptance and actual visual review before normal merge, complete exact-main acceptance and all-asset publication/download verification. Main, immutable 1.24 and production remain unchanged;1.25 is unpublished.


Includes the hosting/domain/cloud observations, explicit assignments, reviewed commercial workflows, bounded settings controls and local StackCP prerequisites listed in [release scope](docs/RELEASE_SCOPE_1.25.0.md). Includes the reviewed licensing migration/master-isolation/older-invoice/history corrections, complete supported-runtime acceptance wiring and authentic1.24 update/recovery fixtures. Held capabilities remain incomplete; provider-issued login and SSH are not included. Existing public/portal layouts are retained.

The [acceptance report](docs/TEST_REPORT_1.25.0.md) records source-specific results and limits. This source change entry does not assert publication or live deployment. The release assets and verification receipts establish those later outcomes.

## 2026-10-17 - paired domain fixture timestamps

Capture one earlier timestamp for each paired refresh/binding update in the domain expiry and contract fixtures. Keep production identity guards, named assertions, runtime behavior and acceptance requirements unchanged. Preserve the original c0ed PHP 8.4/MySQL native failure and its seven archives/five logs. Source and trace support a clock-boundary fixture race; original row values were not captured. Corrected-source full acceptance and verified publication remain pending.

## 2026-10-17 - scheduled-run browser synchronization

Wait for the complete Chromium page load after the scheduler's Run if due action before switching to the separate HTTP client. Preserve every existing assertion, request timeout and capture, and pin the corrected fixture in the acceptance contract. An independent actual localhost reproduction with a synthetic streamed tail passes 13 checks at the unchanged 15-second request timeout. Original failed evidence remains retained; shipping behavior and workflows are unchanged. Fresh complete candidate/main acceptance and verified publication remain pending.

## 2026-10-17 - exact WordPress client projection assertion

Replace the WordPress client privacy fixture's whole-JSON sibling-ID substring search with exact approved-field/value equality. Valid timestamps or receipt IDs containing `2202` now pass; extra/private fields, wrong values/types and cross-client access remain refused. Preserve all existing labels and shipping behavior. Modeled 41+41 cases and independent review pass; full corrected-source acceptance and publication remain pending.

## 2026-10-17 - deterministic payment throttle test

Correct the isolated payment verifier test's dependency on earlier checks sharing one15-minute window. Prepare and restore its two owner-specific counters, require the precise throttle refusal, one counter increment and no provider/gateway/audit side effect. Preserve all54 literal labels and all shipping behavior. Parent9e's failed native result remains retained;12 local source checks and13 modeled boundary/fault cases pass. Full corrected candidate/main acceptance and verified publication remain pending.

## 2026-10-17 - PHP 8.4 upgrade test compatibility

Correct six removed-installer assertions for PHP8.4 built-in-server homepage fallback. Preserve every named assertion, physical installer absence and strict404 on earlier runtimes; also verify POST, reject setup UI/redirects, and require an exact separately fetched homepage DOM after validating its one CSP nonce. Shipping code and Apache behavior are unchanged. Parent155 original failures remain preserved. Focused66 guards and12 actual local HTTP scenarios pass; complete descendant candidate/main acceptance and verified publication remain pending.

## 2026-10-16 — licensing permission-test correction

From f77, distinguish the existing owner-only licensing page refusal from the forbidden-action refusal in the shared HTTP fixture. Verify staff login and exact403/no-history-change page behavior, and exercise both nonowner actions through the accessible dashboard while retaining strict200/action-denial and original-history checks. Align the helper source hash; no production permission, route, schema or workflow change. Preserve original run38965358937's HTTP failure and four native114-check passes. Corrected real HTTP and full descendant/main acceptance remain required before publication.

## Historical checkpoint — licensing fixture and release runtime provisioning

From 8f54, load the licensing fixture's workspace prerequisite and align its contract hash; add a supplemental maintained PHP 8.3/MariaDB HTTP/Chromium job using the unchanged licensing case. Guardedly select the fixed HTTPS setup-php PPA mirror in four PHP 8.2/8.4 setup blocks, retaining original publisher trust, signatures, TLS, strict APT failure behavior and all probes/tests. Distro PHP 8.3 and shipping code stay unchanged. The mirror has 41 offline checks/four shell syntax checks; actual APT and diagnostic execution remain pending. Preserve parent evidence at 20:43 UTC and all previous failures. Complete descendant/maintained and exact-main acceptance, publication and download verification remain required.

## Historical checkpoint — package acquisition retry and retained b42 evidence (2026-10-16)

Starting from b42, add bounded APT acquisition retries and strict index-update failure handling while retaining every runtime selection, application test and release gate. Clarify the generic migration/directory introductions without changing substantive instructions or removing historical records. Retain eight original 061/e713 PPA HTTP 503 setup failures and two successful setup comparisons. B42 Windows packages and e713 focused maintained-cloud results pass only their named scopes; original b42 upgrade diagnostic 38951170365 was still in setup at the documentation cutoff. No complete descendant acceptance, publication or live deployment is claimed.

## Historical checkpoint — actual upgrade fixture correction and focused diagnostic (2026-10-16)

Correct the release-only CLI fixture to use the actual Apache public root, preserving its historical defaults and production vault boundary. Add an exact-source published1.24 upgrade diagnostic for faster failure investigation; every complete candidate/main gate remains required. Align fixture source hashes and clarify historical versus current release documentation. Parent e713 focused probe and four-environment ForceSSL checks pass their bounded scopes; corrected real upgrade execution remains required. No new feature or shipping-runtime change.

## Historical development checkpoints

The entries below retain their original sources, failures and pending gates; their dated status is historical.

## 2026-10-16 — deterministic client-observation privacy assertions

Release-only correction after06140894: replace four blanket JSON substring assertions with exact allowed client shapes and values. Valid opaque IDs/timestamps no longer create false privacy failures; unexpected fields, values, dates and sibling data remain rejected. Preserve all existing named assertions and record only safe mismatch booleans.200 controlled extracted-code checks and four lints pass; corrected native/full release acceptance remains required. No application or feature-scope change. Original failed evidence is retained.

## Frozen candidate CI checkpoint — 2026-10-16

Candidate `13a7b08cc8f8ac1cf2a5e1ea6b4027a28cf2a597` (tree `62eeed74de3fa89347589ec5fce5be1ebdd069b5`) is pushed on `codex/release-1.25.0` in [draft PR #180](https://github.com/afterburn25/Cajun_Web/pull/180). The four original full runs `38941882420`, `38941894576`, `38941906749` and `38941919998` failed during runner setup: selected PHP8.2/8.4 apt packages were unavailable after the cached runtime setup. Application/native/browser/upgrade tests did not run in those attempts. All eight original artifacts, metadata and failure logs remain retained; repair provisioning before starting corrected full runs. No gate or existing test is removed.

Original licensing run [38941836904](https://github.com/afterburn25/Cajun_Web/actions/runs/38941836904) passes 114 exact named native checks in each of four supported environments (456 executions,114 distinct cases), including actual missing-history, interrupted-setup, master isolation and invoice-review cases. Downloaded archives, runtime versions, PDO/ZIP, hashes, CRC and source/attempt identity were independently verified. This is native licensing evidence only. Exact13a independent migration/authority/upgrade review records66 checks across38 files with no remaining P1/P2 within that scope. Clean Windows FULL/COMPLETE/UPDATE packages match exact Git bytes, installer inventory, strict updater metadata/shims/payload, all checksums and directory/member lists (5914/2887/2874 members). They remain candidate artifacts.

The default PHP8.3 PR run `38942023179` builds the separate merge-preview SHA `8d6c90287d9417e073aa4178d33ebac4fa6cc51b`; its successful build cannot substitute for exact-branch full acceptance. Focused probe run `38942022550` failed because the inherited test sent a delete-operation body to the strict renewal endpoint. The reviewed fixture now uses a valid renewal body for the authority gate and separately checks malformed renewal rejection before authority/network; local default/no-INI/disabled-cURL results pass52/53/53 checks. Both original artifact archives and failure logs remain preserved. No shipping behavior changes. Main remains `d7153b07`, published1.24 remains immutable, no1.25 release exists and no production deployment occurred. Full supported runs, actual packaged HTTP/Chromium and populated upgrades, pixel review, final independent review, normal merge, complete exact-main rerun and publication/download checks remain mandatory.

The correction commit following13a changes only the full-run provisioning workflow, the stale probe fixture and continuity documentation. PHP8.2/8.4 CLI/extensions/Apache now use the same signed Ubuntu Noble package repository; PHP8.3 keeps its distribution path. Every148 native recipe, runtime probe, report, timeout and remaining workflow step is retained. The integrated transfer/provenance helper still passes208 checks. These source/local checks require fresh complete CI at the correction commit.

## 2026-10-16 — release-only licensing and complete acceptance corrections

Starting parent `85f0b489b30d4e9e6db839e2cf7da02f512176c5` on `codex/release-1.25.0`, preserve the main/mature20i normal-merge union and add explicit full PHP8.2/8.4 × MariaDB10.11/MySQL8.4 selection with actual runtime evidence. Retain all148 native recipes, earlier browser/recovery/Apache cases and distro compatibility. Add actual pinned published1.24 install/population/update/repeat/interruption/recovery/reapplication in root/subfolder and both rewrite modes; stock1.24 has no recurring/licensing records, so those are created and retained separately after upgrading.

Apply narrowly reviewed licensing master-delivery exclusions, additive populated legacy migration, immutable invoice cutover and explicit owner invoice resolution; keep main serialization/checksums/audit and existing provider/billing authority. Add isolated actual HTTP migration, owner review, client/support/master/foreign download and tampered-checksum regressions. Preserve the existing layout, add four landing-page captures and correct stale invoice copy. The approved concept image is unavailable and its dedicated shell is not implemented; no visual-match claim. Independent review reproduced a missing-family/marker recovery gap using surviving licensing audit history. The reviewed two-file correction now refuses missing-family recreation when audit history survives; only an exact unfinished fresh-start witness permits empty partial setup to resume. Native regressions cover both paired-loss cases and legitimate interrupted setup; actual database execution remains pending.

Actual local checks:208 helper assertions on the integrated matrix/upgrade/UI/licensing-driver union,69 build guards,28 retention/form guards and19 actual pinned-ZIP guards pass. Canonical installer refresh/all shipping PHP/JS syntax pass before the final audit-history correction. Early local retention-guard launches lacked requests in installed Python runtimes; an isolated test environment resolves that prerequisite and28 assertions then pass. No native database/HTTP/Chromium or release acceptance is inferred. Current report sets160browser/204final include additive runtime and authentic-upgrade reports. Keep all original failure evidence.

Remaining gates: final source review and corrected inventory, original exact-candidate full runs and screenshots, Windows/Linux package equality, normal main merge, complete exact-main rerun, immutable tag/release, all-asset download/CRC/hash/member/source verification. Main stays documentation checkpointd7153b07; stable1.24 assets and production remain unchanged. Devin's observed separate PRs176–178 remain held alongside other unaccepted proposals. No new20i capability expansion or live deployment.

## 2026-10-16 — freeze and consolidate release 1.25.0

Owner request: publish the mature increment without waiting for complete20i. Start canonical `codex/release-1.25.0` from main `35d8fb7efc94f6f851399d0755fa93097f1fce66`, then normally merge exact mature `3fa72d7b3b447fa3d281cc981ed861c508f850f2`. Fresh ancestry finds all mature capability heads already included, so no duplicate cherry-pick/branch stack is imported. Adopt the26-row scope/dependency map and9 held groups. Resolve shared licensing/deploy/workspace/tests intentionally and preserve both parents' historical records. Generated installer is rebuilt from final source.

Imported-source original focused38933590118 is independently verified successful in all4 environments (11 reports/1,440 named executions each); Windows5890/2885/2872 packages agree with exact source. Existing main licensing defects and release acceptance gaps are explicit blockers, not waived. Catalog prepares complete supported-runtime wiring; Domains extends real1.24 root/subfolder/client-data acceptance; VPS prepares minimal licensing corrections. No combined candidate/native/browser acceptance is claimed by the merge. Required next steps: reviewed corrections, exact-candidate full acceptance and images, independent review, normal main merge, exact-main full acceptance, immutable publication and all-asset download verification. Published1.24/main/production are unchanged; no new feature work until release.

## Retained historical checkpoints from both integration parents

Earlier current-status labels below are historical; the release-priority entry above supersedes them.

## Unreleased - StackCP candidate and Devin handoff checkpoint

Document published3fa72d7b StackCP performance integration and its first focused native run, verified5bfd distro/native success, and classified original cccf/2a/9779 failures. Put concrete158/159/161 repairs and stacked work through175 in Devin's start-here assignment. Main runtime, release versions and assets are unchanged.

## Documentation - corrected acceptance source and coordinated repairs

Record reviewedcccf85bf, its first full38927120383, preserved2a/9779 failure evidence and scoped package/recovery passes. Publish actionable licensing/mail feedback and distinguish Devin foundations from usable features. Main remains documentation-only; no release or deployment.

## Documentation - scoped cloud passes, preserved full-run failure and Devin priorities

Record exact2a focused3,020 checks, matching Windows/Linux packages and289 recovery checks alongside the original full run's tokenizer test-dependency failure and skipped HTTP. Preserve819c's terminal failure/99 missing reports and9779's newly canceled native-and-browser job with failed aggregate, pending diagnosis. Update current entry points and the159/161 correction priority; PRs163–170 are unaccepted proposals/foundations. This documentation-only descendant of82667afb imports no runtime and changes no installer, version, release or deployment.

## Documentation - corrected StackCP source and preserved acceptance evidence

Record corrected source 819c3ea3, its independently verified original focused archives and Windows packages, first full 819c run in progress and the preserved original 9779 full acceptance. Preserve the failed 13879 fixture run and terminal failed 9538 browser run, with their separate evidence scopes. Update active source pointers and remove conflicting current pause wording for Devin. Published 1.24.0 and all runtime, installer, version, assets and live installations remain unchanged.

## Documentation - current20i acceptance and parallel handoff

Publish source9779879b, independently verified focused registration/Windows evidence and the first full acceptance38909577278. Retain original failures and the separate cloud/StackCP review scope. Refresh status/handoff with immutable links to the complete prior licensing history. No application, installer, version, release or deployment change.

## Documentation - Devin non20i assignment discoverable on main

Add a root start-here guide, complete owner-approved assignment and existing licensing source/review links. Supersede the earlier unrelated-work pause for Devin while Codex retains20i and final integration. This changes no application, schema, installer, version, release or deployment.

## Unreleased - bounded StackCP owner read

Validate complete StackCP setup histories once per protected owner-view snapshot while retaining current package authority, exact forms and fresh validation on every later call. Add actual database HTML/query-count, corruption/recovery and process-lock comparisons. Preserve all predecessor commands, the distro-PHP correction and production gates. Local source/portable/helper checks pass; native and combined acceptance remain pending.

## Unreleased - minimal PHP fixture compatibility

Load the PDO exception class explicitly alongside tokenizer for the extracted cloud-assignment fixture under Ubuntu PHP 8.3 with no INI. Add a small distro-PHP compatibility job retaining six exact reports and runtime configuration; preserve the existing four-way native matrix and all full application gates. Shipping application behavior is unchanged. Original failures remain retained.

## Unreleased - acceptance runtime correction

Load the test extractor tokenizer explicitly in the existing minimal-PHP command and allow180 minutes for the growing stateful native/browser suite after the original105-minute timeout. Keep all shipping code, fixtures, report requirements and failed evidence. Full acceptance and release remain pending.

## Unreleased - correct StackCP native sibling lookup

Fix the test fixture to select the actual protected sibling assignment and assert its exact identity before the unchanged tamper test. Preserve original four-way failure evidence. Shipping code is unchanged; corrected native acceptance remains pending.

## Unreleased - owner StackCP access observations

Add owner membership and whole-user grants reads with fixed GET transport, current authority, authenticated history and finite safe states. Preserve registration/all earlier test contracts. Local source/pure/synthetic checks pass; native, HTTP/browser and release acceptance remain pending. No provider login/session or SSH is enabled.

## Unreleased - private registration drafts integration

Add owner/direct-client private registration drafts with encrypted contact details, exact captured revisions, authenticated history and explicit studio-review submission. Preserve failed-form edits and paginated clients/history. Registration purchase and payment remain unavailable. This normally combines pure ancestor 2e86 and frozen 9538 with reviewed route/test additions; native/browser/package acceptance is pending. Preserve original a63 renewal fixture failure and its separate seal failure.

## Unreleased — protected StackCP setup integration

Add owner-only explicit separate authentication-key configuration and unverified resource assignments with current-password/review checks, encrypted append-only history, missing-key restoration protection and no credential decryption on status pages. Original focused matrix passes all four environments. Combine with directory controls, paid renewals and exact synthetic delivery evidence correction; new combined UI/package acceptance is still required. No provider session/login or remote revoke guarantee is enabled.

## Unreleased — exact synthetic delivery evidence

Retain the three generated public website-delivery files in native/browser evidence without widening other paths or lanes. Add51 actual offline seal/aggregation/boundary regressions while preserving all112 existing cases. Original556 failure and a63/4791 runs remain unchanged; shipping application bytes are untouched and fresh combined acceptance remains required.

## Unreleased — owner directory listing controls

## Unreleased — directory and renewal integration,2026-10-16

- Normally combine tested owner directory controls with paid domain renewal and authenticated Force SSL history; preserve every earlier native/browser scope and both histories.
- Current merged acceptance remains pending. This entry creates no release, provider call or live deployment.

Add explicit owner Enable/Disable requests with current authority, one durable provider attempt, separate exact observation, independent client sharing and narrowly scoped saved-history recovery. Preserve uncertainty/no-retry fences and authenticate retained indexed history before admission. Source/portable review passes; actual native and combined application acceptance remain pending.

## Registration source formatting — 2026-10-16

Remove one trailing blank line from each of the six reviewed registration files, preserving the original commit and all252 named outcomes per mode. No behavior or provider contract change.

## Unreleased isolated work — private registration drafts

Add pure typed contact/registration drafts with redacted ordinary projections and an explicit unverified purchase boundary. Both local PHP modes pass252 named cases. Shipping and purchase behavior are unchanged; native editing and the missing provider term/outcome contracts remain separate work.

## Acceptance job-output correction — 2026-10-16

## Force SSL retained-history correction — 2026-10-16

The current renewal union additionally authenticates all retained Force SSL change documents before indexed admission. Independent source review and PHP syntax pass; four actual storage-tampering cases are added, with native matrix and combined acceptance pending. [Scope and exact evidence](docs/TWENTYI_FORCE_SSL_KEYED_HISTORY_REVIEW.md). The original556 full remains preserved and has reached browser testing; its38 Apache reports are independently verified.

## Unreleased integration: paid renewal dispatch

Normally combine556+b221: permanent prepayment invoice purpose, original client consent, verified receipts and one owner-approved provider submission, with canonical-domain uncertainty fences. All four focused native environments pass; combined browser/release acceptance remains pending. Preserve105 existing native commands plus six additions,121/160 report sets and111 offline helper checks.

Fix the first parallel run rejecting its own sha256 output name. Add nine actual output/entry-point checks (109 total passing locally) and preserve the failed original plus the older105-minute timeout. Application behavior is unchanged; new full acceptance is pending.

## Unreleased — WordPress admin buttons fit narrow cards

Wrap complete WordPress refresh/sharing action labels within their forms and add four real owner-browser bounds checks. The fix responds to two actual original CI screenshots despite passing viewport assertions. Scoped offline before/after checks and independent source review pass; fresh combined application acceptance remains required.

## Unreleased — isolated parallel acceptance workflow

Prepare one immutable build for separate test jobs, preserve sequential native/browser state, validate exact same-attempt evidence and retain honest partial failures. Existing assertions and application behavior are unchanged; source review and actual combined execution are pending.

## Unreleased — integrate finished-ledger recovery and faster packaging

Combine the four-way native-tested Force SSL witness recovery with batched Git packaging and the verified dynamic database port correction. Recovery makes no provider request and retains the effect as uncertain until separate readback. Preserve original failures and exact parent evidence; combined browser/package acceptance for this integration remains pending.

## Unreleased — avoid hosting-test database port collisions

Let Docker assign an available host port for the hosting storage CI database and pass the actual port to the existing native tests. Preserve original run 38879885470, whose PHP 8.2 job stopped before tests because fixed port 33306 was occupied. Current-head execution remains required. Application and installer files are unchanged.

## Unreleased — batched release source reads

Read immutable Git blobs through one binary batch process when packaging, preserving all existing build checks and deterministic archive behavior. Add 45 portable source/protocol/child-cleanup cases after the original 21 builder guards; all 66 pass locally on Windows. Shipping and installer files are unchanged. Current-source CI, full packaging and release acceptance remain required after integration.

## Unreleased isolated work — recover finished Force SSL attempts

Add a current-owner local recovery action for the exact crash window after durable shared-ledger completion but before the dispatch witness. Preserve uncertainty, no-retry fences, original history and separate client sharing; only a subsequent exact GET can observe the desired flag. All four focused native/process environments and portable reports pass with independently verified archives; combined browser/package acceptance remains pending.

## Unreleased — scheduler privacy fixture correction

## Unreleased — paid renewal dispatch source under review

Add permanent unpaid-invoice purpose allocation for accepted one-time USD renewal terms, original receipt verification, saved client purpose/outcome views and owner-approved single submission through the existing operation ledger. Unknown/failed attempts remain domain-fenced; payment/consent alone cannot authorize renewal. Corrected index-proof, optional-family and captured-selector boundaries are source-reviewed. Native, browser and release acceptance remain pending. See [scope and evidence](docs/TWENTYI_DOMAIN_RENEWAL_DISPATCH.md).

Avoid falsely rejecting unrelated website-folder controls when checking scheduler privacy. Require the exact scheduler section and retain full-page credential checks, with21 positive/negative regressions and independent review. Application code is unchanged; new combined browser acceptance remains pending.

## Unreleased — saved WordPress installation indication

Integrate explicit owner refresh, saved true/false/unavailable indication and independent client sharing. Preserve current hosting authority and observation history. Four-way native evidence is verified; combined browser/package acceptance remains pending. Retain the original scheduler privacy assertion failure for a bounded test correction before the next acceptance run.

## Unreleased — renewal agreement integration

Combine exact one-time USD renewal terms and captured client consent with the reviewed hosting controls. Preserve immutable request/agreement history, current authority, reoffer/end safeguards and generic billing exclusions. Four-way native evidence is verified; merged browser/package acceptance remains pending. Record licensing schema3/checksum compatibility without importing Devin's source.
## Unreleased — saved WordPress installation indication

Implement a distinct owner-triggered WordPress installation observation and separately permitted saved client view on an isolated branch. Keep reported true, reported false and unavailable distinct, with current assignment/credential/history guards. This is an indication only, without version, health, absence or installation-action claims. The exact four-way native matrix and all14 downloaded reports are independently verified; combined application/browser acceptance remains pending. Preserve the reviewed correction to the initially misrouted synthetic test response.

## Unreleased — catalog test response-parser correction

Correct the synthetic privacy regression helper to parse raw markup internally while retaining response-object parsing for actual HTTP pages. Preserve all13 privacy cases and the exact public-reference/private-value checks. Downloaded originalc174 browser failure is retained; corrected combined browser acceptance remains pending. No application behavior changes.

## Unreleased — saved directory-listing settings

Integrate owner-triggered observations of the directory-listing setting with independent client sharing, immutable history and current hosting authority. False and unavailable remain distinct. The setting is informational and does not test file exposure. Four-way focused native evidence is verified; combined browser/package acceptance remains pending.

## Unreleased — integration evidence and licensing compatibility notes

Document verified c174 Windows packages, the preserved03b failure, current focused acceptance and source review of main's invoice-linked licensing/schema version2. Record remaining upgrade, legacy-invoice retry and internal-key-delivery requirements without changing the tested application candidate.
## Unreleased isolated work — owner Force SSL change requests

Prepare explicit owner approval against a current saved flag, a single shared-gateway request, immutable dispatch evidence and separate exact GET readback. HTTP200 never acknowledges the effect. Client observation sharing stays separate; pending requests make older observations historical. Reviewed shared wiring is applied; four-way native acceptance now passes atbff7; browser acceptance remains pending, and interrupted-dispatch operator recovery is not yet implemented. The first focused run stopped before native setup on a duplicated test include; change only that include to require_once and retain the original failure. No provider action or release is claimed.
## Unreleased — verified renewal agreement native acceptance

Verify the corrected renewal agreement workflow in all four PHP/database environments with downloaded named reports, including71 new native+69/+69 pure checks and all existing billing/order regressions. Preserve the original fixture failure. Evidence only; combined browser and release acceptance remain pending.

## Unreleased — renewal interruption fixture correction

Preserve the original four-way native failure and correct its synthetic interruption to occur during result persistence. Require one retained intent, one synthetic request and no result after the exact database fault. Application behavior is unchanged; corrected database and combined browser acceptance remain pending.

## Unreleased — renewal service agreement binding

Allow studio-entered one-time USD service terms to be linked to an exact pending domain-renewal request, with explicit privacy scope, acceptance deadline and retained replacement-offer history. Reuse existing client consent/end actions and prevent generic hosting forms from bypassing the renewal binding. Source-reviewed; native/browser acceptance pending. No automatic provider renewal, charge or invoice generation.

## Unreleased — saved Force SSL flags and catalog privacy test repair

Integrate native-tested owner refresh and separate client sharing of saved Force SSL flags, preserving false versus unavailable and immutable authority-bound history. This flag does not verify HTTPS or certificate health. Correct the browser privacy test to distinguish the exact public service reference from a private provider type, retaining private-value rejection and adding13 regressions with safe diagnostics. Combined package/browser acceptance remains pending.

## Unreleased — integrate saved website-folder mappings

Normally integrate native-tested document-root observations. Owners can explicitly prepare, refresh and share saved folder mappings for a currently assigned hosting package; clients see only permitted current or historical data. Folder paths are escaped text, with independent sharing, credential/current-assignment checks and recoverable immutable history. No file operations or provider mutation. Combined browser/package acceptance remains pending.

## Unreleased — current expiry native and package verification

Record frozen03b and its original acceptance handles, verified four-way expiry reports and exact Windows packages. Close original427 with preserved browser failure, successful recovery/server protection and exact final packages. Document reviewed website-folder and ForceSSL application lanes without claiming pending browser acceptance or a release. Documentation only; shipping source remains03b.

## Unreleased — integrate saved upstream expiry observations

Normally integrate the native-tested upstream-expiry feature with dedicated-owner test corrections. Owners explicitly prepare and refresh saved expiry observations for current assigned domains; clients see only permitted current or historical records. Separate date/expiry authority, immutable history, unavailable results, credential filtering and owner controls remain. No automatic renewal, provider mutation or eligibility claim. Combined packaged browser/recovery acceptance is pending.

## Unreleased — preserve account authority across fixture login

Correct dedicated period/PHP browser fixtures to account for the normal last-login update before preserving the complete account row. All password, authority, role, immutable-history, quota and cooldown assertions remain; add finite setup diagnostics before assertions. Preserve actual427 failed browser evidence and completed main94 source review. Application/installer bytes remain unchanged; corrected combined acceptance and release remain pending.

## Unreleased — frozen candidate verification and parallel handoff

Record all20 exact combined reader matrix artifacts, successful combined packaging/native checks, verified original PHP full acceptance and preserved domain full failure evidence. Record independent integration review, separately native-tested expiry and pure cloud/DNS components; document-root/ForceSSL work remains separate. Update issue2 ownership/status and current-main licensing findings. Documentation only;427 shipping stays fixed,1.25.0 remains unreleased, and no live provider action occurs.

## Unreleased — registration-period browser owner isolation

Correct the combined browser fixture's exhausted shared-owner read budget by creating and activating a dedicated synthetic owner through existing admin forms. Preserve every original account, intent, audit, epoch, production limit and cooldown; retain response/counter diagnostics and exact history checks. Application source and installer are unchanged; actual combined acceptance remains required.

## Unreleased — combined browser evidence and bounded corrections

Record the actual25e browser failure at the period empty-response fixture after2130 passing checks, verified domain/cloud views and all-four f725 recovery matrices. Preserve original full runs and failed reports. Coordinate the separate period-owner fixture, domain credential-echo guard and PHP transaction-fixture correction before one new combined acceptance. Documentation only; published1.24.0 remains unchanged and1.25.0 is unreleased.

## Unreleased — current handoff consolidation

Replace repeated historical status/handoff/ownership blocks with current sources, exact evidence, open findings and next actions. Preserve prior text in immutable75d5c60a and local hash-recorded copies. Original25e full now passes native integration and runs HTTP/Chromium; no shipping or release change.

## Unreleased — parent full acceptance and recovery handoff

Record downloaded210 final packages/reports and Windows parity; retain the screenshot limitation. Record actual red domain recovery evidence and reviewed f725 correction, pending PHP credential-echo repair, and separate pure registry-expiry ownership. Documentation only; no release or shipping change.

## Unreleased — combined discovery native matrix

Record all16 downloaded exact25e date/period/cloud/scheduler archives, each with named native and pure checks across four PHP/database combinations. The original full continues, and separate altered-sequence recovery corrections remain required. Documentation only.

## Unreleased — combined source and package checkpoint

Record pushed25e combined discovery source, exact verified Windows packages and original GitHub acceptance handles. Record terminal incoming date/cloud full acceptance, parent210 browser evidence and the retained element-capture limitation. Preserve the PHP packaging-only correction and new PHP/domain-date source-ordering findings for separate regression work. Documentation only; no runtime or release change.

## Unreleased — client domain renewal-date observations

Normally integrate explicit owner refresh and private client display of saved provider renewal dates with registration options and cloud-provider identifiers. Preserve exact current assignment/account/source binding, typed scalar/list separation, current-versus-historical failures and scheduler authority. Correct a locally caught merge syntax error before publication and regenerate installer hashes. Combined runtime acceptance and release remain pending.

## Unreleased — observed cloud-provider identifiers

Normally integrate explicitly refreshed owner-only cloud-provider observations with registration options, preserving current/historical labels, both typed list readers and the shared guarded recorder. Retain the verified binary SQL witness correction and all original failures. Current combined acceptance and publication remain pending.

## Unreleased — observed registration options

Normally integrate owner-triggered registration-year observations and current-policy client display with the accepted history/forwarding/scheduler queue. Preserve exact read authority, sequence-first failure behavior, current offered suffixes, audit witnesses and scheduler ordering. Correct only failure-mobile screenshot timing and add complete-page context. Record verified incoming native/browser/package evidence and completed parent4f full acceptance; new combined acceptance and release remain pending.

## Unreleased - combined browser verification and reader corrections

Follow-up records corrected cloud-provider four-way native acceptance and the independent64-check incoming shared-boundary review. Runtime remains unchanged.

Record actual combined4f browser/native/package evidence and scoped desktop/mobile visual review. Preserve failed child runs and exact domain-date, registration-period and cloud-provider corrections; record the latest licensing parser source fix and remaining findings. Documentation only; no new runtime or release in this checkpoint.

## Unreleased — combined build and source-review continuity

Preserve exact4f Windows build verification and original combined CI handles. Document independent licensing setup/migration/master-delivery findings and the owner API reference's auth-code side effect. Documentation only; no runtime or published release change.

## Unreleased integration queue — owner-approved scheduled inventory

Normally integrate reviewed scheduled hosting reads with forwarding requests, client service history and cloud recovery. Preserve explicit schedule approval, durable leases/backoff, exact authority and the shared guarded recorder. Regenerate combined installer and retain native/browser/private-route coverage. Combined acceptance and publication remain pending.

## Unreleased integration queue — forwarding and owner API reference

Normally integrate client single-address forwarding requests, cancellation and owner review/decline with client hosting history and the cloud recovery correction. Preserve separate forwarding permission and local-review-only behavior; provider forwarding dispatch/readback remains unfinished. Retain/index the new complete owner API reference for the parallel registration-period, cloud-provider and renewal-date readers. Document verified a963 full/package evidence and ready scheduler checkpoint. Combined acceptance and release remain pending.

## Unreleased — registration-period fixture ordering

Correct the native expected client rows to match the existing sorted offered-extension policy, including exact saved years. Preserve all four failed original reports and exact Windows package evidence. Runtime and generated installer files are unchanged; fresh corrected native/browser evidence is required.

## Unreleased — verified cloud-provider native compatibility

Record corrected6eeeb020 four-way native acceptance and independent artifact verification, while preserving original MariaDB failures and pending full browser/package acceptance. Save a source-backed next PHP-version observation handoff without adding runtime behavior.

## Unreleased — cloud-provider witness SQL compatibility

Use explicit binary concatenation for cloud-provider retained audit witnesses so MariaDB and MySQL compare the same ASCII bytes. Preserve the original partial native failure, add direct SQL/PHP parity coverage and require new exact acceptance.

## Unreleased — observed account cloud providers

Add an explicit owner refresh for documented account cloud-provider identifiers, dated immutable observations and honest current/historical/empty/unavailable displays. Reuse the existing fixed GET reader, credentials, durable intent/result storage and guarded recorder. Refuse incomplete retained history, changed context and reordered refreshes. No server ordering or runtime controls are added. Source-reviewed; native/browser and exact package acceptance pending.

## Unreleased — explicit domain renewal-date observations

- Exact corrected checkpoint6ba now has four verified native combinations and2038 passing HTTP executions, including49 date checks and four inspected desktop/mobile captures. Shipping remains078; the original failed fixture evidence is retained.
- Test-only renewal-date browser correction preserves all inherited observation timestamps and waits for actual cooldown. Exact078 native matrix passed; its first browser checkpoint failed before new date screens. No shipping behavior changed.

Adds saved date-only provider observations and history for exact current registered-domain assignments, explicit owner refresh and private client display. Corrected exact native and browser acceptance now verifies the saved-date workflow; combined integration and full-run completion remain pending. No renewal or registry-expiry claim.

## Unreleased integration queue — client hosting history

Normally combine reviewed owner-approved client observation history with the cloud recovery correction on a separate integration branch. Preserve both original sources and evidence; retain all documentation history and regenerate combined installer hashes. Fresh combined acceptance is required. The separate release candidate and published assets are unchanged.

## Unreleased — cloud assignment recovery correction

Refuse cloud confirmation/current-assignment claims when retained package-link events disagree with independent link/relink/unlink audits. Preserve historical ordinal pairing, immutable confirmations and provider outcomes; add native history-loss/restoration regressions. Fresh corrected acceptance remains pending. Document incoming master-license migration/delivery findings for Devin and terminal catalog/hosting-history evidence. No release or live action.

## Unreleased — software-product licensing ownership and supporting20i evidence

Record the owner's Devin licensing allocation while Codex continues20i. Verify40 additional combined3bbc matrix archives containing142 named reports; all31 focused workflow gates are successful. Combined recovery passed and Apache remains active. Record corrected scheduler/hosting-history/forwarding native evidence and preserve failed packaging/fixture runs. Documentation only; no runtime, release or live action.

## Unreleased — combined native evidence and parallel source review

Verify20 archives from all five combined3bbc focused matrices and final corrected2239 full acceptance/packages. Record concrete hosting-history/forwarding review fixes and the scheduler MariaDB correction under their separate agent branches. Documentation only; 3bbc packaged browser checkpoint now verifies1945HTTP with actual screenshot review; full recovery/Apache and exact-main release gates remain pending.

## Unreleased — explicitly shared hosting history

Owners can enable saved hosting observation history for an exact current client/package assignment. Clients see only observations from the approved sequence onward, with stale, failed and unfinished latest reads clearly identified. Revocation, current account context and retained permission/history recovery are enforced. No live monitoring or provider operation is added. Corrected checkpoint65725ad passes all four native combinations (71 native and 33 pure checks per mode), plus 1,989 actual HTTP/Chromium checks including 44 hosting-history cases. Full owner/client desktop/mobile screens are inspected; auxiliary mobile capture defects are retained and documented. Combined full acceptance remains required. The initial standalone native fixture failure is preserved and corrected by loading the existing owner layout; shipping is unchanged by that test correction.

## Unreleased — local forwarding requests

Add separate owner permission and client forwarding request/review history. Clients can request or cancel; owners can save review messages or decline. Signed immutable records and exact current domain/account checks fence stale, foreign and replayed actions. Offline request shape follows the verified 20i browser-console example; no forwarding dispatch or activation is enabled. Four-way native, actual packaged owner/client browser, recovery, Apache and exact-source final archive acceptance are verified on isolateda02; root integration/combined acceptance remains separate.

## Domain renewal request integration — candidate, unreleased

## Unreleased — combined package proof and continuing parallel implementation

Verify exact3bbc Windows packages and record its running full combined acceptance. Renewal browser/screens now pass oned4; three agents continue scheduled inventory refresh, client observation history and forwarding requests on separate branches. Record concrete scheduler recovery review findings and correct the stale recurring-defect tracker from existing source/browser evidence. Documentation only; no new runtime, release or live action.


## Unreleased — preserve current main continuity

Normally preserve mainb8f8528b licensing documentation after combined cloud integrationbdeccc09. Archive the incoming handoff and retain the approved portal design reference. No runtime, test, installer or version change in this documentation merge; fresh combined acceptance remains required.


## Unreleased — combined 20i confirmation and parallel scheduler work

Normally integrate reviewed/native-verified cloud package confirmation after catalog readiness and local domain renewal requests. Preserve exact returned-ID/name checks, atomic client assignment/history, sequence-first freshness and existing authorization. Combined installer and full acceptance remain required; published1.24.0 stays unchanged. Catalog browser screens now pass on their own source. Three agents continue independently, including a new bounded scheduled package inventory refresh implementation. No live activation or release is claimed.


Normally merge reviewed/native-verified renewal ed4d1cad: clients request renewal review or withdraw; owners save a review message or decline. Current registrar assignment, immutable request/events and a retained request-count witness protect history. Monotonic event timestamps survive clock rollback. This local intake does not place or complete provider renewals. Combined inventory refresh and syntax pass; new combined application acceptance remains required.

## Unreleased - verified scheduled refresh checkpoint

Record successful corrected packaged/browser checkpoint38836183578, four inspected scheduler captures and preserved four-way native/Windows evidence. The original5fb browser failure remains retained with successful recovery/Apache results. This documentation checkpoint changes no runtime, tests, workflow or installer inventory; combined integration acceptance remains required.

## Unreleased - scheduled refresh browser navigation fixture

Refresh the existing browser document after a separate HTTP session changes scheduler state. Retain all network, persisted outcome, backoff and revoked-approval assertions. Application behavior and installer bytes are unchanged; corrected packaged/browser evidence is required.

## Unreleased - scheduled inventory recovery witnesses

Refuse scheduled work and history when original package rows or inventory audit witnesses are missing. Validate exact audit action bytes and preserve the original worker actor when another owner reviews interruption. Restoration retains the original schedule without replay; fresh native and packaged acceptance are required.

## Unreleased - scheduled inventory packaging correction

Regenerate installer hashes against exact committed LF source. Scheduler runtime is unchanged. All four native combinations are independently verified; the earlier full package failure and pending recovery hardening remain recorded.

## Unreleased - owner-approved scheduled hosting inventory

Add one shared-host PHP scheduled task for bounded read-only hosting inventory, disabled until explicit owner approval. Persist immutable attempts and outcomes, enforce current owner/profile/key bindings, serialize concurrent workers, back off incomplete reads and require reviewed recovery after interruption. Owner setup, status, history and recovery controls are included. Native/browser acceptance is pending; no provider write, purchase, release or deployment is included.

## Catalog readiness integration — candidate, unreleased

Normally merge reviewed catalog872aec34: owner plan summaries explain local readiness, distinguish zero from unknown costs/limits, and show selected immutable mapping reviews without changing current edit or execution authority. All four focused native combinations are verified; actual browser and combined application acceptance remain pending.

## Acceptance repair — foreign-client mailbox fixture

Parallel continuation: catalog readiness is in PR145 with exact-head testing; independent domain/cloud review found bounded clock-order and retained-history gaps for their authors to repair before integration. No further root runtime or release change.

Correction2239a156 is pushed and fresh full38826150967 passed package checks and entered native integration. Prior38bb recovery passed; Apache continues. Catalog readiness has independent source review without a concrete blocker, pending native/browser evidence. No further runtime or release change.

Use the foreign client's actual workspace session token to test mailbox ownership refusal. Retain no-request/network assertions and verify the precise authorization error without password echo. Preserve the failed combined browser artifact and newer main test history; fresh descendant acceptance remains pending. No shipping/version/release change.

## Acceptance checkpoint — combined20i38bb

Verified all four mailbox/registrar/catalog/cloud native matrices and exact Windows packages. Full combined browser/recovery/Apache acceptance is still running. Dispatch three further isolated implementation slices while preserving original branches. Documentation only; no runtime, version, release or live deployment change.

## Integration checkpoint — latest main evidence

Preserve the latest main acceptance record alongside the combined20i source. No additional runtime behavior or release version changes in this final merge.

## Unreleased — combined cloud website creation

The admin capability summary now distinguishes the connection check from separate service permissions and no longer lists implemented domain search and hosting creation as unavailable.

Add an owner-reviewed website to an explicitly linked existing cloud server using the shared provider operation history. Preserve returned package IDs as accepted pending independent observation and explicit linking. Combine cloud, registrar, catalog and mailbox workflows with original branch histories and shared recovery guards. Complete combined acceptance remains pending.

## Unreleased — reviewed provider plan mappings

Combine expiring owner-reviewed provider type and cost mappings with existing retail orders and hosting preparation. Preserve exact selection provenance and withdrawal/recovery history. The provider-settings browser check now waits for returned-page content and retains diagnostics on failure; product behavior is unchanged by that test correction. Combined acceptance remains pending.

## Unreleased — combined registrar and mailbox controls

Registered-domain inventory and explicit client assignment are combined with mailbox creation controls. Preserve both workflows and protected paths. Latest mailbox authority follows the original refresh intent sequence even if recovered timestamps are out of order. Four new native regressions and combined acceptance remain pending; published1.24.0 is unchanged.

# Unreleased — native 20i mailbox creation

Preserve current main's licensing mutation audits and error-response guard during20i integration. Domain replacement keeps its transaction and records the acting identity on all three events; failed audit insertion restores original domains and audit history.40 isolated native checks pass; combined acceptance pending.

Native mailbox runtime334c390c passes87 named checks in each of four PHP/database combinations. Browser and integrated release acceptance remain pending; published1.24.0 is unchanged.

Refuse mailbox storage recreation when only the original shared operation ledger survives. Keep native rendering variables isolated so recovery tests retain the original successful request identity. Current acceptance is pending.

Preserve the selected mailbox domain after an invalid form submission and refuse mailbox setup inside a caller-owned transaction. Independent review regressions are included; acceptance remains pending.

Correct the native gateway fingerprint call and refuse partial database recovery that leaves original mailbox grants/results without their request. Native regression cases cover these surviving witnesses; corrected CI remains pending.

Add expiring owner permissions, real owner/client creation forms and safe request history over explicit client domains, using the existing provider gateway/ledger. Passwords stay transient; original request history prevents replay and ambiguous-address retries. New native/database/browser coverage is prepared but acceptance remains pending. Three parallel agents now work on separate catalog/domain/cloud increments; their changes are not integrated in this checkpoint. Published1.24.0 is unchanged.

## Unreleased - registered-domain browser coverage

- Prepared actual packaged owner/client browser flows, freshness/recovery checks and screenshots. Services explicitly send private cache/referrer headers; long domain headings wrap. Current browser acceptance remains pending.

## Unreleased - registered-domain inventory

- Explicit owner refresh of bounded registered-domain identities and distinct client assignments; native owner/client views keep hosting links separate.
- Current complete observations, exact identity/context and latest-attempt freshness govern assignment and disclosure; failures never fall back through older success.
- Adds native schema, permissions, atomic audit, concurrent reapproval and recovery fixtures. Native/browser acceptance and nameserver execution remain pending. See [scope](docs/TWENTYI_REGISTRAR_INVENTORY.md).

## Unreleased — preserve catalog withdrawal during recovery

- Retain the exact latest mapping ID/digest independently, preventing a partial restore of review and audit history from reactivating an older withdrawn approval.

## Unreleased — catalog review corrections

- Preserve safe inline rendering after oversized or array catalog form input; validate recovered association shape before reading its format.
- Add actual HTTP/Chromium catalog form and existing fulfillment preparation coverage, pending execution.

## Unreleased — owner-reviewed 20i plan catalog

- Save versioned provider account/type/limit mappings for exact published retail plans, with expiry, withdrawal and current-owner/connection checks.
- Keep private provider cost decimal amounts, currencies, periods and tax/allocation evidence separate from client retail prices; no inferred conversion or provider quote.
- Prepare existing hosting service records from an eligible saved mapping while retaining original order/payment/fulfillment safeguards and immutable provenance.
- Local pure checks pass; native/browser/packaged acceptance and automatic discovery remain pending. See [scope](docs/TWENTYI_PLAN_CATALOG.md).

## Unreleased cloud recovery correction

Refuse cloud request replay after paired approval/audit loss when the shared ledger or result audit survives. Refuse operation re-preparation after a lost marker with retained history. New native regressions are pending CI.

## Unreleased cloud verification

Add native two-process request replay coverage and isolated-branch application acceptance. Runtime unchanged from7e742c3b; CI results remain pending.

## Unreleased — cloud website creation

Cloud agent owns `codex/twentyi-vps-controls` from0ebc4623: native owner Add website on an explicitly assigned existing server, safe client request summaries, existing gateway/ledger and exact documented addWeb transport. Local synthetic checks pass; native/browser acceptance and root integration remain pending. Server lifecycle/power/console/SSH are still unfinished. See [cloud scope](docs/TWENTYI_CLOUD_WEBSITES.md). No release/live action.

# Integration checkpoint — current main and domain replacement rollback

Normally merge main16f07e49 into the20i client-search branch. Preserve licensing/domain controls and fix failed replacement leaving the original domain disabled. Local4 rollback cases and35 native licensing checks pass; combined acceptance/review pending. Earlier e375 full application artifacts/packages are now verified. Published1.24 remains unchanged.

# Verification checkpoint — client domain search

Native1fbeb passes106 combined checks including50 client checks in each of four PHP/database combinations; exact Windows packages verified. Correct the browser fixture to grant support access before asserting that real support sessions cannot search. No runtime authorization change. Browser/recovery/review acceptance remains pending. [Evidence](docs/TWENTYI_CLIENT_DOMAIN_SEARCH.md).

# Development checkpoint — private client domain search

Add explicit owner enablement, a native client search form and private history through the existing authenticated 20i reader. Share owner/client rate limits, preserve independent client identity, recheck current access and prevent request replay. Add missing-history recovery and native/browser regression coverage. Local syntax passes; new runtime acceptance and release remain pending. [Scope](docs/TWENTYI_CLIENT_DOMAIN_SEARCH.md).

# Development checkpoint — accepted order and active service wording

Parent `e37517fa` merges tested hosting confirmation with newer main. Four native database/PHP combinations and independent exact Windows package verification pass. Parent `4d059a5b` browser evidence passes 1,737 checks; actual screenshots exposed an accepted-order sentence incorrectly saying setup was pending after activation. Correct the sentence and add owner/client browser regression coverage while retaining the pre-activation assertion. New current-head packaged acceptance, review and release remain pending. Published 1.24.0 is unchanged.

# Changelog

- Security: retain all 128 random bits in newly generated website/software license keys; previously issued four-group keys remain accepted.

## Unreleased — verified confirmation and current-main integration

- Verify38 authenticated confirmation cases on all four native combinations and exact4d059 Windows packages. Full descendant browser/recovery/Apache acceptance remains pending.
- Preserve main13f9662d licensing changes through a normal merge while retaining20i and optional-storage/owner-access safeguards. Regenerate combined inventory; new combined acceptance is required.

## Unreleased — authenticated hosting setup confirmation

- Confirm the original accepted hosting ID through the existing authenticated inventory reader, with matching enabled status, domain, type and credential context. Retain exact evidence and commit native adoption/audit atomically; unavailable or mismatched reads stay pending.
- Add native owner status controls, private client progress and native/browser confirmation regressions. Correct the optional-license legacy fixture and archived documentation link exposed by parent CI. New exact-head acceptance is pending.

## Unreleased — unresolved timeout presentation

- Correct owner/client fulfillment projections to recognize the canonical failed-but-unresolved timeout state. Show uncertainty and no retry control; retain the native regression that exposed the defect.

## Unreleased — native hosting fulfillment screens

- Add owner setup approval, original-response recovery and definite-failure retry preparation on existing agreements. Preserve separate cost/default/account review, exact accepted domain and current authority. Clients see pending versus unknown without private provider data.
- Add native rendered-form and packaged HTTP/Chromium/TLS-fixture cases, including verified receipt, replay, interrupted finalization and no-send recovery. Exact descendant acceptance is pending.

## Unreleased — preserve newer main and repair integration blockers

- Retain main161e3edd licensing/download history alongside tested20i recovery. Repair its schema parse error, optional-storage portal readiness and owner-only licensing authority. Combined acceptance pending; no licensing feature completeness or new release is claimed.
- Verify75 fulfillment/recovery cases in all four native combinations and exact64d Windows packages. Parentf6c full acceptance and downloaded packages are now verified.

## Unreleased — exact retained hosting-result recovery

- Add owner-reviewed local recovery from the original provider ledger after interrupted hosting finalization, preserving exact request/account binding and pending/failed/unknown states. Commit native result and recovery audit atomically; never resend or replace original outcomes. New native and packaged acceptance is pending.

## Unreleased — exact fulfillment packaging correction

- Verify49 new native fulfillment cases across all four supported combinations. Regenerate the installer hash from the exact committed LF service bytes after full packaging correctly rejected local CRLF drift. No release is certified by native checks alone.

## Unreleased — dedicated hosting order execution service

- Bind explicit owner fulfillment approval, current client consent and payment/manual evidence to the existing hosting attempt and provider gateway/ledger. Recheck authority at credential/send boundaries and retain accepted IDs as pending.
- Add native concurrency, replay, reversal, revocation, rollback and recovery cases. No route enables this internal service yet; native approval UI, automated provider configuration verification and exact-result recovery remain required. Exact descendant native and packaged acceptance is pending.

## Unreleased — verified pending provisioning storage

- Verify pending acknowledgement, exact-ID confirmation, replay, rollback and original-history recovery on all four supported PHP/database combinations. Current packaged descendant acceptance remains pending; published version stays1.24.

## Unreleased — pending-history recovery fixture

- Simulate a damaged database restore only within the disposable test, restoring foreign-key enforcement before validating refusal and exact recovery. Keep the real application constraints and original missing-history assertion. First native failure is retained; corrected descendant acceptance is pending.

## Unreleased — accepted hosting provisioning

- Retain the provider's assigned hosting ID as pending, separate from an active service. Preserve the original attempt/result; block duplicate dispatch and refuse absent or different-resource reconciliation.
- Confirm only the exact assigned ID through existing journal/persistence, with regression cases for atomic rollback, replay and missing original history. Local35 pending checks pass in both PHP configurations; native and packaged descendant acceptance remains pending. No provider dispatch or release is enabled.

## Unreleased — verified hosting preparation browser journey

- Corrected provider fixture now passes1652 actual HTTP/Chromium checks; saved order/service identity, private client progress, replay, cancellation and restoration remain verified. Four native combinations pass38 preparation checks within319 combined, plus108 ledger. Exact Windows packages verified. Current full recovery/Apache, independent review and integration/release remain pending; published version stays1.24.

## Unreleased — provider browser fixture history

- Change the stale-provider-context browser fixture through the real settings form so its current revision has retained original history. Preserve the reconciliation-refusal assertion and add history/network checks plus native orphan-revision rollback coverage. Application protections and shipping bytes are unchanged; complete descendant browser acceptance remains pending.

## Unreleased — preparation submission diagnosis

- Preserve the failed Chromium submit assertion and add actual response/screenshot evidence plus native rendered-form roundtrip coverage. Direct native cases pass; the end-to-end form failure is unresolved at this checkpoint. No runtime workaround or publication.

## Unreleased — native hosting preparation and client progress

- Add owner forms to prepare accepted hosting orders with reviewed package/location/numeric limits and current password/confirmation.
- Show client-safe local service progress and retain its history after order cancellation; refuse missing established associations before querying unavailable tables.
- Keep mapping separate from verified provider entitlement and dispatch; document package-type automatic defaults. Add native and extracted-package browser coverage, pending exact-head CI. Parent26ec native timing and association tests passed all4 combinations.

## Unreleased — initial shared-hosting order intake

- Recheck the actual current billing period before initial hosting eligibility, including a paused service with a future scheduled resume.

- Run unchanged complete acceptance for PRs into the isolated 20i integration branch as well as main.

- Add owner-selected agreement/invoice offers and client domain confirmation on existing agreement screens.
- Preserve unique initial obligations, receipt/reversal evidence, expiring manual fulfillment policy, audit history and cancellation without billing/provider side effects.
- Add native matrix cases; first native run caught a recurring-state adapter mismatch, now corrected; corrected source passed72 new native order cases in all four combinations. Further malformed-history/concurrency and browser acceptance is pending. Provider dispatch remains unfinished.

## Unreleased — preserve missing hosting history

- Check every established hosting storage table before reads/setup; lost tables or setup markers with original audit require database restoration instead of empty recreation.
- Preserve fresh/interrupted empty preparation, strict schemas, original histories and every prior test. Add native per-table/whole-family restoration cases and private-path coverage. Current-head acceptance is pending.

## Unreleased — native payment evidence and combined recovery

- Add exact existing-ledger receipt evidence with immutable origin, reversal, client/project/invoice binding and bounded history checks. Keep legacy credits, zero/void totals and sandbox observations separate from verified payment.
- Show authorized staff payment evidence on recurring invoice cards; retain client history and extend native and real receipt/correction browser cases.
- Preserve the first combined Apache cache failure; add full cache diagnostics/capacity to the multi-release fixture while retaining every cache, upgrade and reapplication assertion. Exact-head CI is pending; no release, provider write or deployment.


## Combined20i integration checkpoint — 2026-10-15

Preserve domain/cloud9ab6dc58, commercial/recurring recovery35a564a1 and nameserver deltasf8654252 as merge parents. Keep the newly approved client-portal design from main0b96f3d5. Combine all native/browser/recovery/Apache cases, add internal-path denial for new20i files, and regenerate the shipping inventory. No provider execution permission is widened. Combined CI, review, artifact inspection, order fulfillment and the remaining native controls are pending; this is not a release. [Integration/evidence](docs/TWENTYI_FULFILLMENT_INTEGRATION.md) includes original sources and exact test scope. Billing changelog/history remains in the preserved [35a parent](https://github.com/afterburn25/Cajun_Web/blob/35a564a1a2f6660258ff0e0f2fcf057cb24a07f3/CHANGELOG.md).

## Domain search retained-view fixture repair — 2026-10-15

From099aa2e8 on #136, actual inventory job116492616664 showed an undefined domain-search function while directly rendering the combined provider page. The production router already loads the provider controller. Both retained inventory/upgrade fixtures now use that controller; all earlier cases remain and new assertions prove a combined-page GET creates no tables or settings and leaves populated legacy facts unchanged. Runtime shipping bytes are unchanged.

Native domain search at099 passed54 named cases on all four database/runtime combinations (216 executions), including real concurrent owners. Full099 run38755410755 failed native and skipped browser; its remaining recovery evidence is preserved. Application concurrency now groups by source SHA as well as branch/checkpoint to avoid canceling an earlier candidate when a corrective commit arrives. This descendant's CI is pending, and the packaged domain browser scenarios have not passed yet.

Separate billing35a full38750019637 succeeded (HTTP1,513; recovery275; Apache1,527). Current artifacts/review still remain. Main/release1.24 unchanged; complete20i unfinished. No live provider action or deployment occurred.

## Domain search browser/concurrency checkpoint — 2026-10-15

Parent `3366f51985d61b27626b2328a2f2361924496f81` passed [native domain search workflow38753822132](https://github.com/afterburn25/Cajun_Web/actions/runs/38753822132). All four PHP8.2/8.4 × MariaDB10.11/MySQL8.4 jobs passed **53 named assertions each (212 executions)**, verified by parsing each job's actual named JSON report from its log. Deployment mirror/inventory and documentation checks passed in every job. This is native persistence and actual PHP view rendering, not browser or live-provider acceptance.

This descendant adds an actual extracted-package HTTP/Chromium domain-search suite with the existing loopback HTTPS server, covering explicit preparation, owner configuration, offered suffixes, captured/stale forms, permissions/CSRF, query escaping, canonical requests, checked versus partial results, rate limiting, history, direct-path denial and desktop/mobile captures. It adds two concurrent PHP owner processes sharing the real database/lock and expects one read to proceed. Rejected configuration and query forms now preserve their original profile/policy revisions, requiring a fresh GET after stale context rather than silently rebinding the retry. The corresponding installer hash is regenerated.

Python syntax compilation passes for all three changed drivers; the new native/concurrency/browser cases have **not yet run**. The existing full application workflow is enabled on this feature branch and now includes the native domain test. No long-running billing job was canceled or restarted. Full P0 remains active; these cases cannot substitute for current screenshot inspection, package comparison, independent review, exact-main acceptance and downloaded-release verification.

Continue from `codex/twentyi-domain-search`: inspect current native and full application jobs, fix any product/fixture failure, inspect actual images/reports, then coordinate reviewed integration. Current main c42dd6d7 and released1.24 source7190b582 remain unchanged. Cloud1.25 still needs final artifact inspection, and billing35a564a1 retains its separate full run38750019637. Client/public domain search, periods/pricing, purchase/transfer and wider hosting/VPS/support/SSH remain unfinished. Local writes and shell artifact downloads remain restricted; review-request comments and PR metadata edits were blocked by approval policy. No production call or deployment occurred.

## Native domain search checkpoint — 2026-10-15

Continuing complete 20i P0 from `6d3592a9dc9faeffd8bc86563a40c299066bf098` on `codex/twentyi-domain-search`. The reader workflow [38751661767](https://github.com/afterburn25/Cajun_Web/actions/runs/38751661767) passed on PHP 8.2/8.4; its artifacts have not been independently downloaded in this restricted session. Earlier local parser evidence remains 77 named cases per default/no-ini run.

Added typed owner configuration and lookup forms on the existing 20i page, locally configured extension filtering, immutable request/outcome history, a profile-wide 30-second cooldown and 20/hour limit, and a durable 24-hour pause after a 429 response. Configuration is explicitly enabled by an owner with their current password and bound to the saved connection revision. Routine lookup uses current owner permission and the existing fixed transport; there is no separate execution engine. Stale/revoked access is checked before credentials and after results. Additive schema preparation refuses missing stamped history or unexplained populated tables; preparing or viewing never contacts 20i.

Native MariaDB/MySQL tests and real PHP view rendering cover permissions, stale forms, filtering, failed/partial responses, revocation around networking, audit rollback, incomplete intents, shared limits, backoff and history recovery. The new workflow runs PHP 8.2/8.4 with MariaDB 10.11/MySQL 8.4 and checks deployment hashes/documentation. **This new code has not yet been executed; CI is the next gate.** There are no current HTTP/Chromium, concurrency-worker, independent-review or live-account acceptance claims. Client/public search, periods, verified pricing and purchase/transfer workflows remain unfinished.

This remote checkpoint preserves local work unchanged while filesystem writes and shell networking remain unavailable. Current main c42dd6d7 and published1.24/7190b582 are unchanged. Cloud #127 still needs current screenshots and package comparison before exact-main1.25 release gates. Billing #135 full run38750019637 has passed native, browser and recovery steps and is still running Apache checks; retain its source35a564a1. Review-request comments and PR metadata updates remain blocked by approval policy. The complete owner API source remains a previously requested input.

Next: inspect/fix native domain-search CI, add packaged HTTP/Chromium and concurrent-worker cases, then independent review and integration with cloud/billing. Keep P0 scope active and continue commercial/domain/native hosting/VPS/support/SSH workflows. No production call, charge, mail, SSH or deployment occurred.

## Domain search implementation checkpoint — 2026-10-15

Codex owns complete 20i P0. Branch `codex/twentyi-domain-search` starts from cloud candidate `1d9e372cac7ff9739d339b2544c7c9cfbcee3e33`; it preserves all existing history. Current main remains `c42dd6d70fe57276d75835419db9d6e8e51aaa7f`, and immutable published 1.24 remains source `7190b582a02be8a1daa501db14b5899cfc1eb5d0`.

Implemented the bounded exact-name domain search reader and narrow allowlist in the existing fixed HTTPS transport. The reader accepts documented NDJSON packets in either order and the official console's JSON array example. Only an exact query result with `can: register` and a matching header confirms observed availability. Other names remain unverified suggestions. Invalid, incomplete, conflicting, oversized, unauthorized, rate-limited and failed responses cannot become availability. Secrets and remote error text are excluded from results; no retry, price, reservation or entitlement is inferred. Shipping mirrors and installer inventory accompany the source.

Actual local evidence before this session became read-only: 77 named synthetic assertions pass with PHP's default configuration and again with `-n` (154 executions). The workflow now runs these cases on PHP 8.2 and 8.4. Current remote checks have not yet been observed. There is no native search screen, durable throttle/history, sold-TLD configuration, pricing or checkout in this checkpoint; those remain implementation work. This component is not a completed domain workflow or release.

Cloud #127 candidate full run [38719850550](https://github.com/afterburn25/Cajun_Web/actions/runs/38719850550) passed at synthetic merge `97b0b869a72c527d350c8aa63262dfa01ff23854`, whose tree `24330be901adebc9ae1ab1379ed2312663b03901` equals source 1d9e372c. Its actual current screenshots and Linux/Windows package comparison remain unverified. Preserve 29 focused gates (162 reports / 17,666 named executions) and independent clear review 6189930717. These are cloud evidence, not domain-search tests.

Billing correction #135 is remotely committed at `35a564a1a2f6660258ff0e0f2fcf057cb24a07f3`; 21 focused workflows pass and full acceptance [38750019637](https://github.com/afterburn25/Cajun_Web/actions/runs/38750019637) is running. Its local earlier dirty edits remain preserved and need reconciliation with that remote commit. Review-request comments and PR metadata updates were rejected because this session cannot request approval. Shell filesystem is read-only and its network is restricted; a local artifact-download attempt was denied. No release gate is waived.

Next: finish typed native domain search, durable shared throttling/429 backoff, account/revision-bound history, local sold-TLD filtering and actual native/HTTP tests; then continue domain pricing and purchase eligibility. Verify current cloud images/packages before normal merge and exact-main 1.25 acceptance/publication. Finish current billing acceptance and independent review before integrating dependencies. The owner's complete API reference is still a previously requested external input; do not ask again or stop independent implementation. No provider call, charge, customer mail, SSH or production deployment occurred.

## 1.25 candidate — cloud inventory and explicit client assignments
Local candidate verification: PHP syntax for the original-release fixture, Python compilation of all changed drivers, regenerated canonical installer/bootstrap and documentation checks (170 Markdown files/1,347 links) pass. No current native/browser results are claimed yet; independent review and complete acceptance remain pending.


Retain cloud #127/#128 and normal merges of immutable1.24 plus publication records. Prepare1.25.0 using the actual published1.24 COMPLETE archive (SHA256aa7a84b4c0d1201a4eddf2fe68340b90d4860117ceff4d959d165e6feafa731e;2,591 members). All original hashes/shims are registered; native filesystem and both Apache modes add1.24 while preserving every older baseline. Update staging is `cwp-update-1.25.0`.

Owners explicitly approve an observed cloud/VPS server for an existing client. The existing engine retains exact account/resource context, observations, stale-form checks, client isolation and missing-history recovery. Schema5 preserves package/domain records. Captures start at page top to retain actual sticky navigation. Older c377 evidence (848 native/7,585 packaged checkpoint assertions) does not certify this descendant.

The new original1.24 fixture seeds populated package/domain ownership and one uncertain synthetic label request through the original gateway/ledger. Apply/recover checks preserve original links, observations, approval events, consumed grants and operation attempts. Cloud migration remains an explicit owner HTTP action; recovered requests cannot reach the synthetic network callback. Current native/browser/full recovery, screenshots, independent review, normal merge and exact-main/download gates remain required.

Published1.24 source7190b582 and all16 assets remain immutable. Complete20i orders/payments/native controls/domain/VPS lifecycle/support/SSH remain unfinished. Necessary billing #130/#133 continues separately; unrelated work stays paused. No production operation or deployment occurred.

## Synchronize release entry points after review — 2026-10-15

Review4281622110 on4cbc2375 reproduced stale pending1.24 headers in README, agent coordination, release workflow and inventory owner guide. Update every listed entry point plus current migration instructions to the actual immutable publication, archive earlier coordination and mark historical1.23 sections clearly. No runtime or package bytes change. Starting parent4cbc23758674b29899d235b511b40f0a18cf8503 is “Record verified1.24 publication and current20i continuation”; correctiondf57d0ce is “Synchronize published1.24 across current entry-point guides.” Actual local documentation check passed161 Markdown files/1,330 local links and canonical dependency checks; git diff whitespace check passed. These checks cover documentation/source consistency only, not runtime acceptance, which remains the separately published7190 evidence. Known failure: initial review4281622110 found stale headers and was corrected. Follow-up review4281670745 requested this explicit validation record. Renewed review is pending; continue cloud/commercial20i work.

## Publish and verify first 20i takeover release1.24.0 — 2026-10-15

Starting from exact main7190b582, publish only accepted source after full9134/44reports,30 gates, independent reviews, inspected browser screens and identical Windows/Linux packages. Download and verify all16 draft/public assets and unchanged prior1.23 assets. This documentation checkpoint records actual publication; no runtime/build/test change or deployment. Archive prior summaries, update current release/requirements/ownership and preserve histories. Next: cloud1.25 from the real1.24 baseline; correct recurring lost-binding review4281512902 and continue full commercial/native20i workflows.

## Correct 1.24 download and recovery instructions — 2026-10-15

Starting from normally merged `8b44a7ad5e568481d8b016713e7d3958d19e41f3`, correct START-HERE's obsolete 1.23 folder and migration guide's obsolete 1.18 paths. Both now name the actual `cwp-update-1.24.0` staging directory, `structure-1.24.0` recovery journal and retained starting packages through 1.23.0. The shared `structure-1.11.0/mutex.php` remains unchanged. Current Codex 20i ownership supersedes the guide's old Devin allocation. No application, migration, build, fixture or workflow code changes.

The exact 5fa5998d runtime candidate has 9,134 named full-application assertions and clear independent review; its tree-identical merged 8b44a7ad main has 29 verified focused workflows (158 reports / 17,034 executions), clean Windows packages and inspected browser evidence. Full main run [38707126756](https://github.com/afterburn25/Cajun_Web/actions/runs/38707126756) continues without cancellation. This documentation correction still requires independent review and exact final-source package/instruction verification. Before publication, verify the final main and downloaded assets; previous main evidence must retain its original SHA. Nothing is published or deployed by this correction. Cloud #127 and commercial dependencies #130 remain separate P0 work; all other development remains paused.

## Fresh owner inventory correction before 1.24 publication — 2026-10-15

Codex retains complete 20i P0 ownership. Starting from normally merged main `5dd2a3e1a7ddb3b63bf9bf98c4da3ac1e13edafc`, this branch fixes a fresh-owner provider page warning discovered by cloud #127 checkpoint [38701151657](https://github.com/afterburn25/Cajun_Web/actions/runs/38701151657). The exact diagnostic was an array-offset-on-null warning in `admin/views/providers.php:71`: fresh installation prepares inventory storage but has no saved provider profile. The shared package view read the missing profile's enabled field. This defect also exists on current main, so 1.24 publication is withheld even though the earlier candidate passed.

The inventory section now handles the missing-profile state first and directs the owner to save the connection. It performs no inventory history query or profile-dependent action before that profile exists. Two actual packaged assertions verify the fresh schema stamp and the fresh owner's setup page; the existing final empty-PHP-diagnostics gate must remain. Generated installer inventory is refreshed. No schema, provider operation, credentials, client data or design change is required.

The prior 906df727 candidate has 9,132 verified assertions and clear review; exact-main 5dd2a3e1 run38699707455 remains running and is preserved as historical evidence, not publication approval for the newly discovered path. All 29 focused main workflows passed, with 158 downloaded named reports / 17,034 executions. Actual main screens were inspected. Corrected candidate review, full candidate acceptance, normal merge, exact corrected main and downloaded release gates are required. Native cloud assignments #128 pass 848 assertions; its browser run is preserved and receives this correction afterward. No 1.24 tag, release or deployment has been created. Continue the remaining 20i scope while these gates run.

## 1.24 candidate: packaged checkpoint verified — 2026-10-15

Codex owns the complete 20i integration as P0. The active branch is `codex/twentyi-complete`, [PR #126](https://github.com/afterburn25/Cajun_Web/pull/126), from main `68e4d411acedd9f73cf048018ba570ace9b673be`. Published [v1.23.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.23.0) remains unchanged. This checkpoint starts at `438a3122d3a23bdbd58825ee2da2daec902f351f`; the enclosing commit identifies the corrected candidate. No merge, release or deployment is claimed.

The first increment delivers protected package refresh, explicit client package/domain links, moved-domain and credential-context isolation, populated inventory upgrades, typed label approvals, durable operation history and read-only reconciliation. It retains the original Devin histories and hosting recovery foundations. Commercial order/service workflows, native controls, domain commerce, VPS/cloud and interactive SSH remain active implementation work after this increment.

[Exact 438a3122 checkpoint](https://github.com/afterburn25/Cajun_Web/actions/runs/38692573913) passes 42 downloaded named reports / 7,474 executions, including 1,371 HTTP/Chromium assertions. All 12 generated distribution files match the local Windows build byte for byte; ZIP integrity and member counts are verified (FULL 5,246; COMPLETE 2,591; UPDATE 2,578). This checkpoint deliberately skipped extended recovery and Apache, so it is not release acceptance. The actual refresh diagnostic reports a complete observation with unknown collection coverage and no fixture errors. Desktop/mobile owner/client moved-domain and operation screenshots were inspected. They exposed inaccurate client wording: a moved domain was described as absent. This commit changes it to an unconfirmed approved hosting link and strengthens the existing HTTP assertion without disclosing the new package or another client name.

[Strict native operation matrix](https://github.com/afterburn25/Cajun_Web/actions/runs/38692254706) at 2d2892c9 passes 784 named executions on PHP 8.2/8.4 and MariaDB 10.11/MySQL 8.4. Shipping code matches 438a3122. [Independent review of 438a3122](https://github.com/afterburn25/Cajun_Web/pull/126#issuecomment-6186069993) found no major issues; the new wording correction will receive a follow-up review. Earlier failed fixture/diagnostic runs are preserved in GitHub and local evidence and are not counted as acceptance.

Next: run complete application acceptance on this stable corrected candidate, inspect final reports and corrected screenshots, run exact candidate focused PHP/database gates, and resolve findings. Then merge normally, verify exact main and downloaded draft/release assets, and publish the first tested increment. Preserve all prior baselines, including the checksum-verified published 1.23 package. Continue the remaining 20i platform immediately afterward. Full owner API-reference intake still needs the original accessible file/link; public primary documentation and independent engineering continue. No new live provider call, payment, customer mail or SSH session ran.

## Browser diagnostic correction — 2026-10-15

Candidate2d2892c9 remains unreleased. Prior checkpoint6d9391a completed independent review with no major findings ([review6185823734](https://github.com/afterburn25/Cajun_Web/pull/126#issuecomment-6185823734)). Its native/build stages passed, but browser run38691215693 stopped after1,127 passing named assertions at a test diagnostic NameError: json was not imported. This checkpoint adds the missing import; no shipping code is changed. The failed report is retained and is not browser acceptance. The stricter candidate schema matrix38692254706 is still running.

The earlier b1 full run38688509276 finished with1,271 Apache and261 filesystem-recovery assertions passing. Its browser fixture failure remains recorded. These older results cannot certify1.24. Next: rerun the bounded candidate browser checkpoint, inspect actual upgrade/moved-domain/operation screenshots and reports, resolve any finding, then exact candidate full acceptance and final review before main/release verification. All remaining20i platform work stays active.

## 1.24.0 candidate — 20i inventory, ownership and operation recovery

This is an unreleased candidate on codex/twentyi-complete / PR126, starting from6d9391a. Published1.23.0 and main68e4d41 remain unchanged. The first increment provides protected package refresh, explicit package/domain client links, moved-domain and credential-context isolation, populated inventory upgrades, typed package-label approvals, durable operation history and read-only reconciliation. It retains inherited hosting lifecycle/recovery foundations. It does not complete commercial provisioning, native hosting controls, domains, VPS/cloud or SSH; those remain the active P0 roadmap after this increment.

Review corrections776cab39 pass1,372 inspected named executions: four PHP8.2/8.4 × MariaDB10.11/MySQL8.4 jobs (90 ledger/recovery +104 execution assertions each) and four default/no-ini contract reports (149 each). The exact-package checkpoint38691215693 is running at6d9391a; that source has completed external review with no new inline findings observed. Candidate release acceptance has not run yet. The older b1 full run38688509276 retains its browser fixture failure and ongoing Apache evidence.

Candidate changes add exact Extra/default validation for operation columns, with an invisible auto-increment regression that preserves populated rows. Version labels advance together. The immutable published1.23 COMPLETE archive is checksum-verified (057a58c42e847fe68454c1a7dac47afad10c8c05f46de4301842654b356f7228); all2,566 member hashes and original compatibility-shim hashes are registered for updates. Native filesystem and both Apache modes add1.23 as the first baseline while retaining every older baseline. Existing populated drafts, submission fences, mail/provider keys and other records retain their original preservation checks.

Next gates: inspect the current packaged checkpoint/screenshots, correct any failure, verify the stricter schema case on every supported database/runtime, then run exact1.24 candidate full acceptance and independent review. Only afterward merge normally, test exact main, and verify all draft/downloaded release assets before publication. No release, live provider request, payment, message or deployment is claimed. Continue the remaining complete20i scope after the first release.

## Review corrections and preserved acceptance — 2026-10-15

Starting from b1b0e84, independent review of PR126 reported two P1 defects: generic approval could bypass commercial/message consent, and a missing stamped operation table could be recreated empty, forgetting request history. Both are corrected in this checkpoint. Generic approval and execution allow only the explicit package-label subset; chargeable, destructive, mail and plan requests require their dedicated workflows. An old high-risk grant is refused before consumption, DNS or ledger access. The admin screen now uses package-ID/label fields and hides sending controls on ineligible historical approvals.

Operation migration preflights both original tables for every stamped version 1–4 before any DDL. Readiness fails safely when either is missing; recovery requires the original database. Native regressions rename populated tables reversibly, preserve every ledger/grant/audit row and schema stamp, and verify duplicate suppression after restoration. Existing authority/race/replay cases now dispatch label-only requests; creation acknowledgement tests use the actual lower-level gateway/ledger and explicitly historical consumed grants for read-only reconciliation. No test-only production consent bypass was added.

The exact b1b0e84 focused matrices were downloaded and inspected: 4,456 named executions pass across operation contracts, native operations, hosting and inventory PHP/database combinations. The provider-contract jobs also passed but expose no named artifact and are excluded from that count. The broad b1 run38688509276 passed its native and structure-recovery stages, then its browser stage failed on a malformed package fixture whose primary name was absent from its names array. The fixture is corrected without weakening validation. Its Apache work remains running and is preserved; no full application success is claimed.

Local default/no-ini PHP passes 149 named boundary checks each; changed PHP syntax, Python syntax and generated-mirror checks pass. This new shipping head still needs its native matrix and packaged browser evidence. A manually dispatched checkpoint_only option on the existing application workflow reuses the exact build/native/browser stages without restarting the older extended recovery run; it explicitly cannot certify a release. Follow-up independent review and exact full application/main/release-asset verification remain release gates.

Next: inspect the new matrix and browser artifacts, fix findings, finish stable integrated acceptance and publish the first tested increment, then continue required commercial order/service and native control workflows. Codex retains P0 ownership; main68e4d41 and publishedv1.23.0 are unchanged. No provider write, payment, customer mail or deployment ran. Full owner reference intake remains pending the accessible file/link; independent engineering continues.

## Combined inherited work and write-semantics correction — 2026-10-15

Codex retains complete 20i P0 ownership in [PR126](https://github.com/afterburn25/Cajun_Web/pull/126). Main68e4d41 and publishedv1.23.0 remain unchanged; no release or deployment is claimed.

Downloaded/verified [native run38686612548](https://github.com/afterburn25/Cajun_Web/actions/runs/38686612548), exact962cc3e: all four PHP8.2/8.4 × MariaDB10.11/MySQL8.4 jobs pass, with98 inventory plus38 upgrade/render checks each (544 named executions). The upgrade helper used the same assertion label for its two version-specific audit failures; both ran, and this checkpoint labels them separately. This evidence predates the combined shipping changes below; it does not certify this new descendant. Original d2f59fb full application run38686331742 hit the retained quota fixture failure; its recovery/Apache work was left running for evidence, not cancelled.

Normal merge5b115f0 retains resources40ad600 (including #94's identity descriptors), with139 local pure inventory checks passing. Normal merge08b90c8 retains hosting#87–#89 at1c92e6b, adding explicit deletion retry and durable reconciliation storage. Current ownership/status text won documentation conflicts; the incoming history and dedicated architecture/recovery guides remain intact. Regenerated inventory resolves the generated-file conflicts. These inherited components still require combined native/package acceptance; they are not completed hosting workflows.

This checkpoint repairs the inherited blanket HTTP-success classifier. Documented addWeb responses retain the allocated result ID as accepted_pending, separate from verified active service. Empty, malformed, false/null, mixed per-resource maps and unverified envelopes stay uncertain.408/409/425 are ambiguous rather than definite failures. No write, including mixed billing/deletion updatePackage, is blanket retry-safe. The ledger replays pending acknowledgements with their original IDs and one attempt. Reconciliation can observe the acknowledged ID, but a domain-name match alone cannot prove creation. Old results and attempts are preserved.

updatePackage now requires ID arrays and bounded per-package maps restricted to the approved update IDs; overlapping update/delete IDs and scalar legacy approvals are refused. deleteWeb builds the explicitly documented delete-id array. Existing approved scopes are revalidated before consumption/dispatch. This corrects the documented outer request contract; nested field semantics and the remaining native forms are still tracked implementation work. No live provider write ran. Local default/no-ini PHP passes139 named synthetic operation checks each. Native four-combination operation/hosting matrices and exact combined browser/recovery acceptance are next.

The complete owner upload is still being located; the selected repository summary is not a substitute. The owner was asked for its accessible path/link while independent work continues. Required native controls, registrar commerce, VPS/cloud, SSH, catalog/order/payment connections and release verification remain unfinished in the requirements checklist.

Next: inspect native matrix artifacts; fix any failures; run the stable combined packaged/browser/Apache candidate and obtain independent review. Then publish a verified incremental release and continue the remaining20i scope. Do not resume mail or other unrelated feature work.

## Focused CI fixture correction — 2026-10-15

Takeover [PR #126](https://github.com/afterburn25/Cajun_Web/pull/126) is open at first implementation `d2f59fba32b98153119b33072647cccdbb25696d`. Downloaded push run [38686268179](https://github.com/afterburn25/Cajun_Web/actions/runs/38686268179) fails: MariaDB passes 74 assertions (including the repaired revocation check) then the longer fixture reaches the real hourly quota before its audit scenario; MySQL reaches the audit-injection trigger but binary logging requires test-server configuration (error1419). Failed reports remain retained. These are fixture constraints, not relaxed production requirements.

This test-only correction explicitly fills/tests the 20/hour refusal and verifies no intent/network use, then ages only isolated fixture timestamps for the next scenario while retaining rows. The disposable MySQL CI service permits synthetic fault-injection triggers; application-user privilege and production code are unchanged. Local38-case upgrade/render evidence remains scoped to the same shipping code. Current native matrices and full acceptance still require verification. Preserve the original application run38686331742; use a focused manual run for this iteration instead of restarting its long package/recovery work. The commit deliberately skips automatic CI and dispatches the native matrix explicitly, not a release check bypass.

Next: inspect all focused results/logs, correct remaining failures, reconcile resource/hosting descendants, and run complete stable-candidate acceptance/review before release. Codex P0 ownership and v1.23 baseline remain unchanged.

## 2026-10-15 — Codex P0 takeover and first inventory repairs

Owner transferred the complete 20i lane to Codex and paused unrelated development. From main68e4d41, preserved #122/#115 via normal merges through9744110; repaired old-schema readiness, generated-column validation, moved-domain/context display, sibling-name disclosure and two fixture counters. Added genuine populated/interrupted/repeated upgrade and isolation coverage;38 local PHP8.4/MariaDB checks pass. Expanded current PHP/database and packaged HTTP acceptance is pending. Full release review and later hosting/domain/VPS/SSH/billing implementation continue. [Scope, ancestry and evidence](docs/TWENTYI_CODEX_TAKEOVER.md). v1.23 stays unchanged; no live action.

## Unreleased — 20i domain ownership links (devin/twentyi-domain-links)

Owner-approved domain→client mappings over the observed inventory: `provider_domain_links`/`provider_domain_link_events` (provider-inventory schema v3) bind `(profile_id, domain)` to a client with the carrying package, credential-context fingerprint and approved revision. Linking requires the `(package_id, domain)` pair in the last good observation under the exact current revision — the reseller case of several clients' domains on one package. Admin gains per-domain link/unlink/re-approve controls with a stale-domain section; the client portal surfaces linked domains with honest absence markers. Additive migration preserves v1/v2 data. `domains.renew` also joined the buildable write catalog (documented `name`/`years`/`renewPrivacy`); `addDomain`/`transferDomain`/`nameservers`/`dns` are catalogued shape-incomplete and refused.

## Unreleased — 20i write execution path (devin/twentyi-execution, devin/twentyi-reconcile)

- `provider_operations_execute` performs one owner-approved provider write: owner password + current profile revision + active exact-scope grant required before any credential is opened or an endpoint address resolved; undocumented or shape-incomplete operations refuse before any ledger claim; missing grants refuse `no_grant` and are audited.
- `TwentyIOperationAdapter` (foundation + site mirror) implements `ProviderAdapter` over the closed documented catalog — supports only shape-complete operations, builds the allowlisted bounded request, attaches `Authorization` at send time only, classifies outcomes conservatively (`uncertain` for 3xx/429/5xx, other non-terminal 2xx and post-send transport faults; `failed` for provably pre-send failures).
- Execution runs through `ProviderGateway` over the durable `ProviderOperationLedger` — atomic idempotency claim before send, replay/conflict/in-progress/stale-uncertain semantics, durable record required. Owner access is re-verified at send and after; access changes around a possibly-landed write surface `uncertain` for reconciliation.
- Native MariaDB suite `tests/provider_operations_execute_native.php` covers authority gates, grant scope/expiry/revocation, exactly-once send, replay/conflict without re-sending, uncertain propagation and a durable secret scan — scripted transport only, no provider call. Live sends remain gated on operation-specific acceptance.

## Unreleased — 20i inventory slice binding corrections (devin/twentyi-refresh)

Links to observed 20i packages are now bound to the exact profile revision and the credential context approved at link time. Client service views suppress data when the provider settings change until the owner explicitly re-approves the mapping under a current observation; an identical package id under a replaced key can never flow through an old link. Observations carry an installation-bound credential fingerprint (never key material); schema version 2 upgrades existing tables in place. Native MariaDB suite: 77 assertions on PHP 8.2/8.4.

## 1.23.0 — published and verified

Client account edits reject stale versions and retain safely escaped unsaved details; client/invitation writes recheck current authority and audit atomically. Invitations are single-use and unexpired, and client administration is refused during support sessions. Reviewed internal hosting records, dispatch fences and local provisioning retry remain inactive groundwork. Exact-main 8,360 assertions and 25 workflow gates pass; all 16 draft/public assets are downloaded and verified, with prior 1.22 unchanged. [Release](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.23.0); [report](docs/TEST_REPORT_1.23.0.md). Complete 20i implementation remains assigned to Devin; service agreements and later hosting reconciliation remain separate increments.


## Unreleased — 20i write boundary (descriptive)

Closed package-lifecycle write descriptors (`packages.create`/`update` buildable; `userstatus`/`delete` refused pending documented fields), allowlisted request construction, conservative success/failed/uncertain classification and named refusal surface for domains/VPS/SSH/billing/email. Descriptive only — the adapter still refuses every operation; no credential, transport, ledger or provider call. 81 synthetic checks on PHP 8.2/8.4. [Contract](docs/TWENTYI_OPERATIONS_CONTRACT.md).
## Unreleased — provider operation ledger and owner grants

Durable at-most-once operation ledger (`provider_operations`) implementing the shared OperationLedger contract — claim/replay/conflict/uncertain semantics — plus owner-password grant records bound to exact canonical operation scopes with expiry, revocation and audit. Fresh installs create both tables; existing installs run the owner migration. Persistence only: no credential, request, transport or provider call. [Guide](docs/PROVIDER_OPERATIONS.md).

## Development coordination — acknowledged full 20i ownership

Reconcile the ownership handoff with merged client/hosting groundwork main `4fdb562`, preserving both histories. Record Devin's explicit acceptance and first reader PR #99. No runtime or release asset changes relative to main.

## Development coordination — complete 20i ownership

Transfer the complete 20i implementation to Devin, including application persistence and admin/client portal screens. Record the full requirements, bounded workstreams and shared-file ownership; Codex retains non-20i work and final review/release coordination. Documentation only; no application, version, installer or published-package change.

## 1.23.0 candidate — current client authority and hosting groundwork

Unreleased. Preserve reviewed client conflict/invitation/support safeguards and internal hosting journal/storage/dispatch/local-retry groundwork. Align application/installer/update labels; add actual published1.22 baseline and retained-submission update/recovery/replay cases. Prior combineda9 acceptance passes8,234 checks/all21workflows; this newer candidate still needs its own full acceptance and exact-main release gates. [Candidate guide](docs/CLIENT_ACCOUNT_SAFEGUARDS.md).

## Unreleased combined hosting/client integration

Preserve mail main1.22 while combining guarded client administration and reviewed hosting journal/storage/dispatch/local-retry foundations. No new hosting UI or remote execution is activated. Add all three hosting native suites and12 internal-path checks to combined full acceptance; preserve every existing test and upgrade baseline. This branch is not a published release.
## Unreleased — read-only 20i inventory contract

- `foundation/twentyi-inventory/`: closed documented GET descriptors (packages list/detail/types, balance, VPS/managed-VPS/domain lists, service-change data), allowlist package projection, identity-only resource records for schema-less lists, honest complete/partial/unavailable snapshots with caller-bound scope and unknown coverage, an evidence-graded capability register and an enumerable exclusion list (credential-bearing and GET-mutating endpoints refused). Contract-only; no live calls, persistence, routes or client linking.

## Unreleased — 20i inventory application slice (`devin/twentyi-refresh`)

- Owner-triggered provider inventory: durable refresh intent + bounded result rows, verbatim validated `provider_observation_packages`, explicit owner→client `provider_package_links` against `studio_clients`, and ordered `provider_package_link_events` (auto-increment id for insertion-stable ordering).
- Refresh orchestration mirrors the connection-check protocol: staff-locked intent, private-DNS refusal, mid-flight authority rechecks downgrading to `access_changed`, lazy secret resolution at the transport boundary, cooldown, and last-good preservation on unavailable refreshes.
- Admin provider-settings section (prepare/refresh/link/unlink/history) and portal `?services` view showing only explicitly linked services. `staff_action_permission` maps `provider_inventory_*` to `staff.manage`.
- `tests/provider_inventory_native.php` + `provider-inventory.yml`: 56 native MariaDB assertions on PHP 8.2/8.4 — authorization refusals, revision checks, cooldown, resolver/transport classification, complete/partial/unavailable persistence, link restrictions, duplicate rejection, client isolation, unlink events. Synthetic fixtures only; no live call, real credential or mutation.

## 1.22.0 — verified explicit saved-email sending

- Explicit saved-message review, unchecked confirmation and Send; private author history, separate To/Cc/Bcc, current mailbox/Send grants and exact revisions.
- Deterministic maintained MIME and bounded verified SMTP/TLS, durable one-time dispatch records, truthful accepted/rejected/not-submitted/unknown results. No automatic resend after accepted, unknown or unresolved outcomes; result-storage failure preserves uncertainty.
- Additive owner setup and fresh installation; all24 starting packages through1.21.0 accepted. Actual1.21 update/recovery retains drafts, submission records, prior encrypted history and original keys without resend.
- Published/download-verified `e3b434ffb2244e454ea9f24fa1de9173b7479ccc` after7,854 exact-main assertions and all21 workflows. All16 assets, archive members/checksums/tag and Windows/Linux bytes verified. Actual desktop/mobile views inspected; homepage pixels equal1.21.0. No live customer mail or deployment.

## Unreleased — complete retained application acceptance

Allow75 minutes for the existing full mail/upgrade/Apache suite after verified45m cancellation. Preserve incomplete Apache progress checkpoints without weakening assertions. Runtime/version/installer unchanged; replacement acceptance pending.

## Historical1.22.0 candidate preparation

Explicit saved-message review/Send and private results; latest1.21 package/recovery baseline prepared. [Sending guide](docs/SENDING_EMAIL.md). Full acceptance and independent review remain pending.

## Unreleased — explicit saved-draft Send integration

Connect private review/Send/history with exact current authority and additive/fresh setup. Prepare actual HTTP/Chromium/SMTP and Apache acceptance; validation is pending. Published1.21.0 remains immutable.

## Unreleased — combined submission acceptance preparation

- Add reusable isolated SMTP server and a dedicated MariaDB/TLS matrix for the default coordinator, exact saved MIME, conservative outcomes, strict audit failure and no-resend behavior. Downloaded38535733829 at48060cf passes471 combined checks per PHP8.2/8.4 runtime. No shipping runtime or route activation.

## Unreleased — private Send/history screen preparation

- Prepare guarded saved-message review with explicit confirmation and current-authorized private submission history. Preserve stale form identities/revisions and prevent existing attempts from displaying another Send action.
- Downloaded c5646bf matrix passes85 storage and81 orchestration/read-model native checks per PHP8.2/8.4 runtime, plus58 form/template checks in each default/no-ini mode (564 executions). Actual UI acceptance remains pending. No router/action/navigation/fresh-schema activation or new release version.

## Unreleased — server-only email submission orchestration

- Connect immutable preparation, full DNS validation, current authority, audited dispatch fences and bounded SMTP through an internal coordinator. Stable replay performs no further work; uncertain delivery cannot retry automatically. No route or Send UI is activated.
- Storage85 checks verified per PHP8.2/8.4 at4d68e6e. All85 storage plus68 orchestration/audit/permission/concurrency checks pass on each PHP8.2/8.4 runtime at e5849ca (downloaded38533629082).

## Unreleased — unwired submission persistence

- Add guarded additive immutable intent/dispatch/result storage with current author/Send/revision gates, stable replay, strict audit and exact private-content/attempt binding. Prepare MariaDB concurrency/native evidence; not yet verified.
- Preserve canonical submission dependency bytes in the deployment mirror and refresh installer inventory. No route, fresh-schema activation, DNS/credential orchestration, Send UI or new version.

## Unreleased — isolated SMTP submission

Immutable MIME, maintained bounded SMTP and pinned TLS are verified at2edeec1 through26 downloaded PHP8.2/8.4 reports. Durable current-authorized application persistence and Send UI remain to implement; see PR77. The released1.21 draft workflow is separate.

### Historical SMTP preparation — durable submission contract

- Specify atomic intent/dispatch/result persistence and exact content/attempt binding before application implementation; accepted server outcomes remain recorded even if later browser access expires. Documentation only.

### Historical SMTP preparation — isolated SMTP TLS channel

- Added public-destination pinning, verified original-host TLS and finite I/O for submission.398 actual local loopback TLS checks pass; expanded PHP8.2/8.4 network-isolated matrix pending. No deployable version or application exposure.

### Historical SMTP preparation — isolated maintained SMTP session

- Require all envelope recipients before DATA; bind finite results to the exact attempt/private snapshot; preserve unknown final outcomes without retry.80 local synthetic checks pass per PHP mode; real TLS and application integration remain pending. No deployable version change.

### Historical SMTP preparation — preparation evidence

- Verified all14 PHP8.2/8.4 preparation reports at bead26f, including exact long Unicode decoding on the runner. Reconciled accepted draft main7805e56 without deploying submission code. Draft1.21 release gates remain running.

### Historical SMTP preparation — maintained offline MIME preparation

Fix initial expanded-matrix long-subject folding compatibility without weakening parser equality.29 local MIME/168 independent parser checks now cover14 cases including emoji/combining characters on Python3.10/3.12/3.14; replacement matrix pending.

Pin/reproduce original PHPMailer7.1.1 source/license; generate bounded immutable plaintext MIME with Bcc privacy, literal Unicode/space-sensitive subjects, fixed UTC dates and exact message identity.25 local MIME plus144 independent parser checks per default/no-ini mode pass; missing-function refusal passes. Expanded matrix/review pending. No SMTP, persistence, application integration or sending.

### Historical SMTP preparation — isolated immutable submission preparation

Downloaded replacement38525211291 at59bb4bf verifies52 named preparation checks in all four PHP8.2/8.4 default/no-ini cells. No MIME, SMTP or application acceptance is implied.

Correct the isolated CI mountpoint before container startup; initial38524946551 ran no PHP. Replacement matrix pending.

Add a canonical reference-free private submission snapshot, selected sender/recipient validation, stable Message-ID and full content/revision fingerprint.52 local preparation checks pass default/no-ini; matrix pending. No maintained MIME/SMTP, credentials, persistence, site/runtime integration or sending yet.

### Historical SMTP preparation — submission contract preparation

Record the next explicit-send boundary on a separate branch: maintained protocol/MIME, immutable reviewed snapshots, all-recipient pre-DATA acceptance, durable intent and attempt-bound unknown outcomes. Documentation only; no new application version, transport, UI or message sent.

## Unreleased correction — client support context

Return an explicit403 client-administration page without edit or invitation forms when support is selected. Retain mutation denial and add separate actual HTTP checks for GET/POST rendering and unchanged records. First packaged run was incomplete; corrected acceptance is pending.

## Unreleased — client authority integration

Serialize existing client account edits, invitation creation and public activation with staff/hosting authorization changes. Refuse stale client forms; preserve escaped unsaved details. Retain delegated access, rotate revoked sessions, consume invitations once and make audit failure atomic. Add native concurrent-process and actual packaged form/browser checks. No release or provider activation claimed; [contract](docs/CLIENT_AUTHORITY.md).

## Unreleased — explicit local provisioning retry

Add a current-owner/password retry-preparation action for recorded definite provisioning failures explicitly marked retryable. Retain immutable failed observations, exact request replay, strict atomic audit and new-attempt fencing only through a subsequent authorized claim. Unknown outcomes, unsafe failures and deletion/reconciliation remain blocked. New native fixtures await Linux CI; no release or provider activation. [Contract](docs/HOSTING_PROVISION_RETRY.md).

## Unreleased — durable hosting attempt/result storage

Add current-owner exact attempt/profile fences, bound internal results, immutable unique provider-resource associations and strict atomic audit. Stable replay grants no repeat dispatch. Atb7e9f03, downloaded38545626740 passes79 operation +94 retained local-record checks per PHP8.2/8.4. Independent review and application acceptance remain pending. No transport, route, worker or release activation. [Contract](docs/HOSTING_OPERATIONS.md).


## Unreleased — local hosting records

Add owner-authorized additive MariaDB journal/event storage, exact client/terms/revision checks, stable replay and strict atomic audit. Native PHP8.2/8.4 each pass94 checks, including competing processes and damaged history, atf8001e9 in38543267301/38543364218. No remote operation, route or fresh-install activation; independent review and application acceptance remain pending. [Contract](docs/HOSTING_RECORDS.md).


## Unreleased — hosting lifecycle journal

Add bounded immutable canonical JSON command reconstruction outside deployment. Preserve lifecycle/attempt invariants across process restoration.148 local controls pass per default/no-ini PHP8.4 mode; matrix/review and durable application adapter pending.

## Unreleased — canonical hosting model integration

- Preserve Devin PR60 hosting model/history on current main: packages, account lifecycle, exact-attempt observations, plan changes and explicit panel grants. No deployment/provider/persistence/UI activation.
- Correct final-LF SKU acceptance and malformed primary-domain labels before state/history mutation. Local168 supplied +21 retained review +23 input checks pass per default/no-ini mode; expanded PHP8.2/8.4 CI pending.

## 1.21.0 — verified private local email drafts

- Private author/mailbox drafts with bounded To/Cc/Bcc and plaintext, canonical active-template insertion, escaped preview and retained discard/history. No SMTP sending or server Drafts synchronization.
- Current Send/grant/account/author gates, optimistic revisions, stable create replay and strict metadata audit. Sender changes require confirmation; concurrent discard preserves a separate escaped copy of unsaved text.
- Additive owner setup and fresh installation; actual1.20.0 update/recovery/reapplication retains existing records, prior provider history, added drafts and both original private keys.23 starting versions supported.
- Published/download-verified `7805e56aa9e53cb081600589e92991255475474c` after 6912 named exact-main application checks and PHP8.2/8.4 matrices. All16 assets, ZIP members, checksums/tag and Windows/Linux bytes verified. Actual desktop/mobile views inspected; homepage pixels equal1.20.0.


## 1.20.0 — verified owner 20i connection

- Protected owner profile with private AES-GCM credentials, password/CSRF/revision safeguards and retained settings history. Fresh setup creates empty ready storage; upgraded owners explicitly prepare it.
- Fixed explicit package-list GET with public DNS validation, pinned verified TLS, bounded parsing and transfer, no proxy/redirect/retry. Strict intent audit precedes dispatch; terminal results remain finite and historical or incomplete outcomes stay visible. Saving/viewing never contacts the provider.
- Published/download-verified source `6f0b0ce5a9cba68b6859334818383fcc09e2ba3e` after 6593 named exact-main application checks plus PHP 8.2/8.4 matrices. All 16 assets/tag, member hashes and Windows/Linux bytes verified. Actual desktop/mobile settings screens inspected; homepage pixels equal 1.19.0.
- Twenty-two starting versions supported; actual 1.19.0 recovery/reapplication preserves existing records, provider history and both original encryption keys. No live-account adapter or target-host acceptance is claimed. Other provider operations remain unavailable.

### Earlier 1.20 development records — historical scope

### Historical — exact-byte installer inventory correction

Regenerate owner UI dependency hashes from the committed LF source bytes after CI rejected Windows-normalized development hashes. No application behavior change.

### Historical preparation for 1.20.0 — owner 20i connection screen

Wire protected owner settings, explicit fixed connection checks, finite/history displays and fresh/additive setup. Add actual packaged HTTP/Chromium against isolated HTTPS. Combined application acceptance and release remain pending.

### Historical — audited explicit provider-check preparation

Add unwired current-owner fixed-check orchestration, finite retained outcomes, request limits and interrupted-result handling; reuse canonical provider mirrors and add native process fixtures. Application integration remains pending.

### Historical — owner provider storage preparation

Add unwired owner-only encrypted 20i profile and retained metadata history, exact additive migration, native fixtures/workflow and provider-key package exclusion. Native acceptance and application integration remain pending.

### Historical — isolated transport runner repair

Create writable output mountpoints before mounting the source read-only. This fixes a pre-test Docker startup failure; no application or transport behavior changes.

### Historical preparation for 1.20.0 — isolated 20i HTTPS transport

- Fixed destination/pinning/TLS and proxy/redirect refusal, finite errors, bounded streamed headers/body and transfer deadlines, with 333 local actual synthetic HTTPS checks and request/DNS policy evidence. No admin application integration or real API operation; matrix/review pending.

### Historical — isolated fixed 20i connection probe

- Added a fixed read-only package-list authentication adapter, finite classifications and explicit refusal of all general provider operations, with 120 synthetic checks. No real transport or application integration; no release version reserved.

### Historical provider contract review

- Recorded the 163-check canonical review and reproduced JSON shape defect; narrowed first integration to protected settings and explicit read-only checks. Inventory needs separately verified response fields and ownership acceptance. Documentation only; no feature or release claimed.

### Historical — provider settings contract preparation

Claim an isolated owner20i settings/read-only check contract after the1.19 email increment. Documentation only; no API call, application feature, release or deployment.

### Historical — HC-07 provider foundation (Devin, isolated component)

- Finite probe classification per the [owner 20i settings contract](https://github.com/afterburn25/Cajun_Web/blob/codex/provider-settings/docs/PROVIDER_SETTINGS_CONTRACT.md): `ConnectionCheck` gains an `unknown` state for post-send ambiguity (timeout/protocol/lost result/unaccepted 2xx shape) — never reported as a pass or a clean failure; `unreachable` is reserved for faults demonstrably before send. Check details are bounded printable classifications (≤200 bytes, no control characters), and `probe()`/`HttpTransport` docblocks now state the pinned-endpoint, strict-shape, no-redirect/proxy, DNS-validation and byte-cap invariants adapters and transports must honor. Codex review follow-up: the fixture probe decodes with JSON object/list identity preserved — objects (incl. `{}`/`{"0":…}`), scalars, null, malformed/trailing and over-depth bodies classify `unknown`, never `verified`; validation wording says bytes, not chars. All 171 checks pass locally on PHP 8.4 with/without php.ini. No shipping integration.

- Codex contribution from reviewed provider head 1e42402: reject failed JSON map-key encoding before operation claim, with nested malformed UTF-8, zero side-effect and valid Unicode/replay regressions. All 150 checks pass locally on PHP 8.4 with/without php.ini; matrix and review pending. No shipping integration.

- Added canonical `foundation/provider/` contract classes (adapter interface + capability matrix, lazy secret-resolving credentials, HTTPS-only transport boundary, operation/idempotency-key model, durable ledger interface, enforcing gateway, and external-resource reconciliation) plus `docs/PROVIDER_FOUNDATION.md`.
- Corrected the ledger/execution contract per the HC-07 correction pass: `OperationLedger::claim()` atomically reserves an idempotency key bound to a canonical provider/account/operation/params fingerprint before any remote call (new `LedgerClaim` states `claimed`/`replay`/`in_progress`/`uncertain`/`conflict`); `record()` finalizes under the claim. Non-idempotent operations never auto-retry — ambiguous outcomes (transport fault or 429/5xx after the request may have been sent) record `uncertain` for reconciliation. `TransportException` carries only a finite failure kind; `OperationResult` enforces bounded printable messages; `masked()` fully masks secrets of 8 characters or fewer; timeouts/backoff reject `NAN`/`±INF`.
- Added `tests/provider/run.php` (124 synthetic checks) plus `FileLedger.php`/`file_ledger_worker.php` (a real four-process `flock` claim race — beyond scripted interleaving) and the `provider.yml` GitHub workflow (PHP 8.2/8.4, no network).
- No `site/` changes, schema, persistence, admin UI or real provider calls; HC-07 external gate remains `Awaiting API verification`. Not yet integrated or released. Exactly-once remote execution is not claimed — crash-after-write resolves to `uncertain`, not silent re-execution.

## 1.11.1 — candidate, motion preferences and organized updates

## 1.11.2 — candidate, canonical live estimator integration

## 1.12.0 — candidate, administrator email-template library
## 1.13.0 — candidate, email account preparation
## 1.14.0 — candidate, human website review workflow
## 1.15.0 — candidate, consultation scheduling
### Historical — 1.16.0 candidate: explicit email connection checks
## 1.17.0 candidate — bounded recent inbox headers (unreleased)
## 1.18.0 candidate — exact-message reading (unreleased)

- Acceptance correction: actual Chromium diagnostics prove injected image attempts are blocked by CSP before network handling; the assertion now distinguishes blocked events from network requests and still requires both explicit CSP failures. Stable mobile viewport captures include plaintext and formatted views. Shipping behavior is unchanged; complete release acceptance remains pending.

- Align the actual TLS wrong-UID browser fixture with the established unavailable-message classification; malformed MIME remains a protocol failure and neither exposes partial content.
- Update the existing browser availability assertion for the new message-reading feature while preserving connection, attachment and sending limitations; all 69 new native message tests pass, corrected browser acceptance pending.
- Independent review of 21e00c0 reproduced the components and reviewed application access/rendering boundaries with no blocking finding. Candidate native database checks pass; complete browser/update/recovery/Apache evidence remains pending. Correct staged owner/recovery instructions to candidate 1.18.0.
- Add explicit assigned-staff message opening with current account/actor/revision and exact INBOX/UIDVALIDITY/UID checks, bounded MIME content and unchanged server read flags. Separate strict audit/results, request limits, owner-prepared upgrade storage and empty fresh-install storage retain no message contents.
- Decode raw MIME through private maintained parser state, preserve escaped plaintext and show minimal sanitized formatting in an opaque sandbox with restrictive CSP. No automatic remote resources, attachment content or sending. Missing DOM has an honest plaintext/unavailable-format fallback.
- Preserve all 36 existing dependency pins and add four maintained formatting dependencies. Correct PHP 8.2 full-document handling by stripping html/body wrappers while preserving sanitized children; all 1,227 components pass on PHP 8.2/8.4 at 740b4e2 with fresh byte reproduction.
- Add actual 1.17.0 update/recovery and original key/history preservation fixtures, alongside native/concurrency, packaged TLS/HTTP/Chromium and Apache checks. Application acceptance, independent review and release remain pending; latest published release remains 1.17.0.

## 1.19.0 — verified recent email notices

- Explicit successful inbox reads populate a newest-20 observation window with exact mailbox generation/UID identity; strict result/audit failures roll back notice changes. Failed reads retain the prior window.
- Current-authorized notice page, per-staff opened/dismissed state, independent handle/full-tuple POST opening, and private admin/public bells. Passive polling never fetches mail or renews idle login; client-support pages suppress staff email notices. Server read flags remain unchanged.
- Additive owner preparation and empty fresh storage; accurate last-check/failed/incomplete status without retaining sender, subject, body or credentials in notices. Existing read transport and message limits remain unchanged.
- Corrected ordinary-pageshow cancellation, actual support-header isolation, test clock/capture ordering, and Windows exact Git-blob packaging. Source documentation validation excludes generated evidence folders while still verifying all canonical dependencies.
- Published/download-verified `22a2969692ab5bcc7f35158dcaa5355c623dc821` after exact-main 38504271899: 1153 native, 3084 estimator, 1087 HTTP/Chromium, 205 recovery, 779 Apache and 20 builder checks. All16 assets/tag, archive members and Windows/Linux bytes verified; actual mobile/desktop screens reviewed.
- Twenty-one starting packages supported, with nonempty1.18.0 update/recovery/reapplication retaining notice state, earlier records and the original private key. Prior releases unchanged. Automatic/full synchronization, previews, other folders, attachments, sending/OAuth and server flag changes remain future work. No production operation.

## Unreleased — recent email notice storage preparation

- Corrected the native CI container UID/GID after both database stages passed but artifact upload failed on a root-owned private QA directory. Corrected saved-evidence acceptance is pending; application/test behavior is unchanged.
- Added a native migration fixture that removes full-tuple uniqueness while preserving the supporting foreign-key index; this tests application refusal rather than InnoDB index protection. Database acceptance remains pending.
- Added bounded current-window metadata, separate per-person opened/dismissed markers, exact current mailbox permission filtering, collision refusal and independent full-tuple resolution.
- Added explicit owner migration with column/key/relationship compatibility checks and strict audit; observation helpers require the successful terminal-result transaction. No schema/reader/HTTP integration or bell UI is wired yet.
- Local PHP syntax passes. Native database fixtures and PHP 8.2/8.4 workflow are added; database and full application acceptance remain pending. Published/download-verified 1.18.0 remains immutable; its publication documentation is merged into this preparation branch.


## 1.18.0 — verified individual email reading

- Assigned staff explicitly open an exact INBOX message under current account/actor/global and mailbox permissions, saved revision, UIDVALIDITY/UID and size checks. Verified TLS and BODY.PEEK preserve server read flags.
- Private maintained MIME parsing, escaped plaintext and minimal maintained sanitized formatting in an opaque CSP-restricted frame. Whole messages over 256 KiB (including encoded attachments) are refused; no partial display or content retention. Missing DOM preserves plaintext with an honest format limitation.
- Additive owner-prepared request storage, strict started/terminal audit and outcome history, independent account/staff limits and current authorization through transport/rendering. Fresh installs create empty storage.
- Fixed PHP 8.2 document-wrapper sanitization and acceptance assertions without editing upstream libraries or weakening application protections. Actual browser evidence distinguishes CSP-blocked request events from network work. Desktop/mobile plaintext/formatted views were inspected; independent review found no blocking application issue.
- Published/download-verified `91550d956c404585545dbd766c96f36f58ae0623` after exact-main 38492678532: 1074 native, 3084 estimator, 1032 HTTP/Chromium, 191 recovery, 701 Apache and 19 builder checks. Each PHP 8.2/8.4 receiving runner passes 1,227 checks and fresh dependency reproduction; all 16 assets/tag and Windows/Linux bytes verified.
- Twenty starting packages supported; actual 1.17.0 upgrade/recovery preserves prior records/private key and new message history. Prior assets unchanged. Attachments, sending, full sync, other folders/OAuth and email bells remain future work. No live mailbox, deployment, provider write, charge or SSH activation.

## 1.17.0 — verified recent inbox headers

- Preserve early browser evidence so failures can be investigated while the complete recovery matrix continues.
- Route inbox authorization through the current account-scoped permission and assignment checks; the generic staff gate has no mailbox identity.
- Correct updater path validation for exact maintained locale filenames containing @; preserve traversal, control-character and symlink rejection.
- Add explicit assigned-staff INBOX header loading over verified TLS, using maintained pinned IMAP/MIME dependencies bundled for PHP shared hosting. Preserve account/folder/UIDVALIDITY/UID identity and existing read flags; no body, attachment, compose/send or background polling.
- Enforce current session, global permission, mailbox assignment and saved revision before credentials and display. Limit refreshes to one per account per 15 seconds and 120 per staff hour. Commit started and terminal audits separately; retain only outcome/count metadata and personal request history, never header contents.
- Add owner-prepared additive request storage, fresh empty install schema, byte-verified protected runtime mirror, and actual 1.16.0 update/recovery coverage. Published and downloaded/verified from `fc3bd89c5e1c286f9dc4df12d50dd566a4d4ab86` after exact-main 38480568403; all 16 assets/tag pass. PHP 8.2/8.4 each pass 548 receiving checks. No real mailbox or deployment.

## 1.16.0 — verified explicit email connection checks

- Add separate owner-only checks of saved incoming/outgoing settings, current password and matching mailbox assignment. Verify public DNS destinations, pin the numeric connection, validate TLS and authenticate without reading or sending messages.
- Add auditable started/terminal history, saved-version labels, bounded response handling and per-account/owner repeat limits. Interrupted or unaudited final outcomes remain incomplete.
- Add additive upgrade setup, fresh empty schema, isolated TLS/DNS/database/HTTP acceptance and actual 1.15.0 update/recovery coverage. Published and downloaded/verified from `372ea28bf0d3081180c836e2c90aa14b7a90bdd3` after exact-main 38466626819; all 16 assets/tag pass. No real mailbox or deployment.

## 1.15.0 — verified consultation scheduling

- Preserve corrected Devin #32 scheduling ancestry and 71 isolated checks. Add one explicitly configured studio calendar, disabled by default, with timezone-aware offered slots, buffers, notice and a 30-day horizon.
- Capture pending public appointments atomically with inquiries; keep honest preference-only intake when scheduling is disabled. Use one locked database configuration row for every conflict-changing write; stale forms, current permissions, contact consent and strict audit protect staff decisions.
- Add explicit old-inquiry adoption, password-confirmed staff confirmation, cancellation/decline, rescheduling back to pending and retained revisions. Add public/admin forms and actual 1.14.0 update/recovery fixtures preserving earlier reviews/templates/email credentials and private key.
- Published/download-verified from `8749bc5a89e52f8f35a193cc49d306136576ec8e` after exact-main run 38460197599; all 16 assets/tag pass. Native/HTTP/Chromium/Apache/build acceptance passes. No provider calendar, outgoing confirmation/cancellation mail or live deployment.

## 1.14.0 — verified human website review workflow

- Preserve corrected Devin #31/#36 and adapt the canonical review lifecycle into the existing inquiry system. Accept bare website hosts deliberately; retain the 500-byte bound and reject fragments, userinfo and control characters. Capture new review identity atomically with accepted leads; older inquiry adoption is explicit.
- Add current website-staff authorization, owner-only additive setup, evidence-labeled findings, saved report revisions, stale/concurrent protection and strict audit. External delivery requires the exact saved draft, current consent, real entered time/reference and staff password; this records delivery outside the app and sends nothing.
- Add escaped desktop/mobile forms, draft finding add/remove controls, history and explicit inquiry deletion of dependent personal review content. Recognize actual 1.13.0 upgrade/recovery with earlier email key/assignment/template retention. Published/download-verified from `f55879339e38769b4ec5fa81780a3d650b63da25` after exact-main run 38453775600; all 16 assets/tag pass. Native/HTTP/Chromium/Apache/build acceptance passes; no live deployment occurred.

## 1.13.0 — verified email account preparation

- Preserve Devin #59 canonical profile/assignment/message models and isolated CI. Re-reviewed 64 no-extension component checks; future-issued grants, UIDVALIDITY, duplicate identities and tuple encoding are corrected. Combined application acceptance passes.
- Add owner-managed incoming/outgoing profiles, encrypted private credentials, immutable metadata/assignment history, strict audit and stale-write protection. Add separate staff read/send permissions and current per-mailbox assignments, including for owners.
- Add administrative settings and scoped assigned-account lists, additive owner setup, fresh schema preparation and actual 1.12.0 upgrade/key/data recovery fixtures. Published/download-verified from `b83cb9fb6856bca4f333fe953bee2e68f5074d51` after exact-main run 38446948573; all 16 assets/tag pass. No transport, mailbox contents, connection success, sending or deployment is implied.

## 1.12.0 — verified administrator email-template library

- Add explicit template permissions, personal/shared drafts, create/edit/duplicate/activate/archive and escaped sample previews. Reuse the corrected Devin #56 model with literal single-pass substitutions and strict field grammar.
- Retain immutable versions and strict actor audit with transaction rollback, row locking and stale-edit rejection. Add owner-authenticated, idempotent schema setup; fresh installs prepare an empty library automatically.
- Keep personal templates private even from other owners; shared modification requires creator/owner access. Exclude support/client identities. Preview never reads client records or sends mail.
- Recognize verified 1.11.2 update inventory, exercise actual latest upgrade/schema/file recovery and all prior supported baselines. Published/download-verified from `c526e960db13246c217f2b9d6f421c17b8d48261` after exact-main run 38441087438; all 16 assets/tag pass. Local and PHP 8.2/8.4 component checks and combined acceptance pass.
- Full webmail, compose insertion, real sending and transactional messages remain outside this bounded increment.

## Historical A-07 isolated component checkpoint — integrated in 1.12.0

- Added canonical `foundation/email-templates/` classes (`PlaceholderRegistry` allowlist with descriptions, `EmailTemplate` with structural placeholder allowlisting, draft→active→archived lifecycle, non-mutating edits, personal-duplicate, shared/personal visibility, transactional compose exclusion, and honest `preview()` that names unresolved placeholders) plus `docs/EMAIL_TEMPLATES_FOUNDATION.md`.
- Added `tests/email-templates/run.php` (50 synthetic checks; fixture templates only) and the `email-templates.yml` GitHub workflow (PHP 8.2/8.4, no network).
- No `site/` changes, compose UI, sending or persistence. Not yet integrated or released.

## 1.11.2 — verified canonical live estimator integration

- Preserve Devin's reviewed adapter contract and route existing calculator arithmetic through the canonical estimator, folding quantities with exact integer guards. Existing field validation, missing-price early returns, invalid-range errors, consultation exclusions/cap and displayed results remain compatible.
- Add direct-entry guards before mirroring the canonical estimator into protected includes; installer inventory verifies the new files.
- Compare actual application results with the immutable published 1.11.1 calculator and independent boundary examples. Retain server-recomputed HTTP results and saved-lead acceptance.
- Recognize verified 1.11.1 for staged owner updates; exercise both organized starting versions as well as retained old-layout upgrades and recovery records. No schema change or live external activation.
- Published/download-verified from `4fd0d5efdc9d94f1f5b29d76be857d944f5f8c36` after exact-main run 38433670555; all 16 assets/tag pass. Homepage pixels match 1.11.1. Prior releases remain immutable; no live deployment.

## 1.11.1 — motion preferences and verified organized updates

- Preserve and integrate Devin PR #34: manual pause also disables root smooth scrolling; restrained logo hover/focus sheen respects pause and reduced motion; remove unused hero-scene CSS without changing the layout or image bytes.
- Measure homepage lab layout shifts with a real buffered PerformanceObserver and session-window calculation; test saved pause/resume, device reduced motion and no-JavaScript readability. This is synthetic browser evidence, not a production performance guarantee.
- Recognize the verified 1.11.0 package for owner updates; retain known upgrade-only routes without adding them to fresh organized sites. Preserve private scaffolds, previous recovery records and all configuration/business data. Separate target-version journals share the original permanent update lock.
- Extend real Apache update/recovery acceptance with the actual retained 1.11.0 installer, database records, session continuity and opcode caching enabled without timestamp validation. Retain old-layout historical acceptance.
- Derive installer page/JavaScript/diagnostic build labels from its canonical build constant; restore organized installer logo/emblem paths. Browser setup now explicitly checks version and loaded brand images; the builder rejects stale literal image paths.
- Make package checksum ordering identical on Windows and Linux; use committed history and guide bytes in exact-source artifacts.
- Published/download-verified from `cefcc41bcd5297715f2a49d6526900ebe8ad4eb4` after exact-main run 38429347222. All 16 assets and tag pass. Prior releases remain immutable; no live deployment or new external payment/provider operations.

## 1.11.0 — organized application and verified three-package release

Published from `de70c5da2e8f7bd35fbb92b9b829d0ab3e880372` after exact-main acceptance; all 16 assets downloaded and verified. Original history and all earlier releases retained. No live deployment.

- Organized administration, portal, public templates, shared bootstrap/security, business services, database migrations, API/webhooks/media and setup source; retained existing visuals and exact image bytes.
- Canonical root/subfolder URLs, shared session identity, guarded internal paths and private upload staging within the configured store.
- Staged owner update with recognized-source verification, backup confirmation, serialized journaled replacement, known-hash retirement, direct legacy callbacks and recovery.
- Three clean-commit artifacts: sanitized FULL-PROJECT, organized COMPLETE-UPLOAD and independently exercised UPDATE; per-artifact inventories/checksums and START-HERE.
- Expanded application, historical upgrade, Apache and filesystem recovery acceptance. Publication depends on completed checks and exact-main verification.

## Historical priority request — fulfilled by 1.11.0

Owner override stops the portfolio-only 1.11.0 publication. Preserve integrated source and implement organized modules/assets, protected storage/upload scaffolding, bounded owner migration/recovery, and FULL-PROJECT/COMPLETE-UPLOAD/UPDATE artifacts. Latest published version remains 1.10.0; no corrected release claimed yet.

## 2026-10-13 — Dependency-aware roadmap and agent instructions (docs only)

- Added a mandatory owner-approved policy for Codex/Devin: unavailable third-party API/merchant/SSH verification blocks only the specific real integration or production operation, **not** safe independent development or unrelated features.
- Clarified HC-07 (Awaiting API verification), HC-09/HC-10 (Development pending — API verification), and A-10 (Architecture/security validation pending), tracking those gates separately from implementation/release/deployment status.
- Linked the rules from agent instructions, roadmap, provider/client roadmaps, coordination, status, handoff and decisions. Explicit production restrictions remain.
- No PHP/runtime feature, integration, live transaction, deployment or app version bump; previously released v1.10.0 stays unchanged.

## Historical 1.11.0 portfolio candidate — included in the corrected release

- Stabilize modified-click acceptance by observing the application decision before suppressing synthetic navigation; add an actual Ctrl-click/new-tab check. Shipping bytes are unchanged by this test-only correction.

- Preserve and integrate Devin's W-01 component and real-project adapter. Public category URLs, no-JavaScript navigation, enhanced history and result counts use published records with truthful labels.
- Correct numeric-string categories, the real category `all`, duplicate public slugs, normalized card values and modified-click handling. Identical media no longer presents a before/after comparison.
- Regenerate the 80-file complete / 79-file update package and versioned keyless installer. No schema changes. Retained 1.10.0 and full application acceptance are required before publication.
- Reviewed estimator integration remains outside deployment, with 65 exact-integer/result checks; existing live estimator is preserved.

## Unreleased — review-request foundation

- Add independent L-02 review-request contract (`foundation/review-requests/`): normalized website addresses with SSRF-readiness flags, contact consent, legal stage transitions, human-reviewed findings with labeled lab/field/absent evidence, and delivery guarded by a saved report plus consent. Not integrated; no schema, form, mail, or URL fetching.

## Unreleased — reviewed estimator foundation

- Integrate Devin PR #27 with preserved ancestry; retain the standalone component outside shipping files. Add exact-integer overflow handling and reject inconsistent direct result objects. Application version remains 1.10.0; adapter and UI acceptance remain pending.

## 1.10.0 — workspace activity

- Personal client and staff activity centers with explicit read/unread and bounded mark-visible actions.
- Unread bells, exact source links and paginated history with current ownership/role checks.
- Generic public mount point; private cookie-authorized count, no-store and active-page polling without extending idle sessions.
- Additive read-state schema, existing-source projections, failure recovery and retained 1.9.0 update coverage.
- Published/download-verified at `4649a913d40166b9874f9550e57b4186edfc43ab`; 603 native / 630 HTTP/Chromium checks pass. Full P-07, email dispatch and A-06–A-08 webmail/templates/mail bells remain incomplete.

## 1.9.0 — client website downloads

- Private staff uploads, immutable manifests and owner-reviewed publication of versioned client ZIPs.
- Client/project history, notes, package scope, SHA-256 checksums and authenticated resumable downloads; strict ownership and support attribution.
- Bounded archive inspection without extraction/execution; reject unsafe paths, links, credentials, encrypted/nested archives and databases. Outside-web-root guarded storage with project/site quotas.
- Additive setup and retained 1.8.0 upgrade coverage preserve the existing portal, payment ledger and credentials.
- Published/download-verified at `080f14aea8fe5c35e04e4eba62976609aef7cd67`; 539 native / 569 HTTP/Chromium checks pass. Automated packaging, database transfer, larger jobs and full P-06 remain incomplete.

## 1.8.0 — invoice payments and client receipts

- Record verified external USD payments in full or in parts; show total paid and remaining invoice balances. Replay, stale review, duplicate reference and overpayment guards share the existing financial lock.
- Owners correct mistakes with an audited full reversal while retaining original entries. No money is sent or refunded.
- Clients view owned statements and printable receipts; staff-only references remain private, and support receipt access is blocked and audited.
- Additive migration imports legacy paid balances without fabricating receipts. Partial payments/corrections retire earlier requests; new requests use the remaining amount.
- Published/download-verified at `c36a36e80c40485c9e3e7e6cdd4884ced25db961`; 427 native / 503 HTTP/Chromium checks pass. Card checkout, actual merchant acceptance and recurring/refund processing remain pending.

## 1.7.0 — sandbox notification inbox

- Owner-configured sandbox callback with raw-body HMAC verification, account/webhook binding, bounded payloads and replay protection. Durable minimal receipt and strict audit precede HTTP 200.
- Owner verification matches an existing attempt and checks current sandbox reporting; observation, processing state and audit commit together. No real invoices are paid.
- Explicit additive upgrade preserves earlier attempts, requests, encrypted credentials and external keys. Browser setup/inbox follows existing styling.
- Published/download-verified at `1fe0670142755f86aee235fabe1847e5d118ee52`; exact-main 358 native / 451 HTTP/Chromium checks pass. Actual merchant delivery, scheduled processing, production and client checkout remain pending.

## 2026-10-13 — Sandbox trial release published and verified

Continued roadmap from main `dd29436fa40c2a0f800bc02569b5fd5d1fb75c09` / 1.5.0 on `codex/sandbox-attempts`. Added owner-only tokenized sandbox UI, fixed sandbox reporting/charge adapter, additive attempt/events, exact amount/reference/account checks, idempotency and concurrency safeguards, unknown-result recovery, shared manual/catalog/request locks, strict audit and immutable 1.5.0 upgrade acceptance. PR #18 merged as `e5d4c17456faff0522429ab3f58093a06a523bd5`; exact-main run https://github.com/afterburn25/Cajun_Web/actions/runs/38380935411 passes 296 native / 421 HTTP/Chromium checks. Independent policy/invitation/documentation checks pass. Reviewed desktop/320px screenshots.

First candidate 4158d37 failed installer inventory due to Windows line endings; normalized LF and regenerated. f36e44a passed native tests but HTTP expected the established duplicate-payment message; restored the message without removing safeguards. 3ebc8a8 passed complete acceptance; final wording clarifies owner sandbox versus disabled client checkout. Final merged source and packages were checked independently. All eight draft and published release assets, extracted hashes, version labels and tag were downloaded-back verified. [Report](docs/TEST_REPORT_1.6.0.md), [verification](docs/RELEASE_VERIFICATION_1.6.0.json). Earlier releases untouched. No live deployment, provider operation or customer mail/data. Real merchant acceptance remains unverified; tests use synthetic gateway/Accept.js boundaries. Next: signed events, actual sandbox acceptance, distinct client/real-money checkout. Preserve Devin PR #7. Publication records are docs-only.

## v1.5.0 — 2026-10-13

**Current release — 2026-10-13:** [v1.5.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.5.0) is published and downloaded-back verified at `3e95bdf81ca0126d99a69c41f2ff6eb0c08da1e1` via [PR #16](https://github.com/afterburn25/Cajun_Web/pull/16). Bounded M2 preparation: owner-only protected separate Authorize.net credentials, sandbox-authentication diagnostics, reviewed invoice requests with expiry/revocation/replacement and strict audit. Card collection, production connections and transaction processing remain disabled. [Payment guide](docs/PAYMENT_PREPARATION.md). Exact-main acceptance: 227 native and 401 HTTP/Chromium checks; all eight assets/tag verified. Provider results use synthetic fixtures, not real merchant authentication. Earlier releases and Devin PR #7 remain unchanged. No live deployment/customer/provider operation. Next: tokenized sandbox payment attempts, verified events and reconciliation before actual design-only checkout; full M2/20i/mail/ZIP/SSH remain pending. [Devin handoff](docs/DEVIN_HANDOFF_1.5.0.md).

## v1.4.0 — 2026-10-13

M1 / partial HC-01 and HC-06: managed service prices, public configured cards, preserved invoice snapshots and audited manual payment/void workflow. Published at `0cdea7fbd17e887d3f4bc0574a411b157431b7df` via PR #14 after 165 native and 345 HTTP/Chromium checks. Eight retained assets/tag downloaded-back verified. No live deployment or gateway operation; [scope](docs/RELEASE_1.4.0.md).

## v1.3.0 — 2026-10-13

- Explicit owner-confirmed client policy and separate staff support view/write/download permissions.
- Browser-bound expiring contexts, persistent staff/client banner and return, stale-tab isolation and separate form tokens.
- Attributed messages/uploads/downloads and strict audit rollback; blocked customer decisions/security/payment actions.
- Additive support schema, preserved 1.2.0 update state, native and HTTP/Chromium acceptance.
- Published and downloaded-back verified at `7856cccf230a1646d02a4a92ad4bfb1c280efa19` through PR #12; 116 native and 289 HTTP/Chromium checks, eight retained assets. No host deployment. SSH/provider access remains separate.

## v1.2.0 — 2026-10-13

Owner-controlled creation, selected website/client-workspace permissions, enable/disable and private 24-hour setup/recovery links are integrated with existing users. Current-password confirmation, per-request authorization, reset/setup/session revocation and concurrent last-owner protection are enforced. Fresh installs initialize the owner; existing installations explicitly select an owner and add two tables without changing legacy credentials. Original and actual released-1.1.0 package upgrades are tested. Canonical foundation components and the embedded installer core are verified by the builder. [User/developer guide](docs/ADMIN_ACCOUNTS.md), [PR #10](https://github.com/afterburn25/Cajun_Web/pull/10). Published and downloaded-back verified at `f85627e05b171e4ee8508231e2ed6ee7b9374274`: eight retained assets; 81 native and 216 HTTP/Chromium checks plus independent suites. See [release register](docs/RELEASES.md). No target-host deployment.

## v1.1.0 — 2026-10-13

Published [v1.1.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.1.0) at `3703783e979abab38e8d72e9092c51dc835833b3` after PR #8. Delivered website/client-workspace scope and release fixes are listed in [the matrix](docs/RELEASE_1.1.0.md). Exact-main tests passed: 50 native, 157 HTTP/browser, all independent component/documentation checks. Seven retained assets were downloaded back and verified. Complete/update ZIPs support fresh setup and the tested populated 1.0.8 upgrade; no live deployment. This later documentation-only checkpoint records publication, remaining roadmap and the exact-release Devin handoff. Older pending statements below are historical.

## 2026-10-13 — Native regression fixes

First MariaDB acceptance found a real schema-idempotency failure: digit-bearing `sha256` was omitted by the column parser. Fixed both column-name patterns without weakening type/engine/unique-key checks. Local native tests then passed, and real unprivileged PHP HTTP tests completed fresh one-page installation and physical cleanup. Also fixed the existing lead editor's seven-status/six-label mismatch and corrected the test's portfolio route to the actual `work` endpoint. Tests now run extracted package bytes and cover incompatible schema preservation, cross-client/CSRF rejection and disguised executable uploads. Public service copy was polished and official comparison sources rechecked on 13 October. Complete browser acceptance and release publication remain pending. No customer host or database was changed.

## 2026-10-13 — Source recovery checkpoint

Recovered 41 actual website files plus native/HTTP tests and a build tool from the nine preserved source fragments. Original external assets were fetched only at the recorded public paths and checked against recorded SHA-256 values. Executable source is now under `site/` on `codex/finish-1.1.0`. This supersedes the older no-source-in-Git blocker on this branch, NOT the unverified runtime or release claims. No production files, database, mail or payments were changed. Review `docs/SOURCE_RECOVERY_2026-10-13.json`; run native integration and authorization tests next. No 1.1.0 release is declared by source recovery.

This log distinguishes repository work from application releases and deployment. Documentation-only work does not advance the application version.

## 2026-10-13 — 1.1.0 release acceptance in progress

- Reconciled recovered application source with main while preserving foundation components and Devin's separate work.
- Renamed the browser runner to avoid shadowing Python's standard HTTP library; the first combined run passed native database tests but failed on that import.
- Aligned candidate labels at 1.1.0, enforced canonical line endings, and made the build compare shipping bytes with its recorded commit.
- Client changes revoke outstanding invitations and sessions; activation rechecks expiry after locking/password hashing. Uploads reject executable extensions/code; form feedback respects reduced motion.
- Expanded release acceptance and publication remain in progress.
- Fixed real JavaScript submissions where a named action field hid the form endpoint; both growth requests and the original inquiry form now use their explicit destination attribute.
- Added the exact historical 1.0.8 test fixture and actual UPDATE-package/admin-upgrade checks, broader saved-state/browser/installer tests, local synthetic mail capture and the feature-to-test matrix. Preserved the original favicon source with its verified hash.

## 2026-10-13 — Staff invitations and source-compatible session tokens

- Inspected the recovered draft's real login/schema and corrected access contexts from integer revisions to opaque authorization strings with strict comparison.
- Added independent PDO staff invitation persistence: hashed single-use links, bounded expiry, replacement/revocation, transactional activation/consumption and rollback.
- Added a native MariaDB test workflow covering concurrency and failures using synthetic accounts; kept application migration, delivery, UI and release integration separate.
- Verified 146 access and 46 native invitation checks on each of PHP 8.2.34/8.4.26 with MariaDB 11.4.13; recorded branch and PR evidence in the invitation contract and continuity documents.

## 2026-10-13 — Independent staff and mailbox permission component

- Added standalone PHP 8.2+ immutable access contexts and permission policy: deny unknown/inactive/stale identities, separate staff management/permission grants, require exact mailbox read/send memberships, and reject conflicting duplicate grants.
- Added 142 local decision checks and a GitHub PHP 8.2/8.4 syntax/unit-test workflow. No Composer/runtime service requirement.
- Verified the source and PR checks on GitHub: 142 decisions and four syntax checks pass on PHP 8.2.34/8.4.26. Evidence is linked from the access foundation and status/handoff; the isolated component is not current-application acceptance.
- Documented the trusted adapter boundary and pending lifecycle/database/browser integration; updated ownership and Devin's independent portfolio scope while another worker finishes 1.1.0.
- No version bump, current-source replacement, admin UI, real account/mail operation, release ZIP or deployment. This component remains outside application packaging.

## 2026-10-12 — GitHub checks, release retention and Codex/Devin coordination

- Integrated roadmap/workflow PR #1 into main, verified passing PR and merged-main documentation jobs, created both work branches and published Devin assignment issue #2. Current status/handoff links the evidence and states that Devin has not been dispatched.
- Added a read-only GitHub Actions workflow and standard-library documentation checker for continuity files and local Markdown links/headings. Application CI remains pending current-source recovery.
- Defined incremental versioning, retained complete GitHub Release ZIPs, manifests/checksums/test reports, exact-artifact verification and a truthful release register.
- Added separate Codex/Devin work assignments, branch ownership, a ready-to-use Devin handoff and sequential PR integration with combined checks.
- Updated agent rules, PR checklist, roadmap F-04, status/handoff and release documentation. No application version bump, deployment or public application release is included.

## 2026-10-12 — Full client portal, website delivery and support access (requirements only)

- Added client-portal capability coverage for verified 20i services and native controls, explicit existing-service grants, project monitoring/updates/approvals and a separate client notification center.
- Added P-05/06/07: design workspace, authorized versioned website ZIP/data downloads with integrity/restore/retention criteria, and measured service observations without fabricated metrics.
- Added A-09/10: one-click staff support sessions into client portals and separately authorized VPS SSH with real verified transport, secure session scope and staff audit identity.
- Reviewed official public 20i documentation and recorded sources/account-specific limitations. Synchronized roadmaps, blueprint, direction, architecture, decisions, status and handoff.
- No current-source implementation, archive release, client login, SSH connection, provider/account change or deployment performed.

## 2026-10-12 — Custom platform, admin webmail, staff access and notifications (requirements only)

- Added `docs/CUSTOM_PLATFORM_BLUEPRINT.md` for the owner's explicit instruction to build our own WHMCS-style platform, mapping owned business modules, shared identity/ledger, provider adapters and phased delivery to the existing roadmap.

- Added A-05 to A-08 and `docs/ADMIN_MAIL_ROADMAP.md` for additional admin accounts, scoped mailbox access, multiple email account setup and webmail, reusable sending templates, and unread-mail bells in admin and the admin-only public header.
- Specified account connection checks, inbox/compose/drafts/attachments, safe email rendering, template preview/variables, provider read-state sync, exact-message links and private endpoint/cache isolation.
- Integrated staff identity in M1 and the independent mail milestone within M5/HC-12, synchronized direction/architecture/decisions/status/handoff, and retained the design-payment roadmap and 1.1.0 source prerequisite.
- No admin or mailbox account created, no provider connection, no email sent, and no runtime feature or deployment performed.

## 2026-10-12 — Dedicated web-design payment forms (requirements only)

- Expanded P-03/HC-11 to define themed Authorize.net forms for full service payments, quote deposits, milestones, final balances and optional maintenance, plus admin-created payment links and portal invoices.
- Specified shared invoice/payment records, accurate project balances, secure request lifecycle, sandbox acceptance, refunds and duplicate/timeout recovery. Design payments operate independently of 20i or a hosting purchase.
- Assigned standalone forms to M2; kept richer project/hosting bundles in M4. Synchronized direction, architecture, decisions, status and handoff, and corrected current source-import prerequisites to owner-confirmed 1.1.0.
- No application source, working form, gateway setup, card charge or deployment changed. Current 1.1.0 source remains to be located and inspected.

## 2026-10-12 — Owner corrects latest version to 1.1.0 (local docs only)

- Recorded the owner's explicit confirmation that 1.1.0 is current; retained verified 1.0.8 only as historical reference.
- Updated status/handoff to locate and verify 1.1.0 before source import or implementation, superseding older 1.0.8 starting-point instructions.
- Rechecked GitHub; remote main remains documentation commit `43bebd0`. Requested the current build's location. No 1.1.0 tests, code changes, push or deployment claimed.

## 2026-10-12 — Current roadmap and available build verification (local docs only)

- Confirmed GitHub main at `43bebd019a6f548d7a059b2c8e259beed3efe561`; feature branches contain no newer source, and no release/tag/workflow run was available.
- Located both original/recovered 1.0.8 archives in local Downloads, verified their recorded SHA-256, and retained an unchanged archive and extracted working copy outside the repository.
- Fresh checks passed ZIP CRC, 33-file byte equality, 32 installer dependency hashes, version/layout constants, original logo hash, 14 PHP syntax checks and two JavaScript syntax checks.
- Updated status, handoff and session log. No source import, feature implementation, new release, host repair, provider action or deployment; remote GitHub content unchanged.

## 2026-10-12 — Hosting resale and custom billing roadmap (docs only)

- Approved first-party hosting/domain/VPS storefront integrated with CajunWebPro web-design services; created docs/HOSTING_AND_BILLING_ROADMAP.md (HC-01 through HC-14, M0–M6 phases and acceptance tests).
- Selected 20i reseller API for infrastructure and Authorize.net for payments (replacing the earlier generic/Stripe proposal), without WHMCS/HostShop requirement. Configurable admin key fields: API Login ID, Transaction Key, Signature Key and Public Client Key; custom themed Accept.js payment flow with PCI gate.
- Added unified client portal, products/plans, domains, VPS, invoices, recurring payments, refunds, provisioning jobs, admin, security and shared-host/installer constraints to roadmap, direction, architecture, status, decisions, handoff and session log.
- **Not shipped:** no application source import, live credentials, integration, checkout, plan prices, hosting account or deployable artifact. Existing download/installer/storage issues remain open.

## 2026-10-12 — Download recovery and release-claim correction

### Corrected

- Withdrawn the unsupported chat claim that a complete, tested v1.1.0 release and update package were ready. The advertised files were not recovered in the active runtime or file searches; GitHub had no published release.
- Documented that main and feature/growth-suite-1.1.0 still pointed to the documentation checkpoint at investigation time. A created branch is not implementation.
- Updated status and handoff with the delivery incident and explicit baseline-import/rebuild prerequisites.

### Verified and recovered

- Rechecked CRC and SHA-256 of the real 33-file v1.0.8 archive and its application/installer version constants.
- Rechecked the exact-favicon archive and decoded PNG/ICO variants; source hash matches the user attachment.
- Created fresh byte-identical recovery copies clearly named as the earlier 1.0.8 baseline and separate favicon assets, not v1.1.0.

### Not changed or claimed

No application source/asset import, feature implementation, host repair, database migration or deployment. No new workflow tests or live download-UI success claimed. Existing files and data on the user's host were untouched.

## 2026-10-12 — Repository documentation bootstrap

### Added

- Project source-of-truth README and `AGENTS.md` working agreement.
- Current status, unresolved host issues, and exact session handoff.
- Approved project direction, feature roadmap, architecture inventory, deployment rules, branding provenance, and decision record.
- Dated session log, session template, and pull-request documentation checklist.
- Inspected 1.0.8 distribution identity and original logo/favicon-source fingerprints.

### Verified in the bootstrap session

- Connected repository identity, default branch, write access, and initially empty tree.
- Supplied ZIP hash, ZIP integrity, flat 33-file listing, version headers, schema declarations, and logo byte identity.

### Not changed or claimed

- No application source/asset import into Git yet.
- No runtime behavior, production files, database, or provider configuration changed.
- No new portal, estimator, comparison page, favicon implementation, payment system, or update manager shipped.
- No historical test counts represented as current tests; no host repair or full installation claimed.

## Pre-repository baseline — supplied 1.0.8 distribution

`CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip` is the latest recoverable complete artifact identified in the conversation. It contains the PHP application and one-page installer source. It is not a Git release/tag created by the bootstrap, and its live deployment is unverified. See `docs/BASELINE_INVENTORY.md` and `PROJECT_STATUS.md`.

- 1.1.0 acceptance follow-up: mobile consent text wraps as one paragraph; expanded browser installer/contact checks and retained runtime diagnostics.

- Candidate 91ef30d: 50 native and 143 HTTP/browser checks passed; final browser-admin, sitemap and favicon-reference assertions added before merge.

Final matrix test correction (parent `7a0c0dd`): run 38356481572 rejected valid versioned favicon URLs because it compared the query suffix as part of the path. Parse the URL path and fetch the actual referenced icon for byte equality. Application source is unchanged; rerun the full suite.


## Historical component contract — review-request adapter contract (L-02 dependency)

- Add `docs/REVIEW_REQUESTS_ADAPTER.md`: the contract for adopting the review-request foundation (PR #31) in the live `website-review` lead path — `WebsiteAddress` intake normalization, consent-aware `ReviewContact`, stage persistence via an additive `review_stages` table, `review_reports` as the delivery gate, and staff transition controls through `ReviewInbox`. No shipping code or schema changes; records two decisions for integration review.

### Draft compose UI preparation (unwired)

Added separate private draft controller/view sources and 38 named raw form-input checks. Prepared account/template selection, save/preview/discard/history, stale revision preservation and explicit template replacement. No route, navigation or setup activates the screens; native/HTTP acceptance and release remain pending. Provider PR74 is merged with exact-main acceptance queued; existing published version remains1.19.0.

### Local email draft application integration (unreleased)

Connect fresh/additive setup, current-authorized admin compose/list/preview/template/discard/history and mailbox navigation. Add packaged HTTP/Chromium and Apache cases plus1.19 recovery/reapplication retaining active/discarded drafts and original keys. These integration scenarios await CI; no SMTP or release publication. Application job budget45minutes preserves all assertions while accommodating the additional recovery cases.

## Unreleased — 20i uncertain-write reconciliation (devin/twentyi-reconcile)

- `provider_operations_reconcile` resolves an `uncertain` ledger row through one owner-authorized, evidence-backed observation of the package list (`GET /package` — the only authenticated-read evidence). Outcomes: `observed`, `not_observed`, `inconclusive` (no read sent for unobservable updates), `unavailable`, `refused`. The recorded provider result is never rewritten and the request is never re-sent.
- `provider_operations` schema v4 appends `reconciled_outcome`/`reconciled_epoch`; the owner operations page shows the reconcile badge and a password-bound check action on uncertain rows.
- Fresh installs now stamp the created schema versions (`provider_inventory`=2, `provider_operations`=4) rather than a stale '1'.

## Unreleased — packaged provider-operations acceptance + approval-time build validation (devin/twentyi-reconcile)

- `provider_operation_grant` now requires the approved scope to construct a documented bounded request — undocumented fields refuse at approval, not at send.
- New packaged HTTP/Chromium suite covers the owner operations surface end-to-end: storage setup, owner-only gates, exact-scope approval, a single bounded POST through the real transport stub, idempotent replay, 202→uncertain classification, reconcile `not_observed`/`observed`, revocation, and internal-file denial.
- The loopback provider fixture and TLS connector gained a strict POST branch and a package list that reflects recorded creates.

## 2026-10-16 - Confirm Devin delivery and preserve legacy-upgrade failure

Fresh issue157 comments6217621738/6217865863 acknowledge non20i work. Open heads: licensing159 e3d627701f14d4d44acc02be49f4bbd20cb2b389; tickets158 3ca4bbb780e155d56914ba834d1e811eebb91d1f; progress160 433c60942aa7e4d72c0576c391d41718f793675c. Licensing proposes fixes to the four findings; independent review is underway, not accepted or merged. Prior unconfirmed-acknowledgement wording is corrected. Full819c native failed at a genuine old-schema owner GET; preserve original run and fix on the next source, without weakening its fixture. Documentation-only descendant of481aff27; no runtime, installer, release or live change.

## 2026-10-16 15:15 UTC - Publish cloud candidate and actionable licensing review

+Owner goal: continue complete20i and coordinate Devin non-20i work. Documentation parent595ac9c3; commit "Document cloud assignment candidate and licensing review" records exact source2a86704f/tree32f1058c/parent819c, original focused run38921917647 attempt1 and pending Windows build. Local/source/staged checks passed; native/full/browser/package/main/release acceptance remains pending. Original819c upgrade failure is preserved and corrected on2a; original9779 browser remains active. Licensing159 review299bf71e produced comments6218434878/6218437910 with two behavior gaps and stale inventory failure. All changed files are Markdown; no runtime, installer, version, release, live provider or customer action. Next: actual focused archives, full combined evidence and sequential integration.

## Cloud inventory and assignments integrated — 2026-10-15

Codex owns the complete 20i P0 integration. Unrelated work stays preserved and paused. Published v1.23.0 remains immutable; 1.24 is withheld for the fresh-owner warning repair until corrected candidate/main and release-download gates pass. No production operation or deployment is claimed.

Cloud inventory and explicit client assignments are combined in [PR #127](https://github.com/afterburn25/Cajun_Web/pull/127), branch `codex/twentyi-cloud-inventory`. Normal merge `235f98e137e838401da26687dd750ac7315b78e0` retains #128 and has the same tree as independently reviewed `c377174cc3072c2ae2aee815c1ccbfd542b8780a`. [Native matrix](https://github.com/afterburn25/Cajun_Web/actions/runs/38703797745): 848 downloaded named executions across PHP 8.2/8.4 and MariaDB 10.11/MySQL 8.4. [Packaged checkpoint](https://github.com/afterburn25/Cajun_Web/actions/runs/38703794283): 7,585 executions in 42 reports, including 1,406 HTTP checks, no PHP diagnostics. Three archive manifests/members/hashes and six actual owner/client screenshots were inspected. [Review](https://github.com/afterburn25/Cajun_Web/pull/128#issuecomment-6187625318) is clear. Extended recovery/Apache were skipped; this is not release acceptance.

The combined implementation reuses the reader/refresh engine, explicit current observation and provider context, existing clients, owner authorization, audit and locks. Schema 5 preserves populated v1–v4 data and immutable approval events. Client cards expose assigned server specifications with original units; hosted package IDs and other clients are excluded. Reapproval/removal rejects stale forms. No assignment creates an order, establishes health or authorizes a provider write.

This continuation starts at the normal merge above. Only the cloud screenshot method changes: full-page captures start at the top, keeping real sticky navigation in its natural location instead of scrolling an oversized element under it. No UI is hidden or rewritten for evidence. Syntax/docs checks cover this commit; its new screenshots remain to be produced by the next release candidate. Prior failed fresh-installer, setup-message and missing-profile runs remain preserved.

Next: complete corrected 1.24 release gates, then prepare full cloud acceptance with the immutable published baseline. Remaining complete-platform scope is tracked in the current handoff.


## Cloud ownership: native pass and shared fresh-owner correction — 2026-10-15

Starting at `4d87ef324047052337fdc34c78180a6ec44cf144`, [PR #128](https://github.com/afterburn25/Cajun_Web/pull/128) passes 848 downloaded named native assertions (156 orchestration and 56 migration cases on each PHP/database combination). [Independent review](https://github.com/afterburn25/Cajun_Web/pull/128#issuecomment-6187370517) is clear. Actual packaged checkpoint [38702057933](https://github.com/afterburn25/Cajun_Web/actions/runs/38702057933) passes 1404 HTTP assertions, including cloud assignment/client isolation/reapproval; it fails only the final empty-PHP-diagnostics check with the shared missing-profile warning. That failure is preserved and is not browser acceptance.

This merge retains cloud parent `cc533c36`, including reviewed fresh-owner correction #129 / `5fa5998d` and its original ancestry. The package section now handles absent profile setup before inventory queries/actions. Fresh cloud schema remains version 5; the combined test retains both cloud setup and fresh package-page regressions. No ownership, migration or provider execution semantics changed in this merge. Generated inventory is refreshed. Changed PHP/Python syntax and documentation checks are required locally, followed by exact merged native/browser evidence and review.

Version 1.24 remains unpublished while its separate fresh-profile candidate completes full acceptance. Preserve older main and cloud evidence. Next: inspect corrected combined cloud reports/screenshots, address findings and normally integrate ready #128 into #127; then prepare the next useful release after 1.24 is verified and published. Cloud lifecycle, commercial order-derived ownership, native controls, broader domain/VPS work and SSH remain active P0 implementation. No production request or live deployment is claimed.


## Cloud assignment implementation checkpoint — 2026-10-15

Codex continues complete20i P0 implementation on `codex/twentyi-cloud-links`, starting from cloud inventory1b6ede97 and retaining its setup-message correction. Stable inventory/operations #126 is normally merged at main5dd2a3e1; exact-main acceptance38699707455 is running and1.24 remains unpublished. Cloud read/setup #127 remains independently tested and under review. All earlier source histories are retained.

This checkpoint adds explicit owner cloud/server-to-existing-client assignments, current-observation/profile-bound approval, stale-form revisions, removal/reapproval and immutable context events. Clients receive only their assigned server's specifications; hosted package IDs, credentials and other clients are excluded. Changed/disabled provider settings hide metadata until explicit review. The existing lock, authorization, audit, refresh and migration engines are reused; assignment never contacts20i or creates billing/order ownership. Schema5 adds two tables with populated v1/v2/v3/v4 upgrade and missing-history recovery cases. Typed owner forms and actual portal cards are implemented with synthetic native and packaged browser cases.

Only changed-PHP syntax, Python syntax, generated inventory and documentation checks are local evidence at this point. Native database matrices, browser rendering/screenshots, independent review and full integrated release acceptance remain pending. Complete commercial order-derived ownership, health/status, native controls, domain/VPS lifecycle and SSH remain unfinished. No live operation, deployment or release is claimed. Next: run native inventory matrices, fix findings, run actual packaged browser cases and integrate this bounded increment with #127 after review; preserve stable main acceptance.


## Cloud setup message correction — 2026-10-15

Starting at1b6ede97, independent review [4280333908](https://github.com/afterburn25/Cajun_Web/pull/127#discussion_r4280333908) and the actual packaged checkpoint38699874602 found a fresh-owner setup message defect: no saved profile was incorrectly described as unprepared inventory. The cloud view now tells a fresh owner to save the connection, reserving preparation guidance for existing profiles with old/incomplete storage. The fresh-owner assertion also requires the correct message. The failed run is preserved; lint/docs/generated checks and a corrected browser checkpoint are required. Separate cloud assignments remain unfinished. No live request or release is claimed.


## Fresh installer version correction — 2026-10-15

Independent review of cloud checkpoint 86660e34 identified that the fresh installer still stamped inventory version 3 after creating version 4 tables ([finding](https://github.com/afterburn25/Cajun_Web/pull/127#discussion_r4280167474)). This correction updates the installer stamp and adds actual fresh-package assertions for version/table/column agreement and the owner's provider page without redundant preparation. Generated inventory is refreshed. The earlier cloud native matrix passes 660 and reader matrix 996 named executions; those do not prove this new fresh-install correction. The running old browser checkpoint is preserved; its reports will be inspected before a corrected checkpoint is dispatched. No release or live operation is claimed.


## Cloud inventory implementation — 2026-10-15

This development branch `codex/twentyi-cloud-inventory` starts at the stable 1.24 candidate `906df7278ba9c3ae9d8f51ae49e480f686f91cee`. It is separate from [PR #126](https://github.com/afterburn25/Cajun_Web/pull/126), whose exact full application run [38694814738](https://github.com/afterburn25/Cajun_Web/actions/runs/38694814738) is continuing through recovery/Apache. That candidate's corrected browser checkpoint passes 1,371 named assertions and its independent review found no major issues. No 1.24 publication is claimed, and this development branch does not replace or restart its acceptance.

The next implemented path extends the existing InventoryReader, fixed transport, durable refresh engine and owner screen with GET /cloud_server. It retains bounded documented metadata and original CPU/memory/disk units, drops unknown response values, separates cloud/package histories and rejects mismatched account/operation snapshots. Inventory v4 adds nullable intent operation (old null intents mean package reads) and a cloud observation table without rewriting original rows. Missing stamped tables require original database recovery. Cloud observations grant no client ownership and cannot displace package data. Current owner authorization, shared cooldown, immutable results, credential-context fencing and audit rollback remain in the existing engine.

Local PHP 8.4 default/no-ini passes 82 cloud reader/transport cases each; the existing 139 inventory and 28 package-reader cases also pass. Native PHP/database cases and real packaged HTTP/Chromium cloud scenarios are implemented but not yet run. They cover populated v1/v2/v3 upgrades, interruption/repeat, retained package/client isolation, mixed and failed cloud reads, consent/rate limits and desktop/mobile screens. The new owner UI, persistence and migrations remain unverified until those runs pass. No live cloud read, provider write, purchase, mail, SSH or deployment has occurred.

Next: refresh generated shipping mirrors, push this checkpoint, run the reader and native inventory matrices, inspect failures, then run the bounded packaged/browser checkpoint and independent review. Add explicit cloud/client ownership, remaining discovery and native control workflows after the underlying path is verified. Continue first-increment release acceptance on the stable branch; exact-main and downloaded-release verification remain required. The full owner API-reference source/link is still pending, while primary public documentation supports this specific read.


## Order-derived local hosting identity

Parent5728c2df, PR138: add one native order/service association with exact commercial and provider-request context, atomic audit/replay and missing-history guards; preserve existing hosting/provider engines. Parent actual browser artifact and four order screenshots are now inspected. New runtime validation is pending Linux CI after the local Windows key-permission refusal; no protection was relaxed. Mapping does not verify provider entitlement or authorize dispatch. [Exact scope, evidence, limitations and continuation](docs/TWENTYI_ORDER_SERVICE_LINKS.md).


Current follow-up:8adf passed all four native combinations, including42 new association checks within277 combined checks. A reproduced future-pause/end timing defect is corrected with four additional effective-boundary cases; exact descendant validation remains pending. See [details](docs/TWENTYI_ORDER_SERVICE_LINKS.md).

### Unreleased verification checkpoint — 2026-10-15

- Verified parent client-search HTTP/Chromium and actual desktop/mobile screens, merged-head native matrices, and exact Windows packages. Full combined acceptance and independent review remain pending; published1.24 is unchanged.

### Unreleased20i mailbox implementation — 2026-10-15

- Add exact mailbox creation validation to the existing provider execution path, requiring a dedicated protected-secret resolver and refusing generic approval. Preserve safe password handling and at-most-once dispatch after unknown outcomes.
- Native mailbox management screens and authorization/persistence are still being implemented; this checkpoint does not activate mailbox creation.

## 2026-10-15 — registrar preparation safeguard and verified native matrix

Parent2fe7e2ff, PR142: all four downloaded native artifacts verify60 cases plus64 registrar reader cases per PHP mode. Adds early transaction refusal before registrar DDL and three rollback/no-table regression checks; syntax passes, new native execution pending. Browser38819247971 is preserved and running; root retains combined acceptance/integration/releases. [Details and artifact hashes](docs/TWENTYI_REGISTRAR_INVENTORY.md). No provider operation or release.

## 2026-10-15 — registrar native/browser evidence and capture handoff

Parent8b4a101a, PR142: four actual artifacts verify63 native +64/64 reader cases each; source2fe7e2ff checkpoint verifies1,810 HTTP/Chromium checks (36 registrar),62 named reports, empty unexpected diagnostics and exact source/package bytes. Owner desktop and client screens inspected. Owner mobile element capture caught sidebar/sticky-header overlays; test-only navigation/settling/full-page correction awaits root combined screenshot inspection. Shipping source unchanged by this final checkpoint. Combined recovery/Apache remains pending; root owns integration/releases. [Exact evidence](docs/TWENTYI_REGISTRAR_INVENTORY.md).

2026-10-15 cloud preparation review correction, starting `2febba4eef5f658a46de33b9b905108d41cb47d6`: `provider_operations_migrate()` now rejects an inherited database transaction before acquiring the staff lock or performing DDL. The native cloud fixture checks fresh preparation creates no table/marker and preserves rollback of an unrelated sentinel write. This closes the implicit-commit risk without changing storage versions or recovery records. Changed PHP syntax and documentation checks pass; installer refresh is included. Corrected-head Linux acceptance remains required. Next: verify the new native and packaged reports, then integrate through the parent branch.

The recovery query at2febba4e failed native MySQL8.4/PHP8.2 run38820482050, job116715582421, with an illegal mixed-collation CONCAT. The enclosing correction casts all audit-prefix/ID/suffix operands to binary, retaining exact request-ID matching on both database engines. The failed report is preserved; later acceptance must verify this compatibility correction.

## 2026-10-15 — Cloud website native acceptance and handoff

Startingce8d4e89, record41 corrected native checks per PHP8.2/8.4 and MariaDB10.11/MySQL8.4 combination in run38821117816, verified from all four downloaded artifacts. Root review found no further source issue. Installer refresh15011, changed PHP syntax, all2,662 indexed hashes and documentation checks passed. Earlier4aa browser checkpoint verifies1,792 HTTP checks including18 cloud checks and four inspected screenshots. Exact corrected full38821117748 passed native and remains in browser; complete application/release acceptance remains pending. No runtime edit, provider call, deployment or release in this documentation checkpoint. Next: root sequential integration, combined installer generation and exact combined acceptance. See cloud evidence for retained failures, hashes and limitations.

## Unreleased — Cloud allocated-package confirmation

Owner-reviewed exact package observation and explicit original client assignment with atomic immutable history and recovery guards. Provider acknowledgement stays pending; no current server membership, health or billing activation claim. Native/browser acceptance pending. [Guide](docs/TWENTYI_CLOUD_CONFIRMATION.md).

## 2026-10-15 — preserve native evidence and stabilize future-time fixture

Parent0ea6e3a8, commit `Stabilize cloud confirmation future-time fixture`. Focused run38827979320 reports45 creation plus74 confirmation named cases passing in PHP8.2/MariaDB10.11, PHP8.4/MariaDB10.11 and PHP8.2/MySQL8.4. Downloaded all four artifacts and independently verified published SHA256, ZIP CRC and exact named outcomes. PHP8.4/MySQL8.4 artifact12015643012, SHA256 `1fff2cf36c61f1c47229deeecc789c83c916f8afeceeb3f7ea46a4acb72ba112`, passes45 creation cases and19 confirmation cases before the future-time assertion fails. Its synthetic timestamp was precomputed at now+31; preceding database work consumed a second, making that input valid at the production now+30 allowance. Change only this fixture input to now+120, preserving the production cutoff and assertion. Shipping files and installer inventory remain unchanged. Preserve the failed report and original full run38827979412. Syntax/documentation/whitespace checks accompany the bounded correction; fresh focused native acceptance is required. No release, live provider, payment, mail, SSH or deployment.

## 2026-10-15 — exact cloud confirmation native acceptance

Parentc3b2c022, commit `Record cloud confirmation native and combined browser evidence`. Exact focused [38828579877](https://github.com/afterburn25/Cajun_Web/actions/runs/38828579877) completed successfully on c3b2c022336fe55a93e685220352a6b5e8230782. All four independently downloaded artifacts match their published SHA256 and pass ZIP CRC, with45 unique creation and74 unique confirmation named outcomes each. This verifies the corrected future-time refusal, sequence-first authority, original-context integrity, exact link/audit pairing, rollback, concurrent confirmation and later-reapproval historical projection on PHP8.2/8.4 with MariaDB10.11/MySQL8.4. The original fourth-combination timing failure remains retained separately; shipping is byte-identical to0ea6e3a8. Full38827979412 remains in progress, last observed after package success during native integration; no new confirmation browser/recovery/Apache acceptance or release is claimed.

Independently verified root2239 browser checkpoint12015677899 from38826150967: SHA256 `630b6400d8af6bb3725f6d20207f5b8880fad8bf3eb097738ab5c4d62b3f715b`,259 ZIP members with clear CRC,1878 passing named HTTP outcomes including18 cloud website-creation cases. Inspected actual owner/client desktop and320px screenshots: pending status and accurate capability wording are visible, controls stay contained; owner mobile nested fields are narrow but have no overlap. PHP diagnostics and unexpected browser errors are empty. This is original creation UI evidence, separate from the new confirmation UI. Root owns the normal integration and release. Next: inspect original0ea full browser/checkpoint/screenshots and final recovery/Apache packages, preserve prior runs, and verify combined descendant acceptance. No live provider calls, payment, mail, SSH or deployment.
## Unreleased - observed domain registration periods

Add explicit owner refresh of documented registration-year options and saved observation/history displays. Clients see only currently offered extensions under their current enabled search policy. Preserve complete-empty, failed, pending and changed-setting states; retain independent history witnesses. No renewal eligibility, prices, reservations or purchasing are inferred. Native, browser and package acceptance remain pending.

## Domain recovery and echo guard integrated — 2026-10-16 UTC

The owner's continued20i request is implemented by a normal merge of root0b4c068d and exact a132b5b3, preserving red28b62223 and corrected f725 history. The current-date projection now requires latest registrar/date intent order to agree with the independent common-start audit; decoded credential echoes cannot become saved dates. This checks latest selected authority, not immutable ordering of every older record. Synthetic date-shaped keys establish local defense only, not real provider exposure.

All four focused38851489059 artifacts were downloaded and verified:98 native plus87 normal/87 minimal reader cases each. Root and catalog source reviews are clear. The domain module/reader, native/pure regression cases, generated inventory and feature guide are integrated; root's concise status/handoff and period-owner fixture are preserved. Combined application acceptance remains required.

Original25e full38846792746 is terminal failure from the retained browser quota fixture failure; recovery and Apache pass and final source-check is skipped. Final12025544003 awaits independent archive verification. PHPcfc6 native38850899544 is verified in all four environments; its normal merge and isolated browser owner are next, then one combined full acceptance. Pure expiry38850341309 passes both PHP versions but remains outside the application. Latest mainc0e087027 is under read-only licensing review. No release, live request or deployment occurred.

## Combined domain and PHP observation candidate — 2026-10-16 UTC

The owner's complete20i request continues through a normal merge of domain integration4b78a59b and frozen PHPcfc6ecd0. Exact a132 domain shipping remains; cfc6 PHP scalar observation, current hosting authority, order/credential defenses, explicit client sharing, storage and forms join the candidate. Shared seven-file preview is root/catalog reviewed, preserving positional domainContext read6/refresh8, named packageContext read7/refresh9, exclusive typed writers, all period/cloud/date hooks and scheduler guard. Automatic navigation/router merges are separately checked; transport mirror and installer inventory are regenerated.

Source-focused evidence is complete: domain98+87+87 and PHP99 native executions/98 distinct labels+99+99 in all four PHP/database environments, with actual archives verified. Perioddd116 and PHP85ea7586 dedicated-owner fixtures preserve original authority, every prior intent/audit byte, production20/hour budget and real cooldown. The PHP collision was predicted from source, not observed combined execution. Browser order is dates/cloud/period/PHP/scheduler-last. New combined full acceptance remains mandatory; no additional small-repair standalone full is dispatched.

Original25e full38846792746 is terminal FAILURE and final12025544003 is fully verified:122 reports/10 diagnostics,2130 HTTP passes plus the retained empty-period failure,289 recovery/2167 Apache,all319 earlier files unchanged,11 checksums/exact Git and Windows package agreement. Final source-check was skipped. Original domainf725 browser has the same retained failure; its full and PHPcfc6 full remain preserved through final evidence. Pure expiry139+139 on each PHP version passes separately; domains begins isolated application wiring outside this candidate. Latest mainc0e087 source review retains licensing migration/master-delivery findings and identifies direct full-master-key display; Devin retains ownership. No live call, charge, customer mail, SSH, deployment or publication occurred.

Next: finish exact staged/build checks, commit/push and record one combined full, unchanged independent review, current-main reconciliation and exact-main/download release gates. [Current status](PROJECT_STATUS.md) and [handoff](SESSION_HANDOFF.md) identify authoritative next actions.

## Frozen combined427 acceptance checkpoint — 2026-10-16 UTC

Starting source427e3a233706f22cd39918d8b97256a7fb2b1e90 is pushed in PR151. Its normal domain4b78/PHPcfc6 histories, period/PHP test owners and runtime are frozen. CI merged106dd95 has identical tree925ec3ba. This commit changes continuity documentation only; site/foundation/tests/tools/workflows remain identical to427.

Exact Windows build47263 completed; independent verifier checks all11 checksums, safe case-unique CRC-clean members, directories, Git bytes and decoded update for FULL5612/COMPLETE2760/UPDATE2747. Source-specific distribution and proof are preserved. Actual427 domain/PHP/cloud four-way artifacts are downloaded/verified (98+87+87;99 native executions/98 labels+99+99;98+85+85). Period/scheduler artifacts remain assigned to catalog. Full38853750274/job116820490485 passes packaging and runs native integration;34 of39 workflow gates succeed and5 continue as of05:11UTC. No browser/final success is inferred.

Original PHPcfc6 browser12026415411 verifies2093 HTTP checks,28 PHP-feature cases and six actual inspected views; its full remains in recovery. Original domainf725 retains the known period browser failure and continues Apache. Root25e final failure is fully preserved. Next lanes stay separate: expiry app wiring, pure DNS proposal validation and pure unsent cloud catalog request contract. Their supplied references do not establish missing DNS/cloud response schemas. Latest licensing review remains with Devin. No live operation, release or deployment occurred. Next: finish exact combined acceptance/review, current-main reconciliation and exact-main/download release gates, then continue full20i.

## 2026-10-16 15:10 UTC - Integrate protected cloud assignments

Owner request: continue full20i while Devin completes non-20i. Parent819c3ea3; commit "Integrate protected cloud client assignment authority" applies reviewed39-input union, bounded legacy/active-client corrections, dynamic-port focused workflow, canonical inventory and continuity. Original full819c exposed absent-operation legacy inventory readiness; preserve proof8e973156 and fix only the affected readiness branch, retain all original upgrade assertions and add15. Focused matrix now requires12 reports. Local evidence:90 binding/34 extracted-client/6 renderer and19 readiness cases per PHP mode;14 fixture-helper and171 transfer-helper cases, plus syntax. Native/HTTP/visual/package/current-main/release acceptance remains pending. PR159 review299bf71e and GitHub comments6218434878/6218437910 give Devin two remaining licensing gaps plus stale inventory failure. Preserve original failures and focused/full819c and9779 runs. Next: first new-source focused matrix, then combined evidence. No live operation or published-asset change.

## Main documentation synchronization

Main documentation checkpoint `d7153b076e91b524ff2f99a9a7aa5c910551fc5e` publishes the same owner release freeze, canonical b98b96eb branch and scope/map to make them discoverable. Its12 Markdown-only changes add no runtime, installer, schema or version delta. The release branch normally merges that checkpoint; its existing release-specific status and all prior histories remain, rather than copying main's pre-integration runtime wording. Both sources agree on frozen capabilities, licensing corrections, complete supported acceptance, published1.24 and no live deployment.
