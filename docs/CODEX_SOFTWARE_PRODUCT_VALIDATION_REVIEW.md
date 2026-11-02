# Software product-bound validation review

Date: 2026-10-28

Base: `03c4f606b5e536c7173ebf35200a40715228e527` (`codex/licensing-integration-review`)

Focused PR: #281, exact head `14008290deb933fc97049c1ca2602a19e35ab8b7`,
tree `f05438f51dc8e6205a11833317c178723e338014`.

## High-severity finding

The public validation endpoint accepts the legacy Website Core request shape
`{license_key, domain}`. PR #266 also made `product_id` optional for new
software-entitlement requests. A valid software key and registered domain
therefore reached `software_grant_verdict()` and returned authorized even when
the calling product supplied no identity. The same customer could reuse a key
for product A to activate product B on the licensed domain.

Supplying the wrong product was already denied. The bypass was omission, not a
comparison error.

## Correction

- A row in `software_entitlements` now makes `product_id` mandatory at runtime.
- Omission receives a definitive HTTP 200 denial, so an unbound client cannot
  treat the request as a transient success path.
- The existing exact product comparison remains unchanged.
- Genuine legacy website licenses without a software-entitlement row retain
  the historical `{license_key, domain}` contract.
- Master software entitlements are product-bound too; their any-domain policy
  does not become any-product authority.

## Regression evidence

- Installed acceptance sends a valid paid software key/domain/version without
  `product_id` and requires the exact definitive denial.
- Native coverage requires product identity for a software entitlement and
  confirms a legacy website key remains compatible without the new field.
- Python syntax, generated inventory hashes, 69 build guards, 283 artifact
  checks, documentation/link checks and whitespace pass locally.
- The canonical build refresh completed dependency and bundled-runtime checks
  and regenerated the installer inventory, then stopped at PHP lint because
  this workspace has no PHP executable. Hosted PHP 8.2/8.4 native and installed
  execution remains mandatory.

No gateway request, credential access, production activation, deployment or
main-branch merge occurred.
