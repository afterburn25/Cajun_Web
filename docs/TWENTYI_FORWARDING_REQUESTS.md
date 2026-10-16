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

Local PHP syntax and15 named offline request-contract assertions pass. New native fixtures cover preparation rollback, separate permission, ownership/support isolation, stale context, original request immutability, audit interruption, history loss, reapproval/revocation and real concurrent processes. Four PHP8.2/8.4 × MariaDB10.11/MySQL8.4 CI modes are required. New packaged browser cases exercise actual owner permission, client request, owner review, cancellation/decline, stale/foreign CSRF requests, private history and direct-file protection, with desktop/mobile screenshots. The exact native/browser runs are now verified below; source or syntax checks alone are not acceptance.

Next: integrate the separately corrected cloud baseline under root review and run exact combined acceptance. The preserved isolated full run is complete. Provider submission, exact acknowledgement, readback, update/delete and account entitlement remain separate unfinished implementation. No live provider, customer mail, charges, SSH or deployment was attempted.

### Exact focused evidence

Sourcea02bcf0564c40085cee5988fea91b9db451d08bf, PR150, focused run38832725045: all four jobs succeeded. Each downloaded archive contains85 passing uniquely named native cases and15 passing uniquely named offline cases. Digests and ZIP CRC were independently verified. These tests use disposable databases and synthetic inventory; no forwarding transport exists.

| Mode | Artifact | SHA256 |
|---|---|---|
| PHP8.2 / MariaDB10.11 | 12018201474 | 22ac4b2cd24b0a0cc8b758de3f8ef9fa653d365ba961b333aa5e9982ffa3f092 |
| PHP8.4 / MariaDB10.11 | 12017383852 | 011564a629e8623f430510933c78c4c5fb111d7309952305a87f59b629249e29 |
| PHP8.2 / MySQL8.4 | 12017692887 | 127d911189b35a6d09d2800dfcacd250faf3d5a90d3bb6a40fe57bbbc94c55fe |
| PHP8.4 / MySQL8.4 | 12017782785 | 127d911189b35a6d09d2800dfcacd250faf3d5a90d3bb6a40fe57bbbc94c55fe |

Exact application run38832724941 is terminal SUCCESS and its final archive is independently verified below. Continued primary-source searches on2026-10-15 found no exact forwarding acknowledgement/readback semantics beyond the request example; keep dispatch disabled. The live forwarding-setup guide now resolves at [email accounts forwarding](https://docs.20i.com/email-hosting-email-accounts/email-forwarding).

### Exact packaged browser evidence — 2026-10-15

Runtimea02, application38832724941, browser artifact12018488822:113,934,446 bytes, SHA256 `e1b6edacd952889d6748d7869b8c6548fd0c1f31896659740911d417530eba0e`. Independently downloaded and verified282 safe unique members, ZIP CRC,71 named-result reports,11 diagnostics and1,987 passing HTTP checks. The42 uniquely named forwarding outcomes include actual Chromium owner permission/client request/owner review, cancellation/decline, CSRF/foreign/replay/revocation/support refusals, recovery refusal and ten private-path denials. Expected estimator422 is the only retained console error; unexpected errors are empty. Four-way85-native/15-contract evidence above remains separate.

All seven actual screenshots were inspected, including the completed client pre-submit form. Owner/client desktop and320px mobile display review-only permission and consent, pending state, escaped literal review message and cancel/decline. Full-page owner crops remain readable without overlapping controls. The narrow native mobile permission select clips the end of its selected label but retains the Allow prefix; the locator-only mobile detail capture contains sticky navigation across its interior, which is absent from the original full-page crop. No blocking visual issue. Proof `work/20i-forwarding-requests/qa/forwarding-a02-browser-verification.json`; archive `forwarding-a02-browser-12018488822.zip`; images `qa/forwarding-a02-browser-review/`. Recovery/Apache/final acceptance subsequently completed successfully in the same original run, as recorded below. No live forwarding occurred.

Separate frozen-root review found an existing cloud-confirmation later-event-loss issue. Root owns its bounded correction and fresh acceptance; this forwarding checkpoint does not certify the older cloud baseline for release. No source change was made here during that read-only review.

### Exact final archive — verified 2026-10-15

Full38832724941 at frozena02 is terminal SUCCESS through all stages, including recovery, Apache and unchanged-source checks. Final artifact12018823878 is132,314,247 bytes; SHA256 `ffdf4ccaf564c6748e2621ba6801823db4c7f8fdb571b653c00029c0a6aa5401`. Independent download inspection verifies403 safe unique outer members/CRC,110 named-result reports,11 diagnostics and all1,987 HTTP/289 recovery/1,999 Apache outcomes. Reports overlap;13,379 passing named outcomes excluding duplicate progress/readiness reports are not unique feature coverage.

All11 SHA256SUMS entries and all three nested ZIP CRC/member/directory manifests pass. Every FULL file matches the actuala02 Git tree, every COMPLETE file matches its exact shipping source, and the decoded UPDATE manifest/payload/history file matches that same source. FULL5510files/675directories, SHA2565f5e0660edd32cecfeec65714780910e3f50339ba68a282dcd1f96df9482e233; COMPLETE2707/351, SHA2569dcf046f430e91082ddfaf62428b40c38c82596c29a13b9d7e861a9c1643d58e; UPDATE2694/348, SHA2568b152038b774b9d8c23c4aa4a567c5c8b08172c0165ab896d0577c1d6acd06c0. All174 screenshot bytes equal the earlier browser checkpoint; the seven forwarding images were actually inspected. No exact Windows archive-build comparison is claimed; Windows installer regeneration/syntax and exact shipping hashes are verified separately.

Reusable proof `work/20i-forwarding-requests/qa/forwarding-a02-final-verification.json`; archive `forwarding-a02-final-12018823878.zip`; local read-only verification utility `qa/verify_forwarding_final.py`. Runtime/test/workflow bytes remaina02. Root's a963 cloud-history correction and next-branch integrations require their own acceptance; no merge, release or live forwarding is claimed by this evidence.
