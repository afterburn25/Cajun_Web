## Catalog acceptance and discovery intake — 2026-10-15

Catalog source `8f7028dbbc23d9926d7d0ebde8d2f6b5e6861e98` is independently verified: full run 38819685543 passes 1,794 HTTP/Chromium, 289 recovery and 1,827 Apache checks; native run 38819685552 passes all four combinations with 43 catalog cases within 508 combined recurring/order cases and 51 pure cases per PHP mode. Downloaded artifact hashes/CRCs, 11 distribution checksums, three manifests and exact source members agree. [Detailed evidence](docs/TWENTYI_PLAN_CATALOG.md).

New documentation branch `codex/twentyi-catalog-discovery-contracts` starts from and preserves that exact controls branch. Primary-source intake confirms endpoint names but still lacks packageTypes/account capability/balance response contracts; [the exact missing fields and next action](docs/TWENTYI_DISCOVERY_CONTRACT_INTAKE.md) are recorded without inventing a reader. Root separately owns combined candidate `38bb69cc` and its own acceptance. No runtime, installer, release or live-operation change occurs here; published 1.24.0 remains unchanged.

## Catalog withdrawal recovery correction — 2026-10-15

Starting41123e9b5b95f8bbe4244ee5db64a15eb3a85a58, PR141: source review found that losing a latest withdrawal record together with its matching audit could make an older enabled mapping appear current. Added an independently retained per-plan latest ID/digest pointer in settings, saved atomically with every review. Exact pointer/history checks and plan-family counts refuse missing/older review histories, including loss of both withdrawal and audit. Native fixtures cover paired loss, refusal of new approval, missing pointer and exact restoration. This strengthens recovery; it does not add provider execution. Local syntax and51 pure checks pass; current native/full acceptance remains pending. Preserve parent41123 full38819193594 and native38819193595; do not replace or cancel those runs.

# Catalog native evidence and review follow-up — 2026-10-15

PR141 parent24e75ca6 is verified at36catalog checks within501combined native checks in all4combinations; actual artifact hashes/CRCs/reports retained. Descendant repairs independent redisplay/recovered-format findings and adds actual browser coverage, pending current acceptance. [Exact scope and artifact evidence](docs/TWENTYI_PLAN_CATALOG.md). Root retains integration and release ownership.

# Catalog review correction — 2026-10-15

Starting24e75ca646f13852b54ea148966048ebdec66bec, PR141. Root independently found that rejected oversized/array catalog fields could throw again during redisplay, and malformed recovered association JSON could access format before validation. Corrected both: rendering uses a bounded nonthrowing UTF-8 projection, while association/catalog document validation normalizes malformed history. Added four native malformed-render cases and actual HTTP/Chromium setup, review, rejection, replay and catalog-backed existing fulfillment coverage. Parent native38818360818 has successful PHP8.2/MariaDB and PHP8.2/MySQL jobs; remaining jobs/artifact verification were pending when this entry was written. Local51 pure checks per PHP8.4 mode and changed PHP syntax pass; exact descendant native/browser verification remains required. All runs and previous releases are preserved.

# Parallel catalog increment — 2026-10-15

The owner requested parallel 20i agents. The catalog agent owns `codex/twentyi-catalog-controls`, parent `0ebc46237fce565005b581fe5a96a2691b9840b5`; root retains mailbox implementation and integration/releases. Main `b66062e4` was inspected and not merged into this isolated branch. [Catalog scope and continuation](docs/TWENTYI_PLAN_CATALOG.md).

Adds immutable owner-reviewed provider mappings on the existing retail plan screen, private exact decimal cost/currency/period evidence, current account/type/limits/defaults review, expiration/withdrawal, and catalog-backed existing hosting preparation. Original retail consent, commercial eligibility, fulfillment engine and histories are preserved. No automatic discovery or provider entitlement is claimed. Additive native storage, route/forms, historical association provenance and focused tests are included; installer inventory is regenerated.

Local PHP 8.4 default/no-INI each pass 51 named pure document checks; changed/new PHP syntax passes. Native catalog/order fixtures are written but await Linux CI; Windows vault protection was not relaxed. Current browser/package/independent review gates remain pending. No live provider operation, charge, customer mail, SSH, deployment or release occurred. Next: inspect exact branch native matrix and fix any failures, then root integrates with other parallel branches and performs combined acceptance/review. Published 1.24.0 remains unchanged.

# Current development — native mailbox creation boundary

Codex owns `codex/twentyi-mailbox-controls`, starting `c00874b1` above client-search PR139. Added exact documented mailbox creation request/response validation inside the existing provider adapter/gateway/ledger and fixed transport. Generic grants/default adapter cannot execute it. Raw mailbox passwords are resolved transiently and echoed response passwords are discarded; replay and unknown outcomes cannot resubmit creation. [Implementation, tests and required native continuation](docs/TWENTYI_MAILBOX_CONTROLS.md).

Local default/no-INI PHP8.4 passes95 mailbox cases and230 existing operation cases in each mode, plus fixed-transport regressions and changed syntax. Installer refresh49660 completed successfully and is closed. Native storage/authority/forms and browser/live acceptance remain unfinished. No live provider operation, customer mail, deployment or release occurred.

Parent b09b combined38812924437/job116690370817 is in browser; f86 full38811407918/job116685268807 passed recovery and is in Apache. Both are confirmed live and must be preserved. Reviews138/139 remain empty. Freshly fetched main is now `b66062e4cc3e2e670ba1c37d4ae902de2b630c30` (licensing audit tests), after merged16f07e49; preserve and inspect this later normal merge without interrupting current acceptance. Published1.24 remains unchanged.

# Current checkpoint — verified client-search browser and merged Windows packages

Starting source `b09b79278d1a459e4235f9a9431031f53abf6334`, draft PR139. Parent f86 actual browser artifact12009795846 verifies1,774 named checks, empty unexpected diagnostics and inspected desktop/mobile client screens. Current b09b four native artifacts verify106 combined checks including50 client cases each. Exact b09b Windows build86750 is complete and independently verified:11 checksums, FULL5417/COMPLETE2659/UPDATE2646 exact source/member bytes. [Detailed evidence](docs/TWENTYI_CLIENT_DOMAIN_SEARCH.md).

Current combined application38812924437/job116690370817 remains live in native integration; parent f86 application38811407918/job116685268807 remains live in recovery. Preserve both. Independent review remains required and earlier write rejections must not be bypassed. Published1.24 is unchanged. Continue native hosting controls using the existing provider gateway/ledger and documented mailbox creation contract; no live provider operation is authorized by this checkpoint.

# Current development: combine client domain search with latest main

Codex retains complete20i P0 ownership. [Draft PR139](https://github.com/afterburn25/Cajun_Web/pull/139) / `codex/twentyi-client-domain-search` now normally merges client-search parent `f86c745c982ae1daedc1a7210bcf91052e4f9c49` with current main `16f07e493bd21b5b0570880cfa4faa5782db4c5e`. Both histories and original20i ownership are preserved. Published [v1.24.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.24.0) is unchanged; no release/deployment is claimed.

Incoming main adds licensing validation status codes, domain equivalence/replacement and portal/admin controls. Integration inspection reproduced an actual rollback defect: invalid replacement or failed insertion disabled the original domain. The replacement now locks its original row inside its own transaction, commits both changes together and rolls back failures. Caller-owned transactions are refused without mutation. Actual local MariaDB reproduces both original failures and passes4 corrected cases; the merged native licensing suite passes35 named cases using an isolated database prefix. No Website_Core source or installation was executed. [Integration repair](docs/LICENSING_IMPLEMENTATION.md).

All changed/merged PHP and Python syntax checks and installer refresh pass. New native regression reports are retained by CI. Combined current-head native/browser/recovery/review acceptance remains required. Client-search f86 full38811407918/job116685268807 has passed packaging/native and is in HTTP/Chromium; preserve it. Its earlier source1fbeb has downloaded/verified four native combinations (106 combined checks including50 client cases each) and exact Windows packages. [Client-search scope](docs/TWENTYI_CLIENT_DOMAIN_SEARCH.md).

Earlier hosting sourcee375 now has fully downloaded/verified application acceptance:61 named-check reports, including1737 HTTP/Chromium,289 recovery and1787 Apache checks; no aggregate total is inferred from overlapping subsets. All11 package checksums and archive/source/update bytes verify. Windows/Linux FULL and COMPLETE ZIPs are identical; UPDATE differs only in its recorded Git/test-merge source. Parent5bec separately verifies1738 browser checks and inspected corrected active-service screenshots. [Exact evidence](docs/TWENTYI_ORDER_FULFILLMENT.md).

Next: push this normal integration and inspect its own combined checks, finish client screenshots and independent review, then exact-main release verification. Full20i catalog/configuration, complete commercial fulfillment, hosting controls, registrar commerce, VPS/cloud, provider support and interactive SSH remain required. No live API operation, payment, customer message, SSH or deployment occurred.
