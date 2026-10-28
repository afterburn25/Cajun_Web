# Financing mail-draft Apache upgrade correction

Date: 2026-10-28

Base: `03c4f606b5e536c7173ebf35200a40715228e527`

Focused PR: #282, exact head `d40c36711f4cd7bad0be555afc9bb59d9fc1cf15`,
tree `17e8ca5ca62cbebe153ca79dc8b5ab3ae190993c`.

Protected Application run `40684017215`, Apache job `122816732929`, passed
2,747 Apache protection/root/subfolder/installer checks. Its populated
published-v1.25 root-with-rewrite upgrade then completed all 158 functional
checks and both upgraded-client screenshots, but the final Apache log gate
found five identical PHP warnings:

`Undefined variable $mail_drafts_account` in
`site/admin/views/studio/licenses.php` line 59.

The finance-agreement sender list accidentally invoked
`$mail_drafts_account(...)` as a variable function. `financing_offers.php`
already loads the maintained `mail_drafts_account()` service. The correction
removes the stray `$` and changes no authorization, mail sending, agreement,
payment, or gateway behavior. The surrounding `try` previously caught the
resulting `Throwable`, so the page rendered with an empty sender list while
leaving warnings in the Apache log.

`tests/license_finance_source_contract.php` now requires the direct service
call and rejects the variable-function spelling. The existing populated
Apache upgrade is the behavioral regression: it must render the owner license
screen with prepared mail/draft storage and finish without PHP warnings.

The complete protected run remains open until its native/browser job reaches
a terminal state. Fresh exact-candidate Apache/root/subfolder/rewrite and full
Application acceptance are required after integration. No external mail,
gateway request, live payment, deployment, release, or main merge occurred.
