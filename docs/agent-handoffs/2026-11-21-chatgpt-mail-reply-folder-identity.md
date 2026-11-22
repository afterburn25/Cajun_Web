# PR #398 mail Reply/Forward exact-folder fixture correction

## Context and frozen source

- Parent: `afterburn25/Cajun_Web` PR #398 head `e83180bf22b3ee3f1586f524f77ed89e443c216e` on `codex/mail-consolidated-main1e79-20261118`; current published release remains v1.26.1.
- Parent Application run: https://github.com/afterburn25/Cajun_Web/actions/runs/44943541659 (attempt 1, failed). The native-and-browser job fails at `Loaded message retains Reply Reply all and Forward controls to the author`. The downstream `acceptance` job fails as a consequence.
- Four Combined PHP/database acceptance lanes and other separate workflow checks succeeded on that parent. Preserve these as parent-only evidence; do not relabel them as proof for the child.

## Bounded correction

`tests/mail_reply_cases.py` now defines the synthetic exact-message route with `'folder': 'INBOX'`, consistent with `tests/mail_message_cases.py` and the shipping `mail_message_route()` / `mail_message_admin_action()` form identity contract. Since the copied-message browser test constructs all its POST dictionaries with `**route`, the correction supplies the exact matching folder across the loaded-message, Reply, Reply All, Forward and refusal cases.

The previous fixture omitted the folder despite the shipping controller validating every route member, including default INBOX, against posted fields before a real message read. This is a fixture-only correction. It does not change application behavior, lower authorization requirements, relax a checked assertion, add provider/network operations, or send messages.

## Evidence and remaining requirements

- Static source comparison: one dictionary literal extended with the required exact folder field. The view already renders the three controls when permitted, with the tested no-send notice.
- No hosted run for this child is claimed here. A complete exact-child source-composition receipt/projection update, canonical source verification and independent review are still required before advancing the frozen PR #398.
- Requalify on a Linux runner with first-attempt full Application native-and-browser and aggregation, all required Combined PHP/database, specific mail native, packaged browser, security/privacy and package checks. Preserve failed parent and any new failures. Recheck current branch heads before any merge.
- Keep this as a separate draft corrective PR targeting PR #398's integration branch, not a push to its frozen head or `main`. No live SMTP, customer communication, payment, provider provisioning, deployment or release publication is authorized.
