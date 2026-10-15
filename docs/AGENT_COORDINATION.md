# Agent coordination — complete20i P0

Codex continues the complete20i P0 priority. This is an implementation/integration branch, not a released or live-verified platform. Published version remains [1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0) at7190b582; inspected main0b96f3d5 adds only the approved portal-design records to c42dd6d7.

The combined `codex/twentyi-fulfillment` source preserves these exact parents:
- Domain search/cloud `9ab6dc58eb2a2e9648968fbe94091c5bbe1e45a8`, tree80f679da9f90aed603d45a00a9d145761823a040; retains cloud #127/#128 through1d9e372c.
- Billing recovery `35a564a1a2f6660258ff0e0f2fcf057cb24a07f3`; retains #130/#132/#133/#135, existing clients, immutable plan consent, recurring invoices and the corrected FK-safe missing-schedule fixture.
- Nameserver request component `f8654252e1d029c0474c4fbf54839142b621ff65`, treead25e1e908bf68d6a4897aa0bbc204bf246716aa; retains main0b96f3d5 and the approved client-portal design.

All three histories are merge parents; source branches and existing PRs remain intact. The combined work is for completing order/provider fulfillment and testing existing dependencies together. It is not permission to publish unfinished controls or automatically charge customers.

## Evidence and limits

Nameserver f8654252 [workflow38759308990](https://github.com/afterburn25/Cajun_Web/actions/runs/38759308990) passed **230 named assertions per run**, including79 nameserver cases, in default/no-ini PHP8.2 and8.4: four actual JSON reports,920 assertion executions. Canonical shipping hashes and documentation checks passed. This is network-disabled request construction; no native nameserver screen, ownership observation, dispatch or completed DNS change is claimed.

Domain #136 at9ab6dc58 passed22 focused workflows, including the four repaired inventory/upgrade combinations. [Full38757561159](https://github.com/afterburn25/Cajun_Web/actions/runs/38757561159) has passed build/native, actual HTTP/Chromium and recovery; Apache is still running at this checkpoint. The earlier099 full38755410755 completed with the retained native fixture failure, browser skipped, and passing289 recovery/1,531 Apache checks. Its failure is not hidden or recast as acceptance.

Billing35a [full38750019637](https://github.com/afterburn25/Cajun_Web/actions/runs/38750019637) succeeded, including1,513 HTTP/Chromium,275 recovery and1,527 Apache checks. Cloud1d9 has separate full38719850550 success on an identical-tree PR merge source. Current screenshots/package comparisons and required independent reviews remain separate outstanding gates. Those parent results do not certify this combined descendant.

Merged Python acceptance drivers pass in-memory AST checks. **Combined native/browser/recovery acceptance has not run yet.** Installer hashes are rebuilt from the selected exact shipping bytes. The existing unpublished1.25 version/staging labels are retained for development testing; a combined release scope/version and exact-main acceptance must be selected before publication. No tag or release asset changed.

## Remaining implementation

The combined branch supplies hosting/cloud ownership, bounded owner domain search, service agreements/recurring billing and exact nameserver request deltas. It still needs verified provider-plan mappings, an authenticated selected-plan order, payment/manual-fulfillment eligibility, the durable order-to-provider-to-client-service connection, native hosting/domain/VPS controls and support/interactive SSH. Client/public search, domain periods/pricing/registration/transfer and native nameserver current-state/confirmation/reconciliation are unfinished. These are engineering tasks, not missing-owner-approval labels.

Real writes stay disabled until their operation-specific account, ownership, consent and acceptance gates are met. No provider call, DNS change, charge, customer mail, SSH or deployment occurred. The complete owner API reference was requested once; continue independent work while it remains unavailable. Local filesystem writes and shell artifact downloads are restricted. GitHub review-request comments and PR metadata edits were rejected by the approval policy; source commits/branch/PR creation are available.

The combined branch owns the forthcoming fulfillment connection and interaction repairs. Preserve separate #127/#130/#133/#135/#136 branches and their stable test evidence. No document or GitHub event proves an external agent has stopped. The complete takeover supersedes earlier Devin allocations; Codex implements remaining work. [Integration record](TWENTYI_FULFILLMENT_INTEGRATION.md).
