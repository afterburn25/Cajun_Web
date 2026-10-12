# Changelog

This log distinguishes repository work from application releases and deployment. Documentation-only work does not advance the application version.

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
