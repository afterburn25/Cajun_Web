# Registrar prerequisite - 2026-10-15

Parallel `codex/twentyi-domain-controls` implements distinct registered-domain inventory and explicit local client assignment through the existing reader. Current complete id/name observations are separate from hosted names; latest failure/partial/unfinished/stale reads cannot be overridden by older success. This establishes the native assignment prerequisite only. Nameserver observation/consent/write response/reconciliation remain unfinished; no mutation is enabled. [Provenance and verification](TWENTYI_REGISTRAR_INVENTORY.md).

# Native nameserver control — implementing

Current evidence: f8654252 passed [workflow38759308990](https://github.com/afterburn25/Cajun_Web/actions/runs/38759308990),230 named cases in each of PHP8.2/8.4 default/no-ini runs (920 executions); actual four JSON reports were inspected. Of each run,79 named cases cover nameservers. Mirror/inventory and documentation checks passed. This proves the request component only. It is now retained in the combined [fulfillment integration](TWENTYI_FULFILLMENT_INTEGRATION.md); combined acceptance and the native workflow below remain pending. Older checkpoint wording below is historical.

## Nameserver delta implementation — 2026-10-15

Continue complete20i P0 on `codex/twentyi-nameserver-controls` from inspected main `0b96f3d5812927640274d285b0180f3025624980`. Main advanced by two documentation-only commits recording the owner's approved client-portal design; their history and exact [design baseline](APPROVED_CLIENT_PORTAL_DESIGN.md) are retained. Published runtime remains1.24/7190b582.

The owner supplied the exact POST `/package/{packageId}/domain/{domainId}/nameservers` fields `ns` and `old-ns`. The existing request builder now accepts those typed arrays, rejects overlap/duplicates/no-op or malformed targets, and retains fixed routing and request bounds. A canonical helper compares known original and desired DNS hostname sets and emits only additions/removals. It does not send overlapping full replacement lists. The generic approval menu remains restricted to package labels; completing a request shape does not grant execution or domain ownership. Unverified response bodies remain uncertain.

Added isolated positive/negative tests for canonicalization, unchanged names, add/remove-only changes, list/hostname bounds, invalid IDs, no-op/overlap rejection, unknown fields, no credentials and denied generic admission. Existing operation cases remain, with the former nameserver “shape missing” expectations replaced by documented typed validation. Canonical shipping mirrors and the installer inventory are regenerated. This checkpoint's new tests have not run yet; the branch workflow will run default/no-ini PHP8.2/8.4 with network disabled and preserve named reports.

The native nameserver screen, fresh authorized observation, per-domain consent, durable dispatch and operation-specific reconciliation remain to implement. This is a request component, not a finished control or release. No provider call, DNS change, charge, mail, SSH or deployment occurred.

Domain-search #136 source9ab6dc58 separately passes all four inventory/upgrade combinations and22 focused workflows; full38757561159 has passed build/native and is running HTTP/Chromium. Earlier099 full38755410755 retains its failed fixture evidence and continues recovery/Apache. Billing35a full38750019637 succeeded; cloud and billing artifact/review/integration gates remain. Preserve all independent branches and running evidence.


## Request contract and limits

Evidence: [the owner's complete20i takeover](TWENTYI_CODEX_TAKEOVER_REQUEST.md), section4, expressly documents POST `/package/{packageId}/domain/{domainId}/nameservers` with added `ns` and removed `old-ns` arrays. [Selected original source mapping](TWENTYI_OWNER_API_REFERENCE.md) identifies lines1337–1352. No new live probe was used. This request evidence is separate from currently unverified response semantics and resource entitlement.

`NameserverChange::between(current, desired)` keeps canonical original/desired sets and computes actual differences. Case and a single terminal DNS dot normalize; equivalent names or ordering do not produce a request. Sets must be lists of at most64 valid ASCII hostnames, each at most253 bytes after normalization. The local bound is not a registry or20i minimum/maximum guarantee. Literal IPs, URLs, paths, ports, controls, whitespace, malformed labels, duplicate entries and an empty desired configuration are refused. ASCII encoded DNS labels may identify nameservers; they do not establish IDN domain-registration eligibility.

The request builder consumes positive allocated package/domain IDs, requires both delta arrays and rejects overlap or an empty change. Only the exact two arrays enter the body. It retains the existing15-second deadline,8KiB request bound, fixed HTTPS host and credential-free construction. Generic authorization still denies this operation. The helper is not proof of current provider state, ownership or consent; the future native workflow must supply those separately.

## Required native workflow

1. Load the client's explicitly approved domain/resource under its current credential/account context. Hosting names alone do not establish registrar authority.
2. Perform an explicitly enabled bounded current-state read, validate the documented response and retain the original resource/context/revision. Missing or partial data cannot become a known empty set.
3. Show original and proposed nameservers and the exact additions/removals. Warn about DNS/service disruption. A stale observation/form requires refresh and renewed confirmation.
4. Persist the exact confirmed request in the existing provider operation ledger. Check current access and original context again before dispatch. Do not add a competing execution engine or admit an arbitrary JSON payload.
5. Treat unverified responses/timeouts as uncertain; preserve attempts, avoid blind replay, and confirm current state through operation-specific evidence. A changed configuration elsewhere is not necessarily proof of our request's outcome.
6. Test native permissions, migrations, two-client isolation, stale context, interrupted writes, current values and desktop/mobile extracted-package forms. Independent review, current artifact inspection, integration/release and authorized provider-side acceptance remain separate gates.

This checkpoint does not implement those remaining native steps. Continue the complete20i scope after this bounded component.
