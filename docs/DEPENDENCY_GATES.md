# Development dependency and verification-gate policy

Owner-approved direction — 2026-10-13. **This policy applies to Codex, Devin and every other contributor** working in `afterburn25/Cajun_Web`. Read alongside [AGENTS](../AGENTS.md), [roadmap](ROADMAP.md), [hosting/billing plan](HOSTING_AND_BILLING_ROADMAP.md), and [client portal](CLIENT_PORTAL_ROADMAP.md). This document changes *how pending requirements are tracked and scheduled*; it does not certify any feature as implemented or deployed.

## Principle — external verification is a gate, not a project-wide stop

Do **not** interpret a missing third-party credential, reseller entitlement, merchant test account, live data, VPS connection, or API endpoint as an instruction to stop all work on the feature or other roadmap items. Instead:

1. Divide the feature into **independently implementable tasks** (public/admin UI, modular interfaces, schema/migrations, data validation, access rules, provider adapters, mock/fixture clients, transactional states, idempotency, error handling, observability, tests, documentation) and **external-dependent tasks** (real provider authentication, paid registration, charging cards, provisioning, destructive changes or production publication).
2. Implement the feasible portion against the verified current source and defined contracts. Use synthetic fixtures and isolated test environments with conspicuous mock/test markings. Do not fabricate API response schemas, provider capabilities, wholesale prices, account entitlements, or merchant acceptance.
3. Open and track an **external verification gate** for the untestable portion, with dependency, owner/required action, evidence needed, risk, and exact unblock/acceptance test. Continue other workstreams; pick another bounded, valuable, testable task when a gate cannot yet be passed.
4. Report **implementation status**, **external dependency/gate state**, and **release/deployment state** as independent fields. Never mark an entire item "blocked" solely because its production gate is waiting; conversely, do not report it completed because a mocked adapter or screen exists.
5. Protect already published releases and other workers' branches. Use scoped PRs, integrate after applicable tests and review, and update the status/handoff with an explicit next executable step. No invented schedules or assurances of unattended/background agent execution.

## Roadmap status vocabulary (two separate axes)

**Implementation:** `Planned` → `In development` → `Implemented (local/source)` → `Verified (specified tests)` → `Released (published artifact)` → `Deployed (target environment confirmed)`; additionally `Partially implemented` for delivered subsets. Always cite evidence and scope. Do not automatically upgrade status because requirements are documented, a PR is open, or unrelated CI passes.

**External gate:** `Not required`, `Awaiting provider/API verification`, `Architecture/security validation pending`, `Awaiting owner configuration/approval`, `Sandbox validation pending`, `Production activation prohibited until acceptance`, or `Validated for named operation`. A gate may be pending even when a safe subset is implemented or released. `Blocked` is reserved for a genuinely impossible next scoped task with no safe alternative, a named blocker and a follow-up owner.

## Reclassification of the four formerly "Blocked" spreadsheet items

| Roadmap | Better dependency status | Safe work that should continue | Live/remote operation still gated by |
|---|---|---|---|
| **HC-07 — 20i reseller API** | **Awaiting API verification** | Secure provider-settings screens, encrypted credential slots, modular adapter, plan/package mappings, connection tests with mocks, queues/reconciliation/idempotency, capability UI | Actual 20i reseller activation, authorized API key, endpoint/entitlement and request/response verification, real isolated test account |
| **HC-09 — Domain resale** | **Development pending — API verification** | Domain search UI, TLD/product/renewal price model, registrant privacy/consent, cart and registration state machine, transfer/expiry flows using fixtures | Supported account-scoped 20i registrar endpoints, funding/credit rules, registrant requirements, prices and approved end-to-end registration/renewal tests |
| **HC-10 — VPS resale** | **Development pending — API verification** | VPS comparison UI, resource/OS/region model, order states, provider adapter interface, entitlement/quotas/costs display with no invented real values, failure and teardown protection tests | Confirmed 20i VPS API/product availability and real provision/cancel lifecycle, pricing, staff permissions, billing reconciliation |
| **A-10 — VPS support SSH** | **Architecture/security validation pending** | Access/grant policies, UX, staff audit model, one-time launch token design, terminal error/logout UI, isolated transport test harness | A vetted secure provider handoff or external SSH gateway, host key verification, secrets/isolation, authorization and real approved VPS test; shared-host PHP must not secretly run a daemon |

The four labels above are **gate classifications, not claims of feature completion**. Keep the latest implemented/released/deployed evidence from source, current CI, release register and target-host checks. Change a gate to validated only with a dated, operation-specific acceptance record.

## Hard safety and business activation boundaries

- **No real credit-card charges, captures, refunds or subscriptions** until owner-enabled merchant configuration, Authorize.net sandbox acceptance, correct webhook-signature verification, merchant/tax/PCI review, per-order authorization and approved production cutover.
- **No live domain registrations/transfers/renewals or DNS mutations** until permission, ownership/consent, account-specific API methods, wholesale funding/pricing, rollback/failure rules and approved integration tests are established.
- **No real shared-hosting/VPS provisioning, suspension, deletion, server rebuild or destructive changes** from a mock, undocumented endpoint, unverified account, or unapproved live action. Expose unsupported controls as unavailable—not falsely active.
- **No customer/VPS SSH sessions** until separate actor/client grants, transport architecture, independent secure-gateway/provider handoff, known host identity, credential protection, session expiry and audit are validated. Shared-host installer must remain browser-based without hidden root/daemon requirements.
- Never paste credentials, transaction details or client secrets in Git/issue/chat, and never use production keys as sandbox fixtures. Authentication failures cannot be bypassed by setting success flags.
- Safe development can be merged/released as an explicitly incomplete and disabled module when tests and the established release workflow pass. **Do not enable checkout, provider writes or advertise unsupported products as orderable** before the relevant gates pass.

## Required progress reporting and next-step rule

Each development session/PR should include:

| Field | Required content |
|---|---|
| Roadmap item(s) | E.g. HC-09 domain resale |
| Implementation status | Exact real state of source and tests; cite commit/release |
| External validation status | Specific gate label above and operation(s) affected |
| Completed independent work | Code/files and tests actually performed |
| Pending validation | Credentials/access/endpoint/pricing/permissions needed — never secret values |
| Safe next action | A concrete independently actionable implementation, test, review or different roadmap item |
| Production prohibition | Which operations remain disabled until acceptance |

Agents **must not repeatedly ask the owner for an unavailable account/key and then stall**. Record the dependency once, link it from subsequent sessions, continue approved independent work, and revisit the gate only when the needed evidence or owner configuration exists. When all feasible work within an item is exhausted, shift to the next prioritized feasible item rather than idling the whole roadmap.

## Existing release context

At this policy snapshot (2026-10-13), v1.10.0 is the latest published/download-verified release. The portfolio 1.11.0 candidate is merged for acceptance but not published. Existing sandbox payments, manual invoices, admin staff access, client activity and other bounded releases remain exactly as recorded; this is **documentation/policy only**. No live 20i or merchant capability, customer-host deployment, SSH gateway or PCI clearance is inferred.
