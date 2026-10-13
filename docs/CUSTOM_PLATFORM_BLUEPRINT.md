# CajunWebPro custom hosting and business-management platform

Owner direction, 2026-10-12: **build our own WHMCS from scratch**. For this project, that means a first-party CajunWebPro platform handling the agreed hosting-business functions: storefront, customer accounts, orders, invoices, recurring billing, payment processing integration, service provisioning, domains/VPS, support and administration. The platform also handles the company's web-design work and admin webmail. This is an implementation direction and delivery blueprint, not a completed software release.

## Product boundary and existing work

Build our own business logic, data model, admin interface, client portal, billing workflows and provider adapters. Do not require a WHMCS installation/license, HostShop front end or another outsourced billing application. Authorize.net still processes payments; 20i still provides the actual hosting/domain/VPS infrastructure and supported control-panel access. Using those provider APIs does not make the CajunWebPro application third-party billing software.

Preserve the current CajunWebPro website, original branding, PHP/MySQL shared-host architecture, app/assets organization and one-page keyless installation. Build the new platform modules around verified existing source. The owner identifies 1.1.0 as current, but that source has not been recovered in this chat. Inspect it before deciding which components need to be created or extended. Historical 1.0.8 is reference only; do not replace the owner's current application with it.

Use maintained PHP libraries where appropriate for protocols and security, bundled in the deployable package; custom product ownership does not require inventing cryptography or email protocols. Customer installation must not depend on command-line tooling, a Node service, root access or a persistent worker. Scheduled operations use verified shared-host cron capabilities.

## Owned modules and acceptance map

| Module | What CajunWebPro will own | Governing requirements |
|---|---|---|
| Storefront and catalog | Products, plans, approved prices, billing cycles, add-ons, discounts, availability and branded public pages | HC-01, C-02 |
| Customer identity and portal | Registration, contacts, permissions, services, projects, invoices, payment methods and support in one account | HC-02, P-01 |
| Orders and checkout | Cart, approved quotes, server-side totals, order snapshots, payment and per-item fulfillment states | HC-03 |
| Payment integration | Authorize.net configuration, tokenized forms, verified transaction events, refunds and reconciliation | HC-04/05 |
| Billing engine | Invoices, profiles, subscriptions, renewals, deposits, credits, overdue/retry rules, cancellation and finance reports | HC-06, P-03/04 |
| Shared hosting | Plan/provider mapping, provisioning jobs, status/usage, supported upgrades and lifecycle recovery | HC-07/08 |
| Domains | Verified availability, registration/transfer/renewal, ownership/contact and supported DNS/control operations | HC-09 |
| VPS | Verified products/options, provisioning/state/control and safe cancellation | HC-10 |
| Web-design services | Proposals, standalone payment forms, deposits/milestones/final balance, approvals, files and handover | HC-11, P-01/02/03 |
| Service desk and operations | Support tickets, order/service recovery, reporting, audit and staff tools | HC-12 |
| Staff administrators | Owner-managed accounts, permission changes, secure resets/deactivation and mailbox grants | A-05 |
| Admin email | Multiple mailbox connections, webmail, templates and private unread-mail notification bells | A-06/07/08 |
| Platform operations | Migrations, installation, scheduled jobs, secret protection, backups, restore and controlled updates | HC-13/14, A-03/04 |
| Complete client workspace | Native verified hosting controls, design timeline/approvals, updates and authorized versioned website ZIP delivery | HC-02/08/11, P-05/06/07 |
| Support access | One-click customer portal sessions and separately authorized real VPS SSH | A-09/10, HC-12 |

See [Hosting and billing roadmap](HOSTING_AND_BILLING_ROADMAP.md) for HC acceptance criteria, [Admin mail roadmap](ADMIN_MAIL_ROADMAP.md) for staff/email detail, and [Roadmap](ROADMAP.md) for public website and project workflows. These modules describe the agreed product, not an unsupported promise of every feature of any commercial platform.

The [client portal roadmap](CLIENT_PORTAL_ROADMAP.md) closes the detailed hosting-control, project progress/update, website download and staff support-access requirements. Aim for native CajunWebPro controls across verified 20i capabilities; identified provider handoffs do not count as completed native features. Keep internal mail/staff records separate from customer activity and preserve staff attribution during support access.

## Unified data and workflow rules

- One customer/account identity connects orders, design projects, services, invoices, subscriptions and support. Staff/admin identities are distinct, with explicit permissions. Do not create disconnected client databases for the design and hosting businesses.
- Maintain one invoice/payment ledger and Authorize.net integration for service checkout and web-design payment forms. Preserve immutable issued prices/terms and unique external transaction identifiers. Keep payment attempts and refunds reconcilable with the gateway.
- Order, payment, service fulfillment and design delivery are separate states. A paid hosting item can queue provisioning; a paid design deposit updates financial progress without declaring the website built. A success redirect cannot make an invoice paid or a service active.
- Use persistent jobs with unique operation keys, retry/backoff policies, visible failures and reconciliation for renewals/provisioning/notifications. A provider timeout can be an unknown outcome; reconcile before retrying a potentially completed charge or provisioning action.
- An integration adapter exposes only verified provider capabilities. Manual admin recovery must remain explicit and audited. Domain or VPS products cannot accept money before their actual provision/renew/cancel support and prices have been validated.
- Admin webmail, support tickets and transactional application notices share identities and relevant authorized context but remain distinct workflows. Mail templates do not trigger financial state changes or bypass a deliberate send action.
- Secrets and private documents/mail belong outside public reach. All record/file/message/notification access must be enforced on the server, including the admin-only bell on publicly cached pages.

## Delivery sequence

1. **M0 — Current source and operating baseline:** recover and verify 1.1.0; map existing features/schema; establish reproducible source/package provenance and real staging/backup/installer acceptance; verify provider/hosting capabilities.
2. **M1 — Platform foundation:** customer and staff identity, owner-controlled additional admins, authorization, catalog and prices, unified portal/admin navigation and migration foundation.
3. **M2 — Payments and billing:** Authorize.net sandbox settings/forms/events; orders/invoices/receipts; standalone design full/deposit/milestone/final-balance forms and links; subscriptions/renewals/refunds/reconciliation. Design-only payments work with 20i disabled.
4. **M3 — Shared-hosting automation:** verified 20i package mapping, payment-to-provisioning, provider reconciliation and renewal/cancellation recovery.
5. **M4 — Domains and full project delivery:** verified domain lifecycle, design progress/updates/previews/approvals, latest/prior website ZIPs and authorized database/content handover, maintenance and optional service bundles.
6. **M5 — Operations modules:** support, reports/admin recovery, multiple mailboxes/templates/bells, separately verified VPS lifecycle and one-click SSH transport. Build portal support-session authorization with M1 and expose it with the client workspace. Mail/support work need not wait for VPS if their own prerequisites pass.
7. **M6 — Release and rollout:** integrated native/provider/HTTPS/security tests, migration and restore drills, exact artifact verification, current documentation and authorized live rollout.

Deliver tested increments into Git with status and evidence. Do not label a roadmap, UI mockup or unverified package as a working replacement platform. Initial functionality must satisfy its own gates even when later modules are still pending.

## Current implementation status

The repository contains project documentation, including this blueprint. Current 1.1.0 source remains to be located and inspected. No new platform runtime, customer/admin/mail account, payment, provider provisioning or deployment was created in this planning change. The next executable step is current-source recovery/import and a capability/gap review, followed by M1 foundation work.
