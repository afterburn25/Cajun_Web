# Owner-approved complete 20i takeover — 2026-10-15

The following owner request supersedes earlier ownership restrictions. Implementation/evidence status lives in the separate checklist.

```text
CODEX PRIORITY TAKEOVER — COMPLETE CAJUNWEBPRO'S 20i INTEGRATION

Repository: https://github.com/afterburn25/Cajun_Web
Authoritative delivery branch: main
Suggested integration branch: codex/twentyi-complete
Priority: P0 — complete this before unrelated Cajun_Web development.

1. OWNER OVERRIDE: TAKE BACK COMPLETE OWNERSHIP

I am changing the previous allocation. Codex now owns the ENTIRE 20i
integration: existing defects, provider adapters, persistence/migrations,
admin and client screens, hosting lifecycle/controls, domains, VPS/managed
cloud, support access/SSH, required billing/order connections, tests,
documentation, integration, and releases.

This supersedes earlier project instructions saying “Devin owns the complete
20i implementation” or that Codex must only review it. Do not keep returning
implementation findings to Devin. Fix and complete the work yourself,
continuing from his existing code rather than starting again.

Pause unrelated feature development in this repository, including general
webmail improvements, cosmetic redesigns, unrelated service-desk enhancements,
and speculative refactoring. Necessary 20i dependencies, regression repairs,
security work, tests, documentation, and release work are part of this
priority. Preserve unrelated work and running acceptance evidence; do not
delete branches or indiscriminately cancel jobs.

Do not modify Website_Core, david, or other repositories. Do not migrate
CajunWebPro onto Website_Core, replace the site, or substitute
WHMCS/HostShop/StackCP for the requested first-party platform.

Your assignment is implementation, not another planning-only handoff.
A passing component test or intermediate release is not the endpoint.
Continue through the complete approved 20i scope.

2. RECORD THE TAKEOVER AND PRESERVE THE STARTING WORK

First fetch current main, releases, every relevant branch/PR, review
discussions, and CI results. The following are LAST-OBSERVED anchors,
not permission to reset newer work:

- main: 68e4d411acedd9f73cf048018ba570ace9b673be.
- Published baseline: v1.23.0;
  source 4fdb56266c77f6e6bf7b58e966d24696e1eaefc8.
- #93/#94: inventory contracts and additional inventory descriptors.
- #99/#100: authenticated package reader, durable refresh, client linking,
  and screens.
- #107/#109/#111: write-request contracts, durable ledger/approvals,
  and execution.
- #115, devin/twentyi-reconcile:
  8d9ea04664a5c9bd05339f544444522b6f379497.
- #122, devin/twentyi-domain-links:
  6bad5f22ee46fe0817750c4cd33e04c88720d0fe.
- Existing provider/hosting foundations and reviewed #87–#89 work are
  dependencies to inspect and reuse, not rebuild.

Read issue #2, supporting issues #95–#97, AGENTS.md, PROJECT_STATUS.md,
SESSION_HANDOFF.md, docs/AGENT_COORDINATION.md,
docs/DEVIN_COMPLETE_20I_HANDOFF.md, docs/HOSTING_AND_BILLING_ROADMAP.md,
docs/CLIENT_PORTAL_ROADMAP.md, docs/DEPENDENCY_GATES.md, and the 20i/API
contracts. Read current code and reviews, not just old PR titles or
status summaries.

Map the actual ancestry. Newer PRs contain earlier work; do not blindly merge
every overlapping branch or cherry-pick the same migrations twice. Create
your takeover branch from current main and integrate the necessary
descendants with preserved history. Record source SHAs, merge bases, reused
components, unresolved findings, and any deliberately excluded changes.

Update issue #2 and the current ownership sections of coordination/status/
handoff documents to record Codex ownership and P0 priority. Preserve
historical evidence but mark former allocation text superseded. Cross-link
the takeover PR and relevant existing PRs/issues. Do not close Devin's work
as completed until its retained changes are integrated and verified.

Post a coordination note asking Devin to stop new 20i modifications after
checkpointing current work. Do not claim a GitHub comment has stopped an
external agent session. Work on your own branch; watch for newer commits
and reconcile them explicitly. Do not wait indefinitely for an
acknowledgement before fixing your isolated copy.

3. FIRST DELIVERY: FIX THE EXISTING BLOCKERS

Refresh these review comments and reproduce the findings against your exact
takeover head before fixing them:

- PR #122: comments 6183981253, 6184136824, and 6184400401.
- PR #115: comment 6183188835 and subsequent review/test results.

A. Database readiness and upgrade paths

New domain screens must not query domain tables on a populated v1/v2
inventory database before the required v3 migration. Keep an accessible
owner-controlled preparation/upgrade action, or separate package/domain
readiness. Test admin and client routes before migration, after migration,
and after repeated migration. No schema-changing GET requests and no
destructive reinstall.

B. Restore removed migration regression tests

Restore populated v1 preservation and unverified legacy-link suppression
coverage. Add populated v2-to-v3 cases, interruptions, repeated preparation,
original fingerprints/revisions/history, incompatible schemas, and preserved
client isolation. A fresh database stamped v3 does not prove an upgrade.

C. Repair schema validation

Restore rejection of unexpected nonempty column Extra behavior. Limit any
auto_increment exception to the exact intended event/sequence columns.
Add the independently reproduced VIRTUAL GENERATED package_type_name case
so validation rejects it before ordinary inserts fail.

D. Correct moved-domain presentation and authorization

Compare the approved domain/package pair and approved account context
against the current observation. A domain moving from package 201 to 301
must not appear as silently approved under 301. Display original/current
bindings and a clear stale/moved state. Preserve the required explicit new
approval and hide inappropriate client data; never infer a registrar
entitlement from a hosting name alone.

E. Fix brittle counters without weakening tests

For “Owner revocation before result discards the read,” capture the
observation count immediately BEFORE that refresh. Assert access_changed
and the same count AFTER it. Do not replace 4 with another guessed constant
such as 2 or 3.

Likewise, the package-request replay test must capture the POST count
immediately before replay and assert it does not increase. Domain-renewal
tests legitimately added an earlier POST. Preserve the original operation's
attempts == 1 check and all replay/revocation/audit assertions.

F. Preserve earlier corrections

Retain credential-context-bound links, stale-observation rejection, explicit
reapproval after unverifiable context changes, rollback on audit failure,
and strict two-client isolation. A key fingerprint fences a credential
context; it does not prove the provider's account identity.

Rerun focused native tests and the complete packaged/browser/Apache suite.
Distinguish test-fixture errors from product defects. Add regression evidence
for each fix; do not remove failing requirements or declare the whole branch
reviewed from one scoped correction.

4. USE THE COMPLETE API REFERENCE, NOT STALE “MISSING FIELDS” NOTES

Inspect the owner's complete 20i reference alongside
docs/TWENTYI_OWNER_API_REFERENCE.md and current official 20i documentation.
The repository handoff is a selected summary, not necessarily the full
upload. Do not assume a summary's omission means the provider did not
document the operation.

The supplied reference explicitly includes these request details to verify
and implement:

- Package deletion: POST /reseller/{resellerId}/deleteWeb with delete-id
  as an array of package IDs.
- Package activation/deactivation: POST /package/{packageId}/userStatus
  with includeRepeated and a subservices map.
- Nameserver changes:
  POST /package/{packageId}/domain/{domainId}/nameservers with ns and old-ns
  arrays; compute actual additions/removals, not overlapping full lists.
- updatePackage: id/delete-id arrays and nested per-package billing, labels,
  limit, limit-set, and packageBundleTypes maps. Audit existing scalar
  declarations against these documented shapes.
- DNS: conflictPolicy, insertPolicy, new record structures, and delete IDs
  are shown. Implement supported record validation rather than continuing
  a blanket “shape missing” refusal.
- Domain registration/transfer: contact and otherContacts structures are
  shown. Resolve remaining registry-specific requirements individually
  instead of marking every registration/transfer undocumented.
- addVPS, addManagedVps, addCloudServer, renewal, PHP-version, SSL, database,
  and snapshot operations also have request examples. Preserve exact
  spelling/case.

These examples do not prove every response shape, entitlement, or live
operation. For each required capability record method/path, semantics,
request and response evidence, identifiers, account requirements, side
effects, implementation, tests, and live acceptance separately. Reconcile
ambiguous or contradictory examples with primary documentation; never
execute an uncertain example just to discover whether it mutates something.

Do not leave executable reads permanently limited to GET /package merely
because that was the first authenticated diagnostic. Implement other
documented readers with bounded validation and fixtures, while tracking the
specific live verification each still requires. Record genuine gaps
precisely and continue independent functionality.

5. COMPLETE THE ACTUAL HOSTING PLATFORM

Deliver usable first-party screens and workflows, not just an endpoint
register or an owner-only raw-JSON request console. Preserve the existing
PHP/MySQL architecture, navy/blue/metallic-gold design, logo/favicon, organized
admin/portal/app/includes/images/storage structure, and existing client
identity.

A. Connection, catalog, inventory, and ownership

Complete protected connection configuration, account capabilities/
entitlements, hosting package types, domain/VPS/managed-cloud inventories,
product/spec/location choices, limits/status/usage, refresh history, and
honest partial/stale/error states.

Keep internal provider cost separate from owner-configured retail pricing.
Preserve amount units and currencies; never assume provider GBP costs equal
customer USD prices or invent exchange rates, plans, quotas, or unlimited
features.

Provide explicit owner-approved links for existing resources and
deterministic order-derived ownership for newly provisioned resources.
Do not create another disconnected customer database or infer ownership
from matching names/emails/domains. Show clients only their authorized
services and actions.

B. Shared hosting lifecycle

Complete plan/package mapping, verified-order eligibility, provisioning,
provider-issued resource IDs, queued progress, confirmation/reconciliation,
plan changes, suspension/resumption, cancellation, and deletion safeguards.
Preserve billing records and customer data according to the approved
lifecycle rather than treating cancellation as immediate deletion.

Persist the approved commercial intent and exact provider request. Separate
accepted requests, pending setup, verified active services, definite failures,
and unknown outcomes. A successful HTTP response alone is not a completed
hosting service.

C. Native hosting controls

Finish applicable DNS/nameserver/subdomain/document-root management,
mailboxes/forwarders/autoresponders/quotas, databases/users, PHP
version/configuration, SSL/HTTPS, CDN/cache, WordPress operations,
FTP/SSH-key/security controls, usage/logs, and provider backups/restores
required by the approved roadmap.

Provide typed forms, current values, validation, confirmation where
appropriate, progress/history, and usable empty/error states. Show unsupported
account features honestly. A StackCP link may be an explicitly labeled bridge;
it does not count as completing a native screen.

D. Domains

Complete bounded availability search and suggestions, TLD/period eligibility,
verified pricing, registration, transfers, renewal/auto-renew policy, expiry,
contacts, nameservers/DNS, privacy, transfer locks/auth-code handling, and
status reconciliation.

Handle the documented newline-delimited JSON search stream; distinguish
provisional suggestions from checked results and throttle to provider limits.
Hosting-package names alone are not proof of registrar inventory or authority
to renew. A transfer purchase being accepted does not mean the transfer
finished. Keep registry expiry, commercial renewal, and payment status
separate.

E. VPS and managed cloud

Complete supported product/spec/zone/OS selection, order/provision/status/
usage, renewal, plan changes, cancellation, power controls, rebuild, console,
and snapshot features. Use exact supported provider capabilities and clear
destructive/downtime warnings. Do not present VNC as SSH or shared-host quotas
as VPS specifications.

F. Support and interactive SSH

Complete scoped, audited, expiring provider support handoffs and the
specifically requested interactive VPS SSH capability. Verify host identity
and destination ownership, restrict credentials, support revoke/disconnect/
expiry, and prevent cross-client sessions. Use a documented provider facility
or explicitly configured secure gateway suitable for this architecture.

No hidden daemon/root requirement on the shared-host application and no
fabricated terminal success. Any unavailable gateway configuration is a
specific remaining acceptance gate, not permission to omit SSH or stop
other work.

6. COMPLETE NECESSARY BILLING DEPENDENCIES WITHOUT A DETOUR

The 20i integration must work with the existing catalog, clients,
orders/invoices, and Authorize.net direction. Implement the minimum missing
order/payment/consent interfaces and customer journeys necessary for correct
20i fulfillment; these dependencies are included in this priority. Do not
rewrite the entire finance system or resume unrelated features.

Prove: selected plan -> authorized customer/order -> verified payment or
explicitly permitted manual fulfillment -> durable provisioning -> correct
client service -> renewal/cancellation state.

Include failure and recovery paths. Synthetic paid-order fixtures must
remain test-only; never mark production invoices paid to unblock provisioning.

Routine client controls use scoped current permissions. Previously consented
automation uses explicit persisted policies and verified eligibility; do not
require a new human owner password for every routine scheduled renewal.
Destructive, chargeable, and access-granting operations retain their
appropriate consent/confirmation controls. Do not confuse development
permission with production transaction authorization.

7. MAKE EXECUTION, RECONCILIATION, AND RECOVERY TRUSTWORTHY

Reuse and harden the existing gateway, fixed transport, operation ledger,
approvals, jobs, migrations, and audit infrastructure. Do not create a second
competing provider execution engine.

Enforce exact account/resource/action authorization, input allowlists,
bounded requests/responses, TLS verification, safe destination resolution,
secret redaction, and current authorization checks at dispatch. Bind
jobs/approvals/results to their original context; a configuration change
must not redirect old work to a different account.

Validate response semantics, not just status codes. Handle false/null
responses, per-resource failure maps, asynchronous IDs, and
accepted-but-pending operations. Do not treat all 2xx responses as
business success.

Unknown outcomes must never trigger blind resubmission of purchases,
renewals, deletes, rebuilds, or access grants. Local duplicate-request
suppression is not a guarantee of exactly-once provider effects. Evaluate
retry safety by the actual operation and parameters; do not
blanket-classify mixed updatePackage billing/deletion operations as
harmless idempotent updates.

Reconciliation appends evidence without rewriting original attempts.
Resource presence by name is not necessarily proof that our request
created it; absence in incomplete inventory cannot prove deletion.
Use operation-specific evidence and retain ambiguity where evidence
is insufficient.

Use shared-host-compatible bounded cron/jobs, locking/leases, rate limits/
backoff, visible failures, and owner-reviewed recovery. Preserve original
encryption keys, approvals, links, jobs, and attempt history through
upgrades/restores. A recovered database must not replay previously
completed or unknown external operations.

8. VERIFY THE INTEGRATED PRODUCT, THEN RELEASE IT

Use isolated synthetic accounts/resources and loopback provider fixtures.
Retain existing acceptance cases and add real native MariaDB/MySQL tests
for every supported database/runtime combination. Do not substitute SQLite
or mocked persistence for production migration acceptance.

Exercise actual extracted ZIPs through HTTP/Chromium: setup, refresh,
link/reapprove/unlink, client views/controls, complete order/service paths,
operation approvals, reconciliation, and failure recovery. Cover
desktop/mobile, root/subfolder, rewrite/no-rewrite, non-root permissions,
private-path denial, stale forms, revoked sessions, cross-client access,
mixed/partial provider results, timeouts, interrupted jobs, and
upgrade/reapplication.

Inspect actual screenshots and saved database results. Keep existing public
design, mail sending, client projects, invoice history, and unrelated
behavior intact. Regenerate canonical deployment mirrors and installer/build
inventories for changed bytes. Fix runtime warnings, schema errors, and
broken pages rather than hiding them.

Use focused tests during iteration; run complete acceptance for stable
integration candidates. Avoid continuously restarting long suites for
documentation-only changes. Verify the exact candidate SHA and inspect final
reports/logs/artifacts. Parent-branch test results do not certify a merged
descendant. Independent review is a release gate, not an excuse to stop all
further independent 20i implementation.

Deliver tested incremental releases containing useful integrated
functionality; continue the remaining 20i scope afterward. Do not collect
an indefinitely growing stack of unmerged “finished” components. Merge
normally only when protections/reviews/tests permit, then verify exact main
before publication. Never bypass branch protections or erase another
agent's history.

Produce verified FULL-PROJECT, COMPLETE-UPLOAD, and UPDATE packages, release
notes, upgrade/recovery instructions, and named evidence. Preserve one-page
key-free installation, configured private storage, and existing customer
data. No files dumped into root, 0777 workaround, reintroduced installation
key, or destructive fresh install over an existing website.

Do not move published tags or replace previous release assets. Download
the new release assets and verify their contents/checksums against the
accepted source. Clearly distinguish repository release from live-site
deployment.

9. REAL-ACCOUNT SAFETY WITHOUT ARTIFICIAL DEVELOPMENT BLOCKERS

The owner previously saved TWENTYI_API_KEY in Cajun_Web's GitHub Actions
secrets and the authorized GET /package diagnostic succeeded. Verify the
present configuration without displaying the secret. An Actions secret is
not automatically available to Codex or the deployed application. Do not
ask the owner to paste it into chat or commit it.

Use public documentation and isolated fixtures freely for development.
Reuse explicitly authorized live diagnostic scope only within its existing
permission; obtain specific approval for additional sensitive/live operations
where required. Key possession alone does not authorize buying/renewing
domains or servers, changing DNS, deleting services, sending customer mail,
accessing SSH, or deploying to production.

Keep real writes disabled until the relevant account configuration,
ownership, consent, and operation-specific acceptance are satisfied. Do not
weaken those protections to make tests pass. Conversely, missing live
credentials, a gateway, or merchant setup must not block schema, UI, adapters,
fixture integration, or other independent engineering.

Record the exact remaining external input/action once. Request it precisely
when necessary, continue all unblocked 20i work, and never label missing
implementation “owner-gated.” Do not divert to unrelated projects because
one live acceptance case is waiting.

10. COMPLETION, REPORTING, AND CONTINUITY

Create docs/TWENTYI_CODEX_TAKEOVER.md and a requirements-to-evidence checklist
using the existing full scope. Track each capability as: not started,
implementing, branch-tested, integrated, released, live-verified, or awaiting
a named external input. Track outstanding defects separately. Do not equate
endpoint count, green test count, or repeated documentation with business
functionality completed.

At every useful checkpoint update PROJECT_STATUS.md, SESSION_HANDOFF.md,
CHANGELOG.md, issue #2, the takeover PR, and relevant architecture/API/upgrade
guides. Record exact commits, repaired findings, implemented screens/workflows,
test/artifact links, remaining work, and next steps. Keep the current summary
concise; archive old status instead of repeating contradictory “current”
sections.

The first checkpoint must contain the ownership update AND a real
implementation/test correction, not documentation alone. The first release
target is the stable inherited inventory/ownership/operation workflow; then
continue native hosting controls, domains, VPS/cloud, support access, and
required commercial automation until the complete scope is addressed.

Completion means the required first-party workflows are implemented,
migrated, permission-checked, integrated, tested from distributable packages,
documented, and released. Provider-side/target-host acceptance must be
separately evidenced. Unsupported capabilities and genuinely missing external
approvals remain explicit; do not silently drop required scope or call the
entire 20i integration complete while major workflows remain unfinished.

Do not resume unrelated Cajun_Web roadmap development until this priority's
implementable scope and release work are complete. Do not stop at an arbitrary
version, after contracts, after the first fixed PR, or simply because another
narrow task has not been assigned. At session limits, push a recoverable
checkpoint and exact continuation instructions; do not claim execution
continues outside an active task.

Start now: inspect the current branches, record the owner override, preserve
Devin's implementation, fix the documented domain/migration/test blockers,
and continue end-to-end 20i delivery.
```
