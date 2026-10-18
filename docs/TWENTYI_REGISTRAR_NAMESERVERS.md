# Registered-domain nameserver observations

The `codex/twentyi-registrar-nameservers` candidate now adds owner-only observation setup, refresh and dated history to the 20i connection screen. It builds on the typed provider boundary committed as `17cf2863`, originally based on main `e397d9a6`. Neither this candidate nor its request builder grants nameserver write authority. Native SQL, packaged browser, upgrade/recovery and release acceptance remain pending; published 1.25 remains immutable.

## Owner screen and persistence

After preparing registered domains and assigning one to an active client, an owner can explicitly enable nameserver observations and refresh the selected assignment using their current password. Existing POST routing checks CSRF before dispatch; owner authorization also rejects support sessions. Setup creates two dedicated tables and its own audit/markers, without enabling unrelated renewal-date storage or changing base installation behavior. Recognized empty interrupted setup can resume; lost established history and unknown storage refuse recreation.

Each refresh persists its original intent and exact registrar source/link/profile binding before resolving credentials. The shared executor rechecks authority before sending and before persisting the result. Typed results, hashes, counters and corresponding audits preserve the original history. Credential, owner, assignment or source changes invalidate the in-flight read. Network execution stays outside the database transaction.

The owner screen displays a current list only for a fresh successful observation. Empty successful lists are explicit. A newer failed or unfinished attempt suppresses the old current value; prior values remain dated history only. Changed assignments are labeled, history is paginated at 20 entries, and its selected page never selects the current observation. Full integrity validation still inspects retained history. Passwords remain blank and private binding data is never projected into the screen. A saved set does not confirm propagation or eligibility to change it.

## Observation implementation checks — 2026-10-18

Applied default/minimal PHP 8.4.25 checks pass 144 reader, 230 operation, 64 registrar and 42 actual renderer outcomes each, totaling 960 named executions, plus 22 touched-file syntax executions. Evidence is retained at `work/registered-nameservers-application-integration-checks/`. Independent source receipts: native proposal `d47ac3e5481b3a588fdbbb24ff7e3b400b6b1e8c47ed8d0e911d0d3cd97a81c2`, native review `e74c3c571271c570d23110ca50d8ffde6db541e6b0071d025e0d2718ed1083ec`, UI review `1ff474f4fc70102f253898e2f4cae7b5eb38c5c75a7cf6835b6ab9476433ef10`. RNS-UI-1 was a missing synthetic site URL in the renderer test. Its original exact failure is retained; the one-line fixture correction closes it without changing shipping behavior.

The existing registrar workflow adds default/minimal reader, operation and view commands and one isolated Linux native fixture, with explicit public JSON artifacts. The four PHP 8.2/8.4 and MariaDB/MySQL environments remain. Its timeout increases from 12 to 20 minutes for genuine cooldown waits, preserving original tests and other settings. The Linux fixture uses disposable `cwp_test`/`rnsci_` storage, actual owner wrappers and synthetic fixed GET transport; it has not yet executed at this source checkpoint. Windows permission checks are not bypassed. Full application/report wiring, actual packaged HTTP/Chromium and published-baseline upgrade/recovery are still required.

Canonical installer refresh completed, including shipping PHP/JavaScript and dependency checks. A separate synthetic local Chromium layout probe passes 12 checks at 1280px and 320px using the actual PHP renderer and all four admin stylesheets; both complete captures were inspected. This does not exercise authenticated HTTP, database setup or a provider call. The first external layout probe measured scrolling before completion and omitted two admin stylesheets; its failed reachability outcome and the intermediate unsettled captures remain retained. Correcting only that external fixture leaves all 12 assertions and shipping files unchanged.

The following sections retain the earlier boundary evidence and its constraints.

## Supported evidence and route

The retained owner SDK example and [20i API module README](https://packagist.org/packages/20i/api-modules) select a numeric domain ID from `/domain`, read `/domain/{id}/nameservers`, and pass the complete result list as `old-ns` alongside desired `ns` on that same route. The retained SDK source is commit `696a5f1299cd2b1e67ae7f688d644ac800f41b67`; README SHA256 `9de7dbce74bcd6c1d203b2f5eef2d01b2629de00c884ade28a3cf8570616dbbf`. Root checked the public package reference on 2026-10-18; it supplies the same example, not additional response or partial-edit semantics.

The [owner reference](reference/20i-owner-api-34e713-redacted.txt) separately documents additions/removals for `/package/{packageId}/domain/{domainId}/nameservers`. Those package-route rules do not establish registrar behavior. Review RNS-1 rejected the first proposal's reuse of that delta construction. The original proposal, review finding and test results remain preserved outside the checkout; only the corrected full-set subset is integrated.

## Current code

`foundation/twentyi-registrar-nameservers/` reuses the exact registrar link/profile/credential/inventory binding validation from the domain-contract reader, with a distinct observation scope. A binding records provenance; it is not current user authority. The reader performs one fixed numeric-ID GET and projects only a bounded, sole `result` hostname list. Unknown shapes, duplicate keys, invalid names, credential echoes and transport failures remain unavailable. An empty observed set is not permission to remove all nameservers. Neither names nor package IDs can replace the registered-domain ID.

`RegistrarNameserverChange` adds the separate `registrar.nameservers` operation. It serializes the entire nonempty desired list and entire nonempty observed list only when they are disjoint. Hostname validation is shared; package `between`/`delta` subtraction is never used. Overlap, empty current/desired, add-only, remove-only and no-op inputs are refused. Partial changes remain unsupported. Local hostname/body limits are structural safeguards, not registry allowances.

The fixed transport admits only the exact numeric registrar GET/POST paths and canonical bounded body. Generic operation approval remains denied. The existing classifier and gateway remain unchanged: a nameserver response is uncertain, and the same idempotency key cannot resend after an uncertain result or missing terminal record. A later read may observe the desired set without proving that a particular write caused it.

Canonical shipping mirrors and installer inventory include the new boundary. Existing package-domain behavior and all previous operation assertions remain intact. That earlier boundary commit had no schema, controller, portal or owner authorization changes; the observation candidate above now adds its own owner lifecycle.

## Verification and limits

Actual applied-source PHP 8.4.25 default/minimal checks pass 144 focused nameserver, 230 operation, 64 registrar-reader and 87 domain-contract cases in each mode. These use synthetic transport, modeled ledger and real pure reader/builder/gateway bodies; they do not contact 20i or use SQL. All 19 proposal files pass syntax checks in both modes. The unchanged application acceptance helper passes 221 outcomes.

The focused `twentyi-operations.yml` workflow retains its PHP 8.2/8.4 matrix, original commands, network-disabled containers and artifacts, and adds two nameserver reports. Its first exact-source run remains pending. Full application workflow/report integration belongs with the following native observation lifecycle; it is not claimed complete by this focused boundary test.

The initial patch transport encoded one non-ASCII negative-test literal incorrectly; actual integration failed at JSON encoding before completing its assertions. The failure and wrong bytes are retained under `work/registered-nameservers-integration-checks/initial-patch-encoding-failure/`. Restoring the exact frozen UTF-8 test bytes made all 19 applied files match their reviewed hashes and the affected tests pass. No product behavior or assertion was weakened. Frozen v2 manifest SHA256: `85978076a52b9f2296875fe996bc674130793af60b9c645c046ce509b884b38f`; independent correction review: `277bb3cb7b9c041e55398d36dea16e85a23b622d111bc317fae58d8c119e3f86`.

## Remaining acceptance and later writes

The owner-only read lifecycle described above is now implemented. Run its first focused native matrix, then add real packaged HTTP/browser checks and full application/report integration. Admin output describes observations, not verified registry configuration.

The reviewed application map reuses `provider_inventory_refresh_attempt` and the registrar/domain-contract provenance helpers. It must adapt rather than directly reuse `domain_contracts_binding` (which requires unrelated renewal-date setup) and `domain_contracts_intent_order` (which hardcodes that feature family). Generic inventory helpers that select finished or old successful reads cannot represent the newest pending/failed nameserver intent. The implemented first slice has separate `registered_nameserver_bindings` and `registered_nameserver_sets` tables with explicit owner preparation, service/storage code, and a native fixture. Its original design is retained as planning evidence, not test acceptance. The external map is pinned to e397 and 30 existing source files, receipt SHA256 `c43c7d4d9910aec7acf9b157109b03a3571241f40fce2bb69712655c51948f03`.

A later write slice must separately bind the numeric ID and prove `old-ns` equals the entire current retained observation, show the supported change for owner confirmation, persist a reservation before one send, and fence unresolved profile/domain operations. A fresh matching-context readback may record observed desired/different/unavailable without automatic retries or causal claims. Generic caller arrays never establish observation completeness or authority.

Native database/concurrency, packaged forms, supported upgrades/recovery, full application acceptance and release/download verification remain required. No provider write, purchase, charge, customer mail, SSH or live deployment is performed by this increment.
