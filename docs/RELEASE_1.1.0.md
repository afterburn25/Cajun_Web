# 1.1.0 website and client-workspace acceptance

**Final evidence:** [v1.1.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.1.0), tested source `3703783e979abab38e8d72e9092c51dc835833b3`, [exact-main CI](https://github.com/afterburn25/Cajun_Web/actions/runs/38357271480). All 50 native and 157 HTTP/browser checks passed. [Test report](TEST_REPORT_1.1.0.md) and [published-download verification](RELEASE_VERIFICATION_1.1.0.json) supersede candidate history below. The matrix describes shipped functionality; broader roadmap limits remain unchanged.

Competitor references rechecked 13 October 2026 against official [Wix hosting](https://support.wix.com/en/article/exporting-or-embedding-your-wix-site-elsewhere), [Wix Studio](https://www.wix.com/studio), [Squarespace custom code](https://support.squarespace.com/hc/en-us/articles/205815928-Add-custom-code-to-your-site) and [Squarespace export](https://support.squarespace.com/hc/en-us/articles/206566687-Export-your-site-content). Public copy distinguishes service delivery from platform capability, acknowledges plan-dependent code features and partial exports, and makes no ranking/sales guarantees. These references do not certify provider integration.

Merged in [PR #8](https://github.com/afterburn25/Cajun_Web/pull/8), based on recovered application `90ba4f5` and main `b28a67c`. This matrix maps actual implementation to acceptance, not to the entire hosting/billing roadmap. Final exact-commit CI and publication evidence are recorded above.

| Delivered scope under acceptance | Routes / controls / handlers | Persistence | Named acceptance |
|---|---|---|---|
| Why Custom, Wix/Squarespace comparison, switching, packages, FAQ and industries | `index.php?page=why-custom`, `compare-wix`, `compare-squarespace`, `switch`, `packages`, `faq`, `industries`; `growth-pages.php` | Settings/content; service records | Public routes, desktop/mobile/narrow layouts; source review of official competitor references |
| Single-page estimator | `index.php?page=estimate`; `growth_estimate`, `growth_submit`; admin `studio-prices` | `settings.growth_prices`, `leads`, `rate_limits` | Forged totals ignored, validation, disabled prices, admin saved rules, no-JavaScript and browser server response |
| Website review and consultation requests | `index.php?page=website-review` / `consultation`; `growth_submit` | `leads`, notification status | HTTP saved submissions, DB verification, local mail capture, consent/CSRF/spam/rate/duplicate tests; appointment remains unconfirmed |
| Public portfolio and previews | `index.php?page=work` / `project&slug=...`; admin `project`, `studio-portfolio`; `growth_portfolio_view` | `projects`, `media`, `growth_portfolio_*` settings | Concept labeling, real GD upload, device touch control, before/after keyboard control using explicitly synthetic captures |
| Content, prices, lead pipeline and history | Admin `studio-content`, `studio-prices`, `studio-pipeline`, `lead`, `studio-history` | `settings`, `leads`, `studio_revisions`, `audit` | Saved state, legacy status-label regression, stale-edit denial, deliberate restore as another recorded revision |
| Client invitations and sessions | Admin `studio-clients`; `portal.php?invite=...`; `studio_activate`, `studio_login`, `studio_client` | `studio_clients`, `rate_limits` | Digest storage, expiry/single use, real browser activation/login, account-change invalidation |
| Projects, milestones and messaging | Admin `studio-job`; `portal.php?job=...` | `studio_jobs`, `studio_messages` | Staff/client saved messages, browser admin milestones, cross-client denial, CSRF rejection |
| Version-specific design/proposal decisions | `studio_publish`, `studio_decide`; portal `decision` | `studio_versions`, `studio_messages` | Superseded/duplicate/unauthorized rejection, immutable body, exact version/client, change-request feedback |
| Private documents | Portal/admin `upload`; `studio_upload`, `studio_download` | Guarded permanent private files, `studio_files` | Owner byte-identical download, anonymous/cross-client denial, disguised executable rejection, stored-path traversal rejection |
| Manual invoices | Admin `studio_invoice` / `studio_paid`; portal invoice section | `studio_invoices` | Issued/paid persistence, independent receipt confirmation, duplicate/client payment-mutation denial |
| Additive database upgrade | Admin `studio-upgrade`; `studio_migrate` | Existing ten tables plus seven workspace tables | Exact historical 1.0.8 fixture, actual UPDATE ZIP, preserved settings/owner/inquiries/projects/config/media/unrelated files; repeated and interrupted migration, incompatible digest-column rejection |
| Installation, branding and packaging | `install.php`, `tools/build.py`, page heads | New config/private storage/permanent guards | Actual COMPLETE ZIP installation, cleanup, existing-install guards, missing files/extensions, unprivileged write denial, root/subfolder URLs, exact hashes |

## Test environment and evidence rules

`application-tests.yml` uses a disposable MariaDB 10.11 service and PHP 8.3 with PDO MySQL/GD/ZIP, a non-root runner, real loopback HTTP and Playwright Chromium. `tests/native.php` reads the original 1.0.8 schema extracted from the fingerprinted historical test ZIP. `tests/browser_acceptance.py` and `tests/release_cases.py` operate on extracted distribution bytes and verify saved data. No browser requests are stubbed. Screenshots and JSON reports are retained by CI. `tests/fixture_db.php` is a CLI-only helper guarded to the disposable `cwp_test` database; it is never shipped.

Local mail capture tests PHP mail acceptance independently of lead persistence. They neither send external mail nor certify production SMTP inbox delivery. No customer host, HTTPS certificate/proxy, live mailbox, payment provider or 20i account is tested by this environment. Independent access/invitation CI runs separately on PHP 8.2/8.4 and is not evidence of application compatibility on those versions.

## Scope retained for later roadmap work

1.1.0 does not implement Authorize.net/card processing, subscriptions, native 20i hosting/domain/VPS controls, full admin webmail, integrated additional-staff UI, support impersonation/SSH, client ZIP handover, full backup/restore, an application-file updater or confirmed calendar bookings. Current manual invoices and database-upgrade controls are labeled accordingly. Existing portfolio UI does not complete Devin's broader W-01 assignment. Those remain in the approved roadmaps.

## Candidate verification history

- `df57b40`: combined native checks passed; HTTP runner failed due to its filename shadowing Python's standard HTTP module. Renamed to `browser_acceptance.py`.
- `a654a43`: native, HTTP workflows and fresh installation/physical cleanup executed; the browser test hit CSP's deliberate `unsafe-eval` restriction. Changed the test to locator assertions; did not weaken application CSP.
- `91ef30d`: [full candidate run](https://github.com/afterburn25/Cajun_Web/actions/runs/38355321670) passed 50 native and 143 HTTP/browser checks, including real browser installation, self-deletion and contact submission. Final browser admin/sitemap/favicon assertions added afterward; rerun before merge. Exact-main release evidence will supersede candidate evidence.
