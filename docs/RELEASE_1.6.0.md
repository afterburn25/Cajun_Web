# 1.6.0 bounded scope and acceptance

PR [#18](https://github.com/afterburn25/Cajun_Web/pull/18) continues M2 / partial HC-04 and HC-11 from verified 1.5.0. Final exact-main acceptance and publication evidence are recorded separately when complete. [Sandbox guide](SANDBOX_PAYMENTS.md).

| Boundary | Delivered behavior | Acceptance |
|---|---|---|
| Owner trial | Existing prepared request; HTTPS; current owner password; explicit test-card confirmation; Accept.js nonce only | HTTP auth/CSRF/HTTPS checks; simulated browser tokenization and single POST boundary |
| Durable intent | One attempt per request; no automatic charge retry; no token storage; intent/audit commit before send | Native concurrency and pre/post-send audit failure recovery |
| Provider verification | Fixed sandbox endpoint; TLS and bounded transport; USD-only merchant preflight; exact transaction ID/reference/type/amount matching | Synthetic reporting mismatches and unavailable responses; real provider acceptance pending |
| Uncertain result | Unknown/submitting/held retained; explicit authenticated transaction lookup; wrong IDs do not attach | Native timeout, held result, account rotation, collision, stale and terminal conflict tests |
| Invoice integrity | Sandbox never pays real invoices; manual/catalog/request changes share mutex and unresolved-attempt guard | Native manual-preflight race, catalog paid/void denial and strict manual-audit rollback |
| Existing installation | Additive attempt/event tables; preserve credentials/key, requests, invoices, accounts, private/config/guard bytes | Actual pinned original 1.5.0 UPDATE and retained earlier upgrade fixtures |
| Presentation | Existing navy/gold admin style, desktop and 320px form/history navigation; public fields only on authorized trial | Actual Chromium screenshots and scoped CSP/no uncaught errors |
| Delivery | Flat complete/update packages, exact source hashes, installer parity and retained evidence | Exact-main CI plus draft/public download verification required |

Production/client checkout, guest links, signed webhooks, automatic reconciliation/discovery, real settlement, receipts/refunds and recurring billing are not delivered. A missing sandbox transaction after timeout remains unresolved; no forced-success shortcut. Broader 20i, admin email/templates/bells, client website ZIPs and VPS SSH remain roadmap work. Real merchant credentials, HTTPS/provider acceptance and host deployment were not available or performed. Synthetic tests do not establish PCI compliance.
