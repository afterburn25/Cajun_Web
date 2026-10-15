# 20i write boundary and response semantics

Codex owns the complete [20i takeover](TWENTYI_CODEX_TAKEOVER.md). The existing adapter, protected transport, owner grants and durable gateway/ledger are retained. This boundary is executable through the owner operation workflow; it is not the completed first-party hosting platform. No current synthetic test proves live provider behavior.

## Request contracts

| Operation | Method/path | Current implementation and evidence |
|---|---|---|
| packages.create | POST /reseller/{resellerId}/addWeb | Existing bounded type/domain/extra names/label/document roots/StackUser/template fields. [Official creation guide](https://docs.20i.com/api/provision-hosting-package-api) supplies the result-ID response. |
| packages.update | POST /reseller/{resellerId}/updatePackage | Owner complete-reference clarification: id/delete-id arrays; billing, labels, limit, limit-set, packageBundleTypes are per-package maps. Outer shape and target isolation implemented; inner-field semantics still require full-reference intake and typed forms. |
| packages.delete | POST /reseller/{resellerId}/deleteWeb | Owner clarification: nonempty delete-id array. Destructive, separately approved request; never an automatic cancellation effect. |
| domains.renew | POST /reseller/{resellerId}/renewDomain | Existing name/years/renewPrivacy request. This is not proof of TLD eligibility, a verified quote, completed renewal or registrar entitlement. |
| packages.userstatus | POST /package/{packageId}/userStatus | Owner supplied includeRepeated/subservices; value-domain implementation remains pending. |
| domains.nameservers | POST /package/{packageId}/domain/{domainId}/nameservers | Owner supplied ns/old-ns arrays and actual add/remove semantics; implementation remains pending. |
| domains.dns | POST /package/{packageId}/domain/{domainId}/dns | Owner supplied conflictPolicy/insertPolicy/new/delete structures; record validation remains pending. |
| domains.register / domains.transfer | POST /reseller/{resellerId}/addDomain or transferDomain | Owner supplied contact/otherContacts examples. Full-source intake and individual registry validation remain pending; blanket claims that provider documentation lacks these fields are superseded. |

The selected [repository reference](TWENTYI_OWNER_API_REFERENCE.md) is not the complete upload. Its omissions cannot establish absent provider documentation. Current unimplemented entries remain unbuildable until their supported typed contract is implemented; this is an engineering backlog, not an owner-permission excuse.

Allocated reseller IDs or the documented self alias are accepted. Requests use the fixed verified HTTPS host, exact path allowlist, bounded JSON (8 KiB, depth8,256 visited nodes), and allowlisted body fields. ID lists contain1–64 distinct positive integers. updatePackage maps are restricted to explicitly approved update IDs; a package cannot appear in both update and delete lists. No existing scalar approval is silently converted. Old grants must pass the current builder before consumption and DNS/dispatch.

## Acknowledgement versus completion

| Observed response | Durable result |
|---|---|
| addWeb200/201/202 with exactly the documented result integer | accepted_pending; retain allocated ID, verify service separately |
| Empty/malformed/oversized body, false/null, arbitrary boolean, unverified ID, per-resource failure map, mixed error envelope | uncertain; no automatic retry or business success |
|401/403 | failed/authentication, without raw response disclosure |
| Other4xx except408/409/425/429 | rejected request; bounded failed result |
|408/409/425/429, redirects,5xx, other unevidenced2xx | uncertain |
| Transport known to fail before sending | failed; no completed effect claimed |
| Timeout/protocol/unknown transport | uncertain |

No write is blanket retry-safe. updatePackage can contain billing and deletion; its generic capability is explicitly non-idempotent. Local duplicate suppression is not proof of exactly-once provider effects. accepted_pending is terminal for automatic dispatch but requires reconciliation; replay retains the ID and attempts. Historical succeeded results are preserved rather than rewritten under the new classifier.

## Authorization, reconciliation and limits

The owner must approve an exact bounded scope and separately execute it with current password, current settings revision and a live single-use grant. The credential context is fenced at dispatch. Rejected stale/expired/revoked grants never become authority for a new context. The gateway records before returning and never automatically repeats an uncertain purchase/delete/renewal.

Reconciliation observes a provider-issued package ID when one was retained. A matching domain name alone is inconclusive about creation. An incomplete list cannot prove deletion. Observing a package does not certify service readiness, order fulfillment or registrar rights. Original attempt/result fields remain retained; current observation summaries and append-only audit events record checks. Structured operation-specific reconciliation history and native service workflows remain required platform work.

## Verification

The current local pure boundary suite passes139 named cases with default PHP and with no ini;50 transport-policy cases stop before any network. Native PHP8.2/8.4 × MariaDB10.11/MySQL8.4 operation/hosting matrices and packaged acceptance are dispatched on the containing checkpoint and must be verified from their actual reports. Tests cover allocated IDs, pending replay, false/null/mixed responses, authorization/context races and absence/name ambiguity. The earlier544 native inventory executions at962cc3e cover that earlier head only. Release, provider and target-host acceptance remain separate.
