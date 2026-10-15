# Project status — complete 20i P0

## Native domain search checkpoint — 2026-10-15

Continuing complete 20i P0 from `6d3592a9dc9faeffd8bc86563a40c299066bf098` on `codex/twentyi-domain-search`. The reader workflow [38751661767](https://github.com/afterburn25/Cajun_Web/actions/runs/38751661767) passed on PHP 8.2/8.4; its artifacts have not been independently downloaded in this restricted session. Earlier local parser evidence remains 77 named cases per default/no-ini run.

Added typed owner configuration and lookup forms on the existing 20i page, locally configured extension filtering, immutable request/outcome history, a profile-wide 30-second cooldown and 20/hour limit, and a durable 24-hour pause after a 429 response. Configuration is explicitly enabled by an owner with their current password and bound to the saved connection revision. Routine lookup uses current owner permission and the existing fixed transport; there is no separate execution engine. Stale/revoked access is checked before credentials and after results. Additive schema preparation refuses missing stamped history or unexplained populated tables; preparing or viewing never contacts 20i.

Native MariaDB/MySQL tests and real PHP view rendering cover permissions, stale forms, filtering, failed/partial responses, revocation around networking, audit rollback, incomplete intents, shared limits, backoff and history recovery. The new workflow runs PHP 8.2/8.4 with MariaDB 10.11/MySQL 8.4 and checks deployment hashes/documentation. **This new code has not yet been executed; CI is the next gate.** There are no current HTTP/Chromium, concurrency-worker, independent-review or live-account acceptance claims. Client/public search, periods, verified pricing and purchase/transfer workflows remain unfinished.

This remote checkpoint preserves local work unchanged while filesystem writes and shell networking remain unavailable. Current main c42dd6d7 and published1.24/7190b582 are unchanged. Cloud #127 still needs current screenshots and package comparison before exact-main1.25 release gates. Billing #135 full run38750019637 has passed native, browser and recovery steps and is still running Apache checks; retain its source35a564a1. Review-request comments and PR metadata updates remain blocked by approval policy. The complete owner API source remains a previously requested input.

Next: inspect/fix native domain-search CI, add packaged HTTP/Chromium and concurrent-worker cases, then independent review and integration with cloud/billing. Keep P0 scope active and continue commercial/domain/native hosting/VPS/support/SSH workflows. No production call, charge, mail, SSH or deployment occurred.

