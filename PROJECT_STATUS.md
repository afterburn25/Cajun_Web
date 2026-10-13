# Project status

## 2026-10-13 — Candidate passed; exact main release gate next

Candidate `91ef30d7cb218a132b514b02f60d4e9e6c3fdbe9` passed [application acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38355321670): **50 native MariaDB checks and 143 real HTTP/Chromium checks**, package validation, non-root filesystem cases and source consistency. Repository and independent access/invitation checks also passed. Reviewed implementation screenshots for public pages, portfolio, installer and client/admin workspaces. Corrected a mobile capture taken mid-transition by using reduced motion; added final explicit browser admin controls, favicon-reference and sitemap acceptance. These test additions require a complete rerun.

[PR #8](https://github.com/afterburn25/Cajun_Web/pull/8) combines recovered application `90ba4f5e0c824e422de8cb2232f5d3ee92b7bec6` with main `b28a67cdbf20c81842cf73735ee4e6024030262f`. Original branches and independent foundations are preserved. Source is in `site/`; older source-recovery blockers below are historical. Version 1.1.0 remains a candidate until its real release is published and downloaded back.

Next: finish candidate checks, merge PR #8, run combined acceptance on exact resulting main, tag/build only that tested commit, publish complete/update packages and permanent evidence, verify downloaded bytes, update the exact-release Devin handoff and issue #2, close #4, then stop. Later roadmap work remains paused. No live hosting/customer/mail/payment/provider operation is authorized or performed.

Devin PR #7 remains intact at `55d6067`; issue #2 also records a separate estimator component intent on `devin/lead-estimator`. Inspect both before handoff and avoid duplicating already delivered estimator functionality. Review numeric-category and ambiguous-slug edge cases in the standalone portfolio component during its next bounded increment.

## Historical checkpoints — superseded where noted

## 2026-10-13 — Native regression fixes

First MariaDB acceptance found a real schema-idempotency failure: digit-bearing `sha256` was omitted by the column parser. Fixed both column-name patterns without weakening type/engine/unique-key checks. Local native tests then passed, and real unprivileged PHP HTTP tests completed fresh one-page installation and physical cleanup. Also fixed the existing lead editor's seven-status/six-label mismatch and corrected the test's portfolio route to the actual `work` endpoint. Tests now run extracted package bytes and cover incompatible schema preservation, cross-client/CSRF rejection and disguised executable uploads. Public service copy was polished and official comparison sources rechecked on 13 October. Complete browser acceptance and release publication remain pending. No customer host or database was changed.


## 2026-10-13 — Source recovery checkpoint

Recovered 41 actual website files plus native/HTTP tests and a build tool from the nine preserved source fragments. Original external assets were fetched only at the recorded public paths and checked against recorded SHA-256 values. Executable source is now under `site/` on `codex/finish-1.1.0`. This supersedes the older no-source-in-Git blocker on this branch, NOT the unverified runtime or release claims. No production files, database, mail or payments were changed. Review `docs/SOURCE_RECOVERY_2026-10-13.json`; run native integration and authorization tests next. No 1.1.0 release is declared by source recovery.


Updated: 2026-10-12 — GitHub testing/release workflow and Codex/Devin split added; current 1.1.0 artifact location pending.

Updated: 2026-10-13 — independent staff/mailbox permission component started while the separate 1.1.0 worker finishes the application.
Repository: `afterburn25/Cajun_Web` · Authoritative branch: `main`.

## Current repository delivery and coordination checkpoint

**Next A-05 increment, 2026-10-13:** inspected recovered `site/` source on `codex/finish-1.1.0` at `4a8b4c4`, without modifying that worker's branch. The draft is now available for inspection; it is not yet an accepted release. Corrected the independent access context to use the actual opaque string session tokens. Added [staff invitation persistence](docs/STAFF_INVITATIONS.md) with expiry, replacement/revocation and transactional one-use activation, plus synthetic native MariaDB concurrency/rollback tests. Source `718dbc4` in [PR #6](https://github.com/afterburn25/Cajun_Web/pull/6) passes 146 access and 46 native invitation checks on PHP 8.2.34/8.4.26 with MariaDB 11.4.13; [branch](https://github.com/afterburn25/Cajun_Web/actions/runs/38351494316) and [PR merge-ref](https://github.com/afterburn25/Cajun_Web/actions/runs/38351532791) workflows passed. Staff UI, complete account lifecycle and final 1.1.0 integration remain pending.

The owner confirms another worker is finishing 1.1.0 and will update GitHub, and authorizes independent parallel implementation now. Refreshed main is `9e30564ee83d8e859c0d420ae42d6bbd805a6a57`; its [1.1.0 progress report](docs/PROGRESS_1.1.0_INTEGRATION.md) describes that worker's local integration, not source imported here. The `codex/finish-1.1.0` branch currently carries recovery/test-toolchain preparation. Preserve its ownership; no need to ask the owner for the same build again.

On `codex/platform-foundation`, implemented a standalone PHP 8.2+ [permission component](docs/ACCESS_FOUNDATION.md) for staff capabilities and explicit mailbox read/send grants in commit `5cf3f01975dec7365d57415eecd061dda43a4823`, [PR #5](https://github.com/afterburn25/Cajun_Web/pull/5). Local PHP 8.4.25 passes 142 isolated checks and syntax checks for four files; [GitHub PHP 8.2.34/8.4.26 checks](https://github.com/afterburn25/Cajun_Web/actions/runs/38349906968) and [PR integration checks](https://github.com/afterburn25/Cajun_Web/actions/runs/38349921814) also passed. This component has no login, database, installer or UI connection yet, and does not complete A-05/A-06. Devin's [assignment #2](https://github.com/afterburn25/Cajun_Web/issues/2) is updated to allow independent portfolio contract/filtering work before 1.1.0 integration. Inspect the PR's current state before resuming.

The owner authorizes GitHub testing, incremental version releases with retained copies, ongoing agent documentation, and separate Codex/Devin work branches integrated as they finish. Added [release workflow](docs/RELEASE_WORKFLOW.md), [release register](docs/RELEASES.md), [agent coordination and Devin handoff](docs/AGENT_COORDINATION.md), and read-only GitHub Actions documentation checks. This workflow does not yet test application code or build packages.

[PR #1](https://github.com/afterburn25/Cajun_Web/pull/1) is merged into `main` at `633f5a1e5c27f0ad626276f2bae75cbc2b88ea66`. Both the [PR check](https://github.com/afterburn25/Cajun_Web/actions/runs/38338949061) and [merged-main check](https://github.com/afterburn25/Cajun_Web/actions/runs/38339033073) passed. These check required continuity documents and local Markdown links/headings only. The sections below are historical checkpoints and do not override this one.

Created separate GitHub branches from that merged checkpoint: `codex/platform-foundation` and `devin/public-experience`. Refresh both from the verified current-source baseline before implementation. Codex owns foundation/integration; Devin's first bounded assignment is [issue #2: W-01 portfolio](https://github.com/afterburn25/Cajun_Web/issues/2). The owner chose to paste that assignment into Devin. Devin has not been dispatched from this chat and is not claimed to be running.

**Integration dependency:** the 1.1.0 worker publishes verified current source; then inspect existing features and adapt independent modules to it. No version bump, release ZIP, payment/provider operation, mail send or deployment occurred here. Historical 1.0.8 remains reference only. Documentation and isolated component tests are not application acceptance.

## Latest requirements — complete client portal and support access

Added `docs/CLIENT_PORTAL_ROADMAP.md`, P-05/06/07 and A-09/10: native verified 20i service controls, explicit existing-service mapping, design progress/updates/approvals, client notifications, versioned website ZIP/data handover, one-click client portal support sessions and separately authorized VPS SSH. Public official 20i documentation supports the broad integration direction; exact account API/entitlement and terminal transport remain unverified.

**Status:** documentation only in [draft PR #1](https://github.com/afterburn25/Cajun_Web/pull/1). No client login, SSH connection, download build, account/provider write or deployment occurred. Recover/inspect current 1.1.0 and verify account capabilities before implementation. All earlier payment/admin-mail requirements remain in scope.

## Explicit platform direction — our own WHMCS

The owner clarified that the business needs its own platform built from scratch for the agreed WHMCS-style functions. `docs/CUSTOM_PLATFORM_BLUEPRINT.md` maps first-party catalog, customer/staff accounts, orders, invoices, recurring billing, 20i service automation, support and admin/portal modules, including Authorize.net design payments and admin email. This confirms the custom-software direction and ties the existing roadmap together; it does not establish any new runtime implementation. Recover/inspect 1.1.0 and preserve current work before foundation development.

## Latest requirement — admin email, team accounts and notifications

Added A-05 through A-08 and `docs/ADMIN_MAIL_ROADMAP.md`: owner-managed additional administrators, mailbox permissions, multiple email accounts with setup and webmail send/receive, reusable compose templates, and unread-email bells in admin and the public header for authenticated admins only. Notifications open the exact authorized webmail message. Defined setup errors, message/attachment safety, template previews, read-state sync and anonymous/cache isolation tests.

**Status:** approved requirements only, added to the existing documentation review [PR #1](https://github.com/afterburn25/Cajun_Web/pull/1). No additional administrator, mailbox connection, outgoing email, deployed notification bell or source implementation is established. Current 1.1.0 source and eventual secure mailbox setup remain prerequisites; existing design-payment requirements are retained.

## Latest requirement — payment forms for web-design services

The owner explicitly requested payment forms for web-design services as well as hosting checkout. P-03 and HC-11 now specify branded Authorize.net forms for full service payments, quote deposits, milestones, final balances and optional recurring maintenance; admin-configured payment requests, secure shareable links, portal invoice payment, receipts and correct project balances. Design-only payments must work without hosting purchases or 20i setup. The core forms belong in M2; richer project/hosting integration remains M4.

**Status:** requirements captured, pending current 1.1.0 source inspection and implementation evidence. Documentation, direction, architecture, decisions and release handoff are synchronized. No card charge, gateway configuration, actual payment form, runtime change or deployment was performed. The artifact-location question below remains open.

## Owner correction — latest version is 1.1.0

The owner explicitly confirmed that **1.1.0 is the latest version** after the verification below. Treat 1.1.0 as the current version to recover and inspect. The verified 1.0.8 copy is historical reference only; its availability does not make it the owner's latest build.

A fresh GitHub fetch still finds main at `43bebd019a6f548d7a059b2c8e259beed3efe561`, with no published releases or newer application commits. The current 1.1.0 artifact has not yet been located or inspected in this chat. The owner has been asked for its local folder, download link, or repository/branch. Prior missing-download records document earlier evidence limitations; they do not override the owner's current version correction. No 1.1.0 implementation or test result is inferred from the version number alone.

**Immediate next step:** Locate and verify the actual 1.1.0 source/package, then reconcile it with Git and the roadmap before implementation. Do not begin new work from 1.0.8 or replace the owner's current installation with it.

## Latest verification — 2026-10-12

Fresh clone and final fetch found GitHub `main` at `43bebd019a6f548d7a059b2c8e259beed3efe561` (October 12, 7:59 p.m. America/Chicago). Both feature branches are ancestors of main; neither contains newer application work. The remote tree remains documentation only. GitHub returned no releases, tags, workflow runs, open pull requests, or open issues.

The owner's local Downloads folder contains both the original and recovered 1.0.8 complete ZIPs. Both are 1,185,879 bytes and match SHA-256 `13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf`. A byte-identical archive and extracted working copy were retained outside the repository. Fresh checks passed: ZIP CRC, all 33 extracted files, all 32 installer dependency hashes, original logo hash, consistent 1.0.8 app/installer constants and one-page layout, PHP syntax for 14 files under PHP 8.4.25, and JavaScript syntax for two files.

This establishes the latest verifiable available baseline as **1.0.8**, not a newly built release. No CajunWebPro 1.1.0 archive was found in the scoped Downloads/Desktop/Documents filename search. No live host, database, SMTP, payment or provisioning tests ran. Source import and original favicon recovery/integration remain pending; the extracted baseline is not yet tracked application source. The accepted hosting/billing roadmap selects **Authorize.net**, superseding the older Stripe proposal in P-03.

## Critical correction

The assistant's latest chat reply claimed an expanded, packaged and locally tested **v1.1.0** and supplied download links. The owner received **Failed to download file**. The v1.1.0 complete ZIP, update ZIP, development checkpoint, previews and operations report could not be found in the active runtime or recovered through the file searches in this session. **Do not treat that reply as evidence of a completed release or passing tests. Its packaging/completion claims are withdrawn.**

At investigation time both `main` and `feature/growth-suite-1.1.0` pointed to documentation commit `089d2d9e7b62416bc10f6a53f9cba8879f644550`. GitHub's releases collection was empty. This recovery commit changes documentation only; it does not add application source or ship the growth suite. See [delivery incident](docs/DELIVERY_INCIDENT_2026-10-12.md).

## Verified available artifacts

| Artifact | Verified here | What it is not |
|---|---|---|
| `CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip` | 1,185,879 bytes; archive CRC passes; 33 files; app and installer declare 1.0.8; archive hash matches the baseline inventory | Not v1.1.0, not a new feature update, not proof of host deployment |
| `CajunWebPro-Exact-Favicon.zip` | 247,403 bytes; archive CRC passes; PNG/ICO images decode; square RGBA PNG sizes 16/32/48/64/180/192/512 and ICO frames 16/32/48/64/128/256 | Not evidence the favicon has been wired into a deployed site |
| `icon(4).png` | SHA-256 matches the source recorded in the favicon archive and the earlier `icon(1).png` | Not permission to replace the horizontal logo |

Fresh byte-identical recovery copies were created as `CajunWebPro-1.0.8-Recovered-Original.zip` and `CajunWebPro-Favicon-Recovered.zip`. The older baseline was not renamed to v1.1.0. These are new attachment paths, not permanent hosting URLs.

## Evidence ledger

| Area | Current status |
|---|---|
| Documentation and approved direction | Preserved in Git; this incident and next steps recorded |
| PHP source in Git | Still pending import; current reachable source tree is documentation only |
| 1.0.8 source distribution | Recoverable locally; source inventory is in `docs/BASELINE_INVENTORY.md` |
| Favicon derivatives | Recoverable, decoded and archive-checked; integration/deployment pending |
| Expanded portfolio, estimator, comparison content and client workflows | Approved roadmap; no complete recoverable release or verified implementation commit established here |
| Native database, image upload, email, HTTPS acceptance | Not run in this recovery session; historical baseline report lists outstanding host checks |
| Running installer and storage issue | Still unverified on the actual host; do not declare fixed |

## Existing baseline and unresolved work

The baseline includes public PHP routes, admin/API/media/mail code, robots/sitemap endpoints, branding and a keyless one-page installer. Its ten schema declarations are settings, users, projects, services, testimonials, leads, media, rate_limits, resets and audit. That inventory is not evidence of the proposed private client portal, invoices, scheduling, approvals or backup/update tools.

- **B-001 — Source continuity:** import the exact 1.0.8 distribution and original assets into Git, preserving byte provenance and excluding secrets. Do not replace the existing PHP application with a mockup.
- **B-002 — Running installer mismatch:** verify the actual served installer path/build; the last owner report included a 1.0.5 wizard. No upload-location/cache explanation has been proven on the host.
- **B-003 — Hosting filesystem:** verify real PHP-worker permissions, permanent storage and cleanup access; preserve customer data/locks. Do not promise root ownership or use 0777.
- **B-004 — Native acceptance:** stage and test actual MySQL/MariaDB, GD uploads, SMTP receipt, HTTPS flows, recovery and cleanup.
- **B-005 — Approved expansion:** implement and verify `docs/ROADMAP.md`; no feature is complete because it appeared in an image or chat summary.
- **B-006 — Missing v1.1.0 artifacts:** no downloadable complete/update package or test evidence recovered. Reconstruct from preserved source only after committing a real baseline; never silently substitute the old site as the requested update.

## Owner direction remains unchanged

Keep the rich navy/gold design and original horizontal logo without .com, use the exact fleur-de-lis favicon, retain PHP shared hosting and one-page/no-key installation, add the full agreed feature suite and accurate Wix/Squarespace comparison content. Prices, payment/calendar accounts and contractual commitments must not be invented. Keep existing hosting files and data intact during recovery.


## 2026-10-12 — Approved custom hosting and billing expansion (documentation only)

The owner approved a much larger CajunWebPro scope: **web design and white-label hosting/domain/VPS sales**, all with a custom PHP/MySQL product catalog, checkout, billing engine, and unified client portal; direct 20i API provisioning; Authorize.net payment integration with the four credential fields in admin and branded tokenized payment forms. The detailed acceptance criteria, dependencies and phases are in docs/HOSTING_AND_BILLING_ROADMAP.md; docs/ROADMAP.md now links these HC-01..HC-14 items.

**Status: approved/not implemented.** No source import, web pages, transactions, customer accounts, provisioning credentials or deployment occurred as part of this planning change. B-001 to B-006 and the earlier installer/storage/download blockers remain open. 20i domain/VPS endpoint support and Authorize.net compliance/live settings require account-specific verification. Begin with exact baseline source import and feasibility checks, not payment activation.

Release acceptance follow-up (parent `f007803`): HTTP/browser checks reached account revocation; console classification stopped on the intentionally invalid estimator response. The next commit scopes that expected resource message to the precise estimator URL, retains diagnostic JSON, adds original-contact and real-browser fresh-install coverage, scans PHP runtime diagnostics, and improves mobile consent wrapping. Full rerun required before publication.

Final matrix test correction (parent `7a0c0dd`): run 38356481572 rejected valid versioned favicon URLs because it compared the query suffix as part of the path. Parse the URL path and fetch the actual referenced icon for byte equality. Application source is unchanged; rerun the full suite.
