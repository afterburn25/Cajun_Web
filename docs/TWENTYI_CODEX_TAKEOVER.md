# Codex takeover: complete 20i integration

Codex owns the complete 20i integration as P0 under the owner's 2026-10-15 override. Unrelated development is paused. Earlier Devin-only allocation is superseded; all source branches and evidence remain preserved. Current integration: `codex/twentyi-complete`, from main `68e4d411acedd9f73cf048018ba570ace9b673be`. Published v1.23.0 (`4fdb56266c77f6e6bf7b58e966d24696e1eaefc8`) remains unchanged. No release or live deployment is claimed.

## Required outcome

Complete the first-party PHP/MySQL hosting platform: configuration/catalog/inventory and explicit ownership; order-derived provisioning, hosting lifecycle and native controls; domain search/commerce/management; VPS and managed cloud; scoped support and interactive SSH; the minimum existing billing/order/consent connections needed for correct fulfillment; shared-host jobs, migrations, recovery, packaged verification and incremental releases. See the [complete owner request](TWENTYI_CODEX_TAKEOVER_REQUEST.md) and [requirements-to-evidence checklist](TWENTYI_REQUIREMENTS_EVIDENCE.md). Endpoint counts and green component tests are not completed business workflows.

## Preserved starting state and ancestry

Fresh GitHub fetch inspected main/releases, #87–#89, #93/#94, #99/#100, #107/#109/#111, #115/#122, issue #2 and supporting #95–#97, comments/reviews and check results. #115 had one failing check; #122 had three failing and two cancelled checks. The current corrections had no submitted GitHub reviews. No release/tag was altered.

| Reference | Captured source SHA | Merge base with main | Relationship |
|---|---|---|---|
| `origin/main` | `68e4d411acedd9f73cf048018ba570ace9b673be` | `68e4d411acedd9f73cf048018ba570ace9b673be` | Contained in #122 |
| `origin/devin/twentyi-domain-links` | `6bad5f22ee46fe0817750c4cd33e04c88720d0fe` | `68e4d411acedd9f73cf048018ba570ace9b673be` | Contained in #122 |
| `origin/devin/twentyi-reconcile` | `8d9ea04664a5c9bd05339f544444522b6f379497` | `68e4d411acedd9f73cf048018ba570ace9b673be` | Separate delta; inspect explicitly |
| `origin/devin/twentyi-resources` | `40ad60024a8aa34cf7dd051701f42f5e00474478` | `68e4d411acedd9f73cf048018ba570ace9b673be` | Separate delta; inspect explicitly |
| `origin/devin/twentyi-inventory-vps` | `a2e6deda57d9089fc14d4447de6b4ef7b84243dd` | `68e4d411acedd9f73cf048018ba570ace9b673be` | Separate delta; inspect explicitly |
| `origin/devin/twentyi-inventory-reader` | `e53ff2d989bde43cc2884b119e16603c7e0ac03f` | `68e4d411acedd9f73cf048018ba570ace9b673be` | Contained in #122 |
| `origin/devin/twentyi-inventory` | `25f3beefb61aa23db6aee796dc6bfc85441547c4` | `68e4d411acedd9f73cf048018ba570ace9b673be` | Separate delta; inspect explicitly |
| `origin/devin/twentyi-execution` | `bdce177d038f6aaa0cbc9285f2ff9c378333c3b2` | `68e4d411acedd9f73cf048018ba570ace9b673be` | Separate delta; inspect explicitly |
| `origin/codex/hosting-reconciliation-storage` | `1c92e6bfb0f3f53ab0322c6d159ec1b5cbcf92f9` | `21ebe6928ecd4c4dcbda2c8b27333583e5b2d5fa` | Separate delta; inspect explicitly |

Normal merge `c36f11a` preserves #122's 99 commits over main, including the overlapping inventory/reader/storage/operation histories. Normal merge `974411075ecb1cf13dce92e17d647dd06e9692ae` preserves #115's remaining clarification/readiness commits. Its sole generated-inventory conflict was resolved by regeneration from canonical files. This exact head was the pre-fix reproduction source. Earlier PRs are not blindly merged or cherry-picked again. Resource branch 40ad600, newer inventory clarification, execution patch equivalents and hosting #89 remain explicitly to reconcile before the stable integration candidate; no inherited full-scope approval is inferred.

Unrelated mail PR125 is preserved at `426ba6cbe3af6d03d5badd0cf302339549e0f4eb` and excluded from this branch. Native draft storage there remains unverified; the Windows test stopped at the existing POSIX permission guard. The completed installer refresh and prior acceptance evidence were retained, with no running job cancelled. Other pending non-20i branches remain untouched.

## First repaired findings and evidence

Refreshed #122 comments [6183981253](https://github.com/afterburn25/Cajun_Web/pull/122#issuecomment-6183981253), [6184136824](https://github.com/afterburn25/Cajun_Web/pull/122#issuecomment-6184136824), [6184400401](https://github.com/afterburn25/Cajun_Web/pull/122#issuecomment-6184400401) and #115 [6183188835](https://github.com/afterburn25/Cajun_Web/pull/115#issuecomment-6183188835). Reproduced on exact9744110: v2 readiness returned true with missing domain tables and native PDO42S02; a VIRTUAL GENERATED package_type_name passed validation but ordinary insertion failed1906; the actual PHP domain view claimed package301 linked despite approval on201. Prior failed native/HTTP counters remain identified fixture defects, not proof of duplicate sends or broken revocation.

The first implementation checkpoint fixes v1/v2 readiness, unexpected generated-column acceptance, moved-domain approval presentation, sibling hosting-name disclosure and the two brittle replay/revocation counters. It restores populated upgrade coverage and adds interrupted preparation, preserved fingerprints/history, explicit approval and client-isolation tests. Existing v3 databases missing established tables require recovery instead of silent reconstruction.

Local PHP8.4/MariaDB10.11 passes **38 named native upgrade/render checks** in `tests/provider_inventory_upgrade_native.php`. This uses actual database migrations and PHP owner views with synthetic data, no stored API key or network. The owned database stopped; fixture rows were retained. Current HTTP/Chromium, PHP8.2/8.4 × MariaDB10.11/MySQL8.4 matrices, full packaged/Apache recovery and independent review are pending. Syntax and whitespace pass; no parent acceptance is claimed for this descendant.

Next: push the takeover PR and inspect focused/native evidence; fix failures; integrate the remaining justified resource/hosting descendants with preserved history; run stable combined package/browser/Apache acceptance and independent review before exact-main release verification. Continue the complete scope after the first release. Required provider controls, domain commerce, VPS/cloud, SSH and order/payment connections remain implementation work, not owner-gated omissions.

## Source, account and external acceptance

The owner's full uploaded API reference is broader than the [selected summary](TWENTYI_OWNER_API_REFERENCE.md). Its retained identity is195,457bytes, SHA256 `f7c738e746c2aebb9e1d047a555f3874a0766255795ed80edeebe8e599b9ec4d`; locating the original upload remains an active intake task. Do not claim it was reread or that missing summary fields are absent from provider documentation. [Official20i guide](https://docs.20i.com/api/20i-api-documentation) was reread2026-10-15; it directs account-specific documentation access through My20i. No uncertain example was executed.

GitHub secret metadata confirms `TWENTYI_API_KEY` exists, last updated2026-10-13T19:59:22Z. Its value was not read/displayed. Prior authorized GET/package diagnostic is evidence only for that named read; no new live diagnostic ran in this checkpoint. Actions secrets are not automatically application configuration. Other real reads/writes, payments, access/SSH and deployment remain separate operation-specific acceptance. Missing configuration does not excuse unfinished engineering.

Release gates remain exact candidate/main, independent review, actual extracted FULL/COMPLETE/UPDATE HTTP/Chromium, supported native databases/runtimes, retained recovery, inspected screenshots and downloaded release asset verification. Do not move published tags or replace old assets.

## Focused CI fixture correction — 2026-10-15

Takeover [PR #126](https://github.com/afterburn25/Cajun_Web/pull/126) is open at first implementation `d2f59fba32b98153119b33072647cccdbb25696d`. Downloaded push run [38686268179](https://github.com/afterburn25/Cajun_Web/actions/runs/38686268179) fails: MariaDB passes 74 assertions (including the repaired revocation check) then the longer fixture reaches the real hourly quota before its audit scenario; MySQL reaches the audit-injection trigger but binary logging requires test-server configuration (error1419). Failed reports remain retained. These are fixture constraints, not relaxed production requirements.

This test-only correction explicitly fills/tests the 20/hour refusal and verifies no intent/network use, then ages only isolated fixture timestamps for the next scenario while retaining rows. The disposable MySQL CI service permits synthetic fault-injection triggers; application-user privilege and production code are unchanged. Local38-case upgrade/render evidence remains scoped to the same shipping code. Current native matrices and full acceptance still require verification. Preserve the original application run38686331742; use a focused manual run for this iteration instead of restarting its long package/recovery work. The commit deliberately skips automatic CI and dispatches the native matrix explicitly, not a release check bypass.

Next: inspect all focused results/logs, correct remaining failures, reconcile resource/hosting descendants, and run complete stable-candidate acceptance/review before release. Codex P0 ownership and v1.23 baseline remain unchanged.

## Combined inherited work and write-semantics correction — 2026-10-15

Codex retains complete 20i P0 ownership in [PR126](https://github.com/afterburn25/Cajun_Web/pull/126). Main68e4d41 and publishedv1.23.0 remain unchanged; no release or deployment is claimed.

Downloaded/verified [native run38686612548](https://github.com/afterburn25/Cajun_Web/actions/runs/38686612548), exact962cc3e: all four PHP8.2/8.4 × MariaDB10.11/MySQL8.4 jobs pass, with98 inventory plus38 upgrade/render checks each (544 named executions). The upgrade helper used the same assertion label for its two version-specific audit failures; both ran, and this checkpoint labels them separately. This evidence predates the combined shipping changes below; it does not certify this new descendant. Original d2f59fb full application run38686331742 hit the retained quota fixture failure; its recovery/Apache work was left running for evidence, not cancelled.

Normal merge5b115f0 retains resources40ad600 (including #94's identity descriptors), with139 local pure inventory checks passing. Normal merge08b90c8 retains hosting#87–#89 at1c92e6b, adding explicit deletion retry and durable reconciliation storage. Current ownership/status text won documentation conflicts; the incoming history and dedicated architecture/recovery guides remain intact. Regenerated inventory resolves the generated-file conflicts. These inherited components still require combined native/package acceptance; they are not completed hosting workflows.

This checkpoint repairs the inherited blanket HTTP-success classifier. Documented addWeb responses retain the allocated result ID as accepted_pending, separate from verified active service. Empty, malformed, false/null, mixed per-resource maps and unverified envelopes stay uncertain.408/409/425 are ambiguous rather than definite failures. No write, including mixed billing/deletion updatePackage, is blanket retry-safe. The ledger replays pending acknowledgements with their original IDs and one attempt. Reconciliation can observe the acknowledged ID, but a domain-name match alone cannot prove creation. Old results and attempts are preserved.

updatePackage now requires ID arrays and bounded per-package maps restricted to the approved update IDs; overlapping update/delete IDs and scalar legacy approvals are refused. deleteWeb builds the explicitly documented delete-id array. Existing approved scopes are revalidated before consumption/dispatch. This corrects the documented outer request contract; nested field semantics and the remaining native forms are still tracked implementation work. No live provider write ran. Local default/no-ini PHP passes139 named synthetic operation checks each. Native four-combination operation/hosting matrices and exact combined browser/recovery acceptance are next.

The complete owner upload is still being located; the selected repository summary is not a substitute. The owner was asked for its accessible path/link while independent work continues. Required native controls, registrar commerce, VPS/cloud, SSH, catalog/order/payment connections and release verification remain unfinished in the requirements checklist.

Next: inspect native matrix artifacts; fix any failures; run the stable combined packaged/browser/Apache candidate and obtain independent review. Then publish a verified incremental release and continue the remaining20i scope. Do not resume mail or other unrelated feature work.
