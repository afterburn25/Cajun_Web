# Devin sole-owner takeover: integrate first, then complete the whole site

Owner directive recorded 2026-10-19. Repository: `afterburn25/Cajun_Web`. Authoritative integration branch: `main`.

## 1. Assignment and precedence

The owner explicitly instructs Devin to take the reins, work independently, begin by merging completed work into main, and then finish ALL remaining 20i and non-20i work. Devin is now the sole implementation lead, integration coordinator and release owner for CajunWebPro. This includes former Codex work, former Devin work, shared authentication/billing/schema/navigation/build seams, CI diagnosis, reviews, documentation, packaging and GitHub publication.

This supersedes earlier ownership restrictions in AGENTS.md, DEVIN_START_HERE.md, issues #2/#157, coordination documents and historical branch handoffs that reserve 20i, SSH, integration or release authority to Codex, or prohibit Devin from changing those areas. Scope, security, data-preservation and release-quality requirements are NOT waived. Devin does not need Codex to resume, finish a feature, perform the merge, or publish a release.

Other workers are instructed to preserve and hand off their current source and evidence, and not start overlapping implementation or integration unless the owner reassigns work. A repository instruction does not stop an already running external Codex/Devin session. Devin must check for concurrent writers before shared changes, preserve their commits and continue on a separate branch rather than racing their checkouts. No additional paid service, infrastructure or external agent is authorized by this handoff.

This assignment is for CajunWebPro only. The separately discussed TwoPage Links application and other repositories are not automatically added to this project's scope.

## 2. Verified starting reference, not a permanent pin

At handoff, main was `a86f6f4fa592e6f715a9420aec8de4622258642e`. The latest published release read from GitHub was v1.25.0, source `b79c67110829126916e3a65e616191542448bccc`. Preserve that release and its existing assets. A later documentation commit or development package carrying the same VERSION is not a replacement published release.

PR216 remains an open direct-hosting-catalog candidate at `f67b1631683b29096dd8ec1daee405e104dc0b83`; PR220 is its capture-correction successor. Many other capabilities remain on stacked feature branches. These are starting pointers only: fetch current refs, PR heads, ancestry, discussions, reviews, run attempts, release assets and unresolved findings before acting. Do not trust an old issue body, a PR title, a green parent run or an approximate chat percentage as current completion evidence.

Read these sources before implementation:

- [Working agreement](../AGENTS.md), [start here](../DEVIN_START_HERE.md), [status](../PROJECT_STATUS.md), [handoff](../SESSION_HANDOFF.md), [coordination](AGENT_COORDINATION.md).
- [Whole roadmap](ROADMAP.md), [project direction](PROJECT_DIRECTION.md), [hosting and billing](HOSTING_AND_BILLING_ROADMAP.md), [client portal](CLIENT_PORTAL_ROADMAP.md), [approved portal design](APPROVED_CLIENT_PORTAL_DESIGN.md), [admin mail](ADMIN_MAIL_ROADMAP.md).
- [20i requirements/evidence](TWENTYI_REQUIREMENTS_EVIDENCE.md), [20i checkpoint](TWENTYI_CURRENT_CHECKPOINT.md), [preserved owner API reference](TWENTYI_OWNER_API_REFERENCE.md), [non-20i requirements](DEVIN_NON20I_SITE_COMPLETION.md).
- [Release workflow](RELEASE_WORKFLOW.md), [published 1.25 evidence](RELEASE_1.25.0_PUBLICATION.md), [latest continuation record](ROADMAP_CONTINUATION_2026_10_19.md), [queue-cleanup evidence](CI_SUPERSEDED_QUEUE_CLEANUP.md), and the exact feature guides referenced by each current PR.

Historical ownership prose in those documents is overridden by section 1. Preserve their functional requirements and evidence. Missing files or inaccessible evidence must be reported precisely; do not invent their contents.

## 3. P0: reconcile and merge completed work before expanding features

Start by taking stock of BOTH workers' entire pending implementation, not only the newest PR. Fetch all relevant refs with pagination where needed. Preserve local uncommitted work and recorded remote heads. Create or safely reuse a Devin-owned integration branch from current main, such as `devin/full-site-integration`; inspect an existing branch before using it. Keep one integration writer.

Create `docs/DEVIN_INTEGRATION_QUEUE.md` with one record per actual candidate: PR, exact head/base/tree, dependencies, capabilities, shared files, review findings, executed tests, missing tests, migration implications, intended merge order and disposition. Dispositions must distinguish READY FOR REVIEW/MERGE, NEEDS CORRECTION OR ACCEPTANCE, INCOMPLETE/FOUNDATION, and ALREADY INTEGRATED/SUPERSEDED. A draft, mergeable flag or passed syntax check is not merge clearance.

Use Git ancestry plus inspected diffs to avoid counting or integrating the same work twice. Some open predecessor PRs may already be included through normal merges; some newer PRs depend on several unmerged parents. Never merge everything in numerical order or choose ours/theirs indiscriminately.

First review and integrate the completed, tested, useful slices. Fix the small release-blocking defects that prevent otherwise mature work from landing. Do not delay a ready independent feature until every unrelated PR is complete. Incomplete foundations remain preserved and explicitly tracked, not exposed as finished controls. If all ready work is waiting on genuine hosted checks, continue an independent remaining task without claiming a merge or bypassing the check.

For each accepted slice: merge on the integration branch in dependency order, resolve shared-file changes intentionally, regenerate canonical mirrors and installer inventory, run the applicable combined acceptance, obtain required review and then normal-merge through the repository's supported PR process into current main. Recheck the destination head immediately before merging. No force push, hard reset of others' work, blanket overwrite, detached old application deployment or suppression of unresolved release-critical findings.

Completed existing work must not sit indefinitely behind a statement that only Codex may integrate it. Devin now owns that responsibility. Close or mark superseded proposals only after proving their accepted implementation is preserved and linking the replacement/merge. Do not delete historical branches or failed artifacts just to tidy the list.

## 4. Starting integration inventory and concrete caveats

These groups identify known existing work to inspect, not an exhaustive list or an instruction to merge unverified heads:

| Existing family | Initial PR pointers | Important boundary |
|---|---|---|
| Direct catalog and captures | #216, #220 | Original catalog results do not certify later capture changes or combined main. Refresh actual hosted outcomes rather than repeating stale queued text. |
| Hosting offers, storefront, setup, purchase inbox and billing options | #219, #223, #228, #229, #231, #235, #245, #247, #248 | Preserve dependency merges, original commercial terms and exact first-invoice identity. An unpaid invoice is not a charge or fulfilled service. |
| Registrar nameservers | #221, #222, #230 | Retain owner/client approval, once-only dispatch, uncertainty and separately observed results. Nameserver replacement is not a complete DNS editor. |
| Runtime, folders, HTTPS and directory settings | #224, #225, #244, #246, #249 | PHP available-options mapping has a documented evidence gap. Do not enable unsupported production setters merely because synthetic options pass. |
| Subdomains and cloud website requests | #226, #227, #234, #236, #237, #242 | A hostname observation is not DNS/TLS/site health; adding a website on an existing server is not buying/provisioning a VPS. |
| StackCP owner/client/support launch | #233, #243, #255 | Preserve actual whole-user scope and local-vs-remote revocation limits. A browser handoff is not SSH; modeled launches are not live provider proof. |
| Renewal invoicing and reset templates | #252, #254 | Invoice creation does not pay it; template read/write does not send mail or reset a password. |
| VPS uncertainty component | #45, #256 | PR256 is a pure component on a historical foundation tree, outside shipping. Carry only the reviewed component/history through a main-preserving integration; never deploy the old full application tree. Storage, adapters, billing, screens and SSH still need implementation. |
| Client contacts, milestones and account work | #203, #204, #205, #209, #210, #211, #212, #213, #250 | PR250 corrects duplicate contacts-table setup in the earliest affected fixture. Integrate the accepted correction through the dependency stack; the old failed run does not prove skipped tests. |
| Payment links | #202, #253 | PR253 addresses stale/private invoice details on unusable links. A shareable link is not completed card checkout. |
| Mail, backups, notifications, projects and public-site work | #158, #160-#198, #199-#201, and their actual predecessors/successors | Inspect every real PR and guide; the numeric range includes dependencies and non-feature work. Preserve permissions, schema versions, exact-message identities and existing provider hooks. |

Inspect current review threads before repeating old findings: a branch may have changed since a review. Do not fix an already-repaired defect again or import older whole-file versions to recover one desired function.

## 5. CI and integration discipline

Resolve CI as part of delivery, not as an endless separate project. Reuse valid exact-source evidence and existing original runs. Inspect the current attempt, actual checkout/tree, jobs, mandatory reports and artifacts; the first page of a helper or only pull-request-triggered runs may not cover manual runs. A queued, skipped, cancelled or missing test has not passed. Source-equivalent evidence requires the repository's actual proof, not an assumption.

The documented cleanup completed 466 reviewed cancellations and retained 31 candidates from its 497-candidate inventory. Do not restart that old cancellation plan. Preserve original failed evidence, current required application/release runs and useful completed artifacts. Do not redispatch because a run is merely queued. Any further cancellation must follow a fresh, narrow, documented superseded-run review; do not cancel working validation to manufacture green status.

Retain dependency-aware path filters. Do not trigger dozens of unnecessary full pipelines per tiny edit, broaden unrelated work indefinitely, disable test families, lower assertions, delete reports or declare a CI-infrastructure problem without evidence. If API access is rate-limited, use Git/local source and retained evidence while backing off API calls; report the exact external limitation and continue unblocked work.

Required validation includes the maintained PHP/database matrix, native transactional/authorization/concurrency/migration tests, synthetic provider success/failure/unknown outcomes, real installed HTTP/Chromium flows, actual desktop/mobile screenshot review, Apache root/subfolder and rewrite variants, interrupted/repeated update/recovery and packaged data preservation. Follow the current release contract rather than hardcoding obsolete report counts.

Keep implementation review and release review as separate deliberate passes. Retain independent review requirements using an actually available separate review path; a same-agent reread must be labeled self-review, not fabricated independent approval. No dependency specifically on Codex remains. If a repository rule truly requires an unavailable external approver, report that narrow requirement without bypassing it and continue independent work.

## 6. Deliver the first consolidated incremental release

After the first useful group of ready work is integrated, complete exact-main acceptance and publish the next appropriate version. Inspect current versions/tags first: 1.26.0 is a candidate name, not permission to overwrite an existing tag or automatically include every branch in that version. Do not wait for the entire roadmap before providing a tested incremental release.

Preserve the current public site, admin, clients, projects, files, invoices, payment state, licenses, mailbox data, provider assignments, operation journals, private storage, encryption keys and installed configuration. Test with the ACTUAL latest published installation archive populated with representative records; a fabricated empty baseline is insufficient. Retain the existing supported older upgrade baselines.

Before publication, test the exact source and delivered artifacts, normal-merge the accepted candidate, rerun the required exact-main acceptance, inspect actual main captures, and verify version/runtime/installer/update metadata agree. Use `python tools/build.py --refresh` when shipping files change and run syntax checks on every touched PHP file.

Publish the repository's FULL-PROJECT, COMPLETE-UPLOAD and UPDATE ZIPs, manifests, directory/checksum lists, test report, migration instructions and required evidence assets. Preserve all old releases. Verify draft assets where required, publish, then download every public release asset again and verify bytes, hashes, ZIP CRCs/members, manifests and source/version identity. A local archive or an Actions artifact is not a published release.

Give the owner direct release/download links, the full installable ZIP and UPDATE ZIP, exact source/version, changes, known limitations, migration/rollback guidance and actual build screenshots. Do not hand over an old archive renamed as the new version. GitHub publication does not deploy the live site.

## 7. Then complete ALL remaining 20i work

Work from the full existing roadmap and capability register; the list below is a coverage floor, not a reduced substitute:

- Catalog and commercial flow: true supported hosting/domain/VPS products, exact provider type/ID mapping, capability/entitlement and capacity reporting, owner retail terms separate from provider cost, monthly/annual options, publication, cart/checkout where required, exact invoicing, verified-payment eligibility, fulfillment, reconciliation and usable client/admin history. Preserve local custom artwork; automatic provider artwork/prices require actual supported contracts and transferable rights, never fabrication.
- Hosting lifecycle: provisioning, verified IDs and state, plan amendments with reviewed quote/consent and future billing cutover, upgrade/downgrade, suspend/resume, renewal/cancel/deprovision, retention safeguards and uncertain/interrupted outcomes without blind replay.
- Native hosting controls: full supported DNS records, nameservers, subdomain lifecycle, folders/document roots, databases/users/credentials, FTP/SFTP/SSH keys where supported, PHP versions/settings, SSL/certificate information and controls, mailboxes/passwords/forwarding/autoresponders/quotas/spam settings, WordPress/app/staging actions, CDN/cache, usage/logs, backups/download/restore. Owner, staff and client authority must be explicit for every operation.
- Domain commerce and management: availability, real prices/terms, registration/transfer/renewal, contacts/registrant ownership, premium/unsupported restrictions, expiry/auto-renew, transfer lock/auth-code/privacy where supported, balances and registry recovery. Draft contacts or a renewal request alone are not a purchase engine.
- VPS/managed cloud: entitled catalog/specs/OS/region/prices, ordering, provisioning/readiness, IPs/credentials, billing/renew/upgrade/cancel, usage, power/reboot/rebuild/console, snapshots/backups/restore and teardown as actually supported. Complete the application, persistence, screens and tests, not only a state machine.
- StackCP and support: finish owner/client/staff handoff scope and real acceptance where authorized, expiry/revocation and audit. Build the separately requested real interactive VPS SSH path with verified host identity, short-lived scoped admission, protected credentials, disconnect and revocation. A simulated terminal or VNC is not SSH. Do not add a hidden daemon/root requirement to shared-host installation; any necessary external gateway requires explicit provisioning/authorization.
- Automation and reliability: shared-host-compatible cron/jobs, durable leases/backoff, safe retries, consent-bound renewal/invoice automation, reconciliation, stale/error/health states, actual monitoring and operational alerts. Defaults must never cause an unapproved real charge, provisioning action or message.

Missing API contracts are engineering/evidence tasks, not a reason to abandon the remainder or invent a response. Use the preserved owner reference and current official documentation. An authenticated GET can itself issue credentials or have side effects; retain operation-specific classification and authorization. A cloud `null` response does not prove a complete or empty catalog.

## 8. Complete ALL remaining non-20i work

- Public marketing, portfolio/case studies, lead pipeline, consultations, factual comparisons with sources/dates, pricing/service content and approved visual/accessibility/performance polish.
- Client/admin/staff identity, contacts and permission scopes, account/password/session management, invitations/recovery, the roadmap's security/MFA requirements, support tickets/private notes and traceable support sessions.
- Full design-only and combined design/hosting journeys: proposal versions/approval/revisions, project stages/milestones/dates, protected previews, content uploads, blockers/action items, exact contact attribution, financial milestones and activity history.
- Authorize.net settings/tokenized customer payments, design deposits/installments/final balances, payment links, invoices/receipts, credits/refunds/tax/coupons and customer payment profiles where required, recurring maintenance/subscriptions, cancellation/dunning, exports and reconciliation. Preserve sandbox/live separation; invoice creation, manual receipts and synthetic payments do not establish production checkout.
- Admin multi-account webmail: secure account setup and grants, inbox/folder/message reads, compose/reply/reply-all/forward, drafts, signatures, attachments, sanitized formatted bodies, Sent handling, read/unread state, templates and exact-message bells in admin and admin-only public headers. No private mailbox data in anonymous cached pages; distinguish SMTP acceptance from recipient delivery.
- Notifications/preferences: correct project/support/hosting/billing/delivery event recipients, exact authorized links, durable outbox/deduplication/retries and scheduled execution where required. Page-visit-driven retries alone do not establish unattended automation.
- Software-product licensing and delivery: retain existing generation/activation/domain history, secure bundles and legacy migration. CajunWebPro's own installer stays keyless. Reconcile old licensing review findings against the released fixes instead of rebuilding the feature.
- Versioned client website delivery: usable authorized code/assets/content/database handover, private immutable versions, large/resumable downloads, retention/expiry, secret and cross-client exclusion, and a real isolated dynamic-site restore. Platform backup exports are not automatically client-specific handover packages.
- Backup/restore and operations: genuinely usable file/database recovery with original keys where required, retention, tested drills, updater interruption/reapplication/rollback, audit/reporting and measured health. Merely embedding SQL in a ZIP does not prove automatic database restoration.

Preserve the original navy/gold branding, approved portal design and current useful layouts. Reconcile approved design requirements against actual shipped screens; do not claim an approved mockup has been implemented without checking. Keep the organized admin/app/includes/images/assets/database/storage/upload/docs/tests structure according to the existing contract, with deployable contents directly at the installation root and no root-dumped application regression. Retain the one-page, one-submit, keyless installer, inline errors, minimum administrator password policy, preserved configuration and explicit isolated fresh-start behavior. Never use chmod 0777 or delete production records/locks to force installation.

## 9. Authority and narrow external gates

The owner authorizes this development handoff, reviewed GitHub integration and verified incremental publication. This does NOT authorize live deployment, real customer charges/refunds/mail, new paid provider resources, destructive changes, real support/SSH sessions, purchasing services, or moving existing tags. Follow existing explicit approvals for each real operation; secrets belong in protected configuration, never commits/issues/screenshots.

Complete code, local/synthetic tests and isolated permitted integration work without demanding new credentials in chat. When a required live check truly lacks an account, entitlement, gateway, approval or reviewer, record the exact missing input, the finished implementation, the unproven behavior and the next safe test. Keep that item incomplete in the appropriate stage and continue all independent work. Never use a broad 'blocked' badge to hide missing engineering.

## 10. Fixed scope and honest progress reporting

Create `docs/COMPLETION_TRACKER.md` with a stable requirement inventory covering BOTH mandates and the existing F/P/A/C/HC items and 20i capability rows. Deduplicate shared features and ancestor PRs. For each item separately record implemented code, focused tests, installed/combined acceptance, integrated main SHA, published version and any required live verification. Missing evidence is UNKNOWN/PENDING, not success or automatic proof that code is absent.

Previous chat estimates such as 87% were informal estimates, not audited repository counters. Do not inherit them as verified facts, add the two workers' percentages, count commits/tests/PRs as feature completion, or increase a percentage solely to prevent it moving backward. Publish any weights/denominator before using percentages, keep them stable, and explain scope changes or factual corrections explicitly. Do not quietly switch between implemented, released and live-ready definitions.

At every meaningful checkpoint update current status, handoff, integration queue, tracker, changelog, roadmap, decisions, session log and release register as applicable. Report exact source/PRs, what actually merged, tests passed/failed/queued, limitations, newly published ZIPs and the next action. Consolidate obsolete ownership notices with links to this directive, while preserving historical evidence and prior failed outcomes.

## 11. Completion and continuation

Do not stop after acknowledging, planning, making a PR, merging one component, or publishing one increment. First deliver the ready integration milestone; then continue both roadmaps in usable tested increments through the complete remaining scope. Do not wait for another 'continue' for work already authorized. If a session limit intervenes, commit a precise handoff identifying the next executable action; never claim work continues after the session unless an actual supported runner is active.

Software-roadmap completion requires the recorded acceptance criteria, integrated current main, verified install/update/recovery packages, review and publication. Required real-provider/merchant/target-host checks remain explicitly separate until performed. Do not declare 100% commercial readiness while those required checks or major capabilities are absent.

## 12. Handoff record

Request: transfer sole ownership to Devin, merge completed work first, then finish all 20i and non-20i development. Starting main: `a86f6f4fa592e6f715a9420aec8de4622258642e`. This handoff edits documentation and issue ownership/instructions only; it does not merge application candidates, rerun application tests, deploy, stop external sessions, or start a Devin session. The enclosing Git commit identifies the published instruction revision.

The prior complete [status](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/PROJECT_STATUS.md), [session handoff](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/SESSION_HANDOFF.md), [working agreement](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/AGENTS.md) and [coordination history](https://github.com/afterburn25/Cajun_Web/blob/a86f6f4fa592e6f715a9420aec8de4622258642e/docs/AGENT_COORDINATION.md) remain available at the exact pre-handoff source. All feature guides, roadmap bodies, archived reports and release assets remain preserved.
