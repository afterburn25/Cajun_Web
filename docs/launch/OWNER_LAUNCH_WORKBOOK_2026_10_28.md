# CajunWebPro owner launch workbook — 2026-10-28

## Scope and handoff

Owner request: prepare the installed website for business use while the separate Codex licensing/financing integration continues.

Installed version: **1.25.0, owner-reported**. Reviewed release source: b79c67110829126916e3a65e616191542448bccc. Development main at preparation: fdf40a25be3b9a7c2e154a1fad17ab4c7ae55827.

This branch contains owner preparation documents only. Application files, active integration branches, production payments and deployment are unchanged. Copy and catalog entries below are drafts for review; none was entered into the live database.

| Check | Evidence/status |
|---|---|
| Published email/inquiry workflow | Source reviewed at v1.25.0 |
| Settings field names and copy limits | Checked against the v1.25.0 settings view |
| Catalog families, periods and SKU syntax | Checked against the v1.25.0 catalog service |
| Public website and DNS | Not verified: the web retrieval service could not access the website or DNS resolver URLs |
| Authenticated live admin | Not available: browser runtime failed before session inventory with an MXC volume authentication error |
| Current SMTP settings and actual inbox delivery | Not inspected or tested |
| Actual client login, uploads and downloads | Not executed |
| New release or deployment | None performed |

These access failures are limitations of this session. They do not establish that the website, DNS or email service is down.

## First launch scope

Prepare the public web-design site, inquiries, owner administration and the existing client project/file workspace. Hosting can retain its consultation page. Automated hosting sales, software checkout and financing remain subject to their separate release gates.

- [Website copy draft](WEBSITE_COPY_DRAFT_2026_10_28.md)
- [Catalog and software-product worksheet](CATALOG_PRODUCT_WORKSHEET_2026_10_28.md)

## 1. Confirm the installed environment

Open **Settings → System health** and record:

| Item | Required observation | Live result |
|---|---|---|
| Application | 1.25.0 | Owner reports 1.25.0; health screen not inspected |
| Transport | HTTPS | Pending |
| Private storage and media | File-operation checks pass | Pending |
| Installer | Removed, or follow the existing cleanup control and recheck | Pending |
| Email delivery | Authenticated SMTP for this owner's requested setup | Pending |

A read-only application root can be intentional. The private-storage/media checks determine whether ongoing file operations work. Preserve existing configuration, encryption keys, installed markers, private files and customer records.

## 2. Configure and verify inquiry email

Open **Email → Email delivery**.

| Admin field | Enter or verify |
|---|---|
| Delivery method | Authenticated SMTP |
| Sender email | A real mailbox/address your provider authorizes |
| Send inquiry notifications to | The owner's chosen business inbox |
| SMTP hostname | The mail provider's verified server hostname |
| Port and encryption | 465 + Implicit TLS, or 587 + STARTTLS, as supplied by the provider |
| SMTP username | The provider's required login; commonly the full mailbox address |
| SMTP password | Enter only in the protected live admin form |

Do not infer the correct SMTP hostname from the website domain. The hostname must match the server's valid TLS certificate. Leave an already-saved password field blank to retain it; the separate removal checkbox clears it.

Saving settings does not prove delivery. Save, then use **Send Test Email**. The actual controller sends that test to the currently signed-in administrator's email address, which may differ from the inquiry-notification inbox.

Record these separately:

| Test | Expected outcome | Live result |
|---|---|---|
| Built-in email test | Mail server accepts the message | Pending |
| Recipient inbox/spam check | Test message is actually received | Pending |
| Sender authentication | Provider confirms SPF, DKIM and DMARC configuration for the actual sending service | Pending |

The controller limits built-in mail tests to five per administrator per hour. Record the exact non-secret error before retrying a failed test.

## 3. Exercise one owner-controlled inquiry

Use the normal public Contact form with an address controlled by the owner. Complete all required fields, wait at least two seconds after opening the form, and acknowledge the privacy notice.

Suggested test message:

> Owner launch check: this is a test of the CajunWebPro inquiry form and notification delivery. No customer action is required.

Choose a service, budget and timeline actually shown by the form. Do not invent a customer or use another person's email.

| Observation | Expected outcome | Live result |
|---|---|---|
| Submit | Success with a CWP reference | Pending |
| Clients & Projects → Inquiries | One matching saved inquiry | Pending |
| Notification recipient | Owner's configured inquiry inbox receives the message | Pending |
| Reference/name/message | Match the saved inquiry | Pending |
| Status/notes | Owner can mark and annotate the launch test | Pending |

Source findings:
- The handler inserts the lead before calling the mail transport.
- A mail exception records notification failure without discarding the saved inquiry.
- A repeated submission with the same token returns the existing reference.
- The form token expires after two hours.
- Current limits are five new inquiries per connection per hour and twelve per day.

If the inquiry is saved but email fails, repair delivery first. The existing **retry notification** action sends the saved lead again; use it deliberately after checking the inbox to avoid unnecessary repeat notifications.

## 4. Review public content

Use the companion copy draft in **Settings → Website settings**. Owner confirmation is still needed for the public email, optional phone, actual service area, social links, commercial prices and privacy text.

Review public desktop/mobile pages:
- Home, About, Web Design, Hosting, Products, Portfolio, Process and Contact.
- Actual navigation, images, form labels, contact details and mobile menu.
- Portfolio entries: retain the **Design concept** label where appropriate; use real client names/work only with permission.
- Products: review what actually appears after catalog changes.

The setting **Allow search-engine indexing** controls noindex/robots behavior. It does not password-protect the site. Saving ordinary website settings publishes that copy immediately, so review the text before saving.

## 5. Walk through the client workspace

Use a clearly labeled owner-controlled test client/project. Do not send invitations or other messages to real customers during this rehearsal.

1. Open **Clients & Projects → Client workspace** and inspect preparation/readiness before adding data.
2. Prepare a test client/project using an owner-controlled address and only synthetic project information.
3. Verify the existing portal login/access process, project details, files and the approval controls actually available on this installation. Record absent controls as gaps.
4. Prepare a second owner-controlled test identity if testing cross-client isolation; its session must not see the first project's private files.
5. For website delivery, follow the existing owner-reviewed publication flow. Keep an uploaded draft private until its contents and manifest have been checked.

The documented delivery limits are 50 MiB compressed, 200 MiB expanded, 1,000 entries and 32 MiB per member. Include HANDOVER.md and LICENSES.md at the archive root. Never put a ZIP inside another ZIP. Database exports and automatic dynamic-site handover are outside this delivery subset.

| Workflow | Live result |
|---|---|
| Test client and project visible only to the correct account | Pending |
| Protected file upload/download | Pending |
| Available project preview/approval flow | Pending |
| Owner-reviewed website ZIP and retained earlier version | Pending |
| Cross-client denial | Pending |

## Owner inputs needed next

To continue email diagnosis, supply only the delivery method, SMTP hostname, port/encryption and exact non-secret error/result. A screenshot with mailbox addresses/passwords/private paths obscured is also suitable. Passwords, API keys and private customer records stay in the protected application.

Content/catalog decisions can be completed independently: public contact details, service area, approved service scope/prices, portfolio permissions and the software-product worksheet. Final finance/legal terms remain in the separate release review.

## Source references

- [Published v1.25.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.25.0)
- [Inquiry handler](https://github.com/afterburn25/Cajun_Web/blob/v1.25.0/site/api/handlers/inquiry.php)
- [Email controller](https://github.com/afterburn25/Cajun_Web/blob/v1.25.0/site/admin/controllers/mail.php)
- [Email settings view](https://github.com/afterburn25/Cajun_Web/blob/v1.25.0/site/admin/views/email.php)
- [SMTP transport](https://github.com/afterburn25/Cajun_Web/blob/v1.25.0/site/app/mail/transport.php)
- [Website settings view](https://github.com/afterburn25/Cajun_Web/blob/v1.25.0/site/admin/views/settings.php)
- [Service catalog guide](../SERVICE_CATALOG.md)
- [Client delivery guide](../CLIENT_DELIVERIES.md)
- [Provider connection guide](../PROVIDER_CONNECTION_SETTINGS.md)

Verification for this document: source review and document checks only. No new application runtime, live DNS/SMTP, browser, database, release or deployment acceptance is claimed.
