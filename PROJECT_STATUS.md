# Project status

## Focused CI fixture correction — 2026-10-15

Takeover [PR #126](https://github.com/afterburn25/Cajun_Web/pull/126) is open at first implementation `d2f59fba32b98153119b33072647cccdbb25696d`. Downloaded push run [38686268179](https://github.com/afterburn25/Cajun_Web/actions/runs/38686268179) fails: MariaDB passes 74 assertions (including the repaired revocation check) then the longer fixture reaches the real hourly quota before its audit scenario; MySQL reaches the audit-injection trigger but binary logging requires test-server configuration (error1419). Failed reports remain retained. These are fixture constraints, not relaxed production requirements.

This test-only correction explicitly fills/tests the 20/hour refusal and verifies no intent/network use, then ages only isolated fixture timestamps for the next scenario while retaining rows. The disposable MySQL CI service permits synthetic fault-injection triggers; application-user privilege and production code are unchanged. Local38-case upgrade/render evidence remains scoped to the same shipping code. Current native matrices and full acceptance still require verification. Preserve the original application run38686331742; use a focused manual run for this iteration instead of restarting its long package/recovery work. The commit deliberately skips automatic CI and dispatches the native matrix explicitly, not a release check bypass.

Next: inspect all focused results/logs, correct remaining failures, reconcile resource/hosting descendants, and run complete stable-candidate acceptance/review before release. Codex P0 ownership and v1.23 baseline remain unchanged.

Codex owns the complete 20i integration as P0 under the owner's 2026-10-15 override. Unrelated development is paused. Earlier Devin-only allocation is superseded; all source branches and evidence remain preserved. Current integration: `codex/twentyi-complete`, from main `68e4d411acedd9f73cf048018ba570ace9b673be`. Published v1.23.0 (`4fdb56266c77f6e6bf7b58e966d24696e1eaefc8`) remains unchanged. No release or live deployment is claimed.

The first implementation checkpoint fixes v1/v2 readiness, unexpected generated-column acceptance, moved-domain approval presentation, sibling hosting-name disclosure and the two brittle replay/revocation counters. It restores populated upgrade coverage and adds interrupted preparation, preserved fingerprints/history, explicit approval and client-isolation tests. Existing v3 databases missing established tables require recovery instead of silent reconstruction.

Local PHP8.4/MariaDB10.11 passes **38 named native upgrade/render checks** in `tests/provider_inventory_upgrade_native.php`. This uses actual database migrations and PHP owner views with synthetic data, no stored API key or network. The owned database stopped; fixture rows were retained. Current HTTP/Chromium, PHP8.2/8.4 × MariaDB10.11/MySQL8.4 matrices, full packaged/Apache recovery and independent review are pending. Syntax and whitespace pass; no parent acceptance is claimed for this descendant.

Next: push the takeover PR and inspect focused/native evidence; fix failures; integrate the remaining justified resource/hosting descendants with preserved history; run stable combined package/browser/Apache acceptance and independent review before exact-main release verification. Continue the complete scope after the first release. Required provider controls, domain commerce, VPS/cloud, SSH and order/payment connections remain implementation work, not owner-gated omissions.

[Takeover and ancestry](docs/TWENTYI_CODEX_TAKEOVER.md) · [Complete requirements](docs/TWENTYI_REQUIREMENTS_EVIDENCE.md) · [Historical status](docs/archive/2026-10-15-PROJECT_STATUS.md)
