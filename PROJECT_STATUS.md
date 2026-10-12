# Project status

Updated: 2026-10-12 · Repository: `afterburn25/Cajun_Web` · Source-of-truth branch: `main`.

## Executive checkpoint

The owner created this repository and requested persistent documentation for every project change and direction decision. The repository was verified empty before this documentation bootstrap.

**This checkpoint adds documentation only. PHP source and original artwork are not yet imported into Git. No website release, feature implementation, hosting change, database migration, or deployment occurred in this session.**

The latest supplied distribution is `CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip`. Its bytes were inspected locally during this session: archive SHA-256 matched the historical report, ZIP integrity passed, 33 files were present at the archive root, and application/installer constants both identify 1.0.8. See `docs/BASELINE_INVENTORY.md`.

## Evidence ledger

| Area | Current status | Evidence or remaining boundary |
|---|---|---|
| Repository access | Verified | Connected GitHub account has push access; default branch is `main` |
| Documentation foundation | Added in this bootstrap | Requirements, direction, backlog, decisions, status, handoff, session procedure |
| 1.0.8 distribution | Inspected locally | Exact ZIP hash, entry list, core/installer headers, schema definitions, branding fingerprints |
| Application source in Git | Pending import | Do not call this documentation tree deployable |
| Public PHP pages and administration | Present in the distribution; not re-accepted end to end here | Public routes, admin/API/media/mail code, schema and assets are in the inspected ZIP |
| One-page/keyless installer | Declared in supplied 1.0.8 source; prior report describes local tests | This session did not run a browser installation; user's running copy has not been verified |
| Live installation | Unverified | Last user reports included version 1.0.5, a multi-step screen, missing-file concerns, root-owned storage, and missing media |
| Storage ownership on host | Unresolved until verified | No live worker UID, ACL, document-root permissions, or writable-parent evidence collected here |
| Favicon requested by owner | Approved, implementation pending verification/import | Exact source is `icon(1).png`; distribution already has a different favicon file, which is not evidence this request was applied |
| New sales/portfolio/client features | Approved backlog, not implemented by this bootstrap | See `docs/ROADMAP.md` |
| Native MySQL/GD/SMTP/HTTPS acceptance | Still requires verified runs | Historical report expressly lists these target-host checks as outstanding |

## Baseline functionality inventory

The inspected distribution includes PHP public pages, an administration entry point, project/service/testimonial records, lead submission, media handling, SMTP code, account-reset support, robots and sitemap endpoints, and the installer. The ten schema definitions are `settings`, `users`, `projects`, `services`, `testimonials`, `leads`, `media`, `rate_limits`, `resets`, and `audit`.

This is a source inventory, not a full security or functionality audit. Do not infer a client portal, invoices, scheduling engine, proposal system, or backup manager from the existence of a generic users/projects table.

## Open blockers

- **B-001 — Source continuity:** import the exact complete baseline and original reference assets into Git with provenance. Preserve the existing PHP application; do not replace it with the latest generated mockup.
- **B-002 — Running installer mismatch:** establish the actual deployed path, displayed build, served source/hash where accessible, and active route before diagnosing caching or upload placement as the cause.
- **B-003 — Host filesystem:** verify targeted writable locations and permanent private storage under the real PHP worker. Preserve existing data and locks. A root-owned `0755` directory is not repaired by changing a version label.
- **B-004 — Native acceptance:** run real MySQL/MariaDB installation, recovery, admin/public operations, GD uploads, SMTP receipt, HTTPS form/session behavior, and cleanup on suitable staging.
- **B-005 — Approved additions:** favicon, interactive portfolio, enhanced motion, estimator, comparison content, review requests, client workspace, proposals/payments/care plans, scheduling/content, pipeline, backups/history/update manager remain tracked work, not completed claims.

## Latest user direction

The owner accepted all additions proposed in the prior feature discussion, specifically requested persuasive but organized Wix/Squarespace switching comparisons and more useful service information, and supplied the fleur-de-lis for the favicon. The subsequent image generation was a visual concept, not implementation of those systems.

No prices, provider account credentials, legal contract terms, guaranteed response times, or production payment configuration have been supplied. Do not invent these values.
