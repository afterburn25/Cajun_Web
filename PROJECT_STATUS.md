## Current owner allocation - 2026-10-16

**Devin: continue all remaining non-20i site work, including licensing. Codex and its three agents: continue complete20i.** This supersedes older unrelated-work pause statements for Devin. [Start here](DEVIN_START_HERE.md) gives the full assignment, direct licensing code locations, shared-file boundaries and instructions that work without the GitHub API. Licensing is already on main; inspect and repair its documented gaps rather than recreating it. Work on separate feature branches; Codex coordinates final integration/releases. Assignment [#157](https://github.com/afterburn25/Cajun_Web/issues/157) is posted; external-session acknowledgement is not assumed.

# Project status — 20i P0

## Devin non-20i: licensing v4 fixes — 2026-10-16

Assignment #157 first increment on `devin/non20i-licensing` (from `80ce8b19`): the four `docs/LICENSING_INTEGRATION_REVIEW.md` findings are resolved in source — marker-before-DDL refusal for unknown/future versions, additive `is_master` upgrade for populated pre-master databases, master-key exclusion across portal/bundle/direct-download/admin layers, and ambiguity-safe NULL-invoice reconciliation in fulfilment. Schema marker moves to `4`. PHP syntax clean; installer inventory regenerated; native MariaDB + packaged lanes run in CI. Remaining non-20i roadmap continues after this increment.

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

[Preserved earlier checkpoint](docs/archive/2026-10-15-pre-1.24-publication-PROJECT_STATUS.md). No production charge, provider write, customer mail, SSH or deployment occurred.
