# Add a hosting subdomain

## Development status

This candidate starts from main `f688f79ee838e86e4774bfa5ec71fc7fb79126a5` on `codex/twentyi-subdomain-add`. It adds an owner workflow for one new subdomain on an explicitly assigned hosting package. Native database and full application acceptance are required before integration and release. Published 1.25.0 remains unchanged.

## Owner workflow

In **Hosting & Domains → 20i connection**, refresh the assigned hosting inventory and its **Website folders** observation. Enable **Subdomain requests**, select an attached parent, and enter the full new name, such as `shop.example.com` under `example.com`. The initial workflow accepts one child label beneath that selected parent. Confirm the request and enter the current owner's password.

The parent must appear in both the current assigned package and the reviewed website-folder map. An already attached child is refused. The signed form expires after five minutes and is bound to the owner, assignment, provider credentials, reviewed observation and preceding request. Failed form submissions retain the original request identity and entered names; passwords and consent are never restored.

After submission, use **Check reported website names** in request history. This performs a separate read of the provider's website-folder map. A matching name is displayed as observed after the request; it does not establish that this request caused the change or confirm DNS, certificate or website availability. Missing or unavailable results remain unresolved.

An uncertain request blocks another add for the same hostname and package, including after assignment or credential changes. Other hostnames remain eligible for a new explicit review. Reposting an existing request never sends it again. A separate recovery action can restore a missing local completion record from the exact finished provider attempt; it does not replay the request. Incomplete attempts or damaged retained evidence require recovery of their original records.

Owners can see retained request history after an assignment is removed. Further checks and recovery require current authority matching the original approval. Client-request forms, delegated writes, deletion, custom document-root changes and automatic DNS or certificate setup are outside this increment.

## Provider and storage contract

The [owner-supplied reference](reference/20i-owner-api-34e713-redacted.txt), lines 866–881, documents the subdomain map and the subdomain-specific add/remove endpoint. The typed operation sends only `POST /package/{allocatedId}/web/subdomains` with `{"add":["shop.example.com"],"rem":[]}`. The parent is retained as local approval context and is not an invented provider field. The provider enforces its subdomain allowance. No successful acknowledgement schema is supplied, so the response cannot establish business completion.

Execution uses the existing fixed HTTPS transport, protected provider credentials and shared operation ledger. The new request, terminal and readback records are authenticated and audited separately. Preparation is an explicit owner action; viewing the page never creates tables or sends provider requests. Missing preparation markers, altered history or lost ledger/approval records cannot be reset by preparing the feature again.

The existing document-root reader supplies the independent follow-up. Only the exact newly returned receipt may be attached to a request. An older saved name, a different package, changed authority, or an unavailable read cannot become confirmation. The general-purpose operation approval screen does not authorize this dedicated workflow.

## Validation

The [local verification receipt](evidence/subdomain-add-local-validation.json) records named results and independent reviews. Both PHP modes pass 127 transport, 230 existing operation, 65 service/parser and 39 renderer checks. The offline acceptance helper passes 297 checks while preserving the original 221 outcomes. A local Chromium preview passes 14 checks with synthetic projections and the complete shipping stylesheet order; it is not an installed-site login or live provider test. Canonical installer refresh and all 21 touched PHP lints pass.

The guarded native fixture and additive complete-application recipes cover the actual schema, owner authority, single dispatch, same-hostname replay prevention, independent readback and history recovery. The existing document-roots focused workflow is reused for supported PHP/database combinations. No live subdomain was created during development.
