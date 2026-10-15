# 20i commercial eligibility implementation

Codex owns this necessary dependency under the complete 20i P0 override. Branch `codex/twentyi-order-eligibility` retains the service-plan models, persistence and actual agreement screens from #90–#92 through a normal merge of 76a1537. It is not a separate finance rewrite. Existing accounts, catalog/invoice ledger, hosting journal and provider execution engine remain authoritative.

## Implemented dependency, pending combined acceptance

Owners offer immutable exact USD service terms to an existing active client. That client explicitly accepts the pinned terms; support sessions and owners cannot consent on the client's behalf. Original revisions, hashes and attributed history are retained. Consent to terms is distinct from authorization to charge or fulfill. Current preparation protects missing established storage instead of recreating empty terms/consent history. Restore the original database when prepared tables are missing; additive interrupted empty setup can still be retried.

## Required next interfaces

- An exact retail plan revision must map to a verified provider account/package type and allowed region/resources. Provider cost and customer retail price remain separate.
- A client order must bind the authenticated client, exact selected plan/mapping and consent. A provider resource's name cannot establish ownership.
- Fulfillment requires a verified eligible payment from the existing invoice ledger, or a separate owner-approved manual fulfillment policy/reason. Synthetic paid fixtures are test-only.
- A durable hosting intent retains that eligibility and the exact provider request, then uses the existing operation ledger. Accepted requests and allocated IDs are pending until operation-specific reconciliation establishes the service. Unknown outcomes cannot resubmit blindly.
- Renewal/cancellation must preserve commercial records, explicit recurring policies, client ownership and original attempt history. Agreement activation alone is not a paid order or live server.

These interfaces are unfinished engineering, not external owner gates. Merchant and feature-specific real account acceptance remain separate after implementation. No production request, charge or deployment is enabled by this checkpoint.
