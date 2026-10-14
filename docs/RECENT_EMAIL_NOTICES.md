# Recent email notices — 1.19.0 candidate

This feature is implemented on `codex/mail-notices` / [draft PR #72](https://github.com/afterburn25/Cajun_Web/pull/72) and is not published yet. The latest verified release is 1.18.0. Application, upgrade, browser and release acceptance for this increment remain pending.

## Enable notices

An owner of an existing installation opens **Email accounts**, prepares inbox and message reading if needed, then uses **Prepare email notices** with their current password. Preparation creates local storage without connecting to a mailbox. Fresh installation starts with empty ready storage. Each staff member, including owners, still needs global email Read access and a current Read assignment for the specific account.

Open **My email accounts**, select an assigned inbox, and explicitly load recent headers. Its newest 20 messages become the current observed window. The **Email notices** page shows when each assigned inbox was last checked, whether its latest request failed or remained incomplete, and the messages' observed read flags. Subjects, senders and message bodies are not saved in this list.

## Use the bell

The admin and public website headers show an email bell for currently authorized staff. Client-support pages suppress it; return to your administrator view to use staff email. The count describes messages in the last checked windows that were observed unread and have not been opened or dismissed by that person. It stops displaying an exact number above 99. This is not the live count of all unread mail.

Opening the bell displays your recent notices. **Open message** explicitly requests that exact message through the existing reader. **Dismiss for me** removes it from your count. These actions do not set the mailbox's Seen flag or change another person's local opened/dismissed state. Actual server flags returned by later explicit reads can update the shared observation.

The header refreshes local counts while the page is visible and focused. It does not check an email server or keep an idle login alive. To discover new messages, explicitly check the assigned inbox again. A failed header request clears the bell until access and the local endpoint become available.

## Changed or unavailable notices

An account edit, disabled account, expired or revoked assignment, or removed Read permission can make a notice unavailable. A newer inbox window may also evict it. Reload the recent notices page or check an authorized inbox again. Old forms carry both their original message identity and a separate notice identifier; they cannot silently select a replacement message.

Automatic inbox synchronization, previews in the bell, other folders, attachments, outgoing mail and server read-flag changes remain future work. Real mailbox compatibility and target-host deployment are separate from the isolated acceptance fixtures.
