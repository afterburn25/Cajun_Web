# Client website folder requests

## Source and scope

Branch `codex/twentyi-client-document-roots` starts from owner-control source `f545cf939bfc11534978346171f3f4471784dbac` ([PR225](https://github.com/afterburn25/Cajun_Web/pull/225)), then normally merges development main `dcd6966c4e0c209d15f9d88c8832ef601765c336` at `3bd43ca891c9faefc6c07a293e4d2d59e00d4a62`. That merge changes scheduling and continuity documents only; all shipping files retain the owner source. Five additive documentation conflicts retain every nonempty line from both parents. The initial merge helper's Windows text-decoding failure occurred before writing; it was corrected with explicit UTF-8.

The enclosing feature commit is **Add client website folder requests with owner review**. This implements a client request and owner review workflow for the existing documented single-host website folder change. It does not merge the separate HTTPS, directory-listing, catalog or billing feature branches. Published 1.25.0 and the live site remain unchanged. Devin retains the separate non-20i assignment.

## Using the workflow

The owner explicitly prepares client request history after enabling the existing website-folder observations and controls. The owner shares a saved map for a currently assigned service. In the primary client portal, **Hosting services → Website folder requests** offers the shared hostnames and the saved observation date. The client selects one hostname, supplies a relative destination folder and confirms the request. A pending request can be withdrawn. One unresolved request covers the entire hosting package.

In **Hosting & Domains → Website folder requests**, an owner can open a request, decline it with a message visible to the client, or review a fresh complete map before approval. The immutable client destination cannot be edited during approval. The owner confirms that the hosting supports folder changes and the destination directory already exists, then enters their current password. Those statements remain manual attestations; the application has no verified platform or directory-existence field for this operation.

Approval commits the exact owner approval and reciprocal client dispatch record together before one provider send. An undocumented success response stays unconfirmed. Separate folder reads can report a different value, an unavailable map, or the requested value. A matching observation does not prove causality, functioning routing or protected files. Interrupted dispatches use the existing narrowly scoped local history recovery; it does not resend the request.

Clients see the selected hostname, requested folder, decisions and dated outcome. Private subsequent paths remain owner-only. When account, assignment or sharing authority changes, new actions are refused; the original request history remains authenticated. Support sessions, contacts and mixed admin/client sessions cannot submit primary-client requests.

## Compatibility and storage

- Existing direct owner request documents remain version 1. Only client-origin owner request documents use version 2, adding an exact client request ID and document hash. Existing terminal/check formats and the compact grant commitment are unchanged.
- New local events use `provider_website_folder_requests_events` and the matching independent settings/audit namespace. This avoids widening the existing closed `provider_document_root_` table inventory.
- Resources retain the nested document-root binding, original client/assignment owner/sharing owner identities, sharing revision and the submitted observation. A newer complete map may change the owner's current before-value without replacing the client's original intent.
- Original observed paths remain opaque UTF-8 text, including an empty string. Requested paths retain the existing strict ASCII relative-path validator. Missing hostnames, empty JSON arrays and malformed maps are not treated as usable observations.
- Missing established journals, altered event/audit correspondence, and surviving reciprocal history refuse recovery through preparation. GET requests create no schema. All writes remain guarded by the owning mutex and transaction; network dispatch occurs outside a database transaction.
- Original client, owner, sharing, assignment and credential authority is checked at admission, credential/send boundaries, readback/save and recovery, including the older direct owner entry points.

## Verification and continuity

Root owns shared routing, navigation, packaging, installed browser continuation, acceptance integration and documentation. Domains owns the document/storage/schema/migration and upgrade companion; VPS owns service and owner integration with native/model coverage; Catalog owns controllers, views and rendering/browser models. Independent source reviews are retained alongside named local evidence.

The original owner-control native fixture, browser callback, eight captures and installed upgrade producers remain intact. New checks add document/state transitions, authenticated storage, actual service/controller models, native SQL lifecycle/concurrency/revocation, a packaged client-to-owner-to-client journey with eight distinct captures, and a separate populated published-1.25 update/retry/rollback/reapply companion. The acceptance helper reconstructs and verifies exact predecessor sources before adding requirements for every new report. It never treats modeled pages as installed application evidence.

Review found and corrected two integration risks: owner readiness is called outside mutation scopes by existing admin pages, so its new journal validation must acquire the existing mutex; and surviving client history must prevent recreation of lost owner control storage before any DDL. Original failed preparation/model attempts are retained with their correction evidence.

[Named local evidence](evidence/client-document-root-requests-local.json) records143 document,92 storage,69 service and83 view outcomes in each default/minimal PHP mode;72 modeled Chromium,30 portable upgrade and360 combined helper outcomes. All272 earlier helper outcomes remain the exact ordered prefix. Independent reviews verify33 source pins, four complete predecessor inverses and the service/upgrade integration. All33 changed-source syntax checks and canonical inventory refresh pass. The refresh creates no archives. Final shared review also corrected the focused workflow's dependency filters and added exact preservation assertions for six existing financial tables in the installed callback.

The session handoff records these local results; the PR delivery record identifies the first exact-source GitHub runs after commit. Linux SQL, installed HTTP/Chromium, populated upgrade and full acceptance are not inferred from portable checks. Current releases still require accepted combined source, exact-main acceptance, immutable assets and fresh download verification under [the release workflow](RELEASE_WORKFLOW.md).

Next: review this branch's first focused and full results, repair demonstrated failures on a successor, inspect actual new captures, integrate accepted increments sequentially and complete release verification. Do not restart or cancel preserved queued runs because they remain queued.
