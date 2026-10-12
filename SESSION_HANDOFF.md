# Session handoff — read before continuing

Checkpoint: 2026-10-12 · `afterburn25/Cajun_Web` · `main`.

## Latest request

The owner created `Cajun_Web` and requires all changes, project direction, verification results, and next steps to be documented in Git so a new session can resume without reconstructing the conversation.

## What this session did

- Verified that the connected repository is `afterburn25/Cajun_Web`, with `main` as its default branch and write access available.
- Read the repository root: it was empty.
- Initialized the README in commit `4ea74bf8b83ef7474a7f0f06b7464c56b0990ed3`.
- Added the continuity agreement, status, direction, full approved roadmap, architecture notes, deployment rules, asset fingerprints, decision record, session log, and PR documentation checklist.
- Inspected the actual supplied 1.0.8 ZIP locally: archive hash, integrity, 33-file flat layout, application/installer version headers, schema definitions, and original logo identity.
- Inspected the attached favicon source metadata and recorded its exact fingerprint.

**No PHP implementation, source import, hosting repair, or live deployment was performed.** The documentation/bootstrap commit is not a new application version. Historical QA reports were read, not re-run.

## Resume in this order

1. Read `AGENTS.md`, `PROJECT_STATUS.md`, `docs/PROJECT_DIRECTION.md`, and `docs/ROADMAP.md`; inspect the new current Git head before writing.
2. Import the complete baseline from `CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip`, fingerprinted in `docs/BASELINE_INVENTORY.md`. Inspect all application files, exclude secrets/private data, and preserve original assets. Choose and document a source-directory layout; the final upload ZIP must still be flat. Do not upload a partial application as a complete release.
3. Retrieve/preserve the exact original logo and the attached `icon(1).png`, identified in `docs/BRAND_ASSETS.md`. Add real favicon outputs/references, test browser display, and regenerate the installer's embedded dependency inventory for every changed or added shipping file.
4. Verify the current installed or unfinished host state before applying a fresh installer. The last user report was an old 1.0.5 screen with root-owned storage and missing media. That report is not proof the current ZIP has those same bytes, and a correct ZIP is not proof it was deployed.
5. Run native staging acceptance for the PHP/MySQL baseline. Keep unresolved target-host blockers visible; do not force reinstallation or remove guards to obtain a passing result.
6. Implement approved roadmap items in coherent, tested increments. Start with exact favicon/branding, documented comparison content, portfolio and homepage improvements, and single-page lead workflows. Build sensitive client/payment/backup features with access-control, integrity, and recovery tests before enabling them.
7. Update status, roadmap, changelog, decisions where relevant, and the dated session log. Replace this handoff with the new exact checkpoint and next action.

## Files/artifacts to recover

These are conversation artifact names, not repository paths or guaranteed future sandbox paths:

- `CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip` — 33-file PHP distribution, SHA-256 in baseline inventory.
- `CajunWebPro-1.0.8-Verification.md` — historical local-test report, with explicit target-host limits.
- `CajunWebPro-Website-Logo.png` — exact horizontal logo; identical to the distribution's `assets/logo.png`.
- `icon(1).png` — user-supplied favicon source, not an invitation to generate a new mark.
- `cajunwebpro_louisiana_digital_excellence.png` — first selected navy/gold homepage concept.
- `cajunwebpro_s_luxury_web_agency_homepage.png` — later feature-expansion concept only; its comparisons/claims must not be copied as verified facts.

Use available conversation files first. For missing prior artifacts, search the file Library by exact name and verify hashes; do not assume old `/mnt/data` paths persist. Once originals are imported, use Git paths instead and update this handoff.

## Do not repeat

No installation keys, wizard screens, alternate setup filenames, chained hotfixes, silent logo substitutions, flying pixels, generic re-theming, untested claims of live success, invented clients/results, or false root-ownership promises. This is a service company, not a site builder. The user's one-page requirement concerns installation and the estimator, not flattening the entire public website into one page.
