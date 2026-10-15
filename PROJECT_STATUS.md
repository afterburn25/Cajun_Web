# Project status — complete 20i P0

Codex owns implementation through release. Published baseline remains [v1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0), source `7190b582a02be8a1daa501db14b5899cfc1eb5d0`; main remains `c42dd6d70fe57276d75835419db9d6e8e51aaa7f`. Full 20i remains unfinished. No production action or deployment occurred.

## Domain search — PR #136

[PR #136](https://github.com/afterburn25/Cajun_Web/pull/136) extends cloud #127 on `codex/twentyi-domain-search`. At `099aa2e8cef128d340f634b9cbebe2c46c2fa2cf`, [native domain workflow38755410653](https://github.com/afterburn25/Cajun_Web/actions/runs/38755410653) passed **54 named assertions on each of four PHP8.2/8.4 × MariaDB10.11/MySQL8.4 combinations**, including two concurrent owners sharing one provider-read allowance. Actual named JSON results were parsed from all four logs.

The broader [inventory workflow38755469853](https://github.com/afterburn25/Cajun_Web/actions/runs/38755469853) failed because two older native fixtures rendered the combined provider view without its controller, leaving `provider_domain_search_ready` undefined. [Full application run38755410755](https://github.com/afterburn25/Cajun_Web/actions/runs/38755410755) failed native acceptance and skipped browser execution; its remaining recovery checks are retained. This checkpoint repairs both test fixtures to load the same controller as the real admin route, retains all original assertions, and adds checks that the unprepared combined page changes neither table inventory nor settings. It does not hide a failing feature or change runtime behavior.

Full application concurrency now distinguishes source commits so a necessary fix does not cancel an earlier candidate's running recovery evidence. Duplicate runs of the same source still share a group. **The corrected fixtures and packaged browser cases await new CI.** No screenshot/artifact or release acceptance is claimed for this descendant.

## Other integration candidates

- Cloud #127, source `1d9e372cac7ff9739d339b2544c7c9cfbcee3e33`: full run38719850550 succeeded using merge source97b0b869 with identical tree24330be9; current screenshots and package comparison remain pending before1.25 integration/release.
- Billing recovery #135, source `35a564a1a2f6660258ff0e0f2fcf057cb24a07f3`: [full run38750019637](https://github.com/afterburn25/Cajun_Web/actions/runs/38750019637) succeeded, including1,513 HTTP/browser,275 recovery and1,527 Apache checks. Current artifacts/package comparison and independent review remain pending. Preserve #130/#133/#135 ancestry.
- Client/public domain search, verified pricing/periods, registration/transfer, native hosting controls, VPS/cloud lifecycle, support/SSH and the complete order-to-service journey remain unfinished.

Local writes and shell artifact downloads are restricted. Review-request comments and PR metadata updates were rejected by the approval policy; source commits and PR creation work through the GitHub connector. The complete owner API source remains a previously requested input. Continue independent P0 implementation; no missing implementation is classified as owner-gated.
