# Review and send saved email

Released in [1.22.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.22.0) after independent review and exact-main packaged HTTP/Chromium, SMTP/TLS, Apache and actual1.21 upgrade/recovery acceptance. [Named test report](TEST_REPORT_1.22.0.md). Real mailbox and target-host compatibility remain separate.

Open **My email accounts**, choose a sending account and save a draft. Select **Review saved message for sending**. Review the saved From, To, Cc, Bcc, subject and plain-text body, check the confirmation, then select **Send email**. Unsaved edits in another tab are not included. If the saved draft or account changed, reopen the saved draft and review its current version.

Each sender needs both the global Send permission and a current Send assignment for that mailbox. Even an owner needs the mailbox assignment. Drafts and submission history remain private to their author. Revoking access hides that content without deleting it. Customer support sessions cannot send staff email.

On an upgraded installation, an owner first prepares email accounts and local drafts, then opens **My email submissions** and confirms their current password to prepare submission storage. Fresh installation prepares empty tables. Setup, saving a draft and opening its review never send mail.

The result describes what the website actually observed:

| Result | Meaning |
|---|---|
| Server accepted | The sending server accepted responsibility. Recipient delivery is not confirmed. |
| Server rejected | The server explicitly rejected the completed submission. Review and deliberately save a changed draft before another attempt. |
| Not submitted | No completed message was submitted. Review the account/message and the displayed reason. |
| Confirmation missing | The final server response was not confirmed. The message may have been accepted; do not resend it. |
| Submission unresolved | An attempt started but its result could not be saved. The message may have been accepted; do not resend it. |
| Preparation incomplete | No sending attempt was recorded. This preparation will not resume automatically. |

Opening or replaying an existing submission never sends again. Accepted, uncertain and unresolved attempts also prevent resending an edited version of that same draft. Check with the mailbox provider before taking any separate action after an uncertain result. There is no automatic retry queue or resume button.

The server supports verified encrypted SMTP on port465 (implicit TLS) or587 (STARTTLS), with advertised PLAIN or LOGIN password authentication. All recipients must be accepted before message content is submitted. Bcc addresses remain in the private envelope and history, without appearing in the visible message headers. Up to20 recipients, a200-byte UTF-8 subject and a20,000-byte normalized plain-text body are supported; message preparation can refuse content that exceeds the final wire limit.

There are no attachments, HTML messages, OAuth sign-in, server Sent-folder synchronization, delivery/read receipts or reply/forward controls in this increment. Website system-email settings remain separate. Sending limits include one attempt per mailbox every30 seconds and20 attempts per staff member per hour; they are limits, not a promise of delivery.

Back up the database and configured private storage, including the original mail and provider encryption keys. File recovery retains submission content, fences and results; it must never erase an uncertain attempt to make sending available again. [Drafts](LOCAL_EMAIL_DRAFTS.md), [accounts](ADMIN_EMAIL_ACCOUNTS.md), [storage contract](MAIL_SUBMISSION_STORAGE_CONTRACT.md), [transport](MAIL_SUBMISSION_TRANSPORT.md).
