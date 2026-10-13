# Domain lifecycle foundation

Started 2026-10-13 as an independent Devin increment under the dependency-gate policy ([DEPENDENCY_GATES.md](DEPENDENCY_GATES.md)): HC-09's gate (`Development pending — API verification`) does not block the implementable portion — TLD/renewal price model, registrant privacy/consent, and the registration/transfer/expiry state machine. Branch: `devin/domain-foundation`. Scope: the domain contract core only — no cart UI, no persistence, no registrar calls.

## Implemented component

The standalone PHP 8.2+ classes in [foundation/domains](../foundation/domains) need no framework, database, network access or Composer installation. Keep this directory outside deployment packages until the adapter contract is agreed and the release inventory is updated.

- [DomainName](../foundation/domains/DomainName.php) — normalized ASCII name with LDH validation (label length, hyphen rules, ≥2 labels, ≤253 total). Non-ASCII input is rejected; punycode encoding is an adapter responsibility.
- [TldOffering](../foundation/domains/TldOffering.php) — one TLD's admin-configured register/renew/transfer prices (integer minor units, `null` = not offered), year bounds, and WHOIS-privacy support flag. No prices exist inside the component.
- [RegistrantContact](../foundation/domains/RegistrantContact.php) — validated registrant details plus the `privacyRequested` preference.
- [DomainPricing](../foundation/domains/DomainPricing.php) → [DomainQuote](../foundation/domains/DomainQuote.php) — honest quoting: unknown TLDs, unconfigured prices, out-of-range years, or privacy on unsupported TLDs return `null` (unavailable), never an invented or zero price.
- [DomainOrder](../foundation/domains/DomainOrder.php) — guarded lifecycle state machine with append-only history.

## Order lifecycle

```
pending ──consent+submit──► submitted ──provider confirms──► active
   │                          │                              │  ├── renew (extends expiry)
   ├── fail ◄─────────────────┤                              │  ├── expire → expired ──► cancelled
   │      │                   └── fail ──► failed ──retry──►  │  └── transfer out → transferring
   ▼      │                        (ref/consent cleared)      │         ├── complete → transferred (terminal)
cancelled (terminal)                                          │         └── abort → active
```

- `submit()` requires `recordConsent()` first — registrant consent is a hard precondition for any provider submission.
- `retry()` clears the stale provider reference **and** consent, forcing a fresh explicit consent before resubmission.
- An `active` or `transferring` domain cannot be `cancel()`ed — deprovisioning/transfer is the only way out, matching HC-09's no-silent-loss rule.
- `cancelled` and `transferred` are terminal; nothing can transition out.

## What this component deliberately does not do

- No registrar endpoints, request schemas, or response shapes — domain operations map onto `ProviderOperation` names (`domains.register`, `domains.renew`, `domains.transfer`) and run through the [provider gateway](PROVIDER_FOUNDATION.md) once an adapter is built. Those provider capabilities are `unverified` until inspected against the real account.
- No persistence, cart UI, search UI, or payment — the state machine is pure; the site adapter owns storage and display.
- No automatic ownership inference — linking an order to a provider resource uses `ExternalResource`'s explicit link/verify contract.

## Integration work for the adapter phase

1. Site adapter: schema for orders/contacts/history, cart wiring to `DomainPricing`, provider operations via `foundation/provider`, consent capture in the order form.
2. Admin: TLD offering editor (prices, year bounds, privacy support) feeding `DomainPricing`.
3. `build.py` gains `'domains'` in the foundation bundling list; post-refactor destination is `site/includes/foundation/domains/`.

## Verification

Run `php tests/domains/run.php`. The dependency-free [runner](../tests/domains/run.php) uses `fx*` fixture TLDs and synthetic prices only — no real registrar data. 81 named checks cover name validation, offering rules, honest-availability quoting, consent gating, every legal/illegal transition, renewal semantics, transfer out/abort, and terminal-state enforcement. [GitHub domains workflow](../.github/workflows/domains.yml) runs syntax + the suite on PHP 8.2 and 8.4 with no network or secrets.

Local PHP 8.4.25: 81 checks pass; all files `php -l` clean. These checks do not establish cart UI, persistence, real registrar connectivity or deployment — those remain gated on the adapter phase and API verification.
