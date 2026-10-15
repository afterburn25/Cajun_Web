# Project status — complete20i P0

## Nameserver delta implementation — 2026-10-15

Continue complete20i P0 on `codex/twentyi-nameserver-controls` from inspected main `0b96f3d5812927640274d285b0180f3025624980`. Main advanced by two documentation-only commits recording the owner's approved client-portal design; their history and exact [design baseline](docs/APPROVED_CLIENT_PORTAL_DESIGN.md) are retained. Published runtime remains1.24/7190b582.

The owner supplied the exact POST `/package/{packageId}/domain/{domainId}/nameservers` fields `ns` and `old-ns`. The existing request builder now accepts those typed arrays, rejects overlap/duplicates/no-op or malformed targets, and retains fixed routing and request bounds. A canonical helper compares known original and desired DNS hostname sets and emits only additions/removals. It does not send overlapping full replacement lists. The generic approval menu remains restricted to package labels; completing a request shape does not grant execution or domain ownership. Unverified response bodies remain uncertain.

Added isolated positive/negative tests for canonicalization, unchanged names, add/remove-only changes, list/hostname bounds, invalid IDs, no-op/overlap rejection, unknown fields, no credentials and denied generic admission. Existing operation cases remain, with the former nameserver “shape missing” expectations replaced by documented typed validation. Canonical shipping mirrors and the installer inventory are regenerated. This checkpoint's new tests have not run yet; the branch workflow will run default/no-ini PHP8.2/8.4 with network disabled and preserve named reports.

The native nameserver screen, fresh authorized observation, per-domain consent, durable dispatch and operation-specific reconciliation remain to implement. This is a request component, not a finished control or release. No provider call, DNS change, charge, mail, SSH or deployment occurred.

Domain-search #136 source9ab6dc58 separately passes all four inventory/upgrade combinations and22 focused workflows; full38757561159 has passed build/native and is running HTTP/Chromium. Earlier099 full38755410755 retains its failed fixture evidence and continues recovery/Apache. Billing35a full38750019637 succeeded; cloud and billing artifact/review/integration gates remain. Preserve all independent branches and running evidence.

Next: inspect the nameserver workflow at the resulting commit, fix any finding, then bind the typed delta to fresh resource-scoped observations and the existing ledger. Continue current domain browser acceptance and reviewed cloud/billing releases. Full20i remains unfinished; unrelated work stays paused. Local filesystem/network restrictions and rejected review-comment/PR-metadata approval remain as recorded in the current domain handoff.
