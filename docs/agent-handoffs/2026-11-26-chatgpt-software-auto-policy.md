# Software product lifecycle — ChatGPT — 2026-11-26

- Repository: `afterburn25/Cajun_Web`
- Branch: `chatgpt/software-auto-policy-20261126`
- Starting exact main: `7d9c8aa7d934117a494b19a32c58c071c190d56f` (published v1.27.0).
- Owner scope: begin with Adult_Core four SKU variants (#399), but keep the issuer generic: any new software product can be added and licensed through admin; future product retirement/end-of-support/archive must preserve customer rights and historical records (#403).
- Coordination: Codex is designated shared integration lead (#157, #340). Another workstation is preparing project progress; **this draft branch is isolated and may not be merged without Codex review, refreshed installer/source artifacts, and required acceptance**.

## Bounded implemented first increment

1. `site/app/billing/catalog.php`: manual advanced catalog **new software** saves now require prepared software commerce and atomically add the default canonical software product policy in the same staff mutex/transaction. Failed policy creation rolls back catalog/revision/audit. Existing product edits do not overwrite existing policy. ZIP onboarding calls `catalog_save_in_transaction` directly and retains its detected module key and its own single policy initialization.
2. `site/admin/controllers/catalog.php`: explain private drafts, public sales and **Retired** status; retirement is not paid license revocation.
3. `tests/software_onboarding_native.php`: adds SQL-backed checks for refusal before licensing setup, default policy, no issued keys, and retirement preserving product/policy identity. These new checks were **written but NOT executed** in this session.
4. `docs/SOFTWARE_ONBOARDING.md`: explicit lifecycle, authority and Adult_Core handoff.

## Exact verification status and next action

- Current branch commits are published via authenticated GitHub Contents API; exact source blob IDs recorded in GitHub history.
- The environment cannot access the full GitHub checkout through `git clone` (GitHub DNS resolution unavailable). No full-site PHP lint, canonical build refresh, generated shipping mirror/inventory, MySQL/MariaDB, installed HTTP/Chromium, package or upgrade acceptance was executed in this session. **CI status is not yet a pass.**
- Before any merge: Codex must fetch the branch in its normal checkout, run `php -l` on changed PHP, `python tools/build.py --refresh` and commit generated mirrors/inventory; run the existing four PHP/database combinations, software onboarding/current-source/browser/upgrade suites, review permissions/CSRF/stale revisions and conflicts, then integrate normally and verify exact resulting main.
- Specifically check the existing guided ZIP onboarding test still preserves embedded `module_key`, and that normal catalog (non-software) product saves are unchanged. The new advanced-editor test requires a disposable database, not real production data.
- Implementation **does not** configure actual Adult_Core SKUs, merchant settings, staging keys, or paid entitlements. Four SKUs are known only in #399, not in shipping code. The 1.27.0 release and all production operations remain untouched.
- Subsequent issue #403 slices: owner UI for variant/term management linked to one audited release asset, explicit new-sales retirement, safe deletion only for unreferenced drafts, separately reviewed end-of-support/update cutoff and no silent paid-user revocation, plus controlled Adult_Core staging real HTTPS/merchant sandbox acceptance.

## Publication state

Pushed to separate GitHub branch; draft PR pending creation. NOT merged, released or deployed. This note is evidence of scope and remaining work, not approval or successful test results.
