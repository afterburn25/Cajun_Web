# Client HTTPS redirect requests

This increment starts at main `dcd6966c4e0c209d15f9d88c8832ef601765c336` on `codex/twentyi-client-force-ssl`. It adds a primary-client request and owner review flow around the existing Force SSL controls. The separate StackCP candidate remains frozen at `51938fc80110931e80ebfd097066f2e4e1377df2` in [PR243](https://github.com/afterburn25/Cajun_Web/pull/243). Published 1.25.0 remains unchanged.

## Using the feature

An owner enables request history from **Hosting & Domains → HTTPS requests** after preparing Force SSL observations and change history. The owner must explicitly share a saved setting for the client's currently assigned hosting service. Sharing an observation allows the primary client to ask for a change; it does not allow the client to call the provider.

At most ten new requests per client per day and one pending request per package are admitted. Repeating the original submission is inert.

The client opens **Hosting services → HTTPS redirect requests**, chooses On or Off and confirms the request. Pending requests can be withdrawn. The owner can decline with a client-visible reason or review the latest saved flag and approve a single change with their current password. If the review is out of date, the owner refreshes the saved setting and reviews again. Approval never silently changes what the client asked for.

The client sees dated request status. A provider response alone does not confirm the change. The owner separately checks the reported setting; uncertain results remain unresolved and are never automatically resent. A matching flag does not prove which request caused the change, validate a certificate or test the public website. Restoring eligible missing local history does not resend the change.

## Scope and authority

The existing exact GET and POST `/package/{packageId}/web/forceSSL` contracts come from the retained [owner reference](reference/20i-owner-api-34e713-redacted.txt), lines 1663–1678. This increment introduces no additional provider endpoint and does not infer an undocumented success response.

Client forms require a current primary-client session and the exact current hosting assignment and observation-sharing permission. Contact, mixed staff/client and support sessions are excluded before session normalization. Sharing removal, account authentication changes, assignment changes or provider key changes invalidate outstanding authority. Re-enabling sharing does not revive an earlier request.

Client intent and owner approval retain separate actors. The owner operation is reciprocally bound to the original request in its admission transaction and uses the existing operation ledger, current-authority checks and unresolved-package fence. Direct owner requests retain their previous format and behavior. GET renders local state only; it does not open provider credentials, dispatch a change or write an admission.

The forms retain their original submitted identifiers and review proof after an error, clear confirmation and password fields, and preserve validated page/detail navigation. A new review cannot silently replace an expired or conflicting submitted approval.

## Verification and release

Local verification passes: document 105, storage 92 and service 53 named checks in both default and minimal PHP; existing owner controls 76, transport 37 and recovery 57 in both modes; 92 actual-controller/render HTTP/Chromium checks with modeled identities/business storage; 309 acceptance-helper checks. PHP/JavaScript syntax, canonical installer refresh, and documentation checks pass. The guarded native fixture has syntax/Windows-refusal evidence only: Linux SQL execution and full installed application acceptance remain pending. Named results and exact scopes are retained in the evidence receipt.

[Local evidence](evidence/client-https-requests-local.json) includes full named results and source/review hashes. The native fixture retains Linux CLI, explicit opt-in, isolated database and exact loopback guards. Its future provider calls use a synthetic transport. No live provider operation, deployment or new release is claimed.

Independent source review is complete. The first exact-source full GitHub run, installed application acceptance, normal integration, exact-main acceptance and release download verification remain required before publication. Other pending provider candidates are not certified by this branch's checks.
