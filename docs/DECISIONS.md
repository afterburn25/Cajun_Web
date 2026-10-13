# Decision record

Recorded on 2026-10-12 from the owner's project instructions. This is a consolidated record, not an invented timestamp-by-timestamp history of earlier work. Supersede decisions explicitly; do not silently reverse them.

| ID | Accepted decision | Reason / practical consequence |
|---|---|---|
| D-001 | `afterburn25/Cajun_Web`, default `main`, is the ongoing project source of truth | Owner requested durable documentation so new sessions resume accurately; source import is still pending at bootstrap |
| D-002 | Every work session updates direction/status/handoff/history as applicable | Chat memory alone produced loss of continuity and cannot serve as release evidence |
| D-003 | Service-company site, not a DIY website builder | Owner builds websites for clients and wants to showcase/sell that work |
| D-004 | PHP/MySQL shared-hosting application | Preserve normal browser installation without required server root, CLI builds or Node services |
| D-005 | Original horizontal navy/gold CajunWebPro logo without `.com` | Owner selected the existing website mark and rejected unwanted substitutes |
| D-006 | Attached `icon(1).png` is the favicon source | Exact owner-supplied fleur-de-lis; implementation must be checked rather than assumed |
| D-007 | Fancy, sharp, technology-driven but organized public presentation | Earlier muted/plain templates and overloaded effects both failed the desired balance |
| D-008 | No flying/disintegrating pixels | Explicit repeated visual rejection; do not reintroduce through new artwork or animation |
| D-009 | Keyless, genuinely one-page installer | Owner did not request an installation key and rejected Next/Back setup screens and alternate repair entry points |
| D-010 | One complete flat deployment package with one canonical installer | Prior wrapper-folder and standalone-file inconsistencies made deployment confusing |
| D-011 | Preserve private data/configuration/guards and use targeted safe storage | Do not fix deployment by erasing data, disabling installation protection, broad chmod/chown, or promising impossible privileges |
| D-012 | Implement the complete approved feature expansion | Portfolio/motion/estimator/review/client/proposal/payment/care/scheduling/content/pipeline/backup/history/update work is captured in the roadmap, not marked done |
| D-013 | Add more information and Wix/Squarespace switching comparisons | Owner wants a persuasive reason to hire CajunWebPro; claims still require truthful, current evidence and clear service scope |
| D-014 | Keep evidence layers separate | A concept is not implementation; implemented source is not tested deployment; a release ZIP is not the running host |

## Open implementation choices

No actual payment/calendar provider account, package pricing, contract wording, guaranteed service response times, source-directory layout, or new schema migration design has been finalized here. Stripe and calendar integration were suggestions, not already connected services. Record the choice and evidence when made.

## Future entry format

`ID · date · status (accepted/superseded) · request/context · decision · consequences · affected files/roadmap items · verification · supersedes (if any)`.


## 2026-10-12 — Hosting and payment direction

| ID | Status | Decision | Consequence |
|---|---|---|---|
| D-015 | Accepted | CajunWebPro will sell custom web design PLUS white-label hosting, domains and VPS | Unified marketing site/client dashboard and fulfillment lifecycle |
| D-016 | Accepted | Use 20i reseller API directly, not a HostShop or WHMCS branded checkout | Must implement and test first-party catalog, billing, portal, automation and API adapters |
| D-017 | Accepted | Use Authorize.net instead of Stripe for the selected custom billing platform | P-03's generic/Stripe suggestion is superseded for this product; API Login ID, Transaction Key, Signature Key and Public Client Key configurable in admin with separate sandbox/live |
| D-018 | Accepted with launch gate | Custom brand-matched payment form using Accept.js tokenization | Never collect PAN/CVV on server; own-field SAQ A-EP scope and merchant PCI review before live acceptance; hosted-field/Accept Hosted alternative if required |
| D-019 | Accepted | Offer admin-managed real plans/prices for hosting, domains, VPS, web design and maintenance | No invented public prices/provider costs; verify capability and margin before activation |
| D-020 | Accepted | Preserve existing single-page keyless installer, PHP/MySQL shared host, navy/gold logo and client data | Add modular code and safe migrations, not a root-file monolith or forced fresh install |

Unresolved verification items: actual 20i API credentials/capabilities (especially domain/VPS), Authorize.net merchant account/test credentials, final published plan prices, tax/legal/PCI determinations, cron support on target host and live payment activation authorization. See docs/HOSTING_AND_BILLING_ROADMAP.md.

## 2026-10-12 — Current version and dedicated design-service payments

| ID | Status | Decision | Consequence |
|---|---|---|---|
| D-021 | Accepted owner correction | The owner confirms 1.1.0 is the latest build | Locate and verify that artifact before implementation; the verified 1.0.8 archive is historical reference and must not become a downgrade or default starting point |
| D-022 | Accepted requirement | Provide branded Authorize.net payment forms for web-design services as well as hosting checkout | P-03 and HC-11 explicitly include full service payments, quote deposits, milestones, final balances, secure payment links and optional maintenance; one invoice/payment engine and client portal |
| D-023 | Delivery sequence | Standalone design-payment forms belong in M2 with payments, independent of 20i provisioning | Supersedes the earlier implication that all HC-11 work waits for M4; project/bundle integration remains M4, with no hosting purchase required to pay for design |

The concrete form/admin flows and acceptance cases are specified in P-03 and HC-11. No current 1.1.0 source inspection, form implementation or payment integration test occurred in this documentation change.

## 2026-10-12 — Admin email, additional administrators and notifications

| ID | Status | Decision | Consequence |
|---|---|---|---|
| D-024 | Accepted requirement | Add multiple connected email accounts and send/receive webmail within admin | Account setup, incoming/outgoing connection checks, mailbox grants, inbox/compose/folders/drafts/attachments and failure recovery belong in A-06/HC-12 |
| D-025 | Accepted requirement | Allow owner-managed additional admin accounts | A-05 adds administrator lifecycle and scoped roles/mailbox permissions; protect existing owner access and revoke disabled sessions |
| D-026 | Accepted requirement | Create reusable templates for sending mail | A-07 includes subject/body editing, safe variables, preview and explicit sending from the selected authorized mailbox |
| D-027 | Accepted requirement | Show unread-email bells in admin and the public site for signed-in admins only | A-08 provides per-mailbox permission-filtered counts, unread items and exact-message links; private responses and public-cache isolation are mandatory |

Admin identity/permissions precede mail access. Implement the mail/template/bell group within M5/HC-12 independently of VPS readiness once its own prerequisites pass. Account setup initially connects existing mailboxes; provider-side mailbox creation remains an optional separate scope choice. No mailbox connection, additional account creation, message send or UI implementation occurred in this documentation session.

## 2026-10-12 — Build our own WHMCS-style platform

**D-028 — Accepted owner direction:** Build the agreed hosting-management platform from scratch as first-party CajunWebPro software: customer/staff identity, catalog, orders, invoices, recurring billing, service provisioning/lifecycle, support, admin and portal. This explicitly confirms D-016/D-017, with direct 20i/Authorize.net adapters and no required WHMCS or HostShop application. Web-design payments and admin webmail/templates/bells are integrated modules. Maintained bundled protocol/security libraries remain compatible with custom ownership. Preserve current 1.1.0 source when recovered; the [platform blueprint](CUSTOM_PLATFORM_BLUEPRINT.md) maps milestones and acceptance without claiming implementation.

## 2026-10-12 — Full client workspace, ZIP handover and one-click support

| ID | Status | Decision | Consequence |
|---|---|---|---|
| D-029 | Accepted | Everything customer-facing belongs in one portal, including design and verified 20i service controls | Native controls are the target; maintain endpoint/entitlement/permission evidence and identify StackCP handoffs as incomplete native coverage |
| D-030 | Accepted | Clients monitor project progress, receive updates and download website ZIPs | P-05/06/07 add real timeline/approval events, private notifications, immutable releases and authorized data/instructions; delivery archives are distinct from provider backups |
| D-031 | Accepted | Owner/authorized staff can open any permitted client's portal in one click for support | A-09 adds separately identified, audited support sessions with a banner/exit and role checks, using configured customer-support authorization rather than client passwords; supersedes HC-12's tentative treatment of this feature |
| D-032 | Accepted with technical discovery | One-click SSH access to eligible client VPS for authorized support staff | A-10 requires verified product authentication and real transport, protected keys/scoped launch and audit; a separate gateway may be necessary without changing shared-host installation requirements |
| D-033 | Scope clarified | Full customer hosting controls include that customer's mailbox management where 20i permits | This adds provider-side customer mailbox operations beyond A-06's existing-mailbox connections for staff webmail; does not grant customer access to staff email |

Official public 20i API, client service grants, StackCP, webmail, backup and SSH documentation was reviewed on 2026-10-12; citations and verification limits are in [Client portal roadmap](CLIENT_PORTAL_ROADMAP.md). No account-specific entitlement, API, delegated browser terminal, live SSH or restore test has been performed.

## 2026-10-12 — GitHub delivery and multi-agent ownership

| ID | Status | Decision | Consequence |
|---|---|---|---|
| D-034 | Accepted | Use GitHub testing and incremental releases; retain versions and agent documentation | Add scoped CI now; publish exact tested full deployment ZIPs with tags/manifests/checksums/reports after source recovery; maintain release register, main, status and handoff; no application version for docs-only changes |
| D-035 | Accepted | Split implementation between Codex and Devin using separate branches and integrate completed work | Codex coordinates shared contracts, platform foundations, review/integration and releases; Devin starts with bounded public portfolio work; explicit file ownership and combined checks prevent competing schema or interface changes |
