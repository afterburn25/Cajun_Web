# Working agreement for CajunWebPro

## Current owner directive - 2026-10-19

**Devin is the sole end-to-end implementation, integration and release owner for `afterburn25/Cajun_Web`. First review and merge completed work from BOTH workers into current main; then continue ALL remaining 20i and non-20i work through tested incremental releases.** Read [DEVIN_START_HERE.md](DEVIN_START_HERE.md) and [the full takeover directive](docs/DEVIN_FULL_SITE_TAKEOVER.md).

This supersedes historical instructions reserving 20i/provider/SSH/shared seams/main merges/releases to Codex or restricting Devin to non-20i. Devin must not wait for Codex to execute the next task. Other workers preserve their source/evidence and do not start overlapping implementation without a later owner assignment. Publishing this directive does not stop or start external sessions. Preserve independent review and repository protection requirements; ownership transfer is not permission to bypass checks.

The authoritative application integration branch is main. At handoff its source was `a86f6f4fa592e6f715a9420aec8de4622258642e`, and the latest published release was v1.25.0 at `b79c67110829126916e3a65e616191542448bccc`. Refresh those facts at session start. Preserve previous tags/assets. Development code and published downloads are different states.

## Standing owner directive - merge-first (2026-10-20, reaffirmed)

**Merging completed, tested work into `main` is a standing top priority; keep `main` caught up with completed development.** This applies to all existing Codex/Devin work and every future feature. Do not let finished work accumulate on feature or integration branches.

- Clear the integration backlog first: reconcile every candidate against current `main` (integrated / ready / blocked-with-specific-blocker / incomplete) in `docs/DEVIN_INTEGRATION_QUEUE.md`. Preserve and reuse existing work; never rebuild because a PR is closed or a branch name is confusing.
- Normal cycle: implement a coherent feature, test it, complete required review, merge to `main`, verify `main`, then start the next feature. A completed branch is still pending delivery until its accepted implementation reaches `main`. Prefer small independent increments over one large integration batch; keep each candidate stable while its acceptance runs.
- Before opening another implementation branch, land ready work first and fix specific mature blockers. A blocked candidate needs a recorded exact blocker and next action; keep landing independent ready work meanwhile.
- Validate the merge result against current `main`, including combined changes and conflict resolutions — a green run on an older branch state does not validate the current merge result. Keep all required tests, reviews and protections intact; never weaken assertions, skip required checks or bypass review for a merge.
- Recheck the `main` head immediately before merging; if the destination advanced, reconcile and complete required validation for the resulting combination. After merging, verify the resulting `main` commit and required checks; repair regressions before unrelated feature work. Start subsequent work from the latest verified `main`.
- Continue the incremental release process after accepted groups land; merging, publishing a release and live deployment remain distinct actions.
- Status reports must distinguish: implemented-on-branch, awaiting-test-or-review, ready-to-merge, merged-into-main, verified-on-main, included-in-a-published-release. For landed work cite PR, resulting `main` commit and verification; for blocked work cite the exact blocker and next action.

This directive persists across sessions and qualifies under the existing owner authorization without waiting for another continue instruction. It does not relax any required check, review, protection, or operation-specific approval.

## Begin every session

Read the current status, handoff, project direction, complete roadmap, relevant feature guides, coordination and release workflow. Inspect current main, PR ancestry, exact heads, review discussions and actual test results before choosing work. Preserve local uncommitted edits and other workers' frozen heads. Create a Devin-owned integration/feature branch; do not force-push or reset another checkout.

The P0 task is the integration inventory and first ready merge group, not another broad speculative foundation. Use the full directive's READY / NEEDS CORRECTION OR ACCEPTANCE / INCOMPLETE / ALREADY INTEGRATED distinctions. Do not treat a draft, branch count, old passing parent or mergeable flag as current acceptance. Normal-merge accepted histories sequentially, resolve shared auth/schema/billing/navigation/installer behavior intentionally, regenerate mirrors and test the combined result. Do not leave accepted work unmerged solely because an old document assigns integration to Codex.

## Preserve the product and data

- CajunWebPro is the existing web-design/hosting business platform, not a replacement template, DIY builder, WHMCS/HostShop wrapper or another project. The separate TwoPage Links app is not implicitly in scope.
- Keep PHP/MySQL shared-host deployment without a required Node service, SSH/root/Composer installation command or hidden daemon. A necessary external SSH gateway is separately provisioned and authorized.
- Preserve the approved navy/gold visual direction, original horizontal logo without .com, supplied favicon, approved portal design, real content and accessibility/reduced-motion behavior. Do not invent prices, reviews, metrics, uptime or comparison claims.
- Retain the organized deployment directory contract and directly extractable install/update packages. Do not dump application modules in the root or regress to a wrapper-directory package.
- Keep the one-page, one-submit, keyless installer with all required fields visible and inline errors preserving nonsecret input. Retain administrator-password requirements and explicit isolated fresh start. Never delete configuration, locks, private storage, media, customer data or encryption keys to make setup run. No chmod 0777 or false claim that PHP can grant itself ownership.
- Preserve existing licensing, client identity/permissions, project history, invoices/payments, mailbox data, provider assignments, operation ledgers and recovery evidence. Code from an old component branch must not overwrite the modern application tree.

## Verification and release discipline

Follow [RELEASE_WORKFLOW.md](docs/RELEASE_WORKFLOW.md). Development, local tests, native SQL tests, installed HTTP/browser acceptance, integrated main, published release and authorized live verification must be reported separately. Preserve original failures. A cancelled, queued, skipped or missing report is not success; no fabricated assertion counts, screenshot inspection or independent approval.

Run `php -l` on each touched PHP file. On shipping changes run `python tools/build.py --refresh`, commit canonical mirrors/generated installer inventories and validate exact staged bytes. Retain the maintained PHP/database matrix, authorization/CSRF/tenant isolation, concurrency and uncertain-result tests, actual browser captures, Apache/root/subfolder/rewrite coverage, real populated published-version upgrades and interrupted/repeated recovery. Do not weaken assertions or hide failures to get green CI.

Use valid exact-source evidence instead of repeating finished investigations. Preserve existing required runs and do not redispatch for queue delay. Respect dependency-aware filters and current narrow queue-maintenance rules. API throttling is not permission to invent results or abandon unblocked work.

Normal-merge reviewed accepted source to current main, then complete required exact-main acceptance. Publish immutable versioned FULL-PROJECT, COMPLETE-UPLOAD and UPDATE artifacts plus current manifests/checksums/reports/evidence/migration instructions. Download draft/public assets as required and verify them against the accepted bytes. Provide actual build screenshots and the owner-ready ZIP links. A branch build is not a release, and a release is not live deployment. Do not wait for the entire roadmap before delivering safe incremental releases.

Independent review remains required where the repository requires it. Devin now coordinates it; Codex specifically is not a prerequisite. Label same-agent checks as self-review. Do not bypass required external approval; report the narrow dependency and continue independent work.

## Authority

This handoff authorizes development, reviewed GitHub integration and verified publication. It does not itself authorize real charges/refunds, customer messages, paid provider provisioning, destructive operations, live support/SSH access, external-service purchases or deployment. Preserve operation-specific consent, account entitlement and configuration gates. Secrets/cards/private diagnostics/customer records do not belong in Git, issues, screenshots or public packages.

Use retained owner API material and current official provider references. Missing contracts are explicit unfinished engineering/evidence work. Continue independent implementation and isolated tests while recording exact missing live inputs. Do not turn unsupported or simulated behavior into a working marketing claim.

## CI queue management - 2026-10-26

Every development check workflow must scope `concurrency.group` to the pull request or branch (for example `<workflow-key>-${{ github.event.pull_request.number || github.ref }}`) and set `cancel-in-progress` so a newer push or PR sync supersedes obsolete queued and running runs of that workflow for that ref only. Never include `github.sha`, `run_id` or `run_attempt` in a development workflow's group — a per-commit group makes cancellation a no-op and is what produced the ~1000-run backlog. `main` pushes are exempt from cancellation so each main commit keeps its evidence.

Never convert protected runs to newest-wins cancellation: `application-tests.yml` (sealed acceptance), `release-licensing.yml`, `release-upgrade-diagnostic.yml`, `stackcp-access-read-diagnostic.yml`, and the explicit `import-preserved-source` / `twentyi-catalog-schema` / `twentyi-cloud-root` ops workflows keep non-canceling or fixed serialization groups. Do not redispatch queued runs and do not dispatch a duplicate run for a commit whose run is pending. Poll the Actions API at no faster than ~10-minute intervals — the REST limit is shared account-wide.

Diagnose backlog with `python tools/ci_health.py`; the `CI queue health` workflow writes a weekly snapshot. See `docs/CI_HEALTH.md` for the full rules and diagnosis table.

## Document and hand off every session

Maintain PROJECT_STATUS.md, SESSION_HANDOFF.md, CHANGELOG.md, docs/ROADMAP.md, docs/DECISIONS.md, docs/SESSION_LOG.md, docs/RELEASES.md, the integration queue and stable completion tracker as applicable. Record the owner request, parent/current source, changed paths, exact tests/evidence, failures, remaining gates, real merge/release state and next action. The enclosing Git commit can identify a record that cannot know its own SHA in advance.

Use stable requirement IDs and distinct implemented/tested/integrated/released/live columns; do not inherit chat percentages as audited counters or inflate progress to maintain an upward trend. Preserve historical reports, but put the current ownership and next action first. Commit the handoff before a session limit; do not claim continued background execution without an active supported runner.

The full [previous working agreement](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/AGENTS.md) remains available as historical evidence. Its product/safety/acceptance requirements continue except where the current explicit owner directive supersedes ownership or sequencing. Earlier source-discovery and 1.25 release-freeze tasks must not be repeated as though they are still pending.
