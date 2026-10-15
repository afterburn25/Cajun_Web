# Parallel 20i work — 2026-10-15

The owner explicitly requested simultaneous agents to speed up implementation. Three additional agents are running in isolated worktrees from `0ebc46237fce565005b581fe5a96a2691b9840b5`; root continues mailbox creation and coordinates integration, review, testing and releases.

| Worker | Branch | Current bounded increment |
|---|---|---|
| Root | `codex/twentyi-mailbox-controls` | Native mailbox permissions, creation forms and durable history |
| Catalog | `codex/twentyi-catalog-controls` | Reusable reviewed provider plan mappings, separate provider cost/retail terms and hosting selection |
| Domains | `codex/twentyi-domain-controls` | Verified registrar inventory and explicit client links, separate from hosted names |
| Cloud | `codex/twentyi-vps-controls` | Documented website creation on an explicitly linked existing cloud server |

Each worker owns its checkout. Shared changes integrate sequentially with current combined acceptance. Source presence and agent reports are not completed acceptance. Automatic catalog discovery, full domain controls, server ordering/power/console and SSH remain unfinished. No agent is assigned live provider writes, charges, mail, SSH, deployment or release publication.

Root's new mailbox implementation includes additive immutable policies/requests, real owner/client controls, safe history, current resource/session checks and the existing shared gateway/ledger. The password is transient; a request-bound keyed proof detects changed-password nonce reuse. No recoverable password is stored and unfinished requests cannot automatically resume. A local daily request allowance does not establish provider quota or cost.

Local changed PHP/Python syntax, 95 synthetic mailbox checks and 230 retained operation checks pass. New real-database and packaged HTTP/Chromium tests are prepared, but have not passed yet. Windows native execution retains the strict key-file permission refusal; Linux CI is required. Next: push the native increment, inspect its own native matrix and browser results, repair concrete failures, then review and integrate agent branches.

Published v1.24.0 is unchanged. Parent 0ebc Windows build59233 completed with exact source/member verification:11 checksums, FULL5421/COMPLETE2660/UPDATE2647. Its focused run38815461691 artifacts12010666737 and12009578966 verify95 mailbox plus230 operation cases each in default/no-INI modes. Parent full38815495066 remains in progress. These are parent-only results, not native mailbox acceptance.

Parent client-search b09b browser artifact12010656349 verifies1774 HTTP/Chromium and35 licensing checks with clear unexpected diagnostics; actual desktop/mobile screenshots were inspected. SHA256: `9c9e8268f68665603730c72a673106f7dff74ec43b5a6272a287d7495c249ce0`. PR merge28225dc50072921a7a3ded8b7d514302dcd22e23 has the same tree as b09b. Preserve earlier checks and mainb66062e4 for normal integration. Independent review and final release acceptance remain outstanding.
