# Primary application review: shared portal, sessions, proposals and tickets

**Decision: primary integration is not cleared.** The separate finance review identifies two current P1 defects, and whole-source review and exact-source acceptance remain unfinished. Within the shared components and changed logic described below, this reviewer found no additional confirmed blocker. That bounded result is not an approval of the entire application, PR317, a release, or live operations.

## Exact source and independence

| Identity | Value |
| --- | --- |
| Main baseline | `1c44ca120e7768998fafa720b3e1c4c85990c330` |
| Reviewed primary source | `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd` |
| Reviewed primary tree | `a206e95562ab751cd9461db3a8ed32c4305ecbec` |
| Read-only exact checkout | `/workspace/scratch/8cc2b4f257ab/Cajun_Web_primary_review` |
| Reviewer | `/root/github_status_review` |

This reviewer did not implement these primary application features. The reviewer did compose the later account/notification integration's CI and fixture layers; this report does not independently approve those authored changes. The later notification correction has its own separately bound review. Source was read from the detached exact84 checkout, rather than from the integration checkout containing later account/notification changes.

The companion `primary-shared-review.json` records the exact Git blob and SHA-256 for 64 reviewed component, changed-logic or deployment-mirror paths, three canonical proposal dependencies, and 13 retained fixture references. It also inventories all 286 changed shipping/build/version paths. Its coverage labels distinguish direct review, the separate finance review's own limits, generated-source gates, and paths not cleared here. A path's presence in an inventory is not an approval.

No source files, ordinary indexes or branches were changed. No new runtime tests, database/services, provider operations, remote writes or Actions queries were performed. The checkout remained clean after receipt generation.

## Reviewed behavior

### Current administrator authority and master client access

`admin_session_matches()` refreshes the current administrator, requires the same positive identity and canonical authorization version, and rejects support selectors and support context. `admin_user()` retains the active profile and authorization-version checks and the idle and absolute session limits. Replacing repeated password verification with this helper therefore follows the recorded current-session directive; it does not create an unauthenticated owner path.

The changed staff mutation path still runs inside the database-scoped staff mutex and transaction. Staff profile changes rotate authorization versions and revoke credentials, and the last activated owner remains protected. Action dispatch still performs CSRF validation, requires an authenticated administrator, and checks the action-specific permission before entering the newly routed handlers. Software and license mutations explicitly reject support context before dispatch. The one-line mail, appointment and website-review changes retain their existing manager/actor and locked mutation boundaries, with the new same-session check at the prior password check.

The master portal accepts a fixed, local route and bounded search/page inputs. Its client projection rechecks the authenticated administrator under the shared mutex. An owner account-review session retains the real staff identity and a distinct audited context; ordinary staff support access still requires the client's support policy. Support binding, staff/client authorization versions, expiry and idle checks remain current in `support_check()`. A stale or invalid support selector cannot silently become a client login. Client signatures, payments, security changes and ticket read state remain outside support authority.

This is changed-logic and relevant call-path coverage. It is not a fresh audit of every unchanged reset, invitation, mail or support-upload implementation.

### Support tickets and activity

The ticket adapter checks the current staff or client actor inside the staff mutex and transaction, including requests that would otherwise be idempotent replays. Client ownership and active-account checks occur before disclosure or mutation. Support replies preserve the actual staff and support-session attribution and require both ticket and support-write authority; support cannot create a client ticket, change client read state or close the ticket as the customer.

The immutable document parser requires canonical field order, bounded UTF-8 content, exact identifiers and monotonic event times. It rebuilds the ticket model and verifies the persisted row's projections and every retained event, including before/after hashes, actor attribution, audience and submitted revision. Journal changes, event insertion and strict audit records share the transaction. Staff and client revisions remain separate: private notes and assignments do not change the client's public revision, public timestamp, history or notification cursor. Client-facing related records are rechecked against current ownership; subscriptions stay outside support projections.

Read state is scoped to the actual viewer and advances monotonically only through explicit CSRF-protected actions. Passive activity counting restores idle timestamps and does not mark anything read. Client notification SQL and subsequent projection checks exclude internal events. The web controllers enforce POST, CSRF, exact route/form ticket binding, bounded form fields and explicit status confirmation. Rendering escapes ticket content and preserves failed submissions without granting a new request identity.

Ticket setup preflights existing tables before DDL, refuses an established marker with missing history, checks exact indexes and foreign-key relationships, and rechecks owner identity before committing activation and audit. The inspected native fixtures include actual waiting-writer permission revocation, client revocation while waiting, private-event invisibility, altered/missing immutable history, exact support attribution and schema-preservation denials. Those are existing test definitions; this review did not rerun them or convert another source's passing result into exact84 acceptance.

### Proposals

Proposal create, revise, send and withdraw revalidate the same current staff identity under the shared mutex. Creation binds replay identity to creator, client and normalized input. Revisions retain old versions and reject a duplicate content hash. The client decision path rejects support context, refreshes the current client session, locks the proposal, checks current client ownership and requires the exact version ID and content hash displayed to the customer. It recomputes the content hash before applying the foundation lifecycle, and commits the decision, proposal state and event together.

The foundation enforces ordered versions, current-version decisions, terminal approval/withdrawal and a new revision before re-sending changed terms. The client form requires explicit confirmation and includes the version/hash pair. Setup validates existing schema and refuses incompatible or missing established storage. The selected retained native cases test same-owner setup denial and revoked delegated-manager operations without changing the recorded history.

This is a bounded code review of supported application transitions. It does not claim exhaustive manually corrupted or independently restored database-history testing, nor a legal review of agreement text.

### Adjacent source, models and build behavior

The desk foundation's approval and fulfillment models retain different-actor/required-role approval, expiry, operation/subject binding, immutable attempt tokens, uncertain stalled outcomes and a verified not-executed requirement before retry. These pure records do not themselves authorize a provider operation; their caller remains responsible for actual actor and provider evidence.

The changed delivery-download path refreshes financed access before its audit transaction and rechecks customer/release ownership at attribution time under the same staff mutex. This avoids introducing a nested financing transaction into delivery-event recording. The finance verdict implementation and unrelated gateway behavior remain governed by the separate finance review and its outstanding defects.

The shared routing additions retain destination-specific permission checks, no-store headers for sensitive views, CSRF on mutations and escaped navigation values. The payment CSP extension accepts only fixed sandbox/production host sets and requires initialized nonce state; this source observation does not authorize production mode. The build adds the desk/proposal canonical mirrors and excludes tracked ZIP fixtures from the distributed FULL-PROJECT archive. Version/default/installer values consistently select 1.26.1, while the start guide keeps update, fresh installation, release publication and live activation separate. This review does not claim that a 1.26.1 release has been published.

Nine deployment mirrors in this receipt were compared byte-for-byte with their reviewed canonical sources. Generated inventory/bootstrap and complete package extraction remain source-integrity and release-acceptance gates, rather than manually reviewed application files.

## Current blockers and remaining review

The separate `primary-finance-review.md` and its JSON identify FIN-01 (unresolved replacement creation across permanent payoff) and FIN-02 (external receipts after enrollment leaving the original recurring amount active). Their traces are bound to exact84. Historical original findings and their source limits are retained, and no fresh execution of the unavailable original probes is claimed. Root has assigned a separate isolated correction lane after a bounded attempt to recover the claimed successor source did not find attributable implementation.

Hosting/catalog checkout source review is active in a separate worker. Software settlement/reversal/webhook/product/asset/notice tails, several finance/recurring migrations, shared finance/license forms, visual/CSS details and remaining inventory paths require their own explicit coverage. The companion JSON does not turn the finance lane's partial or inventory-only records into approval. Historical producer self-review, prior source receipts and passing matrices are reused only within their demonstrated source bounds.

The next integration decision therefore requires the current financing corrections and their independent review, completed relevant source coverage, required acceptance on the resulting combined source, and the repository's merge/main/release gates. Existing exact84 and account/notification runs must stay intact; this review is not grounds to dispatch duplicates or bypass required checks.
