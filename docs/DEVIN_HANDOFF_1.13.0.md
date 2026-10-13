# Devin / agent handoff — verified 1.13.0

Immutable source/tag: `b83cb9fb6856bca4f333fe953bee2e68f5074d51` / [v1.13.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.13.0), [PR #63](https://github.com/afterburn25/Cajun_Web/pull/63). All 16 assets freshly downloaded/verified; [exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38446948573) passes. [Detailed evidence](TEST_REPORT_1.13.0.md). No live deployment. Later publication records are documentation only.

## Accepted scope and ownership

Codex preserves Devin #59 through `13c04f6`, including corrected temporal assignments, UIDVALIDITY message identities, duplicate rejection and length-prefixed tuple encoding (64 isolated no-extension checks). The application reuses the existing canonical permission policy; temporal assignments never become a competing authority. Owner-managed profiles and encrypted passwords, separate read/send grants, fresh authorization, scoped staff listings, strict audit/history transactions, stale/concurrent-write protection and additive setup are released. [Guide](ADMIN_EMAIL_ACCOUNTS.md). No transport or message operation is implemented by this release. Devin independently reviewed #63 at `2bf549a` with no findings.

Next executable work: integrate reviewed L-02 review-request components and the corrected #36 contract into a bounded staff workflow, preserving existing intake protection, current permissions, explicit human evidence, durable revision history and confirmed external-delivery records. Codex owns application/schema/UI/tests/build/release; Devin retains independent component corrections. Real IMAP/SMTP transport, mailbox rendering, compose/send and A-08 email bells remain incomplete and need separate transport/security/provider acceptance. Corrected #32 scheduling has component evidence but still needs adapter/database concurrency acceptance. Preserve other branches and existing activity/support implementations. The roadmap remains active.

#31 corrections through b7d4b0c previously passed 86 PHP 8.4 no-extension checks; current heads need preservation/re-review before integration. #36 at ebc87937c3b0e4889ab8808461c7883d63edf478 corrects the URL-input contract: live 500-byte cap, already-rejected userinfo, bare-host broadening under option A and fragment narrowing under either option. L-02 must explicitly select its acceptance rules and test those changed edges, not claim identical intake. The adapter must keep contact consent, saved report and genuine recorded delivery distinct; no automatic website fetch, invented metrics or customer mail. Scheduling and other foundations remain unaccepted at application level. Follow [dependency gates](DEPENDENCY_GATES.md).

## Package/update contract

FULL-PROJECT is sanitized development source; COMPLETE-UPLOAD extracts organized site contents directly for fresh setup; UPDATE stages cwp-update-1.13.0 for the authenticated owner workflow after backups. Fifteen supported starting packages: 1.0.8 and 1.1.0 through 1.12.0. Preserve config/installed guards/private data/root scaffolds/prior journals. Recovery uses structure-1.13.0 and shared structure-1.11.0/mutex.php. File recovery retains new account database rows and the original private mail-master.key. Missing keys are never recreated while encrypted records exist. Never fresh-install over an existing site or move published tags. [Directory map](DIRECTORY_STRUCTURE.md); [migration](STRUCTURE_MIGRATION.md).

## Open heads at handoff

Inventory only; newer heads need fresh review.

| PR | Branch | Head |
| --- | --- | --- |
| #31 | devin/review-requests | `ea0a4d3733266e6509fe959715a97b699211eca3` |
| #32 | devin/consultation-scheduling | `3617ad567d543675075cf53c82ccca70b351ef9d` |
| #36 | devin/review-adapter | `79585780dab874a6ba0b311722f3abedbf778ebe` |
| #37 | devin/scheduling-adapter | `fda5408ef06873e5cc0f4424817e79baa50d3f41` |
| #43 | devin/provider-foundation | `d3f372cb6c8bdd6b67a95120fb9d1ac3ec4674ba` |
| #44 | devin/domain-foundation | `afde95c1d9665d7e16103ed9791c00fb613a4107` |
| #45 | devin/vps-foundation | `79ccb07560bd7911c066b9cc821f7c930a1b522d` |
| #46 | devin/ssh-access | `4793416c8f1abc63c4181ba458de14792e24ce9d` |
| #47 | devin/proposals-foundation | `cfa72ee1a7359e2de6abec3c29050f739a8f0b66` |
| #48 | devin/revisions-foundation | `fe7bfa634ba438fe1be5af377c679494bb956fa4` |
| #49 | devin/comparisons-foundation | `12462d007141e596b0c95527a0b07637948417d1` |
| #51 | devin/pipeline-foundation | `318a3888222ecdb7dee55f81b91b7bc42d5354ff` |
| #52 | devin/backups-foundation | `4772d5892c6c69e67a0f3bb8e0aadd12c93058dd` |
| #53 | devin/service-plans | `af6d120719a13d79754f2d778da48f1991cba346` |
| #54 | devin/support-access | `692a5ae890fbf590209056322b89c6461a8c9399` |
| #57 | devin/notifications | `18e5207b51b04ba54936842ae66dbedcc823f112` |
| #60 | devin/shared-hosting | `eff61c23bdd6aa18f89b8fc82f1855f7bf226b84` |
| #61 | devin/service-desk | `0deb334c2f7a9565e89f78567e23e6f24d79d03e` |
| #62 | devin/mail-bells-adapter | `d107945019eaf1c5de40e3b02ce50076b4f963b3` |
