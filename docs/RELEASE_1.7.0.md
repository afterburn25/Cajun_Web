# CajunWebPro 1.7.0 — sandbox notification inbox

Bounded M2 / partial HC-04 and HC-11, starting from published 1.6.0 and main `3dd6d5e82e22ad4a2ab27dbf54d08bde45c50a9a`. Implementation: [PR #20](https://github.com/afterburn25/Cajun_Web/pull/20), `codex/sandbox-webhooks`. Devin's portfolio PR #7 is preserved. Final exact-main acceptance and publication evidence are recorded separately when complete.

| Scope | Delivered behavior / limit |
|---|---|
| Receiver | Fixed HTTPS POST endpoint, disabled by default, no login session or redirects, bounded JSON body, exact-byte HMAC-SHA512 with binary-decoded current sandbox key |
| Merchant binding | Expected webhook ID, sandbox account fingerprint and explicit current credential revision; changing credentials pauses receipt |
| Durability | Minimal identifiers/body hash and strict audit commit before HTTP 200; duplicate ID/body returns 200 without another row, changed-body replay returns 409 |
| Verification | Owner password and current independent sandbox merchant/transaction reporting; exact attempt/account/reference/amount/type match; atomic attempt observation, notification processing and audit |
| Recovery | Unknown-attempt recovery when matched, current reporting for old events, stale/conflicting report protection, no blind retry or forced payment success |
| Owner UI | Receiver setup, paginated inbox, individual verification and disable controls, existing visual design and small-screen layout |
| Upgrade | Additive validated table/marker; retained 1.6.0 UPDATE, all earlier upgrades and one-page fresh install; no daemon/CLI prerequisite |
| Real payments | No invoice settlement, production callbacks, client checkout, receipts/refunds, recurring billing or automated processing |
| Provider acceptance | Local/native/HTTP signatures and reporting are synthetic; actual merchant key compatibility, delivery/retries/rotation and HTTPS host acceptance remain unverified |

See [notification guide](SANDBOX_NOTIFICATIONS.md) and [trial guide](SANDBOX_PAYMENTS.md). Keep configuration, installed guards, existing media/data and the original external payment master key. Never rerun fresh setup on an installed website. Earlier tags/assets remain immutable. No live deployment, customer mail or real provider/payment operation was performed.

Candidate review: existing attempt writes retain their prior wrapper; a shared locked helper allows inbox processing and attempt observation to commit in one transaction. Signature receipt performs no reporting request. Raw bodies/signatures/customer details are not stored. Ownership boundaries leave Devin's component untouched. Earlier CI fixture failures (overlong synthetic account name, stale HTTP credential revision) are documented in the session log; they did not relax application validation.
