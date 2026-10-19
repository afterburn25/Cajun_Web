## Later change candidate — 2026-10-18

The separate [owner-approved nameserver change candidate](TWENTYI_REGISTRAR_NAMESERVER_CHANGES.md) extends this read-only fbe history. It adds complete-set approval, durable dispatch/fences and explicit readback; native/browser/update/release acceptance remains pending. The earlier no-setter statements below describe their named read-only source checkpoints, which remain preserved.

# Registered-domain nameserver observations

## Packaged acceptance producers — 2026-10-18

Starting from `2c5898fa665252fdb92bd2d2c91f8786fcea143c`, the enclosing “Exercise nameserver forms and preserve history through updates” commit adds the missing packaged acceptance producers. Application behavior and VERSION are unchanged. Later sections retain the preceding implementation checkpoints.

`tests/registered_nameservers_cases.py` runs last in the existing browser sequence. It creates a separate owner through real invitation/activation forms and uses a previously unused synthetic numeric registrar ID. Actual CSRF/password forms prepare storage and issue fixed numeric GETs through the existing loopback TLS provider. It tests incorrect bindings, staff/client/support denials, private paths, absence of setters, and latest failed/unfinished masking of older history. An actual post-GET audit trigger failure supplies the unfinished case. Shared cooldowns use elapsed time; inherited timestamps and rate rows are not reset. It declares 62 HTTP outcomes and eight scoped desktop/mobile captures; those application outcomes have not run locally.

`tests/registered_nameservers_upgrade.py` installs the immutable published1.25 COMPLETE archive (`bebe9a62e36a7b1898baa95babd4f03b2e438a69b48ab42eedcda0f9dd443e39`, 2,895 members), then exercises the current UPDATE through seven actual owner actions. The target remains version1.25.0, so exact Git/archive/member identities distinguish builds. Setup happens explicitly after update. Hash-only snapshots preserve original rows, private files, bindings, saved sets and audits through retries and complete-journal rollback/reapplication. A deliberately removed set must stay refused after further file recovery. This one subfolder/rewrite producer adds no Chromium capture and does not replace the older upgrade matrix or interrupted-update tests.

Published1.25 input preparation and the fixed raw-archive allowlist extend the existing1.24 path with both immutable member checks retained. Five native commands extend152 to157; two Apache commands append to the existing four. Required reports become169 for browser/checkpoint and215 for final aggregation. Whole-file inverses preserve original driver/helper behavior; the old negative example now refuses the unapproved1.26 archive instead of the newly approved1.25 input.

Actual applied local validation passes271 acceptance-helper outcomes,20 portable upgrade guards,19 real published1.24 input guards, and four PHP syntax executions. Canonical refresh and all shipping dependency/PHP/JavaScript checks pass. The browser proposal separately passed56 portable response/clock/inverse guards; the baseline proposal checked both retained public ZIPs. These counts are not native or browser acceptance. Final reviewed source receipts: browser `0e5002f0d42fe8aef0056a12588fc2a0266783a082d6663490906ce24d13d02c`, installed update `45d17802cf5c73c9e86bc0a56a23ab77c42d74cad26c8efd3d2b7f93860bc76d`, combined wiring `80434e2f2a2f1db030774d7ae63c005e65fbfbc1fb2c51584c5e5fb1b7460ac2`.

Source review corrected two upgrade-fixture errors: internal database port is not the CI host-mapped port (RNS-UPGRADE-1), and DNS occurs outside the intent mutex while transport authority is rechecked under it (RNS-UPGRADE-2). The first issue also existed in the committed native fixture; only its invalid equality is removed. A draft browser identity collision and shared acceptance composition conflict were corrected before final application. Original bytes, failed portable authoring/sealing probes and their finite corrections remain outside the checkout. Original queued CI evidence is not cancelled or reclassified.

First Linux native matrix, actual packaged HTTP/Chromium, installed update/recovery, full aggregation, capture review and eventual release verification remain required. No provider write or live deployment is enabled.

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
