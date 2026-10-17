# CajunWebPro current handoff

## Active non-20i increment — folder-scoped mailbox browsing

`devin/non20i-mail-folders` stacks on `devin/non20i-mail-send-attachments` (`213325a4`) and delivers the remaining A-06 folder gap: header and exact-message reads can target any wire-safe server folder (`ReadSession::folder` — printable ASCII ≤64, no quote/backslash/wildcard; `EXAMINE "<name>"` stays read-only). `mail_inbox_reads` gains an additive `folder` column (`DEFAULT 'INBOX'`; storage version 2; version-1 installs ALTER through the same owner-gated `mail_inbox_setup`), `mail_message_reads.folder` journals message/attachment reads, and the transport's claimed folder is verified against the request on every path. A bounded select offers common folders plus the account's configured Sent folder. Bell notices and protected copied-draft origins remain INBOX-bound, so non-INBOX message pages hide Reply/Reply all/Forward. Pure session suite 53 checks; native/HTTP cases extended; `EXAMINE "Sent"` and missing-folder `NO` covered by the synthetic TLS fixture.

Next: push, open stacked PR on `devin/non20i-mail-send-attachments`, update #157, then continue remaining non-20i items (HTML composition, OAuth, other-folder notices, external flag sync remain unscheduled).

## Release 1.25.0 priority — 2026-10-16

New20i capabilities and nonessential refactors are frozen until1.25.0 publication and all-asset download verification. Canonical [codex/release-1.25.0](https://github.com/afterburn25/Cajun_Web/tree/codex/release-1.25.0) is published at `b98b96eb2a33e4bdeb72e15217213431885aad67`, normally merging current main35d8 and mature3fa. The [frozen scope](https://github.com/afterburn25/Cajun_Web/blob/codex/release-1.25.0/docs/RELEASE_SCOPE_1.25.0.md) and [integration map](https://github.com/afterburn25/Cajun_Web/blob/codex/release-1.25.0/docs/RELEASE_1.25_INTEGRATION_MAP.md) are also copied into main for discovery. The owner wants the mature increment now, without waiting for complete20i.

Root and agents are closing existing licensing defects, extending real populated1.24 root/subfolder client-data upgrades, and preparing complete PHP8.2/8.4 × MariaDB10.11/MySQL8.4 application/browser/update runs. Devin continues separate non20i work; Codex owns the narrow release licensing corrections and shared seams. Proposed PRs are not automatically included. Unfinished branches and original failed evidence remain preserved.

Merge checkpoint passes canonical installer generation, all shipping PHP/JavaScript syntax, documentation and exact2886-file staged preservation checks. Imported3fa focused38933590118 passes11 reports/1440 executions in each of4 environments and its Windows packages. These are scoped results, not combined release acceptance. Exact-candidate full checks/images, independent review, normal main merge, exact-main full checks, packages, publication and download verification remain required. Published1.24 is unchanged; no1.25 release or live deployment exists.

This main checkpoint changes documentation only; runtime remains35d8. Follow the release branch for implementation and acceptance. Earlier current-status labels below are historical where superseded.

Previous checkpoint: https://github.com/afterburn25/Cajun_Web/blob/35d8fb7efc94f6f851399d0755fa93097f1fce66/SESSION_HANDOFF.md
