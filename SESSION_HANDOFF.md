## 2026-10-18 — cloud endpoint supplied no catalog details

The separate [cloud-only diagnostic](docs/TWENTYI_CLOUD_ROOT_DIAGNOSTIC.md#original-follow-up-outcome--2026-10-18), original run39090973425 at source `bf788894b1acd4c07daf74e718c4f95774acef85`, completed successfully after 42 passing offline checks. Its one fixed GET observed a JSON `null` root. This establishes the response type for that request and credential context, not an empty catalog, missing entitlement, provider fault or valid import contract. No cloud product fields were supplied. The original two-operation run39090394774 remains failed and unchanged; this later observation cannot retrospectively identify its scalar kind.

Continue the hosting catalog import using the observed `id`/`label` fields and documented package-type ID relationship. Do not substitute cloud data or automatic provider images. Three agents now own the hosting projection/importer, durable observations, and focused tests; root coordinates the admin flow and integration. No repeated cloud request is planned without new evidence. Published 1.25.0 and production remain unchanged. Earlier preparation blocks are historical.

## 2026-10-18 — separate cloud response-type check prepared

The [first catalog diagnostic](docs/TWENTYI_CATALOG_SCHEMA_DIAGNOSTIC.md#original-outcome--2026-10-18) remains failed and preserved. A separate [cloud-only follow-up](docs/TWENTYI_CLOUD_ROOT_DIAGNOSTIC.md) addresses its missing root-kind observation with one fixed metadata GET. It never repeats the hosting catalog request. Only a finite JSON type or the same closed container shape is exported; values, URLs and unknown names remain withheld. This source checkpoint prepares the follow-up, without claiming a live outcome or a valid cloud catalog. Hosting import implementation proceeds separately from the observed hosting fields and documented type-ID relationship. Released 1.25.0 is unchanged.

## 2026-10-18 — preserve first catalog outcome; review bounded follow-through

Previous checkpoint: https://github.com/afterburn25/Cajun_Web/blob/35d8fb7efc94f6f851399d0755fa93097f1fce66/SESSION_HANDOFF.md

## CI queue state — 2026-10-18 (Devin non-20i heads)

The three fix waves re-queued ~5,000 lanes across 51 heads at the
repo's limited GitHub concurrency (~3–5 jobs). Completed lanes are
green with zero failures, but external confirmation drains slowly.
Local verification substitutes in the meantime:

- `tests/acceptance_artifacts_test.py` passes on every head
  (221–226 checks; contract pins, recipe extensions, baselines).
- `tools/build.py --commit` assembles all archives end-to-end
  (locked packages, runtime mirrors, PHPMailer pins, installer
  inventory over LF bytes, ops/ area approved).
- All 51 `devin/non20i-*` PRs report MERGEABLE; each stacked child
  contains its parent's fix commits.
- Superseded-head runs were cancelled; GitHub concurrency groups
  auto-cancel further stale runs per branch.

Re-check `gh api repos/afterburn25/Cajun_Web/actions/runs` for
failures as the queue drains; re-dispatch long lanes after Codex
retargets stacked PRs. No merge, deployment or release claimed.

## Devin non-20i lane — 2026-10-18 contract/recipe wave

Two further CI defects fixed after the LF-inventory wave. First, main
integrated `ops/` diagnostic trees but `build.py`'s full-project
`allowed` set predated them — `'ops'` added on all 51 heads, matching
the identical fix on `codex/twentyi-direct-hosting-catalog`;
`tools/build.py --commit` now assembles all archives end-to-end.
Second, 13 heads wire existing feature-native tests into the
selected-runtime recipe (153–154 commands vs pinned 152); following
the `provider_package_types` pattern, each carries a
`non20i_native_command_extension` contract entry plus a strip-check
in `tests/acceptance_artifacts_test.py`. All 51 heads pass the full
artifacts suite locally (221–226 checks) and every stacked pair was
re-merged after each fix wave. 167 stale-head CI runs were cancelled;
current-head lanes are queued — results pending.

Latest tips: portal-account `45ba4d97`; client-actions `904604ff`+ops;
mail chain tip `b9f96940`; ticket-notifications `47e81656`;
ticket-screenshots `935c634d`; stage-history `1a537618`;
recurring-billing `88f03ccb`; renewal-notifications `12400816`;
client-contacts `a49b6409`; contact-decisions `935bfcfa`;
contact-activity `bb0de709`; staff-notes `362a2365`; milestones
`828f2720`; activity-prefs `c3c24671`.

## Devin non-20i lane — 2026-10-18 LF-inventory fix status

Follow-up to the mass restack: CI failures on restacked heads were
traced to installer inventory hashes recorded over CRLF worktree bytes
(ad-hoc text-mode writes during conflict resolution) while
`.gitattributes eol=lf` commits LF content. Regenerated
`site/install/inventory.generated.php` over LF bytes on 43 heads and
updated `tools/acceptance-contract.json` pins where each branch's own
sources legitimately changed pinned test files (contract diff keeps
the change reviewable). 14 heads were already clean; 6 old
`devin/twentyi-*` heads predate the contract. Each fixed parent head
was then merged into its stacked child (32 merges), regenerating
inventory/contract per child so every stacked pair stays mergeable.
Four literal merge-marker lines committed long ago in
`docs/SESSION_LOG.md` were removed, preserving all genuine entries.
Full local build gates pass on merged heads. CI re-queued; results
pending. Branch-CI evidence only — Codex owns retarget/integration;
no merge, deployment or 20i work claimed.

Latest tips after fix: portal-account `9f3c3a10`; client-actions
`c585f06d`; activity-prefs `701444e4`; milestones `fb7054e1`;
staff-notes `6633d1bf`; contact-activity `55997ad1`;
contact-decisions `0bff1c3f`; client-contacts `b4459a45`;
stage-history `fb8b67cf`; recurring-billing `7eea8733`;
renewal-notifications `6c8b55fc`; service-plans `6c10933f`;
mail chain tip mail-html-compose `6d02642c` (mail-flags `8173ac6e`,
mail-folders `0a70f154`, mail-send-attachments `f28a752a`,
mail-reply-all `93eedc3e`, mail-sent-sync `21b454ff`,
mail-attachments `bffb875b`, mail-replies `28a78132`);
ticket-notifications `4d18132e`; ticket-screenshots `d4f82eb1`;
support-tickets `2c64853f`; notification-events `fd16844a`;
notification-outbox `33e050f2`; notification-metrics `6ed0786a`;
announcements `7f036c55`; notifications-runtime `e0aec1c2`;
comparisons `966905e4`/sources `fa3023a3`; backups `c01b138b`;
pipeline `68c085f5`; proposals `62730615`; revisions `f175d53a`;
backup-restore-drill `ddd765a4`; backup-database-export `9eef281a`;
protected-previews `539c3a40`; payment-links `251e8254`;
project chain progress `d7baad67` / planning `87e61633` /
design-payments `f65bb674`.

Next: Codex retargets stacked PRs as parents integrate; combined
acceptance after retarget, not from branch state.

## Devin non-20i lane — 2026-10-18 restack status (superseded heads)

All 51 open `devin/non20i-*` PRs reported MERGEABLE after a full
dependency-order restack: each advanced parent head (and main
`e397d9a6`, post-Codex PR #218) was merged into its Devin children;
conflicts resolved by keeping canonical/main versions where strictly
stronger and re-applying each branch's unique deltas. Those heads
were superseded by the LF-inventory fix above.

Prior restack tips: portal-account `05ecd881`; client-actions
`95de0a9c`; activity-prefs `70b8c74b`; milestones `15216b37`;
staff-notes `a0ef2679`; contact-activity `3c3aa80e`;
contact-decisions `8c269b33`; client-contacts `5224eafc`;
stage-history `577d9925`; recurring-billing `ce895bab`;
service-plans `938efb3c`; mail-html-compose `88428719`;
renewal-notifications `86f03b05`; notification-outbox `38a3786d`;
notification-events `38fe2e41`; ticket-notifications `522686b3`;
ticket-screenshots `f410b588`.

## Devin non-20i lane — 2026-10-17

All earlier Devin PRs verified green: #198 protected previews `21198602` (48/48), #202 payment links `95a3db9b` (48/48), #203 company contacts `37324121` (48/48), plus stacked #200 stage history `93cb8f3a` (43/43), #204 contact decision attribution `c70dc0e0` (37/37), #205 contact activity routing `58c5d4af` (41/41) — long lanes for stacked PRs run after Codex retargets them to main.

Current tips (stacked chain on devin/non20i-contact-activity):
- #209 `devin/non20i-staff-notes` @ `3e6db257` — staff-only internal project notes via `studio_messages.author_type='internal'` (no schema change); portal conversation allow-lists visible author types. `3e6db257` corrects a flash-assert regression in `d203b5ea`.
- #210 `devin/non20i-milestones` @ `868e967c` — additive `studio_milestones` (state + optional YYYY-MM-DD target date) via existing `studio_migrate`; admin manage forms, portal visibility, milestone activity events.
- #211 `devin/non20i-activity-prefs` @ `3b8b5ffd` — additive `activity_prefs`; per-actor hiding of update types from feed/unread/open/mark, re-run `activity_setup` provisions on existing installs.
- #212 `devin/non20i-client-actions` @ `01dce055` — read-only derived "What we need from you" (pending version decisions; issued invoices when billing access applies) and "Open blockers" (blocked milestones) on the portal project dashboard; no schema.

php -l + test-compile + canonical inventory pass on all. Component lanes queued (GitHub runner starvation); long lanes run after Codex retargets to main.

No merge, deployment, provider call, email, payment or SSH occurred. Codex owns 20i integration and release coordination; Devin owns licensing and non-20i roadmap items only.

## Devin review corrections — 2026-10-17 (round 2)

Owner review produced four findings; all corrected and pushed:

- #203 `devin/non20i-client-contacts` @ `52f524bc` — GREEN 48/48 incl. native-and-browser. Revoked/disabled contact no longer degrades to primary-client identity (full auth context invalidated at lookup failure); bundle download requires `contact_can('downloads')`; contact multipart upload accepted with `author_type='contact'` attribution.
- #202 `devin/non20i-payment-links` @ `40eb7f29` — `payments_master(true)` refuses a replacement key when sealed `payment_requests.link_secret` history exists, even with gateway credentials absent/cleared. `40eb7f29` also fixes a bound-parameter bug: `table()` returns a backtick-quoted identifier and never matched `information_schema`; uses the unquoted prefixed name. Matrix re-running.
- #209 `devin/non20i-staff-notes` @ `82c62c98` — GREEN 43/43. Stale `admin/views/studio/job.php` inventory pin refreshed.
- #212 `devin/non20i-client-actions` @ `b85d87db` — GREEN 41/41. Action links navigate via `focus_review` so a pending decision outside the 100-row window lands on its exact decision form.
- #213 `devin/non20i-portal-account` @ `480b0fcd` — GREEN 41/41 (docs head).

## Devin review corrections — 2026-10-17 (round 3)

Owner follow-up reviews produced three more work items; all corrected and verified green:

- #203 `devin/non20i-client-contacts` @ `9015ef8f` — GREEN 48/48 incl. native-and-browser. Upload write boundary serializes under `staff_locked` + fresh `FOR UPDATE` client/contact/permission revalidation before any private-file or row write; job locked with expected client id; rollback cleans the file. Native coverage: four committed interleavings (disable, rotation, permission removal, client deactivation) via worker processes. `fcebfd08` fixed a test-side bug (billing contact has no upload form to `submit()` through — forged POST instead); `9015ef8f` added a bounded warm-up + decomposed print to the shared organized-update OPcache probe after a deterministic-but-external `rewrite 1.17.0` probe failure (bisect run 39067511358 on previously-green `40eb7f29` confirms environment, all probes now report `cached:true`).
- #213 `devin/non20i-portal-account` @ `3411de28` — GREEN 41/41. `studio_password_change` is atomic: fresh `FOR UPDATE` actor reads under `staff_locked`, current password verified against the locked row, credential + audit committed in one transaction, session rotates only post-commit. Native coverage: interleavings + audit-failure rollback. HTTP test now asserts revocation, re-signs the contact, one persistent session for CSRF GET+POST, DOM identity verified. Branch restacked (`de343e95` merge) after base `91f4599e` touched the same test file.
- #212 `devin/non20i-client-actions` @ `5e1e043d` — GREEN 41/41. `91f4599e` added the required boundary regression: pending review + 101 newer versions of another kind, follow the generated `focus_review` link, assert the exact pending review and its decision form render; other-job and superseded controls preserved.

Round-4 review (comment 6239686025) found a latent fixture interaction in that regression: the 101 filler versions' `review:` events displace `review:<vid>` from the first activity page (30-row window, `occurred_at DESC, event_key DESC`), breaking the later feed assertions — and broad `kind:<id>` substrings can false-match other ids. Corrected:

- #212 `devin/non20i-client-actions` @ `6bf64fce` — boundary block moved after all activity-feed assertions; feed checks match exact `data-event-key="kind:id"` attributes. Component lanes re-running; `native-and-browser` (which exercises `contacts_cases.py`) verifies on retarget per convention.
- #213 `devin/non20i-portal-account` @ `9a5534a8` — same fix propagated via base merge `f4835eda` plus session-log record.
- Round-3 reviews confirmed: #203 source fix accepted, #213 implementation accepted; #202/#209 accepted previously.

Reconciled the reported `organized_update_cases.py` single-probe state: the bounded warm-up + diagnostic lives on `devin/non20i-client-contacts` tip `9015ef8f` and is intact; `portal-account` legitimately lacks it until the base propagates — no revert to reconcile.

Round-4 reviews delivered two more corrections plus a full stack propagation:

- #210 `devin/non20i-milestones` @ `72ba94c3` — GREEN 19/19. `2e06f185`: milestone targets require real calendar dates (`checkdate()`; `2027-02-29` refused, `2028-02-29` accepted); milestone events are revision-qualified `milestone:<id>:<hash>` so a later update resurfaces unread and an older ack cannot consume it — read-then-update covered for primary client and projects-permission contact.
- #211 `devin/non20i-activity-prefs` @ `5d325631` — GREEN 19/19. `0cd5ce7a`: the pref-isolation check now uses the client-authored reply (eligible for staff feeds) with exact `data-event-key` identity; admin-authored key asserted absent (staff author filter preserved).
- PR203 dependency propagation per #212/#213 follow-throughs: contacts `9015ef8f` merged forward through contact-decisions (`308805de` 17/17) → contact-activity (`17f0227b` 18/18) → staff-notes (`b4d25087` 19/19) → milestones → activity-prefs → client-actions (`66cc00b7`) → portal-account (`eb6c8082`, docs tip `4f72f3fb`). Union merges preserved every feature; inventory pins regenerated.
- Combined acceptance on the first propagation heads FAILED deterministically at `tests/native.php` "Additive upgrade creates seven workspace tables" — stale count now that #210's `studio_milestones` makes eight (runs 39073761930 / 39073810828; both native lanes, not yet HTTP).
- `0be1fc7a` on `devin/non20i-milestones`: the check now compares against `studio_schema()`'s sorted key set. `fecf93f9`: client + contact suites submit the superseded milestone key over HTTP and assert the mark is rejected while the new revision stays unread. `9ab408b0` on activity-prefs: receivable-kind assertion also requires `milestone` membership.
- Feed-check root cause RESOLVED: `/existing/` is seeded from retained 1.0.8 (`legacy-schema.json`) so `activity_schema_version` can never exist on `ci_` — `activity_ready()` is FALSE until the owner's `activity_setup` runs. Earlier 'Form missing activity_setup' (run 39079050602) was a transient card-render miss, misread as "already enabled".
- `a7b0dfeb` on milestones: order-tolerant `activity_setup` submit before the feed check + `activity_schema_version` marker assert. Verified by run 39084652934 (`c87d1060`): staff_cases fully green including `Milestone change enters client activity feed`.
- Ordering collision fixed `6136f042`: `activity_cases`' setup probes became direct POSTs with state-invariant assertions (work whether `/existing/` starts enabled or disabled); staff_cases' enable-submit is idempotent.
- Session-loss collision fixed `2774c60d`: the `Client deactivation` check destroys alice's session (`studio_client()` unsets `$_SESSION['client_id']` when `active=0` — row restore does not restore the session). The milestone-revision block re-authenticates alice through the real portal login.
- Boundary-block INSERTs fixed `4c172d6e` (client-actions): two raw `INSERT INTO ci_studio_versions` omitted `feedback TEXT NOT NULL` → `fixture_db.php` exit 255 under strict mode. Never executed before because every prior run died upstream.
- Debug dump removed `1b272c84`; final propagated heads: milestones `1b272c84` → activity-prefs `e9c266d3` → client-actions `73f9e861` → portal-account `1339e29f` (docs tip `e99d2aec`).
- **COMBINED ACCEPTANCE GREEN — run 39092079427 on `1339e29f`: SUCCESS** (native, HTTP/Chromium all suites, recovery, Apache ±rewrite, source-mutation, evidence). Also green on `436e6def` (run 39089736893, same code + inert dump). Component lanes: 43/43, 43/43, 41/41, 42/42. One `mariadb:10.11` container-init infra flake passed on rerun.

Next: the stacked chain (#203 → #204 → #205 → #209 → #210 → #211 → #212 → #213) has verified heads end-to-end — Codex performs sequential retarget/integration; long lanes re-run per retargeted parent. Devin fixes failures on exact new commits, then continues licensing / remaining non-20i scope.
Start this documentation follow-up from `1159648f968d46439f7e88a9cd0f6dbee3758643` on `ops/twentyi-catalog-schema-read`. Original [run 39090394774](https://github.com/afterburn25/Cajun_Web/actions/runs/39090394774), attempt 1, failed after 39 passing offline tests: package-type field/type observations were retained, while the cloud operation ended with `unexpected_root` and no retained root kind. Its safe artifact is [copied byte-for-byte](docs/evidence/twentyi-catalog-schema-39090394774.json); [the diagnostic record](docs/TWENTYI_CATALOG_SCHEMA_DIAGNOSTIC.md#original-outcome--2026-10-18) binds the source, job, artifact and limits.

Next, review Domains' hosting follow-through against these observed spellings/types and VPS's proposed single cloud root-kind read. Keep unknown-key semantics, complete catalog coverage, provider prices and image contracts unresolved. The original failure is not a successful import and must not be retried automatically. This documentation-only update changes no diagnostic code or workflow and triggers no new provider read; root owns applying it with a documentation commit message. No provider writes or image downloads occur here. Preserve immutable release `b79c6711` and its published assets. Earlier pending-read statements below describe the preparation checkpoint, before this original execution.

## 2026-10-18 — next original catalog metadata read

The published release remains immutable b79c6711. Root's `ops/twentyi-catalog-schema-read` branch starts from documentation main bdbdf3e1 and adds the reviewed [catalog diagnostic](docs/TWENTYI_CATALOG_SCHEMA_DIAGNOSTIC.md). Preserve its first run, checkout identity and shape artifact after execution. Do not rerun automatically or treat lexical field/type observations as proven product/pricing/image semantics. At this source checkpoint the read is pending; no actual provider response is claimed. Owner-requested import work authorizes these two necessary metadata GETs only; this diagnostic performs no provider writes or image download.

# CajunWebPro 1.25.0 release handoff

## 2026-10-18 — resume after verified publication

[CajunWebPro 1.25.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.25.0) is published and freshly download-verified from immutable source `b79c67110829126916e3a65e616191542448bccc`, tree `f862cc9205e073513f419cdf5ff24cacd1f801df`, after the normal [PR180](https://github.com/afterburn25/Cajun_Web/pull/180) merge. All 18 release assets match their accepted bytes; 1.24.0 is unchanged. This is a GitHub release, not a live deployment.

The acceptance, assembly, draft and public verification stages are complete; [the publication record](docs/RELEASE_1.25.0_PUBLICATION.md) binds the original runs, all 330 reviewed canvases and the exact public proof. Preserve the released tag/assets and all earlier failed/cancelled evidence. This documentation follow-up starts from `b79c67110829126916e3a65e616191542448bccc`; its later commit does not become the release source.

For the existing website, use UPDATE and the same-release migration instructions. Never delete installed configuration or locks to run setup; fresh start is a separate explicit option and does not recover missing original keys. No live deployment has occurred.

Next: Codex resumes held 20i work from the documented gaps and existing branches; Devin continues non-20i proposals separately. The full owner API reference is already retained. Root is preparing a reviewed, bounded read-only schema diagnostic for the owner-requested catalog import, limited to GET /reseller/*/packageTypes and GET /reseller/*/cloudServerProductData; no writes, arbitrary endpoints or image downloads. It has not yet executed. Unaccepted Devin proposals still require exact-head review and fresh acceptance before later integration.

Earlier dated candidate and branch checkpoints below retain their original evidence and failures. Their pending-work statements do not supersede this publication record.

## 2026-10-17 — resume from delivery selector correction

The enclosing correction starts from `de97e63b3354f52ee26508bfc0609e951a6ee20c`. Original default run[39047519450](https://github.com/afterburn25/Cajun_Web/actions/runs/39047519450) failed browser acceptance at the delivery manifest: generic `summary` matched the new navigation groups as well. Preserve its seven original archives and failed-job logs. The two manifest clicks now use `.delivery-detail > details > summary`, with an additive exact fixture pin; shipping source and every release gate remain intact.221 helper checks and12 actual Chromium checks with exact PHP renderers/synthetic rows passed; this is not native/HTTP/full application acceptance.

Require a fresh exact corrected candidate, default PHP8.3 and four supported runtime runs, maintained checks and330 actual images. Keep earlier de97 runs/proofs as parent evidence; their build/recovery/Apache and maintained passes do not certify the successor. Then normal-merge to main and complete fresh exact-main/publication/download gates. Main remains `d7153b07`,1.25 unpublished,1.24 immutable and production unchanged. Devin's separate proposals have concrete review feedback on PRs202,203,205,209,210,211,212,213; preserve those branches and resolve their findings before later integration. Full20i catalog/image fields remain unresolved; the owner has already supplied the retained full reference and must not be asked to resend it.

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

Owner-requested correction on `codex/installer-layout-1.25.0`, starting from `bc5ec277580aa681f43aaf2c9b97df730f6954f1`. Server checks move left on desktop; Website details follows Website files; the exact-permission report and redundant one-form sentence are removed. Administrator setup, activation, reset and account changes use a minimum of 12 Unicode characters and the existing 72-byte maximum. Client password policy is unchanged.

Ordinary file replacement retains configuration and settings. Missing configuration with a surviving local installation marker now shows recovery guidance rather than redirecting into setup. An optional, default-off **Start a fresh website** choice selects separate tables and external private storage, retaining previous data. Configuration and installation locks still block setup; no old tables, locks or media are deleted. Retained data is not a recoverable backup without its original configuration and encryption keys. See [behavior and verification](docs/INSTALLER_FRESH_START_1.25.0.md).

Local password boundaries and isolated PHP/Chrome layout checks passed within their documented scope. Native database, complete fresh-start/install/file-overwrite acceptance, final source review and release acceptance remain pending. Parent bc5 has verified Windows/four-Linux build partitions and maintained compatibility (54 reports, 3,280 named executions). At 15:02 UTC its five original full runs still had native checks active, with successful build/recovery/Apache jobs. Those parent results do not certify this shipping change. Main remains `d7153b076e91b524ff2f99a9a7aa5c910551fc5e`; 1.25 is unpublished, 1.24 immutable and production unchanged.

Next: finish independent source/UI review and focused fixtures, commit this branch, normal-merge into the release lane, then bind fresh complete candidate acceptance. Preserve original bc5 runs/evidence and all prior failures. Publication still requires exact-main acceptance and fresh verification of all 18 assets.

## 2026-10-17 - complete acceptance job allowance

The enclosing correction starts from `88f116f0caf4a9bd8ea6f7faafd1e0eadb432039`. Its supplemental PHP 8.3/MariaDB [run39024070818](https://github.com/afterburn25/Cajun_Web/actions/runs/39024070818) reached the final scheduler flow before GitHub cancelled the combined native/browser job at its three-hour limit. All seven original archives and five completed logs are preserved. The checkpoint is incomplete, not a pass. Independent timing review supports increasing only this job's overall allowance from 180 to 240 minutes. Every test command, native-to-browser state, assertion, application/request/browser/provider timeout, report and image requirement remains unchanged. See [the timing evidence](docs/ACCEPTANCE_JOB_BUDGET_1.25.0.md).

Evidence cutoff: **2026-10-17 13:51 UTC**. Parent88f has three fully verified original environments (both PHP 8.2 databases and PHP 8.4/MySQL), maintained/minimal compatibility, all four recovery and Apache/published1.24-upgrade partitions, and actual328-image review with independently checked coverage and explicit capture limits. PHP 8.4/MariaDB was still running. These are parent results; the correction still requires fresh exact-candidate full acceptance and review, normal main merge, fresh complete exact-main acceptance/images, all18 assets, draft/public fresh-download verification and the owner UPDATE handoff. The corrected default PHP8.3 full run must also finish; the interrupted run cannot stand in for it. Main remains `d7153b076e91b524ff2f99a9a7aa5c910551fc5e`;1.25 is unpublished,1.24 immutable and production unchanged. No new20i capability is added.

Later observation, **2026-10-17 13:58 UTC**: required PHP 8.4/MariaDB run `39024087120` also concluded cancelled after its combined native/browser job reached the three-hour cap; final aggregation failed. Its original build, native database, recovery and Apache phases passed, while browser acceptance remains incomplete. The original job annotation and five logs are retained; archive collection is in progress. This does not change the earlier cutoff or certify a passing candidate. Local validation of the correction passed all 215 acceptance-helper checks and the documentation check (265 Markdown files, 1,781 local links). Root and independent source review confirmed that the complete workflow differs semantically only in the aggregate 180-to-240-minute allowance. Shipping code and tests are unchanged.

## 2026-10-17 - verified bounded StackCP correction integrated for full acceptance

Normal integration preserves parent `60c053b5c9f5a5fded943930500ea57cddd20ff8` and reviewed correction `fd1d4799de8c72cb88a2426f5811101f788446e0`; the enclosing merge commit identifies the new candidate. All four original 60c full runs and the supplemental PR run failed and remain preserved. The MariaDB lanes timed out loading the prepared access section; both MySQL lanes failed the latency-sensitive scheduler assertion. Their partial passes do not certify the correction.

Original isolated [diagnostic run 39023255633](https://github.com/afterburn25/Cajun_Web/actions/runs/39023255633), attempt 1, passed on PHP 8.3.6 / MariaDB 10.11.19: 78 native, 56 extracted-package HTTP and eight runtime checks. On its retained twenty-card dataset, access-section database statements fell from 6,293 to 2,823 and elapsed time from 3.951 to 1.264 seconds. Four complete 30-second-load browser navigations completed in 2.279 / 2.017 / 0.442 / 0.436 seconds with unchanged data/key hashes. All 2,887 COMPLETE members match exact fd1d Git and all 2,886 installer hashes agree. Root 61 and separate 90 evidence checks passed; the latter reviewer authored the fixture and reviewed execution evidence, not independent authorship. The runtime/fixture source had separate independent reviews. These are bounded 21-link diagnostic results, not accumulated full-browser or PHP 8.2 performance proof. Four diagnostic PNGs grant no credit toward release image review.

The additive full workflow now runs the 78-check comparison immediately after the prerequisite snapshot fixture: 149 exact native recipes, 161 browser / 205 final reports and 124 source pins. All 148 preceding recipes, original report requirements, 320+8 images and timeouts remain. The finite seed receipt is diagnostic metadata and cannot mask a failed native report. All 215 offline helper regressions passed; whole-object/source inverse reviews found no weakened gate. The scheduler fixture retains exact event + 1,800 seconds within its request window, with every other outcome/privacy assertion intact.

Evidence cutoff: **2026-10-17 10:12 UTC**. Required next: fresh exact-candidate Windows/four-environment/maintained acceptance, actual 328-image review and independent aggregate review; normal main merge; fresh complete exact-main acceptance and images; 18-asset draft/fresh-download verification, publication and separate fresh public verification. Main remains `d7153b076e91b524ff2f99a9a7aa5c910551fc5e`; 1.25 is unpublished, published 1.24 is immutable and no production deployment occurred. New 20i capabilities remain frozen until verified publication.

Continue the release-first scope in [status](PROJECT_STATUS.md), [frozen scope](docs/RELEASE_SCOPE_1.25.0.md), [integration map](docs/RELEASE_1.25_INTEGRATION_MAP.md) and [release workflow](docs/RELEASE_WORKFLOW.md). The main35d8/mature3fa integration and subsequent main documentation merge already exist; do not repeat feature imports. Preserve the current licensing serialization, provider authority, original histories and installer/source parity.

Use actual source-bound receipts to determine the next unfinished gate: complete candidate acceptance and independent visual/source/package review; normal main merge; complete exact-main acceptance; immutable tag/release assets; then fresh all-asset download verification. Do not infer completion from this handoff or a prior source. Preserve original failures and reviewed corrections. New capabilities and the external PHP snapshot proposal remain held until publication is verified.

Keep all148 original native recipes plus the new access snapshot comparison (149 total), complete PHP 8.2/8.4 × MariaDB10.11/MySQL8.4 environments, the maintained/minimal lane and the current161 browser / 205 final report contracts. Actual published 1.24 upgrades must preserve original keys/data/history in root/subfolder and both rewrite modes, including interruption/recovery/reapplication. Recurring/licensing did not exist in stock1.24; their post-upgrade preservation is a separate check.

The tracked [acceptance report](docs/TEST_REPORT_1.25.0.md) has an explicit source/evidence cutoff. Final exact-main and publication facts belong in the external release report and receipts once those events occur; do not amend an accepted source merely to insert its own future test results. Verify identical versioned/compatibility report asset bytes before publication. No live deployment is authorized. Resume remaining20i only after the release and downloads are verified.

## 2026-10-17 - paired domain fixture timestamps

Continue from parent `c0ed18397349a023224623027ba5bfd3bf9d7e67` with only the reviewed timestamp correction in `tests/domain_expiry_native.php` and `tests/domain_contracts_native.php`, their two acceptance-contract pins and these five continuity documents. Capture one `time()-600` value for both rows in each fixture; do not relax the production equal-epoch guard or any assertion. The full source-binding inventory becomes 122 pins; all 148 native recipes, 160/204 reports, 320+8 images, workflows and timeouts remain. Root owns application, commit and fresh identities.

At **2026-10-17 06:24 UTC**, original PHP 8.4/MySQL run39005276959 had failed after 67 domain-expiry passes, skipped Chromium and failed its final aggregate. Its seven archives/five logs remain preserved; the other three full runs were active. Parent c0ed's build, maintained, recovery and Apache passes are bounded evidence only. Review the actual correction and local evidence, freeze/build/bind its observed source, then complete every candidate/maintained and visual gate before normal merge, complete exact-main rerun and all-asset publication/download verification. No future commit/run/Windows outcome is assumed. Main `d7153b07`, published 1.24 and production stay unchanged; 1.25 is unpublished.

## 2026-10-17 - scheduled-run browser response completion

Continue from the narrow correction to parent `cc0ec4a9b4131498831fb761d4a2d7f93ade4604`: wait for the scheduled-run Chromium page load before the separate requests client fetches its next form, and pin the corrected fixture in the acceptance contract. All original requests, assertions, timeouts, captures and shipping/workflow bytes remain. Independent actual localhost reproduction passes 13 checks with the same 15-second request timeout; its 20-second render tail is synthetic and does not profile the application database.

The original PR run38994351830 failure after 3,003 passing checks remains preserved with all seven artifact archives and completed logs. At **2026-10-17 05:07 UTC**, all four original cc0 full runs are still executing Chromium; their build, native workflow stages, recovery and Apache jobs passed. Complete parent browser acceptance and actual image inspection remain absent. Retain their eventual outcomes; do not count them as corrected-source evidence. Build and bind the actual descendant, run every complete candidate/maintained lane and review all 328 images, then normal-merge and repeat complete exact-main acceptance before publication and fresh verification of all 18 assets. Main `d7153b07`, immutable 1.24 and production remain unchanged; 1.25 is unpublished.

## 2026-10-17 - exact WordPress client projection assertion

This release-only correction starts from `d55a347ec0dec8e351e6cf325e3f3a16cba3a821`; its enclosing commit identifies the corrected candidate. The original [PR acceptance run](https://github.com/afterburn25/Cajun_Web/actions/runs/38989916313) failed the WordPress client projection assertion after 34 successful named checks. Its preview source has the exact candidate tree. The fixture searched all JSON for sibling package digits `2202`, which also occur in a valid timestamp and can occur in an opaque observation ID. The failed per-call projection was not retained; source inspection and modeled reproduction establish the assertion defect without claiming that operand was captured.

Continue from this correction, preserving the exact original failed run and its artifacts. The fixture change and contract pin are the only executable/contract differences from d55; shipping/workflow bytes remain unchanged. Rebuild and bind the actual corrected source, dispatch its original complete four-environment matrix plus maintained lane, review exact artifacts and actual pixels, then follow normal merge/exact-main/publication gates. Do not use earlier passes to certify the new source.

## 2026-10-17 - payment throttle fixture boundary correction

This release-only correction starts from `9e7b62e9520ad728744bfde62cff0e5ab1dce37f`; the enclosing commit identifies its descendant. Evidence cutoff is **2026-10-17 01:12 UTC**. Original PHP8.2/MySQL full run [38985873076](https://github.com/afterburn25/Cajun_Web/actions/runs/38985873076) failed the native payment assertion `Authentication attempts are rate limited` after32 passing checks. Its job ran across01:00 UTC, a fixed15-minute rate-window boundary. Source inspection and a deterministic reproduction support the rollover explanation; original per-call bucket rows/timestamps were not retained. The failed report, original logs and incomplete full run remain preserved and are not accepted.

The isolated `payci_` fixture now prepares the current and immediately following owner-specific counter rows, calls the actual payment verifier once, requires its precise throttle refusal with no provider call or gateway/audit change, verifies exactly one counter increment, and restores the original two rows in `finally`. Unexpected errors, other state changes and clocks outside those two windows fail the assertion. All54 literal check-label occurrences remain in order. Shipping code, rate-limit policy, workflows, all148 native commands,160 browser/204 final reports and320+8 required images are unchanged; the acceptance contract pins the corrected fixture.

Local validation consists of12 source/syntax/inverse checks and13 modeled boundary/fault cases using the exact production limiter/verifier and proposed fixture with deterministic clock and in-memory database boundaries. These are not native database, HTTP or full application acceptance. The first probe's12/13 result is preserved: its harness incorrectly expected unrelated deliberately injected mutations to be erased. The corrected harness verifies refusal, restoration of only the fixture's own rows and continued visibility of the unrelated mutation; it did not change the proposed fixture.

Parent9e has verified Windows/four Linux package agreement and a complete maintained/minimal runtime lane. Those bounded passes do not certify its failed full matrix or this descendant. Next: build the corrected source, run all four full environments and maintained lane, inspect actual browser/upgrade images, obtain independent exact-candidate review, normal-merge to main, rerun complete exact-main acceptance, and publish/freshly verify all18 assets. Main remains `d7153b07`;1.25 is unpublished,1.24 immutable, complete20i unfinished and no live deployment occurred.

## 2026-10-17 — PHP 8.4 upgrade fixture compatibility

This release-only correction starts from `155838eb8574dcc96f7d038cc306677d2d4784a0`; its enclosing commit identifies the descendant. Both original PHP 8.4 full runs, [MariaDB 38975755408](https://github.com/afterburn25/Cajun_Web/actions/runs/38975755408) and [MySQL 38975766343](https://github.com/afterburn25/Cajun_Web/actions/runs/38975766343), passed native integration and stopped at HTTP check126: `upgrade setup cannot be reused after update` (125 pass, one fail). The installer was physically absent; the retained request logs show HTTP200 for its removed URL. That response body was not retained, and the original failed reports/artifacts remain preserved.

The [PHP built-in server manual](https://www.php.net/manual/en/features.commandline.webserver.php) documents a PHP8.4 change: a missing filename can use the directory index. Six related fixture assertions incorrectly required the pre8.4 HTTP404 behavior. The corrected shared helper retains physical installer absence before and after real GET/POST requests, rejects redirects and setup UI, and permits the documented200 fallback only on observed PHP8.4 when its complete homepage DOM matches a separately fetched index after normalizing the one validated CSP nonce. All six original named checks remain; earlier runtimes still require404. Shipping code, Apache404 expectations, database assertions, all148 native recipes and the160 browser/204 final report requirements remain unchanged.

Local validation: 66 offline helper/source guards and an independent 12-scenario/29-request real PHP8.4.25 helper run pass. The latter uses synthetic HTML and does not establish full application acceptance. Root confirms all273 existing named checks in the six edited fixtures remain unchanged. Independent source review found no concrete blocker in this narrow correction.

Parent155 has verified builds across Windows/four Linux environments, all four Apache/published1.24-upgrade/recovery partitions, the maintained runtime lane, and the supplemental licensing flow. Eight upgrade and six supplemental licensing images were actually reviewed. These are bounded parent results; neither failed full run is accepted. The corrected descendant still requires its complete four-environment/maintained acceptance, actual browser review, a normal main merge, complete exact-main acceptance, and publication with fresh verification of all18 assets. Main remains `d7153b07`; v1.25.0 is unpublished, v1.24 is immutable and no live deployment occurred.

## 2026-10-16 — separate licensing page and action refusals

Start the fixture-only correction from `f77c9fb7f4b1b57d13ce352dd7e4fc51b1330695`. The shared licensing HTTP helper incorrectly expects an action-denial page after posting to an owner-only view. Actual run38965358937 preserves HTTP403 and the section-denial message at check39; all four native114-check jobs pass. Root and independent source review identify the same later nonowner legacy-review mismatch. Production authority remains correct.

Retain the twenty exact response records, six images, all existing history comparisons and strict denial expectations. Add actual staff-login and exact403/no-history-change page checks; route only the two ordinary-staff POSTs through the accessible dashboard. Align the changed helper's acceptance-contract hash. No shipping or workflow change is needed. Intended commit: “Separate licensing page and action refusal checks”. Evidence cutoff is **2026-10-16 22:03 UTC**; corrected real execution is still pending.

Run the corrected packaged licensing diagnostic first, using a temporary diagnostic ref if needed to avoid dispatching a full matrix before the blocker clears. `codex/release-1.25.0` remains the sole release integration branch; use the same reviewed descendant there, not an alternate release source. Then bind and run every required complete candidate/maintained lane, actual visual/package checks, normal merge and complete exact-main acceptance before eighteen-asset publication and fresh download verification. Preserve f77's original failed/partial evidence and any explicitly retired incomplete runs. No production deployment or previous-release modification.

## Historical checkpoint — licensing fixture and release runtime provisioning

Batch the reviewed release-only corrections from exact `8f54d36e418ee112ae5c3cb8ac4c63ceb277be32`: one licensing helper import and contract hash, one supplemental runner/job, four guarded PHP 8.2/8.4 mirror selections, and five continuity records. Intended commit description: “Repair licensing fixture and release runtime provisioning”. Preserve original signing scope/key bytes and current fingerprint, strict APT/TLS/probes and every application assertion. Distro PHP 8.3 stays unchanged. Shipping code is unchanged.

Keep the parent-evidence cutoff at **2026-10-16 20:43 UTC**: two build-only Linux/Windows agreements; two PPA 503 setup failures before application tests; three focused native environments × 755 plus distro 260, with one native job pending. Retain the original 13a HTTP failure and missing historical stderr separately from the proven missing-workspace reproduction. B42's 666-check/eight-image actual upgrade diagnostic is supplemental parent evidence. Exact receipts are recorded in the test report; subsequent parent results stay in external receipts.

The new licensing diagnostic is unexecuted. The mirror has source review, 41 offline guards and four shell syntax checks, not an APT installation result. Verify both on the committed descendant, then complete all four full environments and maintained runtime, actual visual/package review, normal merge and complete exact-main acceptance before publication and fresh download verification. Preserve old runs and prior release assets. No new feature, release clearance or live action is included.

## Historical checkpoint — package acquisition retry and retained b42 evidence (2026-10-16)

Base this combined correction on frozen `b42dda4cf763ca48a90b145673214a9641b2e024`; do not invent its descendant identity or reuse parent acceptance. Batch only the reviewed APT acquisition policy (`6dd1e097`, workflow `dacb9292`) and neutral/historical instruction addendum (`76906652`) with these checkpoint records. The policy changes eight updates/eight installs, uses five acquisition retries and update error mode `any`, and leaves application tests, package selection, signatures and TLS unchanged.

Preserve b42 exact Windows proof `45f34633` (5915/2887/2874 members), e713 focused maintained-cloud proof `36aaf524` (four 755-execution environments plus 260 distro/minimal checks), and original setup classification `fe776245` (eight PPA HTTP 503 failures: seven DEB/one index; two successful same-runtime/source comparisons). Source/script review is not a package-acquisition or application pass. Older failed/cancelled records and immutable 1.24 assets remain intact.

The original b42 upgrade diagnostic 38951170365 attempt 1 was in runtime setup at the retained 19:15 UTC snapshot; no pass is claimed. Root follows that original run. Only after actual diagnostic success should the frozen descendant receive a new complete four-environment matrix and maintained lane. Bind new evidence helpers to its observed source/run IDs, preserving earlier chains. Required exact-candidate/main, upgrade, image, package and downloaded-release gates remain. Last verified main is `d7153b07`; no 1.25 publication or live deployment has occurred.

## Historical checkpoint — actual upgrade fixture correction and focused diagnostic (2026-10-16)

The correction starts from `e713623cb93c98642ca8fd1dd61f66a9b72112e2` on `codex/release-1.25.0`; the enclosing Git history identifies the corrected source. The original13a full run [38942023179](https://github.com/afterburn25/Cajun_Web/actions/runs/38942023179), Apache job117113081566, passed its prior Apache checks but stopped after five actual1.24 upgrade checks during original mail-account seeding. Failure proof `ab76032d` retains the source/preview tree, artifact digest,116-member partition and explicit exception. No upgrade scope or new upgrade capture completed.

Root applied the independently reviewed three-file document-root correction (`dc7072d1`, root proof `3d2c62d5`), aligned its three acceptance-contract source hashes and added one supplementary PHP8.3/MariaDB10.11 diagnostic workflow (`059dee57`, review `7ad08c6c`, root application `7d02f281`). The legacy CLI fixture now receives the actual validated Apache public root. Its old callers retain their defaults. All production privacy checks and complete148-command acceptance workflow remain intact. The diagnostic builds its exact source, uses only the pinned published1.24 package, exercises all four layout/rewrite scopes and preserves bounded evidence after success or failure. It cannot replace complete candidate/main acceptance.

Actual e713 focused [probe38946655478](https://github.com/afterburn25/Cajun_Web/actions/runs/38946655478) passed both PHP versions: seven reports and1,063 named executions each, verified proof `84a92ff2`. Actual e713 focused [ForceSSL38946655205](https://github.com/afterburn25/Cajun_Web/actions/runs/38946655205) passed all four PHP/database environments: seven reports and876 named executions each, verified proof `6553f1dd`. These results certify their focused source only. Original failures and earlier cancelled partial evidence remain preserved.

Local correction evidence is limited to28 portable path/guard cases,47 diagnostic YAML/AST/source/evidence cases,664 documentation proposal checks, syntax and source review. The subsequent original1.24 form audit (`7549afae`) found no further concrete mismatch on inspection; it is not HTTP execution. Next run the first corrected-source focused upgrade diagnostic, resolve any concrete failure, then run the complete original four-environment matrix and maintained lane on the frozen corrected source. Inspect actual images and packages before normal merge; repeat complete exact-main acceptance before publication and fresh all-asset verification. At this source cutoff main remains `d7153b076e91b524ff2f99a9a7aa5c910551fc5e`,1.25 is unpublished and no live deployment occurred.

## Historical handoff checkpoints

The records below retain their original source-specific instructions and failures. They do not override the release sequence or current versioned evidence above.

### Historical checkpoint: Observation-fixture correction checkpoint — 2026-10-16

Canonical `codex/release-1.25.0` remains in [draft PR #180](https://github.com/afterburn25/Cajun_Web/pull/180). This correction starts from `0614089482073e8779f7b130b0c8306f7d1f6c6f`; its enclosing commit records the new exact candidate. Frozen included/held features are unchanged. Only four native observation fixtures and continuity documentation change; application, schema, installer, workflows and named test sequences remain unchanged.

Original [Force SSL workflow 38942022993](https://github.com/afterburn25/Cajun_Web/actions/runs/38942022993), source13a/merge-preview8d6, failed in the inherited PHP-observation privacy assertion after29 passing PHP checks. ForceSSL119, document-roots115 and pure165/165 checks passed first; the other three environments passed all7 reports/876 executions each. Four archives, metadata and the failure log are retained. The original compound assertion did not retain its projection, so the exact historical failing operand remains unknown.

Exact-source controlled projection reproductions prove that blanket JSON substring `702` can reject valid random public references or observation timestamps. PHP, ForceSSL, directory-listing and document-root fixtures now compare the complete permitted client shape, exact scalar/map, timestamp and current flag, plus the exact receipt only where exposed. Extra private fields and sibling data remain refused. All original named assertions and sibling/other-client/support checks remain. Safe mismatch diagnostics contain booleans only.200 extracted-code checks across four fixtures/default/minimal PHP and four syntax checks pass; these do not substitute for native database execution. Independent source review is retained with the correction.

Parent061 Windows packages pass exact source, member/directory/checksum, installer and updater checks (5914/2887/2874 members). Parent13a cloud run38941928822 passes four native755-execution environments and260 distro/minimal checks. The frozen external full-run verifier has independent reviews and111 additive/132 preparation checks, including retained original keys and published1.24 upgrades. These are bounded evidence, not full candidate acceptance.

Parent061 original full runs38943147018/38943159324/38943171219/38943182554 and maintained38943195376 stay preserved; they cannot certify this new source. Next: push the corrected candidate, rebuild exact packages, dispatch its original complete four-environment matrix and maintained lane, verify all actual results/captures and review the exact source. Then normal-merge, repeat complete exact-main acceptance, publish and download-verify every asset. Main remains `d7153b07`; stable1.24/tag/assets remain unchanged. No1.25 release or live deployment exists.

### Historical checkpoint: Frozen candidate CI checkpoint — 2026-10-16

Candidate `13a7b08cc8f8ac1cf2a5e1ea6b4027a28cf2a597` (tree `62eeed74de3fa89347589ec5fce5be1ebdd069b5`) is pushed on `codex/release-1.25.0` in [draft PR #180](https://github.com/afterburn25/Cajun_Web/pull/180). The four original full runs `38941882420`, `38941894576`, `38941906749` and `38941919998` failed during runner setup: selected PHP8.2/8.4 apt packages were unavailable after the cached runtime setup. Application/native/browser/upgrade tests did not run in those attempts. All eight original artifacts, metadata and failure logs remain retained; repair provisioning before starting corrected full runs. No gate or existing test is removed.

Original licensing run [38941836904](https://github.com/afterburn25/Cajun_Web/actions/runs/38941836904) passes 114 exact named native checks in each of four supported environments (456 executions,114 distinct cases), including actual missing-history, interrupted-setup, master isolation and invoice-review cases. Downloaded archives, runtime versions, PDO/ZIP, hashes, CRC and source/attempt identity were independently verified. This is native licensing evidence only. Exact13a independent migration/authority/upgrade review records66 checks across38 files with no remaining P1/P2 within that scope. Clean Windows FULL/COMPLETE/UPDATE packages match exact Git bytes, installer inventory, strict updater metadata/shims/payload, all checksums and directory/member lists (5914/2887/2874 members). They remain candidate artifacts.

The default PHP8.3 PR run `38942023179` builds the separate merge-preview SHA `8d6c90287d9417e073aa4178d33ebac4fa6cc51b`; its successful build cannot substitute for exact-branch full acceptance. Focused probe run `38942022550` failed because the inherited test sent a delete-operation body to the strict renewal endpoint. The reviewed fixture now uses a valid renewal body for the authority gate and separately checks malformed renewal rejection before authority/network; local default/no-INI/disabled-cURL results pass52/53/53 checks. Both original artifact archives and failure logs remain preserved. No shipping behavior changes. Main remains `d7153b07`, published1.24 remains immutable, no1.25 release exists and no production deployment occurred. Full supported runs, actual packaged HTTP/Chromium and populated upgrades, pixel review, final independent review, normal merge, complete exact-main rerun and publication/download checks remain mandatory.

The correction commit following13a changes only the full-run provisioning workflow, the stale probe fixture and continuity documentation. PHP8.2/8.4 CLI/extensions/Apache now use the same signed Ubuntu Noble package repository; PHP8.3 keeps its distribution path. Every148 native recipe, runtime probe, report, timeout and remaining workflow step is retained. The integrated transfer/provenance helper still passes208 checks. These source/local checks require fresh complete CI at the correction commit.

### Historical checkpoint: CajunWebPro release handoff

Continue **release 1.25.0 first**, not feature expansion. Read [status](PROJECT_STATUS.md), [frozen scope](docs/RELEASE_SCOPE_1.25.0.md), [integration map](docs/RELEASE_1.25_INTEGRATION_MAP.md) and [release workflow](docs/RELEASE_WORKFLOW.md). Canonical `codex/release-1.25.0` starts at main `35d8fb7efc94f6f851399d0755fa93097f1fce66` and normally merges `3fa72d7b3b447fa3d281cc981ed861c508f850f2`. Git history identifies the resulting integration SHA. Main and all published assets remain unchanged during candidate work.

The shared union keeps main serialized licensing/checksums/marker3 and candidate physical/support/invoice-purpose guards plus provider inventory5. Root applied the independently reviewed16-file licensing correction and optional-storage fixture fix, preserving selected PHP runtime handling. Master keys stay outside client deliveries; populated older adoption is additive; a retained invoice boundary prevents automatic duplicate historical fulfillment; explicit owner review requires exact evidence/password. Future markers and missing established tables refuse recovery bypass. Native/HTTP acceptance is still required. Preserve both native test sets and setup-form-first ordering. Root owns shared edits and installer regeneration.

Catalog owns additive full PHP8.2/8.4 × MariaDB10.11/MySQL8.4 acceptance wiring, including actual selected Apache/runtime evidence; retain distro/minimal compatibility and every existing native/browser/report requirement. Domains owns authentic published1.24 root/subfolder rewrite-on/off client/project/file/invoice/support/mail/provider preservation and repeat/interruption/recovery/reapply tests. Recurring/licensing absent from actual1.24 must be tested as post-upgrade additions, never invented baseline tables. External proposals need root review before application.

Source3fa original focused38933590118 passes all four environments (1,440 named executions each) and Windows5890/2885/2872 package verification. Evidence `efa6da57` and `faa38a5b` is retained; parent5bfd five-job cloud/distro proof `ab93db13` is also retained. These are not combined-release acceptance. Original cccf/2a failures and9779 timeout remain immutable; do not rerun unchanged sources or replace historical evidence. New PHP snapshot proposal stays external and held.

After exact candidate gates and independent review, normal-merge to main, rerun complete acceptance at exact main, build FULL/COMPLETE/UPDATE and all reports/manifests/checksums, publish immutable v1.25.0, then download and verify every asset. Deliver the UPDATE package and owner backup/migration instructions. Do not stop at planning, build, candidate checks, merge or tag. No live deployment authorization. Then resume complete20i incrementally.

Devin's separate assignment and posted repairs158/159/161 remain visible in [start here](DEVIN_START_HERE.md); preserve dependent PRs through178. Release integration does not silently accept those PRs. Main remains documentation checkpointd7153b07; candidate parent85f0b489 preserves it normally. [Acceptance report](docs/TEST_REPORT_1.25.0.md) and [owner update instructions](docs/MIGRATION-INSTRUCTIONS-1.25.0.md) are now tracked. The approved portal concept image is unavailable and its dedicated shell is not implemented; preserve existing layout and do not claim a visual match. Published1.24 remains stable; complete20i remains unfinished.

The final licensing recovery correction also checks surviving audit history when both tables and setup markers are missing. It refuses silent replacement and preserves exact interrupted fresh setup. Independent source review and 33 portable migration cases in each PHP mode pass; native SQL and full release acceptance remain pending.
