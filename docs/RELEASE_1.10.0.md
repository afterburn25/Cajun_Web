# CajunWebPro 1.10.0 release scope

Candidate bounded P-07 workspace activity from main `552ccb7d8c73194e7304af4f903074b0032e53b2`, branch `codex/workspace-activity`, contract `a8c28d2`. Exact accepted source, CI and public download verification will be recorded after the gates pass.

Client/staff centers project retained messages, reviews, private files, invoices, payment recordings/corrections and published website releases. Per-person read/unread state, bounded reviewed-page actions, exact authorized source links, paginated history and private unread bells are included. Staff get their own workspace bell on public pages; background polling preserves idle expiry. Source ownership and grants are checked on each request. Support cannot read or mutate client activity. No business-write hooks or copied content.

Acceptance requires isolated MariaDB/concurrent tests, real HTTP/Chromium, anonymous/cache/role/client/support isolation, CSRF/XSS, failure recovery, passive polling, exact old-source navigation, fresh installation and checksum-pinned retained 1.9.0 UPDATE. All eight release assets must pass draft/public download verification against exact-main CI.

Full P-07, SMTP dispatch, preferences, hosting/ticket/renewal/stage events, A-06–A-08 multi-account webmail/templates/mail bells, actual merchant acceptance/card checkout and broader 20i/VPS SSH remain incomplete. No customer deployment, mail, payment or provider operation. [Setup and limitations](WORKSPACE_ACTIVITY.md). Devin PR #7 remains independent and preserved.
