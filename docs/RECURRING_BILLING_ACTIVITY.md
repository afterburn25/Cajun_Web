# Recurring billing updates in workspace activity

This draft follows the [activity contract](RECURRING_BILLING_ACTIVITY_CONTRACT.md) on `codex/recurring-billing-activity`, based on recurring interface PR121 at `abaa20081870ede3bae9a606f71d730972792d6d`. It is not part of published v1.23.0. Independent review and complete packaged acceptance remain required.

## What clients and staff see

Once an owner has prepared recurring billing and workspace activity, the existing activity center and unread bell include recurring offers and subsequent decisions. Clients see staff-authored updates for their own active account and project. Staff need current client-management and payment-management permissions; they see client decisions and other staff members' updates. People do not receive a notification for their own action. Support sessions cannot borrow either person's activity.

Each notice shows its current project title, a short description and recorded UTC time. The feed does not include billing terms, private reasons, amounts or actor identities. Open the original agreement to read those details. Existing invoice and payment notices continue to represent issued invoices and receipts separately.

Opening an update leaves its read status unchanged. Use **Mark read**, **Mark unread** or **Mark visible project updates read** to change your personal list. Other staff and clients keep their own status. A later arrival is not included in a previously displayed page's mark action. The public-site bell appears for authorized administrators; anonymous visitors receive no count or notice content.

## Exact retained history

An offer opens its original agreement, even after a replacement. A decision opens the exact saved event. The destination resolves the event's current history page each time it is opened, so newer events cannot move it off the linked page. Missing or foreign events show an error instead of falling back to another agreement or decision.

Offer and event identifiers have separate read-state namespaces. Full original agreement, replacement-family and source history are checked before exposing represented notices. Each list/count/open operation holds the existing shared identity lock across projection and validation. Permission, client authentication, active status and project ownership are checked again after waiting for that lock. Unsupported or populated unregistered storage does not become a fabricated empty count.

The existing 30-item page/mark limit and 99+ count remain. UTC timestamps are independent of SQL/PHP session timezone, and same-second records use original revision plus stable key order. Read changes and their audit are atomic. Passive bell polls preserve session idle expiry.

## Scope and verification

This increment changes read projections, personal notification navigation and history anchors. It adds no schema or business journal, automatic invoice worker, merchant charge, outgoing email or provider operation. Native agreement, consent, lifecycle and invoice mutations remain unchanged. Broader recurring billing automation and production activation remain separate requirements.

Local native PHP8.4/MariaDB checks cover original parent behavior plus recurring recipient isolation, same-second pagination, colliding offer/event IDs, exact history after appends/replacement, current authority after lock waits, off-page corruption, missing markers, timezone independence, personal read isolation and rollback. The unchanged financial suite is also executed because both use the activity center. Form tests run under default and no-ini PHP. Actual HTTP/Chromium cases cover both centers, exact anchors, private counts, CSRF/read actions, the public administrator bell and desktop/320px layouts.

The populated v1.23 upgrade fixture now retains recurring personal reads and verifies original destinations after both recovery/reapplication cycles. Its execution, private-path Apache checks, both PHP8.2/8.4 CI runtimes, exact Windows/Linux packages and independent review remain tracked in [current status](../PROJECT_STATUS.md); test source alone is not acceptance.
