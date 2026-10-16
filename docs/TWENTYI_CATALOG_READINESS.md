# Owner catalog readiness and retained review details

## Scope

This increment starts at combined candidate `38bb69ccd1c1fc45f479022fe11748ca78b756d2` on isolated branch `codex/twentyi-catalog-readiness`. That candidate is not yet a release acceptance result. Preserved catalog source `8f7028` and documentation branch `codex/twentyi-catalog-discovery-contracts` / PR144 are unchanged. Root owns combined integration and publication.

The existing owner **Service plans** page shows a bounded readiness summary for its ten displayed plans, with a link to the plan's provider mapping. The detail screen explains the first applicable local reason: missing setup/review, replacement, withdrawal, future or expired review, inactive/changed retail terms, unavailable/changed/disabled provider connection, missing/changed key, or changed original reviewer authority. Connection reasons link to the existing protected provider settings screen.

These reasons come from the same eligibility checks used by hosting preparation. The typed local refusal remains a RuntimeException for existing callers. Readiness is a current local observation; it grants no provider access, verifies no entitlement and cannot replace the separate current owner/password/payment/fulfillment checks. Broken or incomplete retained history remains a restore error, not a harmless "review required" status.

Owners can select any retained mapping revision from the same plan screen. The selected section shows its original package account/type/location, known limits, private exact cost/currency/period/tax/basis/reference, reviewer account number and review dates. Zero is displayed as zero; unrecorded limits and costs remain unrecorded. Historical selections are visibly separate from the latest readiness and never replace the current mapping in the edit form. Retirement preserves this history even when a new mapping cannot be saved.

## Data and access boundaries

- Owner authority is checked again inside the existing staff lock/transaction. Delegated administrators, clients and support sessions cannot use either the list projection or selected review.
- List projections contain only a closed reason/message, current eligibility and mapping revision. Private costs, profile credentials and authentication fingerprints are absent.
- Selected review display uses an explicit field projection. Existing internal history validation still checks the full immutable chain, audits and latest-review pointer before projection; a missing latest withdrawal cannot expose an earlier review as current.
- One page accepts at most ten plan IDs. Historical selection accepts revisions 1–128 and must exist in that plan's validated history. Invalid or array query values produce a bounded inline form error. Query values never select a route or external URL.
- Reads create no schema, append no audit or mapping revision, change no accepted plan/order and make no provider request. Provider transport, operation grants and fulfillment execution are unchanged.

## Verification checkpoint

Changed PHP files pass local syntax checks. The existing pure catalog suite passes 51 named checks in default and no-INI PHP 8.4 modes; those pure checks do not certify database or browser behavior. Focused native cases exercise safe list fields, selected history, local invalidation reasons, unauthorized access, malformed selections/documents, paired history loss, missing provider setup, retired-plan history and unchanged rows. Actual HTTP/Chromium cases exercise the list, malformed query redisplay, client isolation, replacement/withdrawal, original-versus-current evidence, screenshots and unchanged provider-call count.

Current native database and HTTP/browser execution remain pending. The Windows vault permission guard is preserved; no local native-provider pass is claimed. The installer inventory is refreshed before committing shipping changes. No live provider call, charge, customer mail, SSH, deployment, merge or release is performed by this work.

## Inherited browser fixture provenance

This branch imports only `tests/provider_mailbox_cases.py` exactly from root commit `2239a1569dfa691fe18f23a512fb93b1905079ef`. Root reproduced the inherited combined38bb browser failure: a client without mailbox authority has no mailbox form from which to obtain CSRF. The corrected fixture obtains that same client session’s CSRF from its existing workspace logout form, then verifies the protected mailbox POST is refused inline without storing a password, request or provider call. No mailbox runtime changes or other root files are imported. This preserves the intended unauthorized-POST check while allowing catalog browser coverage to execute.

Final local checkpoint: installer refresh56678 completed successfully after the last shipping edit, and all 2,682 indexed shipping hashes match the source. The parent agent independently reviewed the three runtime files and the final retained shared authority call, finding no concrete source blocker. This is source review plus local syntax/package preparation; native/database/browser acceptance still awaits the exact committed branch.
