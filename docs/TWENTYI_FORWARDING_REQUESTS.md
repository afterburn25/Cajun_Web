# Email forwarding requests — candidate implementation

Starting source:3bbc42bd56270d1c754ff2e72ea039b9c557460e. Isolated branch:codex/twentyi-forwarding-requests. Commit description: Add local forwarding permission and immutable client review requests. Published1.24.0 and all prior versions remain unchanged. Root integrates/releases after acceptance.

## Verified request contract

The official [20i API browser-console guide](https://docs.20i.com/api/20i-api-browser-console), updated2026-01-29 and inspected2026-10-15, maps the request to `/package/{packageId}/email/{domain}`. Its [headers screenshot](https://docs.20i.com/uploads/forwarder_API_1_11b3418c09.PNG) visibly shows POST and HTTP200 through the My20i proxy; the article maps that proxy to `https://api.20i.com`. Its [payload screenshot](https://docs.20i.com/uploads/forwarder_API_2_addf6cc9cf.PNG) shows exactly `{"new":{"forward":{"local":"enquiries","remote":"testing@20i.com"}}}`. Both actual images were inspected. SHA256 headers:f19b3f4bfc736ab85288a804958c5e7d618caf684f97d4bbfe81982568e35ea3; payload:974e5eb1b0703093d779f86670be20a2f2e9ec70b031a153451b4bceb30b142d.

The images show no response body, stable forwarder ID, error/empty-result semantics or readback/list contract. HTTP200 alone is insufficient proof. Mailbox response types from the [mailbox guide](https://docs.20i.com/api/provision-mailbox-api) cannot be assigned to forwarding merely because they share an endpoint. The [forwarding guide](https://docs.20i.com/email-hosting/email-forwarding) permits forwarding without a mailbox, so this workflow requires an explicitly linked hosting domain, not a mailbox-create result.

`foundation/twentyi-operations/ForwardingRequest.php` validates one lowercase standard source/destination and builds the exact offline POST request. It is deliberately absent from the operation register, adapter and fixed transport admission. Wildcards, multiple destinations, replacement and deletion are excluded. No request constructor is called by a live dispatch path.

## Owner and client workflow

The owner prepares local history with their current password, refreshes hosting inventory and confirms the explicit client domain link, then grants a separate forwarding-request permission for1–720 hours and1–20 local requests per24 hours. This limit is local intake policy, not a discovered provider quota. Permission revocation remains possible for historical/unlinked assignments.

A client signs in directly, opens Hosting services → Email forwarding requests, and requests one address/destination with explicit review-only confirmation. The owner can save a client-visible message while leaving the request pending, or decline it. A client can cancel their own pending request. Decline/cancel can close historical requests after assignment changes; review cannot. Pending duplicate source addresses are refused under the database lock. Support access cannot request or decide forwarding.

The interface says that no email delivery was changed. No provider operation, accepted/unknown dispatch result, billing status or active-forwarding state is fabricated for an unsent local request.

## Authority and recovery

New permission/request and owner review require the latest sequence-numbered package intent to be complete, zero-skipped, within15 minutes, under the exact enabled profile revision, installation-bound key context and explicit active client/domain/package link. Complete here means every returned item validated; account-wide coverage remains unknown. Failed/unfinished later intents fence older inventory even if their wall-clock timestamps are earlier. Domain link event identity fences reapproval; exact resource/actor event/audit pairing prevents accepting a missing approval audit. Coverage across link/relink/unlink kinds also refuses a lost later event while its audit survives, so an older permission cannot revive. New fresh reads may support a review only when the original permission and assignment remain exact.

Every permission/request/review/cancel/decline is canonical JSON signed by the installation vault key, with an ordinal and previous fingerprint. The single InnoDB event table is append-only through the helper; independent settings head/count plus exact actor/audit pairs detect missing tail rows, paired event/audit loss, altered documents and lost preparation markers. The original request remains immutable. Writes require the same staff mutex and caller-owned transaction; audit failure rolls back event and head together. Preparation refuses caller transactions before any DDL and never rebuilds missing established history. GET performs no schema changes.

## Verification and remaining work

Local PHP syntax and15 named offline request-contract assertions pass. New native fixtures cover preparation rollback, separate permission, ownership/support isolation, stale context, original request immutability, audit interruption, history loss, reapproval/revocation and real concurrent processes. Four PHP8.2/8.4 × MariaDB10.11/MySQL8.4 CI modes are required. New packaged browser cases exercise actual owner permission, client request, owner review, cancellation/decline, stale/foreign CSRF requests, private history and direct-file protection, with desktop/mobile screenshots. Those native/browser runs remain pending; source or syntax checks are not acceptance.

Next: finish independent source review, run regenerated exact source through focused native matrix and packaged browser acceptance, download and verify named artifact results and inspect actual screenshots. Provider submission, exact acknowledgement, readback, update/delete and account entitlement remain separate unfinished implementation. No live provider, customer mail, charges, SSH or deployment was attempted.
