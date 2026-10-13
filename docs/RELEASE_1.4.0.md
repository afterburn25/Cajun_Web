# 1.4.0 release scope and acceptance

| Roadmap | Delivered subset | Evidence |
|---|---|---|
| M1 / HC-01 | Managed service SKUs, USD unit/setup prices, periods, draft/published/retired state, private cost/provider reference, revision history | Native validation/privacy/stale-save and actual browser creation/publication |
| HC-01 public presentation | Configured design/care/additional services replace Packages cards; provider-dependent publication blocked | Public HTTP price/revision/draft/retirement and browser responsive captures |
| HC-06 invoice groundwork | Reviewed server-calculated quantity/setup totals; immutable catalog snapshot on existing client invoices | Browser review/issue/client display; amount tampering and stale-price rejection |
| HC-06 reliability | Same-key replay/concurrent duplicate prevention; strict issue/paid/void audit; no silent paid reversal | Separate-process native concurrency, native/HTTP audit failure rollback and permission tests |
| A-04 / F-04 | Additive catalog setup, retained actual 1.3.0 update, complete/update packages | Exact-main acceptance, byte-identical rebuild and draft/public readback |
| Earlier flows | Public site, estimator, free-form manual invoices, client projects, staff lifecycle and support | Existing native/HTTP/browser regressions and earlier upgrades |

This is partial HC-01/HC-06, not completed commerce or the full M1/M2 roadmap. Cart/orders, taxes/coupons, multi-currency, provider mappings/quotas, automatic billing, gateway checkout, verified receipts/refunds/reconciliation remain pending. Each SKU has one period/price; one catalog item per invoice. Provider families cannot publish or invoice. No live deployment or customer/provider operations. See [guide](SERVICE_CATALOG.md), [report](TEST_REPORT_1.4.0.md), [release register](RELEASES.md).
