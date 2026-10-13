# Project status

Updated: 2026-10-12 — owner confirms latest version is 1.1.0; current artifact location pending.
Repository: `afterburn25/Cajun_Web` · Authoritative branch: `main`.

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
