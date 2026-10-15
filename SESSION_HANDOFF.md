# CajunWebPro handoff — 2026-10-15

Continue the complete20i P0 [owner request](docs/TWENTYI_CODEX_TAKEOVER_REQUEST.md) and [requirements](docs/TWENTYI_REQUIREMENTS_EVIDENCE.md). Goal remains active. Published v1.24.0 is unchanged. This turn made verified package/native evidence progress and dispatched the next three implementation slices.

Root checkout: `C:\Users\after\Documents\Codex\2026-10-12\ok-x20\work\Cajun_Web-twentyi-service-orders`; branch `codex/twentyi-mailbox-controls`, [PR140](https://github.com/afterburn25/Cajun_Web/pull/140). Exact acceptance source38bb69ccd1c1fc45f479022fe11748ca78b756d2 preserves normal registrar/catalog/cloud/main merges. This checkpoint changes documentation only; do not substitute it for the exact source tested. Prior checkpoint is [archived](docs/archive/2026-10-15-38bb69cc-SESSION_HANDOFF.md).

## Active acceptance

- Preserve full run38824052505/job116727202658. Package and native stages passed; HTTP/Chromium is running, no browser artifact at this checkpoint. Do not restart on an observation timeout. Domain agent will download the checkpoint and inspect registrar screenshots, particularly corrected owner-mobile capture; cloud agent will independently inspect cloud captures from shared exact bytes. Root verifies mailbox/capability assertion and complete reports/packages, then recovery and Apache.
- Exact combined native artifacts are all downloaded and verified: mailbox38824046181,91 cases ×4; registrar38824052817,63 native plus64/64 pure ×4; catalog/recurring38824052748,43 catalog within508 combined plus51/51 pure and all13 named reports ×4; cloud38824052849,41 ×4. The PR test merge dab7005f has the exact38bb tree. [Hashes and scope](docs/TWENTYI_PARALLEL_WORK.md). Do not sum overlapping reports into completion percentages.
- Windows build84801 is COMPLETE and CLOSED. Exact38bb packages are preserved at `work/build-evidence/38bb69ccd1c1fc45f479022fe11748ca78b756d2`; `work/20i-combined-38bb-windows-verification.json` verifies11 checksummed files, all ZIP/member/directory inventories, exact Git FULL and site COMPLETE, and UPDATE payload/manifest/history. Counts5466/2683/2670. Root dist contains this exact build. No local build is running. Prior0eb packages are separately preserved. Never rerun old non-idempotent verification scripts.
- Root proof `work/20i-mailbox-38bb-native-verification.json`; catalog `work/catalog-combined38-native-verification.json`; registrar `work/20i-domain-controls/qa/registrar38-native-verification.json`; cloud `work/20i-vps-controls/qa/cloud-combined-native-verification.json`. The archived handoff retains earlier evidence and closed process handles.
- Catalog parent8f full38819685543 is now final-success and artifact-verified:12013629800, SHA2569ed044b20a7bc21860a3cef3d2ef4d768cbd428d59ce48d35b16cbaa99de25ef,1794 HTTP/289 recovery/1827 Apache, all11 checksums and exact packages. Cloud parentce8 full38821117748 passed browser/recovery and entered Apache; cloud agent retains final verification assignment. Earlier mailbox334 capability failure remains preserved; only a new combined browser pass can verify the correction.

## Parallel implementation ownership

1. Domain agent: NEW `codex/twentyi-domain-renewal-requests` from38bb, isolated `work/20i-domain-controls`. Build native client requests, withdrawal, owner review/decline and immutable history, bound to current explicit registrar assignment/account. No provider dispatch, price/expiry or completed-renewal claim. New domain_renewals storage/schema/controllers/views/tests; minimal shared router/navigation wiring. Preserve original725 and nameserver9ea branches.
2. Catalog agent: NEW branch from38bb for owner-only precise readiness reasons and immutable historical configuration/cost details in existing plan views. Preserve execution gates and unknown-provider states. Own catalog/plan views and focused native/browser tests. Original8f remains unchanged. Discovery contract intake is docs-onlyfcf86589, [PR144](https://github.com/afterburn25/Cajun_Web/pull/144), preserved for later deliberate merge; avoid replacing concise root status with older branch status.
3. Cloud agent: NEW `codex/twentyi-cloud-confirmation` in `work/20i-cloud-confirmation`, from38bb. Confirm only exact returned package ID/approved names in fresh complete GET/package evidence plus current original client/server assignment context; explicitly link client and append atomic evidence. Label allocated package observed and assigned. Populated cloud packageIds semantics are unverified, so claim no current server membership/location, health or billing activation. Reject missing/changed/unknown histories and do not retry. Own new confirmation helper/storage/tests plus minimal cloud view/router wiring. Preserve33a2 branch.

All new slices remain under implementation. Root alone integrates shared wiring sequentially and releases. Preserve frozen acceptance bytes and all current runs while agents build in isolated checkouts.

## Next root actions and preserved constraints

Inspect combined browser checkpoint, actual new screens, diagnostics and named reports; compare Linux packages with preserved Windows source38bb. Await recovery/Apache and source-unchanged gate. Thirty companion PR workflows report success but only the named downloaded artifacts above are independently artifact-verified.

Observed main1fcfb5e9 is51 added lines in tests/license_bundle_functional.php, source-inspected only. It is not yet merged and was not executed; keep Website_Core untouched. Merge current main normally before exact-main acceptance. Retain actor-bound domain replacement audit transactions and the40 native licensing checks in populated prefixlicmerge1015_; never reuse that fixture prefix.

Official full20i API documentation points to My20i; a read-only browser check redirected to login with no existing authenticated session. No authentication was attempted. Original195457-byte API upload remains unavailable. Nameserver9ea and discoveryPR144 record exact missing schemas; do not invent readers or repeat the prior upload question while independent implementation can proceed.

Keep provider key-file protection, no Windows bypass. No live writes, charges, mail, SSH or deployment without their existing specific gates. PR attachment cap100 and prior PR comment/review/metadata auto-review rejections remain; do not remove attachments or bypass rejections through another tool. New docs checkpoint needs only documentation/whitespace checks; release still requires independent review, exact-main application acceptance and downloaded asset verification.

## Preserved incoming main evidence

The following report is imported from mainc3b1a69c; it describes a1e64f81 and does not certify this combined source.

Acceptance evidence: [run 38817902722](https://github.com/afterburn25/Cajun_Web/actions/runs/38817902722) on `a1e64f81` passes all 15 `native-and-browser` steps including "Application server has no PHP runtime warnings or errors". The preceding cumulative run 38813445946 failed that check on a pre-existing defect unrelated to licensing: `fail_page()` called `http_response_code()` unconditionally, warning whenever an exception fired after `layout/public.php` emitted its header (58 warnings across `existing`/`ledger-upgrade`/`delivery-upgrade`/`activity-upgrade`/`portfolio-upgrade` fixture sites). `a1e64f81` guards the call with `headers_sent()`; the fail page still renders.

Not live-verified: production needs `license_core_dir` pointing at a Website_Core checkout; no real purchase/bundle/install/validation cycle has run. Full details in [LICENSING_IMPLEMENTATION.md](docs/LICENSING_IMPLEMENTATION.md). This workstream is separate from 20i P0 below.
