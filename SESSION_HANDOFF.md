# Session handoff — complete20i P0

Codex continues the complete20i P0 priority. This is an implementation/integration branch, not a released or live-verified platform. Published version remains [1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0) at7190b582; inspected main0b96f3d5 adds only the approved portal-design records to c42dd6d7.

The combined `codex/twentyi-fulfillment` source preserves these exact parents:
- Domain search/cloud `9ab6dc58eb2a2e9648968fbe94091c5bbe1e45a8`, tree80f679da9f90aed603d45a00a9d145761823a040; retains cloud #127/#128 through1d9e372c.
- Billing recovery `35a564a1a2f6660258ff0e0f2fcf057cb24a07f3`; retains #130/#132/#133/#135, existing clients, immutable plan consent, recurring invoices and the corrected FK-safe missing-schedule fixture.
- Nameserver request component `f8654252e1d029c0474c4fbf54839142b621ff65`, treead25e1e908bf68d6a4897aa0bbc204bf246716aa; retains main0b96f3d5 and the approved client-portal design.

All three histories are merge parents; source branches and existing PRs remain intact. The combined work is for completing order/provider fulfillment and testing existing dependencies together. It is not permission to publish unfinished controls or automatically charge customers.

## Evidence and limits

Nameserver f8654252 [workflow38759308990](https://github.com/afterburn25/Cajun_Web/actions/runs/38759308990) passed **230 named assertions per run**, including79 nameserver cases, in default/no-ini PHP8.2 and8.4: four actual JSON reports,920 assertion executions. Canonical shipping hashes and documentation checks passed. This is network-disabled request construction; no native nameserver screen, ownership observation, dispatch or completed DNS change is claimed.

Domain #136 at9ab6dc58 passed22 focused workflows, including the four repaired inventory/upgrade combinations. [Full38757561159](https://github.com/afterburn25/Cajun_Web/actions/runs/38757561159) completed successfully, including build/native, actual HTTP/Chromium, recovery and Apache. Its full artifact11986376482 is retained; downloaded screenshots/package bytes have not been independently inspected here. The earlier099 full38755410755 completed with the retained native fixture failure, browser skipped, and passing289 recovery/1,531 Apache checks. Its failure is not hidden or recast as acceptance.

Billing35a [full38750019637](https://github.com/afterburn25/Cajun_Web/actions/runs/38750019637) succeeded, including1,513 HTTP/Chromium,275 recovery and1,527 Apache checks. Cloud1d9 has separate full38719850550 success on an identical-tree PR merge source. Current screenshots/package comparisons and required independent reviews remain separate outstanding gates. Those parent results do not certify this combined descendant.

Combined parent8ed80fd9 has32 focused workflows passing. [Full38761131563](https://github.com/afterburn25/Cajun_Web/actions/runs/38761131563) tested same-tree PR source693fe344: build/native, actual HTTP/Chromium and289 recovery checks passed; Apache failed at the retained1.14 cache-presence precondition. This checkpoint gives the multi-installation Apache fixture explicit cache capacity and saves its exact cache diagnostics; the original assertion and every upgrade case remain. A fresh complete run is required. The original failed artifact11988260528 remains retained. Installer hashes are rebuilt from the selected exact shipping bytes. The existing unpublished1.25 version/staging labels are retained for development testing; a combined release scope/version and exact-main acceptance must be selected before publication. No tag or release asset changed.

## Current payment-evidence implementation

From combined parent8ed80fd9 on [PR137](https://github.com/afterburn25/Cajun_Web/pull/137), native receipt assessment validates exact invoice/client/project identity, original receipt audits, corrections, hashes and unresolved attempts. Imported balances, zero/void invoices and sandbox captures cannot become verified new payment. Authorized staff see this distinction in existing recurring invoice cards. The projection does not grant order or provider authority; client payment-history behavior is retained. New native corruption/reversal/legacy/sandbox tests and actual HTTP receipt/correction controls extend the existing suites. Payment source423dd598 (same-tree PR merge33db5925) passed32 focused workflows. Native recurring/ledger run38765096311 passed all four PHP8.2/8.4 and MariaDB10.11/MySQL8.4 combinations; four actual JSON reports contain108 named ledger checks each, including39 new payment-evidence cases (432 executions). Its full38765096283 has passed build/native and actual HTTP/Chromium and is running recovery; it must continue without cancellation. These results do not certify the subsequent hosting recovery repair.


## Hosting history recovery guard — 2026-10-15

Source inspection of parent423dd598 found marker-only hosting readiness and setup paths that attempted additive table creation even for an established family. The new closed hosting storage helper checks every required table and checks original setup audit when a marker is lost. Established missing history now requires original database restoration before any DDL; fresh/interrupted empty setup and strict schema validation remain. This is a recovery repair for the required order/hosting dependency, not a new execution engine.

Native tests retain all previous cases and add each missing records/dispatch/results/resources/observation/reconciliation table, missing marker, whole-family loss, refusal without replacement tables/audit writes, and exact restoration. These new cases are awaiting current-head CI; no baseline reproduction run is claimed. Private-path coverage and installer hashes include the helper. No schema/version transition or provider call is added.

## Remaining implementation

The combined branch supplies hosting/cloud ownership, bounded owner domain search, service agreements/recurring billing and exact nameserver request deltas. It still needs verified provider-plan mappings, an authenticated selected-plan order, the remaining order/manual-policy authorization, the durable order-to-provider-to-client-service connection, native hosting/domain/VPS controls and support/interactive SSH. Client/public search, domain periods/pricing/registration/transfer and native nameserver current-state/confirmation/reconciliation are unfinished. These are engineering tasks, not missing-owner-approval labels.

Real writes stay disabled until their operation-specific account, ownership, consent and acceptance gates are met. No provider call, DNS change, charge, customer mail, SSH or deployment occurred. The complete owner API reference was requested once; continue independent work while it remains unavailable. Local filesystem writes and shell artifact downloads are restricted. GitHub review-request comments and PR metadata edits were rejected by the approval policy; source commits/branch/PR creation are available.

See [integration decisions](docs/TWENTYI_FULFILLMENT_INTEGRATION.md) and [approved portal design](docs/APPROVED_CLIENT_PORTAL_DESIGN.md). Other roadmap development remains paused.


## Next concrete actions

1. Inspect the new hosting recovery native matrix and exact-head full CI. Preserve payment423 full38765096283 in flight; it certifies only its own source. Preserve original8ed/693fe failure and verify the Apache cache precondition through every retained release in both modes.
2. Build durable client order/plan/provider mapping and explicit manual-fulfillment policy. The new ledger evidence is only payment evidence: never treat it as an order, mandate or dispatcher grant. Bind a unique obligation and recheck pinned current evidence at dispatch. Reuse existing gateway/operation ledger and hosting journals.
3. Finish native nameserver/hosting/domain/VPS controls and support/interactive SSH. Keep unknown write outcomes blocked from blind replay.
4. Inspect current screenshots/packages, obtain independent review, normally integrate main, verify exact main and downloaded versioned release assets. Do not call parent/component tests complete platform acceptance.
5. Preserve dirty local worktrees: current source is remote. The exact approved portal image remains in the separate design conversation, not mounted locally or archived to GitHub; do not generate a substitute.

## Owner-approved client portal design — 2026-10-15

The owner approved the immediately preceding navy/blue/metallic-gold client-portal concept: **“make this the approved design.”** Follow [Approved client portal design](docs/APPROVED_CLIENT_PORTAL_DESIGN.md) for the exact reference identity, locked layout, styling, responsive requirements and data/capability boundaries. Approval record committed at `fd2001e90a54e9936e0c3aebf2714e05a2916a82` from inspected main `c42dd6d70fe57276d75835419db9d6e8e51aaa7f`.

Preserve the gold-accented header, left navigation, bayou-sunset welcome banner, four overview tiles, central services/invoices/announcements and right account/actions/support cards. Use the existing approved production logo/favicon and real client-scoped data. Do not replace this with another generic dashboard. Sample identities, amounts, claims and statuses in the concept are not production data or new commercial promises.

Codex applies this baseline to portal implementation within the existing **20i P0** priority; unrelated development stays paused. This approval does not change runtime, release version, API authorization or deployment status. Verification was attachment metadata/hash inspection and a byte-identical local copy, not application testing. **The original PNG remains a conversation attachment and has not been copied into GitHub**; archive the hash-verified original at the intended path documented in the approval before visual acceptance, rather than generating a replacement. Next action: continue the 20i implementation using this approved design and report actual UI/test/release progress separately.


Parent sources and conflict resolutions are recorded in [TWENTYI_FULFILLMENT_INTEGRATION](docs/TWENTYI_FULFILLMENT_INTEGRATION.md). Prior billing and domain session histories remain in the corresponding immutable merge parents; this handoff supersedes their current-status sections without erasing Git history.
