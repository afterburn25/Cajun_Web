# Estimator adapter contract — L-01

Written 2026-10-13 on `devin/estimator-adapter` from main `b975915`. This document is the explicit adapter contract named as the L-01 prerequisite in [PUBLIC_COMPONENT_INTEGRATION.md](PUBLIC_COMPONENT_INTEGRATION.md). It specifies how the live `growth_estimate` path can delegate arithmetic to the reviewed [estimator foundation](../foundation/estimator) without changing visible behavior. **No `site/` code changes are proposed here** — implementing this adapter is a separate, reviewed step.

## Live semantics that must be preserved

`growth_estimate(array $a, array $r)` — currently `site/app/growth.php`, moving to `site/app/marketing/growth.php` under Codex's directory refactor — is authoritative. Its exact behavior:

- Inputs: `project_type` ∈ `growth_types()` (`business`, `redesign`, `store`, `custom`); `pages` integer 1–100 (default 5); `products` integer 0–10000, forced to 0 unless the type is `store`; `options` a list of at most 6 keys from `growth_options()` (`copy`, `brand`, `migration`, `booking`, `accounts`, `integration`), deduplicated.
- Rules: `growth_rules()` decodes the `growth_prices` setting. Each priced key maps to `[low, high]` integer cents with `0 ≤ low ≤ high ≤ 99999999` (validated by `grange`); a missing key or `null` pair means *unpriced*. Pricing is active only when `$r['enabled'] === true`.
- Priced keys, in order: `{type}` ×1; `page` ×(`pages`−5) when `pages` > 5; `product` ×`products` when > 0; each selected option ×1.
- Consultation (no totals) when: pricing is disabled, `type === 'custom'`, `integration` is selected, any required key is unpriced, or the summed `high` exceeds `99999999`.
- Otherwise `mode = 'range'` with `low`/`high` in cents and the formatted label `'$L – $H USD'`.

## Component mapping

The component is range-native: `PriceRule(selectionId, label, lowCents, highCents)` and `EstimateResult` carries `lowCents`/`highCents` with exact-integer overflow protection. The adapter bridges quantities by folding them into the constructed rules — the component itself stays untouched.

### Request construction

```php
new EstimateRequest(
    serviceType: $type,                      // validated against growth_types()
    options: $selectionIds,                  // see rule folding below
);
```

`sizeBand`, `timing` and `customScope` are unused by the live form and remain `null`.

### Rule folding — quantities become rule ranges

`EstimateRequest::options` is a membership set, while the live estimator multiplies `page` and `product` keys by quantity. The adapter folds quantity at *rule construction*:

| Live key   | Condition            | Rule added (when priced)                                         | Request option id |
|------------|----------------------|------------------------------------------------------------------|-------------------|
| `{type}`   | always               | `PriceRule($type, label, low, high)`                             | *(serviceType)*   |
| `page`     | `pages > 5`          | `PriceRule('page', 'Additional pages', uLow×q, uHigh×q)`          | `'page'`          |
| `product`  | `products > 0`       | `PriceRule('product', 'Products', uLow×q, uHigh×q)`               | `'product'`       |
| `{option}` | selected option      | `PriceRule($opt, label, low, high)`                              | option id         |

`q = pages−5` or `products`. The multiplication `uLow×q`/`uHigh×q` must be overflow-checked before constructing the `PriceRule` (`uHigh > intdiv(PHP_INT_MAX, q)` ⇒ treat as unpriced). Line ordering follows the live `$keys` order: type, page, product, options — `EstimateResult` lines then mirror today's accumulation order.

### Consultation conditions — where each lives

| Condition                        | Enforced by                                                        |
|----------------------------------|-------------------------------------------------------------------|
| `enabled !== true`               | Adapter short-circuits to consultation before calling `Estimator` |
| `type === 'custom'`              | Adapter short-circuit (live policy, not a pricing decision)       |
| `integration` option selected    | Adapter short-circuit                                              |
| Required key missing or null pair | Component: `ruleFor()` returns null → `unpriced` → consultation   |
| `u×q` multiplication overflow    | Adapter: treat the folded key as unpriced → component consults     |
| Summed `high > 99999999`         | Adapter post-check on `EstimateResult::highCents` → consultation   |
| `PHP_INT_MAX` overflow           | Component: returns consultation with an explanatory note           |

The live `99999999` cap is deliberately stricter than the component's integer-capacity guard; the adapter applies both, keeping the narrower live ceiling.

### Result mapping

`EstimateResult::KIND_ESTIMATE` ⇒ `['mode'=>'range','low'=>$r->lowCents,'high'=>$r->highCents,'label'=>"$… USD"]` with the existing format string. `KIND_CONSULTATION` ⇒ the existing consultation payload. Echo fields (`type`, `pages`, `products`, `options`, `note`) are unchanged. Component `notes` are not shown in place of live copy; they may be appended to the saved lead detail only if Codex wants the audit trail.

## What stays outside the adapter

Session tokens, honeypot, `check_csrf()`, rate limits, lead persistence, JSON/form response handling, and the estimate-calculate preview action all remain in `growth_submit`/`growth-pages.php`. The adapter replaces only the arithmetic in `growth_estimate`'s pricing branch.

## Acceptance evidence required

- `tests/native.php` fixtures: for a matrix over types × pages {1,5,6,100} × products {0,7,10000} × option subsets including `integration`, with `enabled` on/off and keys missing/null/malformed, the adapter output must equal `growth_estimate` byte-for-byte (`mode`, `low`, `high`, `label`).
- Boundary: configured `high` of exactly `99999999` still prices; `100000000` consults.
- Component suite remains green; no changes to `foundation/estimator` are required by this contract.

## Open question for Codex

If Codex prefers quantity support inside the component (e.g. `EstimateLine` carrying a `quantity`) instead of adapter-side folding, that is a component revision owned by integration review — this contract deliberately chooses the zero-component-change path.
