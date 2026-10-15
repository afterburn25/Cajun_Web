# Cloud website creation — development scope

Owner-authorized parallel work on `codex/twentyi-vps-controls`, starting at `0ebc46237fce565005b581fe5a96a2691b9840b5`. This increment adds a native owner “Add website” form for an existing explicitly client-linked cloud server. It does not create or purchase a server. Root Codex retains integration and release ownership; published 1.24.0 is unchanged.

## Provider contract

The [official Cloud Hosting API guide](https://docs.20i.com/managed-cloud-servers/api-cloud-hosting), published/updated 2024-02-13 and inspected 2026-10-15, documents POST `/cloud_server/{cloudServerId}/addWeb`, with `domain_name` and `extra_domain_names`, returning an object containing the allocated package ID in `result`. No account entitlement, server capacity, billing term or live completion is inferred from this example. The current implementation accepts positive bounded integer IDs and canonical ASCII domain names, with at most 20 distinct aliases as a local application limit.

`CloudWebsiteCreate` validates the complete request body and exact fixed-host transport route. No generic GET or arbitrary cloud endpoint is admitted. A returned package ID is `accepted_pending`, not an active client service. Unknown responses, timeouts and incomplete history remain uncertain. Server power, rebuild, console, snapshots, creation/renewal/cancellation, automatic capacity discovery and SSH remain unfinished. VNC must never be described as SSH.

## Native workflow and retained authority

The owner uses the existing provider page after preparing existing inventory and operation storage. A current explicit cloud/client assignment and the latest complete cloud refresh from the last 15 minutes are required; a newer failed or unfinished refresh cannot fall back to older successful data. The owner enters the primary domain and optional aliases, confirms domain permission and server capacity, and supplies their current password. This review is manual, not a claim of automatic entitlement verification.

One random form request ID creates a consumed approval in the existing `provider_operation_grants` table. Its installation-keyed scope proof binds the original assignment ID/revision/client/server, profile revision, credential context, exact observation and owner authentication version, plus reviewed request fields. The strict audit is atomic with approval. No new ledger or schema is introduced. Missing approval history compared with its retained audit prevents a new attempt.

DNS precedes secret resolution and runs outside the staff/database lock. Current authority, client activity, cloud assignment, settings, approval expiry/revocation and credential context are checked again before credential access and transport. The existing `ProviderGateway` and `ProviderOperationLedger` reserve the request before sending. Duplicate original forms never resolve DNS or send again. A fresh nonce cannot recreate any overlapping domain after an accepted, uncertain or interrupted request. Only a definite failed outcome permits a fresh, separately reviewed request. Known pre-send failures record zero attempts. Raw API keys, passwords and provider responses are never retained in approvals, audit, result history or views.

Owner history retains the returned package ID and directs an explicit hosting inventory refresh and exact package/client link. It does not silently assign the returned package, activate a hosting subscription or fabricate order/billing evidence. The signed-in assigned client sees a safe request summary on the existing cloud card. Another client, a removed/reassigned connection and support selector receive no request details. Support access remains a separate workstream.

## Verification and handoff

Local PHP 8.4 default and no-INI each pass 48 named cloud contract/fixed-transport/shared-file-ledger checks, 230 existing operation checks and 95 mailbox checks. Changed PHP syntax and Python AST checks pass. The first installer refresh exposed a Windows UTF-8 encoding error in one inserted display separator; it was corrected without changing other source text. Installer regeneration is rerun from corrected bytes.

Prepared native tests use actual disposable MariaDB/MySQL tables and the real shared gateway with synthetic provider transport. They cover owner/client/support authority, complete/fresh inventory, interrupted refresh refusal, atomic audit, exact replay and conflicting request IDs, duplicate domains, pending IDs without automatic linking, unknown outcomes, known pre-send failures, DNS-time revocation, actual PHP form rendering, client isolation and missing history. Packaged HTTP/Chromium cases exercise typed owner submission, safe client status and desktop/mobile screenshots against loopback TLS only. These new native/browser cases are not claimed passed until the exact CI reports are inspected. Windows private-key permission validation remains unchanged; native provider acceptance runs on Linux.

Next: inspect exact branch native and packaged reports, fix failures, independently review and merge sequentially with mailbox/catalog/domain branches, regenerate combined installer inventory, and perform exact-main release gates. No live API call, server action, SSH, payment, customer mail or deployment was performed.
