# Recurring billing in the studio and client portal

Current integration: Codex owns complete 20i P0. These preserved interface requirements come from #121 at `abaa20081870ede3bae9a606f71d730972792d6d`; former allocation and acceptance notes below remain historical. The current branch reuses only the necessary billing dependency and requires its own native/HTTP/recovery/review evidence. [Source provenance](TWENTYI_RECURRING_SOURCE.json).

This interface is a draft implementation on `codex/recurring-billing-screens`, following native PR120/`66f7760` and interface contract `4eb85bf`. It is not part of published 1.23.0. Independent review, combined application/upgrade/recovery/package acceptance and exact-main release checks remain required.

## Prepare and offer terms

An owner first prepares service agreements, payment preparation and the existing invoice payment ledger. Open **Recurring billing** in the admin sidebar, confirm a current database backup, and enter the current owner password to enable additive recurring storage. Existing records are preserved; unknown storage or populated unregistered tables require investigation rather than automatic adoption.

Staff need both current client-management and payment-management access. Open a project, select the client's active recurring service agreement, and enter the billing title, terms, first period start, date alignment, timezone and due interval. Price, USD currency and monthly/quarterly/yearly frequency come from the exact agreed service version. They are not editable amounts in this form. A new price requires a separately agreed service.

Use **Preview billing terms** to review the terms without saving an offer or invoice. Publishing requires explicit confirmation. A replacement retains the prior billing agreement and cannot overlap earlier ended billing. Its timezone and earliest start must respect the retained history, including withdrawn intermediate offers.

## Client decisions and future changes

Clients find **Recurring billing** in their workspace and a project link. Only the actual signed-in client can accept or decline their terms. Staff cannot record consent on the client's behalf. Recurring pages and commands are unavailable through support sessions in this increment.

After acceptance, authorized staff can start billing on a future whole-period boundary. Staff can pause, resume, withdraw an unstarted offer or end future billing where the retained lifecycle allows it. The client can end future billing after activation. Reasons are retained for decline, withdrawal, pause, resume and termination.

Dates always resolve against the original calendar. Monthly dates clamped to February return to the original day in later months. Start/pause/resume defaults respect later already-scheduled changes. Cancellation may override those later changes using the next future period boundary. No partial-period charges or credits are calculated.

The latest recorded decision can describe a future change. Read its effective date in **Billing history**, or review a specific period to see whether that period meets the recorded billing terms. Ending future billing preserves existing invoices; it does not fabricate payment or erase debt.

## Period invoices and payment history

**Review a billing period** shows its start, exclusive end, due date, timezone and amount. An authorized staff member can explicitly issue an eligible positive-price period using **Issue this period invoice**. The form binds the reviewed period to its action URL and original agreement revision. A replay preserves the original invoice; a second request cannot create another obligation for the same period.

The existing invoice ledger supplies recorded payments and the remaining balance. **View invoice and payment history** opens the established staff or client invoice route. The agreement's eligibility is never treated as evidence of payment. Historical invoices remain valid after legitimate later service cancellation, while new invoices require a current active source.

This increment does not add automatic charging, a renewal scheduler, provider actions or service entitlement changes. Devin retains complete 20i implementation.

## Rejected forms and access

Ordinary invalid or stale forms preserve bounded entered details and original request/revision/source references. A retained attempt may no longer be available in the current lifecycle; retrying still runs the native checks. Use the current-view link to review changed state. Passwords are never redisplayed. Browser textarea CRLF is converted to canonical LF only when constructing terms/reasons; the draft remains available for redisplay.

All mutations use POST, CSRF and explicit confirmation; preview is read-only. Responses use private no-store and no-referrer headers. Project/source/schedule ownership and complete retained family history are validated before projecting records. Project, source, schedule, event and invoice lists are paginated; pagination does not bypass history validation.

## Implementation and evidence

The native mutations remain in `site/app/billing/recurring_billing.php` and `recurring_billing_invoices.php`. This increment adds strict forms, protected projections and action adapters alongside those files, shared presentation under `site/includes/layout/recurring_billing.php`, and thin admin/portal controllers. Shared router/action/navigation changes are limited to these screens. The mobile facts style is scoped to recurring billing.

Focused tests cover strict form/query types, original-calendar dates, source-derived prices, escaped/retained inputs, authority and session expiry, foreign ownership, full-history corruption, pagination and partial ledger payments. The portable browser module exercises actual owner setup, real client activation and service consent, recurring consent in Chromium, lifecycle decisions and the existing invoice route. Its historical invoices use explicit synthetic canonical fixtures, not real merchant or customer operations.

The focused PHP8.2/8.4 workflow includes native and default/no-ini form checks. The combined browser suite calls `tests/recurring_billing_cases.py`; Apache acceptance checks private recurring files. Current exact CI results, remaining upgrade/recovery cases, package comparison and independent review belong in the session handoff and PR evidence, not an assumed release claim.

Related: [interface contract](RECURRING_BILLING_INTERFACE_CONTRACT.md), [native records contract](RECURRING_BILLING_RECORDS_CONTRACT.md), [service agreements](SERVICE_AGREEMENTS.md).

## Focused acceptance and recovery follow-up

Draft [PR121](https://github.com/afterburn25/Cajun_Web/pull/121) at `e9ee051` has independently downloaded/verified1280 focused named executions across push/PR PHP8.2/8.4 matrices, with all29 triggered workflows successful. The retained1.23 upgrade fixture now includes recurring consent, invoices, partial receipts, original-request replay and future termination through real HTTP recovery/reapplication. These new scenarios have only syntax verification at this checkpoint; their combined Apache execution, Windows/Linux packages and independent review remain required. No release or automatic collection is claimed.
