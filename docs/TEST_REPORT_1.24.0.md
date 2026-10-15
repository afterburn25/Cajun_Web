# CajunWebPro 1.24.0 — 20i inventory ownership and operation recovery

Exact source/tag: `7190b582a02be8a1daa501db14b5899cfc1eb5d0` / `v1.24.0`. [Inventory and recovery PR #126](https://github.com/afterburn25/Cajun_Web/pull/126) and [fresh-owner correction PR #129](https://github.com/afterburn25/Cajun_Web/pull/129) are normally merged. [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38711850732). Reviewed instruction candidate f2aa0125 and final main have identical source trees. Runtime, build, fixtures and workflows are unchanged from fully accepted 5fa5998d/8b44a7ad; final main still receives its own complete acceptance. [Runtime review](https://github.com/afterburn25/Cajun_Web/pull/129#issuecomment-6187503389) and [instruction review](https://github.com/afterburn25/Cajun_Web/pull/131#issuecomment-6188555018) are clear. Downloaded draft/public verification is a separate publication gate.

## Delivered

Fresh owners can open the 20i connection page before saving a profile without PHP warnings or unavailable inventory actions. The one-page installer and prepared storage are retained.

Owners can explicitly refresh the saved 20i package list, inspect durable observations/history and approve package or hosted-domain visibility for existing clients. Original account context is retained. Changed credentials/settings suspend links pending explicit reapproval; a domain moving to another package cannot silently move its client authorization. Populated v1/v2 inventory upgrades preserve original bindings and history. Missing established tables require database recovery instead of empty recreation.

Typed package-label approval binds one exact request, with durable attempts and append-only reconciliation. Pending, definite failures and unknown outcomes remain distinct; unknown writes cannot blindly retry. The request console cannot grant generic purchases, renewals, destructive changes or customer mail. Those workflows require dedicated commercial eligibility and consent work still being implemented. Earlier inherited operation history remains available for recovery. Existing website design, one-page keyless installer, client workspace and local mail behavior remain.

## Verified

- 9,134 named application assertion executions in 44 terminal reports: 1,373 actual HTTP/Chromium, 1,383 Apache, 275 unprivileged structure/recovery, 3,084 estimator, 21 build guards and 2,998 other native/form checks. Repeated names count as separate executions, not unique tests. All 30 exact-main workflow gates pass.
- The 29 focused gates include 158 downloaded named reports/17,034 executions across supported PHP 8.2/8.4 and MariaDB 10.11/MySQL 8.4 matrices. These separate matrix repetitions are not added to the application total. Console-only results are excluded from that named count.
- Actual desktop/mobile owner/client moved-domain and operation screens are inspected. Client pages hide unapproved details. Homepage desktop/mobile pixels match published 1.23. PHP diagnostics are empty; retained formatted-message isolation checks pass.
- Every retained supported starting package through 1.23.0 is tested, including 1.0.8 and 1.11.1/1.11.2. Actual original 1.22 and 1.23 archives participate in recovery/reapplication tests, preserving nonempty drafts, submission history/fences and original encryption keys. Original accepted/unknown/unresolved/abandoned mail requests cannot resend. Both Apache rewrite modes and unprivileged file recovery pass. Recovery is not a database downgrade.
- All 12 distribution files match clean Windows/Linux builds byte for byte. ZIP CRCs, source/version, members, hashes, paths and directory inventories pass. FULL-PROJECT has 5,248 members; COMPLETE-UPLOAD 2,591; UPDATE 2,578. Tests leave shipping source unchanged.

## Installation and remaining scope

Use COMPLETE-UPLOAD for fresh setup, UPDATE for recognized installed versions through 1.23.0, and FULL-PROJECT for development. Back up website, database, private storage and original encryption keys together. Follow START-HERE and MIGRATION-INSTRUCTIONS. UPDATE stages `cwp-update-1.24.0`; upgraded inventory/operations preparation is explicit and owner-authorized. Preserve existing configuration and data.

This first takeover increment does not complete the full 20i platform. Codex owns the remaining P0 work: discovery/catalog, cloud/VPS inventory and ownership, native hosting controls, domain commerce, verified orders/payments, lifecycle automation, scoped support and interactive SSH. Cloud development #127/#128 is separate from these release bytes. The original complete owner API reference and feature-specific live account/gateway acceptance remain external evidence gaps. All acceptance accounts/endpoints are synthetic; no production 20i request, payment, customer mail, SSH session or live deployment is claimed. Previous releases remain unchanged.

Named application report counts: `{"activity-native-results.json": 64, "apache-results.json": 1383, "appointments-native-results.json": 83, "attempts-native-results.json": 69, "build-guards-results.json": 21, "catalog-native-results.json": 49, "client-authority-native-results.json": 60, "delivery-archive-results.json": 55, "delivery-native-results.json": 57, "estimator-adapter-results.json": 3084, "hosting-delete-retry-native-results.json": 67, "hosting-operations-native-results.json": 79, "hosting-reconciliation-native-results.json": 139, "hosting-records-native-results.json": 94, "hosting-retry-native-results.json": 44, "http-results.json": 1373, "ledger-native-results.json": 69, "mail-accounts-native-results.json": 68, "mail-connections-native-results.json": 55, "mail-drafts-form-default-results.json": 41, "mail-drafts-form-results.json": 41, "mail-drafts-native-results.json": 78, "mail-inbox-native-results.json": 57, "mail-message-native-results.json": 69, "mail-notices-native-results.json": 79, "mail-submission-combined-results.json": 471, "mail-submission-dispatch-native-results.json": 80, "mail-submission-forms-default-results.json": 58, "mail-submission-forms-results.json": 58, "mail-submissions-native-results.json": 85, "mail-templates-native-results.json": 59, "native-results.json": 65, "notifications-native-results.json": 62, "payments-native-results.json": 62, "provider-checks-native-results.json": 49, "provider-execution-native-results.json": 104, "provider-inventory-native-results.json": 98, "provider-inventory-upgrade-results.json": 38, "provider-operations-native-results.json": 92, "provider-settings-native-results.json": 69, "reviews-native-results.json": 65, "staff-native-results.json": 31, "structure-results.json": 275, "support-native-results.json": 35}`.

## Download verification completed

Draft and public downloads each contained all16 expected assets. Every byte, manifest, ZIP member/hash/CRC/directory, evidence archive and immutable source/tag passed verification. Prior1.23 assets remained unchanged.

{
  "source": "7190b582a02be8a1daa501db14b5899cfc1eb5d0",
  "tag": "v1.24.0",
  "release_url": "https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0",
  "mode": "published",
  "assets": 16,
  "all_bytes_match_prepared": true,
  "all_checksums_and_members_pass": true,
  "prior_123_assets_unchanged": true,
  "packages": {
    "FULL-PROJECT": {
      "members": 5248,
      "sha256": "5ce865996297cc228b7a01166c4125234e249584ba198f521b0ebc4bb63e1b16"
    },
    "COMPLETE-UPLOAD": {
      "members": 2591,
      "sha256": "aa7a84b4c0d1201a4eddf2fe68340b90d4860117ceff4d959d165e6feafa731e"
    },
    "UPDATE": {
      "members": 2578,
      "sha256": "0fe0df588a45ab99793f292067b21b0abb998bf103d24bb8de9c32ea4bc02293"
    }
  }
}
