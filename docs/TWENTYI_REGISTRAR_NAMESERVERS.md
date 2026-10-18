# Registered-domain nameserver boundary

This development increment starts at main `e397d9a6e293850e8dff61a593e3ba39ee178400` on `codex/twentyi-registrar-nameservers`. It adds typed provider reads and a restricted request builder. It does not add an admin action, grant write authority, change a domain or complete the nameserver feature. Published 1.25 remains immutable.

## Supported evidence and route

The retained owner SDK example and [20i API module README](https://packagist.org/packages/20i/api-modules) select a numeric domain ID from `/domain`, read `/domain/{id}/nameservers`, and pass the complete result list as `old-ns` alongside desired `ns` on that same route. The retained SDK source is commit `696a5f1299cd2b1e67ae7f688d644ac800f41b67`; README SHA256 `9de7dbce74bcd6c1d203b2f5eef2d01b2629de00c884ade28a3cf8570616dbbf`. Root checked the public package reference on 2026-10-18; it supplies the same example, not additional response or partial-edit semantics.

The [owner reference](reference/20i-owner-api-34e713-redacted.txt) separately documents additions/removals for `/package/{packageId}/domain/{domainId}/nameservers`. Those package-route rules do not establish registrar behavior. Review RNS-1 rejected the first proposal's reuse of that delta construction. The original proposal, review finding and test results remain preserved outside the checkout; only the corrected full-set subset is integrated.

## Current code

`foundation/twentyi-registrar-nameservers/` reuses the exact registrar link/profile/credential/inventory binding validation from the domain-contract reader, with a distinct observation scope. A binding records provenance; it is not current user authority. The reader performs one fixed numeric-ID GET and projects only a bounded, sole `result` hostname list. Unknown shapes, duplicate keys, invalid names, credential echoes and transport failures remain unavailable. An empty observed set is not permission to remove all nameservers. Neither names nor package IDs can replace the registered-domain ID.

`RegistrarNameserverChange` adds the separate `registrar.nameservers` operation. It serializes the entire nonempty desired list and entire nonempty observed list only when they are disjoint. Hostname validation is shared; package `between`/`delta` subtraction is never used. Overlap, empty current/desired, add-only, remove-only and no-op inputs are refused. Partial changes remain unsupported. Local hostname/body limits are structural safeguards, not registry allowances.

The fixed transport admits only the exact numeric registrar GET/POST paths and canonical bounded body. Generic operation approval remains denied. The existing classifier and gateway remain unchanged: a nameserver response is uncertain, and the same idempotency key cannot resend after an uncertain result or missing terminal record. A later read may observe the desired set without proving that a particular write caused it.

Canonical shipping mirrors and installer inventory include the new boundary. Existing package-domain behavior and all previous operation assertions remain intact. No schema, controller, portal or owner authorization changes are included.

## Verification and limits

Actual applied-source PHP 8.4.25 default/minimal checks pass 144 focused nameserver, 230 operation, 64 registrar-reader and 87 domain-contract cases in each mode. These use synthetic transport, modeled ledger and real pure reader/builder/gateway bodies; they do not contact 20i or use SQL. All 19 proposal files pass syntax checks in both modes. The unchanged application acceptance helper passes 221 outcomes.

The focused `twentyi-operations.yml` workflow retains its PHP 8.2/8.4 matrix, original commands, network-disabled containers and artifacts, and adds two nameserver reports. Its first exact-source run remains pending. Full application workflow/report integration belongs with the following native observation lifecycle; it is not claimed complete by this focused boundary test.

The initial patch transport encoded one non-ASCII negative-test literal incorrectly; actual integration failed at JSON encoding before completing its assertions. The failure and wrong bytes are retained under `work/registered-nameservers-integration-checks/initial-patch-encoding-failure/`. Restoring the exact frozen UTF-8 test bytes made all 19 applied files match their reviewed hashes and the affected tests pass. No product behavior or assertion was weakened. Frozen v2 manifest SHA256: `85978076a52b9f2296875fe996bc674130793af60b9c645c046ce509b884b38f`; independent correction review: `277bb3cb7b9c041e55398d36dea16e85a23b622d111bc317fae58d8c119e3f86`.

## Next application slice

Add the existing owner-only read lifecycle: explicit setup, password/CSRF checks, active profile and exact registrar link validation, durable intent before credential use, reauthorization before result persistence, immutable observation history and refusal to substitute an older success after a failed newer intent. Admin output must describe an observation, not verified registry configuration.

The reviewed application map reuses `provider_inventory_refresh_attempt` and the registrar/domain-contract provenance helpers. It must adapt rather than directly reuse `domain_contracts_binding` (which requires unrelated renewal-date setup) and `domain_contracts_intent_order` (which hardcodes that feature family). Generic inventory helpers that select finished or old successful reads cannot represent the newest pending/failed nameserver intent. The proposed first slice has separate `registered_nameserver_bindings` and `registered_nameserver_sets` tables with explicit owner preparation, service/storage code, and a native fixture; these are planned, not present in this increment. The external map is pinned to e397 and 30 existing source files, receipt SHA256 `c43c7d4d9910aec7acf9b157109b03a3571241f40fce2bb69712655c51948f03`.

A later write slice must separately bind the numeric ID and prove `old-ns` equals the entire current retained observation, show the supported change for owner confirmation, persist a reservation before one send, and fence unresolved profile/domain operations. A fresh matching-context readback may record observed desired/different/unavailable without automatic retries or causal claims. Generic caller arrays never establish observation completeness or authority.

Native database/concurrency, packaged forms, supported upgrades/recovery, full application acceptance and release/download verification remain required. No provider write, purchase, charge, customer mail, SSH or live deployment is performed by this increment.
