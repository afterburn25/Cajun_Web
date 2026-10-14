# Devin / agent handoff — verified 1.18.0

Immutable source/tag `91550d956c404585545dbd766c96f36f58ae0623` / [v1.18.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.18.0), [PR #70](https://github.com/afterburn25/Cajun_Web/pull/70). All 16 public assets freshly downloaded/verified; [exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38492678532) passes. [Named evidence](TEST_REPORT_1.18.0.md). Later publication records change documentation only; no real mailbox or deployment was tested.

## Accepted scope and review

Current-authorized explicit exact-message reads over the maintained receiving stack, strict audited outcomes without message retention, bounded MIME/HTML work, escaped plaintext and opaque sandbox formatting. [Owner workflow](MAIL_MESSAGE_READER.md). Whole MIME size includes attachments; server flags remain unchanged. Upgraded owners prepare additive storage explicitly; fresh setup is empty.

[Devin review of shipping application 21e00c0](https://github.com/afterburn25/Cajun_Web/pull/70#issuecomment-6157644173) reproduced components/TLS and reviewed authorization/rendering with no blocking findings. Subsequent changes corrected test availability/outcome expectations, captured the actual CSP event-accounting failure, and fixed the assertion to require both CSP failures and no network/script work. Actual Chromium 156 emits blocked request events while Edge omits them. [Evidence checkpoint](https://github.com/afterburn25/Cajun_Web/pull/70#issuecomment-6158215522). No application protection was weakened. Final exact-main acceptance remains the release evidence.

## Next ownership

Next: integrate a bounded admin-only email notification workflow using current authorized recent-message observations and exact-message POST opening. Keep per-staff opened/dismissed state separate from observed server flags, filter counts and metadata by current mailbox grants/revision before exposure, and provide mail-only staff access without granting client-workspace permissions. Passive public-page polling must read local state only and preserve idle session expiry; no automatic IMAP sync is claimed. Resolve the #62 contract review before integration. Preserve Devin #43 and Codex contribution #71 for separate provider persistence/adapter acceptance; current PR status is recorded in the handoff. The prior authorized 20i GET /package proves that read only. Preserve issue #65 and operation-specific payment/provider/SSH gates. Continue feasible roadmap work without a release-boundary stop. Codex has started isolated notice storage/projection preparation on `codex/mail-notices` at `0e92304a65df49047982fcaefec9addee3e0dd8b` ([contract](https://github.com/afterburn25/Cajun_Web/blob/0e92304a65df49047982fcaefec9addee3e0dd8b/docs/MAIL_NOTICES_CONTRACT.md)); its [PHP 8.2/8.4 native matrix](https://github.com/afterburn25/Cajun_Web/actions/runs/38494497409) is `queued` at this publication record. These new modules have no installation/reader/HTTP call sites or bell UI yet and are outside the 1.18.0 tag. Finish that bounded increment on its branch; merge this publication documentation before its next release.

[Current issue #2 handoff](https://github.com/afterburn25/Cajun_Web/issues/2#issuecomment-6158196201). Provider #43 state `OPEN`, observed head `9ee637de4e0896a65187a04e04bb534f0c2ad66a`. Codex key-validation contribution #71 state `OPEN`, observed head `ba1cbc3cf0b1f60f4acdb6944e46c1202621671f`; its [PHP 8.2/8.4 matrix](https://github.com/afterburn25/Cajun_Web/actions/runs/38490967320) passes 150 checks each at ba1cbc3. This validates an isolated component, not MySQL-ledger persistence, account authorization, actual 20i transport or deployment. Preserve branches and independently review any newer heads.

For [#62 contract review](https://github.com/afterburn25/Cajun_Web/pull/62#issuecomment-6157794515), use separate per-staff local opened state and shared observed server flags, collision-safe full-tuple mapping, defined current observation windows, and a mail-only permission/feed boundary. Existing workspace activity requires clients.manage; mail-only staff must not acquire that permission. Passive counts expose no email content and never contact IMAP or extend login expiry. Bell opening requires explicit current-authorized exact-message POST.

## Packages and retained data

FULL-PROJECT is sanitized development source. COMPLETE-UPLOAD extracts organized fresh-install files directly. UPDATE stages cwp-update-1.18.0 after coordinated website/database/private-key/storage backups. Twenty starting packages are verified, including actual immutable 1.17.0 installation/update/recovery/reapplication with retained new message history, earlier inbox/connection records, private data and original key. Preserve permanent guards/scaffolds and structure-1.11.0 lock. Never relabel published tags or treat file recovery as a database downgrade.

## Open branch inventory

An assignment or branch is not proof a worker is running. Preserve independent work; accept components into the application only after its own tests and current contracts pass.

| PR | Branch | Head |
| --- | --- | --- |
| #32 | devin/consultation-scheduling | `e61bc30ddb11cb32aabc34899fc5f40da7a42675` |
| #36 | devin/review-adapter | `d1bf0261767f92ea4a8856c3954619866572daf2` |
| #37 | devin/scheduling-adapter | `de5084f81433d4ddd29eaac7e3628505933be6c8` |
| #43 | devin/provider-foundation | `9ee637de4e0896a65187a04e04bb534f0c2ad66a` |
| #44 | devin/domain-foundation | `58d4a2b5fdc4f617102d0153a5bdfc9164b5a681` |
| #45 | devin/vps-foundation | `068703449f6e220fb686f46b5e33fb155b2244e1` |
| #46 | devin/ssh-access | `467347d2dd065cfb09d3c47f27cdd5fb2fa9faa1` |
| #47 | devin/proposals-foundation | `06ddafa6756d1d85b7f70095174894218ace61d7` |
| #48 | devin/revisions-foundation | `7607428226a681a6142b61e26220bf9d3100e6a8` |
| #49 | devin/comparisons-foundation | `d408c486701a932ec41e259576b3f4b51b6c5d86` |
| #51 | devin/pipeline-foundation | `f3d90c778ac436fbdd852e7ee3b9c3eb45de2d2d` |
| #52 | devin/backups-foundation | `940b0c7ed9f82f2a86b36043f62ee8f7b4cf35b8` |
| #53 | devin/service-plans | `a754e869331831a96951a57809183f26ad6e0777` |
| #54 | devin/support-access | `1feea2c0caedc1a50960fe68500bc3de01da5813` |
| #57 | devin/notifications | `6ad6913d292a05d8f297ecd8f5838fc6f2364bea` |
| #60 | devin/shared-hosting | `ca9969052f4219e8c0256ec5c60fe3de40427ec3` |
| #61 | devin/service-desk | `e7f5bb95fd1c52785a0616e06b29612acbdc3a7f` |
| #62 | devin/mail-bells-adapter | `f4b9be1f4c94165beddfcdcba597923c25970e36` |
| #68 | devin/mail-transport | `e575ecaea89da868abbdac51f9f66fc700d0477c` |
| #71 | codex/provider-key-validation | `ba1cbc3cf0b1f60f4acdb6944e46c1202621671f` |
