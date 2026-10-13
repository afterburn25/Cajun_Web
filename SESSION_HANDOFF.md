# Session handoff — read before continuing

Checkpoint: 2026-10-12 — standalone web-design payment forms added to the approved scope; recover current 1.1.0 source next.
Repository: afterburn25/Cajun_Web · Authoritative branch: main.

## Latest requirement — web-design payment forms

The owner wants payment forms for web-design services too. Updated P-03 and HC-11 with full service payments, fixed/percentage quote deposits, milestones, final balances, optional recurring maintenance, admin-created payment requests/secure links and portal payments. Use the same branded Authorize.net engine and ledger as hosting checkout, but design payments must work with 20i disabled and no infrastructure purchase. Deliver these forms in M2; deeper project/hosting bundles remain M4.

Current branch: `docs/web-design-payment-forms`, starting from local `072449c` over remote main `43bebd0`. This branch also preserves the earlier verification and owner version correction. This is documentation only; inspect the actual 1.1.0 build to identify existing implementation before coding. Use the P-03/HC-11 acceptance cases for sandbox payments, invoice links/access, server-side amounts, duplicate/timeout recovery, receipts/refunds and balances. No live payment or deployment is authorized by this documentation update.

## Current owner correction — takes precedence over older baseline instructions

The owner says the latest version is **1.1.0**. Obtain and inspect that exact build before source import or feature work. Only 1.0.8 has been located and verified in this chat; keep it as historical evidence, not the implementation starting point. GitHub currently has documentation only, so ask for the 1.1.0 local folder/download link or another repository/branch when its location is not available. That location question is pending with the owner.

This correction supersedes older instructions below to import 1.0.8 first. Preserve those earlier investigation results as history. No current 1.1.0 archive, version constants, test evidence or hosted version has yet been verified here.

## Latest owner request and result

The owner asked to check GitHub for the roadmap, understand the intended CajunWebPro product, and have the latest build available. Fresh clone/fetch confirmed main at `43bebd019a6f548d7a059b2c8e259beed3efe561`; both existing feature branches are behind main. There are no published releases/tags, Actions runs, open PRs or open issues, and the remote source tree is documentation only.

Recovered a local working copy of the exact 1.0.8 baseline from Downloads. Both original/recovered ZIPs match the recorded archive hash. ZIP CRC, 33-file extraction equality, all 32 installer dependency hashes, app/installer 1.0.8 constants, one-page layout, original logo hash, 14 PHP syntax checks and two JavaScript syntax checks passed. No application behavior or live deployment is established by these checks.

In this desktop chat workspace the retained archive is `work/CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip`, extracted source is `work/CajunWebPro-1.0.8-baseline/`, and detailed local evidence is `work/baseline-verification.json`. These are workspace-relative local files, outside this repository and not permanent download URLs. Verify their existence and hashes when resuming elsewhere. Local documentation branch: `docs/verify-latest-baseline-20261012`; this investigation was not pushed or deployed.

Next implementation step remains importing the verified baseline and original assets into Git after a secrets/private-data review, then resolving actual staging/installer/storage acceptance. The hosting roadmap and Authorize.net choice remain authoritative; none of HC-01 through HC-14 is claimed implemented by this investigation.

## Prior approved owner request

Create an actionable GitHub roadmap expanding CajunWebPro into a **web design AND hosting business**, with a fully custom first-party billing platform, client portal, plan store, checkout, 20i reseller provisioning for shared hosting/domains/VPS, and Authorize.net instead of Stripe. Admin must accept API Login ID, Transaction Key, Signature Key and Public Client Key. Payment forms should be styled to match the existing navy-and-gold design.

## What changed

- New docs/HOSTING_AND_BILLING_ROADMAP.md defines HC-01 to HC-14, scope, detailed workflows, catalog and admin controls, auth, billing, provider integrations, safety and launch gates, phased M0–M6 implementation, and verification matrix.
- docs/ROADMAP.md integrates the new section with existing F-/W-/L-/P-/A- items, rather than discarding the prior website/project workflows.
- docs/PROJECT_DIRECTION.md, docs/ARCHITECTURE.md, docs/DECISIONS.md, PROJECT_STATUS.md, CHANGELOG.md and docs/SESSION_LOG.md synchronize the accepted architecture, provider choice and status.
- Public provider documentation consulted for 20i package provisioning and Authorize.net Accept.js, webhooks and customer profiles; no account-specific API/merchant connection tested.

## Still not done

**No executable website source is in Git yet.** The actual 1.0.8 distribution/branding import, v1.1.0 missing-release recovery, installer/host permissions, payment gateway, client portal, domain registration, VPS, invoices and hosting fulfillment remain unimplemented and unverified. No checkout or service has been launched. No prices/credentials were invented or committed. The latest runnable deployment state on the actual hosting server is unknown.

## Exact next actions

1. Inspect current main/AGENTS.md/PROJECT_STATUS.md/docs/ROADMAP.md and docs/HOSTING_AND_BILLING_ROADMAP.md; do not use prior chat release claims as evidence.
2. Import exact verified 1.0.8 source ZIP and original logo/favicon into Git, excluding secrets/private data; keep flat deployment, one-page keyless installer and approved brand. Verify source-to-package consistency.
3. Investigate target-host runtime/installer/storage blockers and establish real staging acceptance before expanding platform.
4. Inspect My20i account-only API reference for actual product CRUD, hosting provisioning, domains, VPS and costs. Obtain API credential setup through secured admin ONLY when implementation ready. No request for keys in chat/repo.
5. Configure Authorize.net sandbox through secure admin, implement themed Accept.js with PCI scope review and hardened payment/webhook/recurring engine.
6. Deliver roadmap by M0–M6 gates with tests/commits/status/handoff per phase; no premature live card charging, registrations or destructive provider operations.
7. Produce tested downloadable releases only after actual source, migrations, hosting permissions, backups and release checks are proven.

## Prior evidence still valid

The verified baseline ZIP is CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip (33 files, SHA-256 13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf). Favicon source hash, installer incident and missing v1.1.0 details remain in PROJECT_STATUS.md, docs/BASELINE_INVENTORY.md and docs/DELIVERY_INCIDENT_2026-10-12.md. Do not downgrade the hosted site to repair a missing chat download.

The latest session verified existing documentation and the local distribution only. Next implementation session should record source-import and runtime-test evidence and update every continuity file.
