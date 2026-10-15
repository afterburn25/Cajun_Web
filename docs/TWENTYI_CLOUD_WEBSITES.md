# Cloud website creation — development scope

Owner-authorized parallel work on `codex/twentyi-vps-controls`, starting at `0ebc46237fce565005b581fe5a96a2691b9840b5`. This increment adds a native owner “Add website” form for an existing explicitly client-linked cloud server. It does not create or purchase a server. Root Codex retains integration and release ownership; published 1.24.0 is unchanged.

## Provider contract

The [official Cloud Hosting API guide](https://docs.20i.com/managed-cloud-servers/api-cloud-hosting), published/updated 2024-02-13 and inspected 2026-10-15, documents POST `/cloud_server/{cloudServerId}/addWeb`, with `domain_name` and `extra_domain_names`, returning an object containing the allocated package ID in `result`. No account entitlement, server capacity, billing term or live completion is inferred from this example. The current implementation accepts positive bounded integer IDs and canonical ASCII domain names, with at most 20 distinct aliases as a local application limit.

`CloudWebsiteCreate` validates the complete request body and exact fixed-host transport route. No generic GET or arbitrary cloud endpoint is admitted. A returned package ID is `accepted_pending`, not an active client service. Unknown responses, timeouts and incomplete history remain uncertain. Server power, rebuild, console, snapshots, creation/renewal/cancellation, automatic capacity discovery and SSH remain unfinished. VNC must never be described as SSH.

## Native workflow and retained authority

The owner uses the existing provider page after preparing existing inventory and operation storage. A current explicit cloud/client assignment and the latest complete cloud refresh from the last 15 minutes are required; a newer failed or unfinished refresh cannot fall back to older successful data. The owner enters the primary domain and optional aliases, confirms domain permission and server capacity, and supplies their current password. This review is manual, not a claim of automatic entitlement verification.

One random form request ID creates a consumed approval in the existing `provider_operation_grants` table. Its installation-keyed scope proof binds the original assignment ID/revision/client/server, profile revision, credential context, exact observation and owner authentication version, plus reviewed request fields. The strict audit is atomic with approval. No new ledger or schema is introduced. Missing approval history compared with its retained audit prevents a new attempt. Independent root review then exposed paired approval/start-audit loss; the correction also checks surviving shared cloud results, unresolved cloudsite reservations and result audits for missing approvals. Preparation refuses a missing version marker with surviving operation/cloud audit or populated-table witnesses before DDL.

DNS precedes secret resolution and runs outside the staff/database lock. Current authority, client activity, cloud assignment, settings, approval expiry/revocation and credential context are checked again before credential access and transport. The existing `ProviderGateway` and `ProviderOperationLedger` reserve the request before sending. Duplicate original forms never resolve DNS or send again. A fresh nonce cannot recreate any overlapping domain after an accepted, uncertain or interrupted request. Only a definite failed outcome permits a fresh, separately reviewed request. Known pre-send failures record zero attempts. Raw API keys, passwords and provider responses are never retained in approvals, audit, result history or views.

Owner history retains the returned package ID and directs an explicit hosting inventory refresh and exact package/client link. It does not silently assign the returned package, activate a hosting subscription or fabricate order/billing evidence. The signed-in assigned client sees a safe request summary on the existing cloud card. Another client, a removed/reassigned connection and support selector receive no request details. Support access remains a separate workstream.

## Verification and handoff

Local PHP 8.4 default and no-INI each pass 48 named cloud contract/fixed-transport/shared-file-ledger checks, 230 existing operation checks and 95 mailbox checks. Changed PHP syntax and Python AST checks pass. The first installer refresh exposed a Windows UTF-8 encoding error in one inserted display separator; it was corrected without changing other source text. Installer regeneration completed successfully, and all2662 staged dependency hashes were verified. [Draft PR143](https://github.com/afterburn25/Cajun_Web/pull/143) preserves implementation7e742c3b.

Prepared native tests use actual disposable MariaDB/MySQL tables and the real shared gateway with synthetic provider transport. They cover owner/client/support authority, complete/fresh inventory, interrupted refresh refusal, atomic audit, real two-process same-request concurrency, exact replay and conflicting request IDs, duplicate domains, pending IDs without automatic linking, unknown outcomes, known pre-send failures, DNS-time revocation, actual PHP form rendering, client isolation and missing history. Packaged HTTP/Chromium cases exercise typed owner submission, safe client status and desktop/mobile screenshots against loopback TLS only. These new native/browser cases are not claimed passed until the exact CI reports are inspected. Windows private-key permission validation remains unchanged; native provider acceptance runs on Linux.

Next: inspect exact branch native and packaged reports, fix failures, independently review and merge sequentially with mailbox/catalog/domain branches, regenerate combined installer inventory, and perform exact-main release gates. No live API call, server action, SSH, payment, customer mail or deployment was performed.

## Verified implementation checkpoint and recovery review

All four native combinations at7e742c3b pass31 named checks in [run38819055916](https://github.com/afterburn25/Cajun_Web/actions/runs/38819055916). Downloaded artifacts and SHA256:

- PHP8.2/MySQL12012128129: `fe87c7991bdf783ffcf9b017921f15dc89fdff1cbc303847de23ebff019b7721`.
- PHP8.2/MariaDB12012093111: `5cc8e1a4ba3174fe25515cc0d10604b54c250ba6589457dabaec39964eebc3d9`.
- PHP8.4/MariaDB12012083285: `960599c8eed33b8e93751ddb676a11c35096b72043287a9d3287f2be37995d66`.
- PHP8.4/MySQL12012098233: `161246d1e65643811f83032d53d687d6e5823d1d9f9ee1162ebe48be5634f09f`.

[Boundary38819055822](https://github.com/afterburn25/Cajun_Web/actions/runs/38819055822) passes48 cloud +95 mailbox +230 operation cases per default/no-INI in PHP8.2 and8.4. Verified artifacts12012636495 SHA `f8782989db7ae5a29a8f125c2c146d0734c6cab23321bafa07311a8ea6243f15`,12011503289 SHA `8f52597ba61a18bbadc039939de0fe5a34f54c94f6b16f31ee06183d17454bb2`. All archive CRCs and named outcomes verified. Local proofs are `qa/cloud-native-initial-verification.json` and `qa/cloud-boundary-verification.json` in the isolated worktree.

The later4aa68171 adds process-concurrency coverage; independent review identified the paired-loss gap and the enclosing correction adds ledger/audit/preparation witnesses plus regressions. Earlier green results are preserved but do not prove the corrected head. Full4aa38819232463 and native38819232444 remain live. Corrected-source native/browser acceptance and root review are required before integration/release.

2026-10-15 cloud preparation review correction, starting `2febba4eef5f658a46de33b9b905108d41cb47d6`: `provider_operations_migrate()` now rejects an inherited database transaction before acquiring the staff lock or performing DDL. The native cloud fixture checks fresh preparation creates no table/marker and preserves rollback of an unrelated sentinel write. This closes the implicit-commit risk without changing storage versions or recovery records. Changed PHP syntax and documentation checks pass; installer refresh is included. Corrected-head Linux acceptance remains required. Next: verify the new native and packaged reports, then integrate through the parent branch.

Concurrency checkpoint: all four PHP8.2/8.4 and MariaDB10.11/MySQL8.4 combinations at `4aa68171ba921473ebadc46f5e71670ad37ea035` pass33 named native checks in [run38819232444](https://github.com/afterburn25/Cajun_Web/actions/runs/38819232444), including the two-process duplicate form test. Downloaded artifacts12012845114,12012602701,12011619593,12011504623 have verified published SHA256, valid ZIP CRC and all named outcomes; exact digests are retained locally in `qa/cloud-concurrency-verification.json`. This predates the paired-loss and transaction-entry corrections and does not certify those later changes.

The recovery query at2febba4e failed native MySQL8.4/PHP8.2 run38820482050, job116715582421, with an illegal mixed-collation CONCAT. The enclosing correction casts all audit-prefix/ID/suffix operands to binary, retaining exact request-ID matching on both database engines. The failed report is preserved; later acceptance must verify this compatibility correction.
