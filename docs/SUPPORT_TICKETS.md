# Client support conversations

This is an unreleased feature on `devin/non20i-support-tickets`, ported from the preserved `codex/support-ticket-screens` implementation under the 2026-10-16 owner allocation (Devin owns non-20i work; Codex owns 20i and final integration/releases). The port isolates the ticket slice only — the service-plan/agreement layer that branch also carried is not part of this increment, so subscription links are schema-valid but resolve only when a service-plan module exists. Published 1.23.0 is unchanged. GitHub matrices, packaged update/recovery, combined acceptance and independent review remain required before release. [Technical contract](SUPPORT_TICKETS_CONTRACT.md).

## Preparing access

An owner opens **Support tickets** in administration and enters their current password to prepare the additive ticket tables. Preparation preserves existing clients, projects and invoices. Other administrators need the explicit **Support tickets** permission; this permission does not grant client-account administration or payment access. Staff who have only this permission land on their ticket queue after signing in.

The client workspace exposes **Support tickets** after preparation. A client can open a conversation, choose their own related project or invoice, and exchange public replies. (Service-agreement links are preserved in the document format and resolve when a service-plan module provides them.) Staff can search active client names and open a conversation for the selected client. Related links are resolved against current ownership and permissions each time they are displayed.

## Public replies and private notes

The staff conversation separates **Send a public reply** from **Add a private staff note**. Private notes and assignments appear only in the authorized staff history. They do not change the client's visible history, ordering, reply version or notification count. A public update received while someone is writing causes a stale-form error; the original request and unsaved text remain visible for review. Copy the unsaved text before reloading to submit a new request against the current conversation.

Staff can assign an eligible operator and set an allowed status. Clients can close their conversation or reopen resolved work. Closing is terminal and requires an explicit confirmation; further work starts a new conversation. Ticket messages are plain text. Conversation pages show at most 30 events, and queue pages show at most 10 tickets. Creation and modification are rate limited.

## Notifications and read status

The existing workspace bell and activity center include unread ticket updates. An activity link opens the exact message and its history page. Opening a conversation does not mark anything read. **Mark conversation through these displayed updates read** advances only that person's cursor through the shown page. The activity-center button advances through the selected update; later arrivals remain unread.

Client notifications contain only public changes. Each staff member has a separate cursor; private notes can notify authorized colleagues. Ticket read actions do not change project notifications. Bell polling retains passive session expiry and does not send email or contact a hosting provider. Ticket subjects are shown in the activity center; message bodies remain inside the authorized conversation.

## Supporting a client

Where the existing client support policy and staff permissions allow it, **Open client portal** starts the existing scoped support session. The ticket view uses the same public projection the client receives. A support reply records the real staff member and support session. Staff cannot mark the client's tickets read, change their ticket status or create a ticket as that client from this support view. Private notes belong in the staff desk.

## Evidence and remaining work

The preserved implementation carried local evidence: PHP 8.4 / MariaDB native assertions, form/history checks in default and no-ini modes, isolated HTTP/Chromium flows covering revocation, exact replay, two-client separation, CSRF rejection, escaped stale drafts, public/private notifications, exact message links and attributed support replies, plus desktop/mobile captures. That evidence applies to the ported source; this branch re-runs the same suites in CI (`tests/support_tickets_native.php`, `tests/desk/*`, `tests/ticket_cases.py` browser cases) and the prepared `tests/ticket_upgrade_cases.py` 1.23 recovery/replay coverage.

On this branch only the pure-PHP checks have run locally: 114 service-desk model checks plus deployed document/form/review-control suites on PHP 8.4 — all pass. Native MariaDB, HTTP/Chromium and packaged-upgrade acceptance are wired into CI and still pending on this branch. No live mail, provider or customer action is exercised.

Attachments, ticket email delivery, notification preferences and broader service-desk automation remain follow-on work. Codex owns the complete 20i integration and final release packaging; Devin owns this non-20i ticket increment.
