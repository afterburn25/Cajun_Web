# Devin / agent handoff — verified 1.15.0

Immutable source/tag: `8749bc5a89e52f8f35a193cc49d306136576ec8e` / [v1.15.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.15.0), [PR #66](https://github.com/afterburn25/Cajun_Web/pull/66). All 16 assets freshly downloaded/verified; [exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38460197599) passes. [Detailed evidence](TEST_REPORT_1.15.0.md). No live deployment. Later publication records are documentation only.

## Accepted scope and ownership

Codex preserves corrected Devin #32 through `3617ad5` with 71 isolated checks. The application reuses canonical UTC/DST and lifecycle decisions for one studio calendar, pending public requests, explicit old-inquiry adoption and staff decisions. Every conflict-changing write takes the configuration row lock and then current locking conflict reads. The initial held-lock two-process test caught a REPEATABLE READ snapshot opened before waiting for the mutex; 6d72e02 corrects it and retains the regression. Public intake uses a database transaction/row lock; staff mutations additionally take the staff mutex. Confirmation needs the current authorized staff member's password, not an owner-only role. Configuration/setup are owner-only. [Guide](CONSULTATION_SCHEDULING.md).

Devin reviewed the integration; the precise snapshot correction and public-versus-staff locking/password scope are recorded in [PR #66](https://github.com/afterburn25/Cajun_Web/pull/66#issuecomment-6153908247). No provider calendar or outgoing confirmation/cancellation message is implemented. File recovery is not a database downgrade.

Next executable work: return to A-06 webmail transport, evaluate maintained PHP/shared-host-compatible options and define a bounded connection/inbound-message contract before integration. Reuse the released email profiles, private credential key, current mailbox assignments and canonical permission policy; validate TLS/network boundaries and hostile-content handling with isolated tests. Real account acceptance, outgoing mail and A-08 email bells remain separate gates. The separate owner-requested 20i package-list diagnostic succeeded in run 38458492995, job 115510304263 (HTTP 200 and valid list); its earlier secret-availability blocker is resolved. This establishes only authenticated GET /package, not a shipped adapter or other entitlements. See [issue #65](https://github.com/afterburn25/Cajun_Web/issues/65) and preserved ops/twentyi-read-only-check-20261013 at 7e87d8c; do not rerun or expand provider calls without their own authorization. Continue feasible 20i/merchant/VPS/SSH development under operation-specific verification rules; preserve other Devin branches and current activity/support implementations. The roadmap remains active.

## Package/update contract

FULL-PROJECT is sanitized development source; COMPLETE-UPLOAD extracts organized site contents directly for fresh setup; UPDATE stages cwp-update-1.15.0 for the authenticated owner workflow after backups. Seventeen supported starting packages: 1.0.8 and 1.1.0 through 1.14.0. Preserve config/installed guards/private data/root scaffolds/prior journals. Recovery uses structure-1.15.0 and shared structure-1.11.0/mutex.php. Added appointment/configuration history and earlier review/template/mail data remain after file recovery; the original private mail-master.key still opens credentials. Never fresh-install over an existing site or move published tags. [Directory map](DIRECTORY_STRUCTURE.md); [migration](STRUCTURE_MIGRATION.md).

## Open heads at handoff

Inventory only; newer heads need fresh review. Preserve independent branches and avoid blanket union merges of stale release headings.

| PR | Branch | Head |
| --- | --- | --- |
| #32 | devin/consultation-scheduling | `82a60d2526b347d15a2f9ff028d5c68668f56d2a` |
| #36 | devin/review-adapter | `526f746c63d457bd379f3b7def2de59756b85945` |
| #37 | devin/scheduling-adapter | `7183abd3ce6d7a00887d4fab5a8afe17b80ba78b` |
| #43 | devin/provider-foundation | `41f7df8b1b958972b566b3169b04da35764ecc31` |
| #44 | devin/domain-foundation | `19c26b158917f9a3f7718d8f42325e387c7b55c5` |
| #45 | devin/vps-foundation | `79225830ae09b76a07602225dde19176f9c97f7d` |
| #46 | devin/ssh-access | `c8594d48302241e567fb1c728059484c54f85cbb` |
| #47 | devin/proposals-foundation | `490b514dd103883818bacf592738c4a7a4e60ed5` |
| #48 | devin/revisions-foundation | `e65526062d50a8d6e986dc65a2c91eeedc56dbde` |
| #49 | devin/comparisons-foundation | `4b19feddee22c7a2e183a62849fbedd75d1da37d` |
| #51 | devin/pipeline-foundation | `977cafa7b2ed04b7e33d415612326d23385e89f5` |
| #52 | devin/backups-foundation | `dec5ccbdde0e17b5b1aa2da25e6072ce707f1a23` |
| #53 | devin/service-plans | `2b8bdf886c35b952f2e1f3116f18252a78a4dd7b` |
| #54 | devin/support-access | `408a25066a80d22dabcb1f51031560aa207bd751` |
| #57 | devin/notifications | `002db4688c6506ec5f1affc56b4fdcf87df5b7a0` |
| #60 | devin/shared-hosting | `0963b1548a9061af13b509e2db44ef2b5f76067d` |
| #61 | devin/service-desk | `24d27faf2cd126c4c05e293359cfe8f126b3afdc` |
| #62 | devin/mail-bells-adapter | `c6b1f1f6e11b63209a2b30f32b7baf26955b7fb4` |
