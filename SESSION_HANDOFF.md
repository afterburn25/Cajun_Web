# Session handoff — read before continuing

Checkpoint: 2026-10-12 — custom hosting commerce scope added; documentation only.
Repository: afterburn25/Cajun_Web · Authoritative branch: main.

## Latest owner request

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

This session is documentation/requirements capture. Next session should record actual baseline/import/test evidence and update every continuity file.
