# Complete20i integration — active development

Codex continues the complete20i P0 priority. This is an implementation/integration branch, not a released or live-verified platform. Published version remains [1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0) at7190b582; inspected main0b96f3d5 adds only the approved portal-design records to c42dd6d7.

The combined `codex/twentyi-fulfillment` source preserves these exact parents:
- Domain search/cloud `9ab6dc58eb2a2e9648968fbe94091c5bbe1e45a8`, tree80f679da9f90aed603d45a00a9d145761823a040; retains cloud #127/#128 through1d9e372c.
- Billing recovery `35a564a1a2f6660258ff0e0f2fcf057cb24a07f3`; retains #130/#132/#133/#135, existing clients, immutable plan consent, recurring invoices and the corrected FK-safe missing-schedule fixture.
- Nameserver request component `f8654252e1d029c0474c4fbf54839142b621ff65`, treead25e1e908bf68d6a4897aa0bbc204bf246716aa; retains main0b96f3d5 and the approved client-portal design.

All three histories are merge parents; source branches and existing PRs remain intact. The combined work is for completing order/provider fulfillment and testing existing dependencies together. It is not permission to publish unfinished controls or automatically charge customers.

## Evidence and limits

Nameserver f8654252 [workflow38759308990](https://github.com/afterburn25/Cajun_Web/actions/runs/38759308990) passed **230 named assertions per run**, including79 nameserver cases, in default/no-ini PHP8.2 and8.4: four actual JSON reports,920 assertion executions. Canonical shipping hashes and documentation checks passed. This is network-disabled request construction; no native nameserver screen, ownership observation, dispatch or completed DNS change is claimed.

Domain #136 at9ab6dc58 passed22 focused workflows, including the four repaired inventory/upgrade combinations. [Full38757561159](https://github.com/afterburn25/Cajun_Web/actions/runs/38757561159) completed successfully through Apache; artifact11986376482 is retained, while direct screenshot/package inspection remains separate. The earlier099 full38755410755 completed with the retained native fixture failure, browser skipped, and passing289 recovery/1,531 Apache checks. Its failure is not hidden or recast as acceptance.

Billing35a [full38750019637](https://github.com/afterburn25/Cajun_Web/actions/runs/38750019637) succeeded, including1,513 HTTP/Chromium,275 recovery and1,527 Apache checks. Cloud1d9 has separate full38719850550 success on an identical-tree PR merge source. Current screenshots/package comparisons and required independent reviews remain separate outstanding gates. Those parent results do not certify this combined descendant.

Merged Python acceptance drivers pass in-memory AST checks. Combined8ed/693fe full38761131563 passed native/browser/recovery and failed the Apache cache precondition described below; payment423 full38765096283 is running with that fixture correction. Installer hashes are rebuilt from the selected exact shipping bytes. The existing unpublished1.25 version/staging labels are retained for development testing; a combined release scope/version and exact-main acceptance must be selected before publication. No tag or release asset changed.

## Remaining implementation

The combined branch supplies hosting/cloud ownership, bounded owner domain search, service agreements/recurring billing and exact nameserver request deltas. It still needs verified provider-plan mappings, an authenticated selected-plan order, payment/manual-fulfillment eligibility, the durable order-to-provider-to-client-service connection, native hosting/domain/VPS controls and support/interactive SSH. Client/public search, domain periods/pricing/registration/transfer and native nameserver current-state/confirmation/reconciliation are unfinished. These are engineering tasks, not missing-owner-approval labels.

Real writes stay disabled until their operation-specific account, ownership, consent and acceptance gates are met. No provider call, DNS change, charge, customer mail, SSH or deployment occurred. The complete owner API reference was requested once; continue independent work while it remains unavailable. Local filesystem writes and shell artifact downloads are restricted. GitHub review-request comments and PR metadata edits were rejected by the approval policy; source commits/branch/PR creation are available.

## Ancestry and resolution record

- Domain9ab and billing35a merge base: `5fa5998dab9c727b9b2f29c1ff3e162187364280`. Comparison returned97 billing-side files and83 domain-side files; there are12 shared paths. No source removal or rename was selected.
- Nameserverf865 and domain9ab merge base: `c42dd6d70fe57276d75835419db9d6e8e51aaa7f`. Nameserver runtime/request tests are independent of domain/cloud changes. Its parent0b96 carries the two owner-approved design commits.
- Runtime PHP changes from billing and domain do not conflict outside the generated installer inventory. Billing adds its exact client/staff/plan/invoice modules; domain keeps the latest cloud ownership, parser, policy/history and provider controller/view repairs. Nameserver canonical files/mirrors remain byte-identical to f865.
- The application workflow retains domain-search native tests, both commercial/recurring native/form checks and all existing tests. It adds the network-free nameserver request suite to combined native acceptance. Source-specific concurrency preserves earlier runs.
- Browser acceptance runs the domain-search, service-plan and recurring-billing suites in the existing sequence. Neither suite replaces the other.
- Apache retains billing's private-path cases and domain's actual1.24 upgrade baseline, and adds denial checks for the new cloud/domain/nameserver internals in root/subfolder and rewrite/no-rewrite modes.
- Organized upgrade tests retain the entire domain1.24 populated ownership/unknown-operation recovery case and billing's1.23 agreement/recurring upgrade/reapplication case.
- The shipping inventory is rebuilt from the selected source hashes, including all billing additions, domain/cloud changes and three nameserver files. The build must independently verify every mirror and inventory hash.
- START-HERE and runtime version/staging labels retain domain's1.25 development values, including the real published1.24 baseline. Older billing1.24 staging text is not reintroduced. No1.25 publication is asserted.
- Current status/handoff/coordination are rewritten around combined reality and explicitly retain the approved portal-design section. Changelog/session log use domain's history plus the integration record and immutable links to billing's retained parent histories. No ancestor commit is rewritten.
- The roadmap retains the released1.24 status and adds the commercial integration status. Wider project features stay paused.

## Acceptance still required

Run all applicable focused workflows and complete extracted-package/native/HTTP/Chromium/recovery/Apache tests on this combined tree. Inspect actual reports/screenshots, verify cross-platform package bytes and obtain independent review before release. Preserve current original keys, invoices, client links, unknown operation attempts and consent through updates/restores. Test the forthcoming complete order/service path instead of treating this dependency merge as business fulfillment.

The approved [client-portal design](APPROVED_CLIENT_PORTAL_DESIGN.md) stays authoritative for client work. Its exact image attachment still requires hash-verified repository archival before visual acceptance; the missing file is not replaced with a generated look-alike. Current runtime preserves the existing client portal until its native implementation is completed.

## Payment evidence and first combined acceptance

Parent8ed80fd9 (same-tree PR source693fe344) passed its focused workflows and full build/native/browser/recovery stages in38761131563. Apache failed at `rewrite 1.14.0 prior organized version actually cached without timestamp checks`. The old probe did not save enough cache diagnostics to prove its capacity state. This correction preserves that assertion, saves complete non-secret status and gives the single Apache process capacity for its many retained full installations/staging trees (512MiB/100000 keys). It changes only the test fixture, not production PHP settings. Fresh complete acceptance must establish the result.

The same checkpoint adds bounded immutable payment-evidence assessment plus the authorized staff recurring-invoice projection and native/HTTP regression cases. Existing receipt totals and legacy credits keep their accounting meaning; fulfillment cannot relabel them. See [commercial evidence and remaining interfaces](TWENTYI_COMMERCIAL_ELIGIBILITY.md). No source branch was reset, tag moved or existing artifact replaced.

## Hosting history recovery guard — 2026-10-15

Source inspection of parent423dd598 found marker-only hosting readiness and setup paths that attempted additive table creation even for an established family. The new closed hosting storage helper checks every required table and checks original setup audit when a marker is lost. Established missing history now requires original database restoration before any DDL; fresh/interrupted empty setup and strict schema validation remain. This is a recovery repair for the required order/hosting dependency, not a new execution engine.

Native tests retain all previous cases and add each missing records/dispatch/results/resources/observation/reconciliation table, missing marker, whole-family loss, refusal without replacement tables/audit writes, and exact restoration. These new cases are awaiting current-head CI; no baseline reproduction run is claimed. Private-path coverage and installer hashes include the helper. No schema/version transition or provider call is added.

Payment423 native [38765096311](https://github.com/afterburn25/Cajun_Web/actions/runs/38765096311) passed108 named ledger checks per combination,39 newly added, across four actual reports. All32 focused workflows passed. Full [38765096283](https://github.com/afterburn25/Cajun_Web/actions/runs/38765096283) remains in progress; do not substitute it for this subsequent source's acceptance.
