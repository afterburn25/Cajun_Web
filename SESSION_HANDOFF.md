# Session handoff — continue 20i P0

## Nameserver delta implementation — 2026-10-15

Continue complete20i P0 on `codex/twentyi-nameserver-controls` from inspected main `0b96f3d5812927640274d285b0180f3025624980`. Main advanced by two documentation-only commits recording the owner's approved client-portal design; their history and exact [design baseline](docs/APPROVED_CLIENT_PORTAL_DESIGN.md) are retained. Published runtime remains1.24/7190b582.

The owner supplied the exact POST `/package/{packageId}/domain/{domainId}/nameservers` fields `ns` and `old-ns`. The existing request builder now accepts those typed arrays, rejects overlap/duplicates/no-op or malformed targets, and retains fixed routing and request bounds. A canonical helper compares known original and desired DNS hostname sets and emits only additions/removals. It does not send overlapping full replacement lists. The generic approval menu remains restricted to package labels; completing a request shape does not grant execution or domain ownership. Unverified response bodies remain uncertain.

Added isolated positive/negative tests for canonicalization, unchanged names, add/remove-only changes, list/hostname bounds, invalid IDs, no-op/overlap rejection, unknown fields, no credentials and denied generic admission. Existing operation cases remain, with the former nameserver “shape missing” expectations replaced by documented typed validation. Canonical shipping mirrors and the installer inventory are regenerated. This checkpoint's new tests have not run yet; the branch workflow will run default/no-ini PHP8.2/8.4 with network disabled and preserve named reports.

The native nameserver screen, fresh authorized observation, per-domain consent, durable dispatch and operation-specific reconciliation remain to implement. This is a request component, not a finished control or release. No provider call, DNS change, charge, mail, SSH or deployment occurred.

Domain-search #136 source9ab6dc58 separately passes all four inventory/upgrade combinations and22 focused workflows; full38757561159 has passed build/native and is running HTTP/Chromium. Earlier099 full38755410755 retains its failed fixture evidence and continues recovery/Apache. Billing35a full38750019637 succeeded; cloud and billing artifact/review/integration gates remain. Preserve all independent branches and running evidence.

Current branch checkpoint parent0b96f3d5; commit message “Validate exact nameserver additions and removals.” Inspect that branch's tests, then implement scoped current-state/confirmation/ledger/native forms without widening generic approval. Current domain #136 and billing #135 sources exist remotely beyond their local worktrees; do not discard the preserved local dirty files. Do not overwrite the newly approved design section below. Older release/session notes below are historical.

## Owner-approved client portal design — 2026-10-15

The owner approved the immediately preceding navy/blue/metallic-gold client-portal concept: **“make this the approved design.”** Follow [Approved client portal design](docs/APPROVED_CLIENT_PORTAL_DESIGN.md) for the exact reference identity, locked layout, styling, responsive requirements and data/capability boundaries. Approval record committed at `fd2001e90a54e9936e0c3aebf2714e05a2916a82` from inspected main `c42dd6d70fe57276d75835419db9d6e8e51aaa7f`.

Preserve the gold-accented header, left navigation, bayou-sunset welcome banner, four overview tiles, central services/invoices/announcements and right account/actions/support cards. Use the existing approved production logo/favicon and real client-scoped data. Do not replace this with another generic dashboard. Sample identities, amounts, claims and statuses in the concept are not production data or new commercial promises.

Codex applies this baseline to portal implementation within the existing **20i P0** priority; unrelated development stays paused. This approval does not change runtime, release version, API authorization or deployment status. Verification was attachment metadata/hash inspection and a byte-identical local copy, not application testing. **The original PNG remains a conversation attachment and has not been copied into GitHub**; archive the hash-verified original at the intended path documented in the approval before visual acceptance, rather than generating a replacement. Next action: continue the 20i implementation using this approved design and report actual UI/test/release progress separately.

## Synchronize release entry points after review — 2026-10-15

Review4281622110 on4cbc2375 reproduced stale pending1.24 headers in README, agent coordination, release workflow and inventory owner guide. Update every listed entry point plus current migration instructions to the actual immutable publication, archive earlier coordination and mark historical1.23 sections clearly. No runtime or package bytes change. Starting parent4cbc23758674b29899d235b511b40f0a18cf8503 is “Record verified1.24 publication and current20i continuation”; correctiondf57d0ce is “Synchronize published1.24 across current entry-point guides.” Actual local documentation check passed161 Markdown files/1,330 local links and canonical dependency checks; git diff whitespace check passed. These checks cover documentation/source consistency only, not runtime acceptance, which remains the separately published7190 evidence. Known failure: initial review4281622110 found stale headers and was corrected. Follow-up review4281670745 requested this explicit validation record. Renewed review is pending; continue cloud/commercial20i work.

Updated2026-10-15.

Codex owns the complete 20i P0 integration. [CajunWebPro 1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0) is published and freshly downloaded/verified at immutable source `7190b582a02be8a1daa501db14b5899cfc1eb5d0`. All16 assets, checksums, ZIP members and the tag agree with accepted source; previous1.23 assets are unchanged. This is a GitHub release, not live-site deployment. Full20i is unfinished; unrelated roadmap work remains paused.

[Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38711850732) passes9,134 named assertion executions in44 terminal reports and all30 workflow gates. The29 focused gates contain158 named reports/17,034 executions, counted separately. Fresh/existing setup, populated inventory upgrades, client isolation, moved-domain reapproval, unknown-outcome/no-replay behavior, retained file recovery and both Apache modes pass. Actual desktop/mobile screens were inspected; homepage pixels match1.23 and PHP diagnostics are empty. All12 clean Windows/Linux distribution files match. Full report: [TEST_REPORT_1.24.0.md](docs/TEST_REPORT_1.24.0.md).

## Continue

1. Prepare cloud inventory/client assignments #127 for1.25 using the immutable1.24 update baseline. Retain #128 history and current package/browser/recovery/review gates.
2. Complete the necessary commercial dependency #130/#133. Plan-name correction #132 is normally merged at5d2785cf; exacteddb packaged checkpoint passes7,866 (HTTP1,439), native552 and clear review. Parent7ce full acceptance38711180719 passes9,595 and is historical evidence only. Recurring UI f28c1f44 still needs correction for review4281512902: lost invoice mapping plus changed reference must not become a legacy invoice. Preserve current running checkpoint before changing its branch.
3. Continue exact provider mapping, verified payment or explicit manual fulfillment, durable order-derived ownership, native hosting/domain/VPS controls, support and SSH. Agreements/invoices alone do not authorize provider purchases or automatic charges.
4. The owner's complete API upload still needs an accessible original path/link; requested once. Continue all independent implementation using verified official sources. Production account/merchant/gateway/target-host acceptance stays separately tracked.

[Preserved earlier checkpoint](docs/archive/2026-10-15-pre-1.24-publication-SESSION_HANDOFF.md). No production charge, provider write, customer mail, SSH or deployment occurred.
