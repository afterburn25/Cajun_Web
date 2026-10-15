# Session handoff — complete20i P0

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

## Next concrete actions

1. Inspect combined PR CI against the exact merge head. Keep every retained test and repair any interaction failure. Inspect the completed9ab full run separately; never substitute it for combined acceptance.
2. Implement the missing verified plan/order/payment/manual-policy to durable provider-request path on this integration branch, reusing the existing ledger. Preserve current clients and explicit consent. Synthetic paid fixtures must stay test-only.
3. Finish native controls with current authorized observations, typed forms and scoped dispatch/reconciliation. Nameserver request construction is now tested; it does not establish a provider response or ownership contract.
4. Complete artifact inspection, independent review, normal protected-main integration, exact-main tests and downloaded release verification. Cloud #127 remains a separate candidate; do not overwrite its stable source or prior releases.
5. Reconcile local worktrees when writes return. New domain/native/browser fixes and billing35a corrections exist remotely beyond their local files. Preserve all existing dirty local work.

## Owner-approved client portal design — 2026-10-15

The owner approved the immediately preceding navy/blue/metallic-gold client-portal concept: **“make this the approved design.”** Follow [Approved client portal design](docs/APPROVED_CLIENT_PORTAL_DESIGN.md) for the exact reference identity, locked layout, styling, responsive requirements and data/capability boundaries. Approval record committed at `fd2001e90a54e9936e0c3aebf2714e05a2916a82` from inspected main `c42dd6d70fe57276d75835419db9d6e8e51aaa7f`.

Preserve the gold-accented header, left navigation, bayou-sunset welcome banner, four overview tiles, central services/invoices/announcements and right account/actions/support cards. Use the existing approved production logo/favicon and real client-scoped data. Do not replace this with another generic dashboard. Sample identities, amounts, claims and statuses in the concept are not production data or new commercial promises.

Codex applies this baseline to portal implementation within the existing **20i P0** priority; unrelated development stays paused. This approval does not change runtime, release version, API authorization or deployment status. Verification was attachment metadata/hash inspection and a byte-identical local copy, not application testing. **The original PNG remains a conversation attachment and has not been copied into GitHub**; archive the hash-verified original at the intended path documented in the approval before visual acceptance, rather than generating a replacement. Next action: continue the 20i implementation using this approved design and report actual UI/test/release progress separately.


Parent sources and conflict resolutions are recorded in [TWENTYI_FULFILLMENT_INTEGRATION](docs/TWENTYI_FULFILLMENT_INTEGRATION.md). Prior billing and domain session histories remain in the corresponding immutable merge parents; this handoff supersedes their current-status sections without erasing Git history.
