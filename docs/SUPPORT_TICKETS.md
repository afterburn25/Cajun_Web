# Client support conversations

This is an unreleased feature on `codex/support-ticket-screens`, based on native storage PR103 at `6ba93c3a71cbbc95f41260c3f42f5360d9ae91a0`. Published 1.23.0 and the frozen agreement candidate PR101 are unchanged. The screens have local database and browser evidence; current GitHub matrices, packaged update/recovery, combined acceptance and independent review are still required. [Technical contract](SUPPORT_TICKETS_CONTRACT.md).

## Preparing access

An owner opens **Support tickets** in administration and enters their current password to prepare the additive ticket tables. Preparation preserves existing clients, projects and invoices. Other administrators need the explicit **Support tickets** permission; this permission does not grant client-account administration or payment access. Staff who have only this permission land on their ticket queue after signing in.

The client workspace exposes **Support tickets** after preparation. A client can open a conversation, choose their own related project, invoice or service agreement, and exchange public replies. Staff can search active client names and open a conversation for the selected client. Related links are resolved against current ownership and permissions each time they are displayed.

## Public replies and private notes

The staff conversation separates **Send a public reply** from **Add a private staff note**. Private notes and assignments appear only in the authorized staff history. They do not change the client's visible history, ordering, reply version or notification count. A public update received while someone is writing causes a stale-form error; the original request and unsaved text remain visible for review. Copy the unsaved text before reloading to submit a new request against the current conversation.

Staff can assign an eligible operator and set an allowed status. Clients can close their conversation or reopen resolved work. Closing is terminal and requires an explicit confirmation; further work starts a new conversation. Ticket messages are plain text. Conversation pages show at most 30 events, and queue pages show at most 10 tickets. Creation and modification are rate limited.

## Notifications and read status

The existing workspace bell and activity center include unread ticket updates. An activity link opens the exact message and its history page. Opening a conversation does not mark anything read. **Mark conversation through these displayed updates read** advances only that person's cursor through the shown page. The activity-center button advances through the selected update; later arrivals remain unread.

Client notifications contain only public changes. Each staff member has a separate cursor; private notes can notify authorized colleagues. Ticket read actions do not change project notifications. Bell polling retains passive session expiry and does not send email or contact a hosting provider. Ticket subjects are shown in the activity center; message bodies remain inside the authorized conversation.

## Supporting a client

Where the existing client support policy and staff permissions allow it, **Open client portal** starts the existing scoped support session. The ticket view uses the same public projection the client receives. A support reply records the real staff member and support session. Staff cannot mark the client's tickets read, change their ticket status or create a ticket as that client from this support view. Private notes belong in the staff desk.

## Evidence and remaining work

Local PHP 8.4 / MariaDB 10.11.16 verifies 170 named native assertions, retaining all original 131 storage checks. Form/history checks pass 66 assertions in each default and no-ini mode. Actual isolated Edge/HTTP flows pass 38 checks; the portable HTTP/Chromium acceptance module also passes 65 local checks, including current revocation, exact replay, two-client separation, CSRF rejection, escaped stale drafts, public/private notifications, exact message links and attributed support replies. Client, staff and conflict views fit a 320-pixel screen; six original desktop/mobile captures were inspected.

The first UI test found an unregistered stylesheet, then a four-pixel overflow from the long read button; both shipping issues are corrected. The portable fixture initially expected 403 instead of the existing empty 204 bell response, and expected 403 instead of session revocation's login redirect. The corrected tests assert those existing behaviors and also prove a renewed session cannot regain a revoked ticket grant. Earlier failed reports remain retained and are not counted as passing acceptance.

`tests/ticket_cases.py` is part of the complete packaged browser suite. `tests/ticket_upgrade_cases.py` adds actual latest-1.23 setup, populated public/private history, separate read cursors, updater throttle refusal, recovery, reapplication and original request replay in both Apache rewrite modes. Those packaged upgrade cases are prepared but have not yet passed. Installer source hashes and private-path protections include the new modules.

Attachments, ticket email delivery, notification preferences, broader service-desk automation and related provider resource controls remain follow-on work. Devin owns the complete 20i implementation, including provider support and VPS SSH. Codex owns this local non-20i ticket increment and final integration/release acceptance.
