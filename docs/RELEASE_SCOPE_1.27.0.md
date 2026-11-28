# CajunWebPro 1.27.0 release scope

Status: qualified, tagged, published, and public-download-verified on 2026-11-26. This document preserves the accepted pre-publication scope and exclusions. Publication did not authorize production deployment or any live operation.

## Exact lineage

- Published upgrade baseline: v1.26.1 source `f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391`.
- Verified development starting point: `f73515c681cafc0d46fb117f0999b597fa05eed5`, tree `92c64a1c204ecda4befd8e255af271390d28f6b5`.
- Accepted release source: normal PR #402 merge `7d9c8aa7d934117a494b19a32c58c071c190d56f`, tree `4cdc3b85ee0efb6ab5dfb3dde99f2bedaad2254b`.
- Published version: 1.27.0; release `459005437`; 18 public assets download-verified.
- The stale candidates `f7be9cd2`, `c05c0a8f`, and `926929e2` remain historical preparation only and are not release lineage.

## Included

- Public customer registration, verification, resend/expiry, password recovery, and checkout resume.
- Shared-hosting storefront, checkout/payment evidence, fulfillment state, and explicit VPS unavailability.
- Software upload onboarding, license issuance, customer/admin catalog work, and cloud-site intake.
- Notification health and private project notes.
- Consolidated mail accounts, drafts, HTML bodies, Reply/Reply All/Forward, attachments, folders, read/unread flags, Sent-copy history, and local submission records.
- Exact-source package construction, signed package-source isolation, diagnostic reliability, and published-v1.26.1 upgrade acceptance.
- Additive database migration registration for every schema introduced or upgraded since v1.26.1, including the formatted-draft readiness migration.

## Explicitly excluded

- Live Authorize.Net charges, refunds, or production credentials.
- Live provider provisioning, paid purchases, customer messages, SSH/support access, and production deployment. Release publication was separately authorized and completed without broadening these live-operation boundaries.
- Deferred P2/provider backlog integration.
- Claims that mocked, loopback, sandbox, or disabled-transport evidence is live-provider evidence.

## Required qualification

One reviewed exact source must pass generated-source drift checks, all three deterministic packages, manifests/inventories/checksums, full Application acceptance, the four PHP/database checkout/payment lanes, published-v1.26.1 upgrade interruption/recovery/reapply, Apache root/subfolder rewrite/no-rewrite installs, package privacy/security review, independent implementation review, normal merge, and complete resulting-main verification.

Qualification, tagging, and publication are complete. Any replacement asset, republish, production deployment, or live operation still requires its applicable separate authorization and verification path.
