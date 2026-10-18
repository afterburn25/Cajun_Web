# Hosting offers from imported products

Status: successor implementation on `codex/twentyi-hosting-commerce`, based on frozen 1.26 candidate `6e3dbeff6dc6359a418837d0c95baa43e4c92533`. Owner/public/client routes are connected on this development branch, but the feature is not released or accepted. The [1.26 candidate](RELEASE_SCOPE_1.26.0.md) remains separate; its corrected source `f67b1631` is undergoing full acceptance.

The owner wants imported 20i products to be usable from the client portal. The full supplied API reference is already retained in [the reference directory](reference/20i-owner-api-34e713-redacted.txt); another copy is not needed. The observed hosting type identity and existing `addWeb` implementation support this local connection. Cloud product and automatic image contracts remain unresolved separately.

## Intended workflow

An owner selects an imported hosting draft and an exact active retail service plan with a current reviewed provider mapping. A separate immutable publication makes that offer available to clients. Generic catalog publication and catalog invoices continue to exclude hosting; an ordinary catalog invoice cannot authorize hosting provisioning.

The initial connection supports one shared-hosting package per request, USD monthly or annual pricing, and no separate setup fee. Product title, price and cadence must match the plan. Published inclusions, exclusions, limits and support terms come from the exact plan. Private draft wording, provider cost, reseller details and credential fingerprints never appear in public cards. Images use the existing local media system.

A signed-in client requests a specific published offer. An owner then converts that request into a service agreement for the same client and project. Request conversion and agreement creation use the same database transaction, with a retained unique link for retries. The existing consent, activation, recurring first invoice, externally verified payment, hosting order and explicit provisioning steps remain authoritative. Online card collection is a separate unfinished payment integration.

Editing a product, retiring a plan, withdrawing an offer, replacing a mapping or changing provider credentials prevents new requests against outdated details. Existing requests retain their original terms. This increment does not infer account continuity after replacing credentials; a separate explicit reassociation flow would be needed to reuse an import from different credentials.

## Implemented preparation

- Strict publication and request documents, current-availability checks and closed historical/public projections.
- A guarded agreement-writing composition point preserving the existing owner password, plan and client checks.
- Local publication, request, conversion, withdrawal and request-closure services, with current SQL context and retained request links. Owner and client routes now call these services after their current permission, session and CSRF checks.
- A neutral public read path that does not impersonate an owner, open provider credentials or make provider requests.
- Four additive history tables with explicit owner setup, exact document/audit checks, immutable request decisions and retained publication images protected from media cleanup.

Schema/setup, maintenance, controllers, CSRF routing, public/admin/client screens and agreement/project links are integrated for development. An owner opens **Hosting & Domains → Hosting offers**, prepares history explicitly, selects an imported draft and matching service plan, previews its exact terms and publishes with their current password. The public Hosting page links to the Client Portal, where signed-in clients can request a plan and follow their own requests. The owner reviews requests and offers an agreement for that client's project; the client follows the existing agreement review flow.

Each returned page contains at most 20 records. Public pages now perform one fresh hosting-history validation per call through private read composition, removing a duplicate scan without caching authority. Integrity validation still examines retained history and is not constant-time pagination. The native fixture observes executed statements and tests fresh calls after damaged history, changed credentials and revoked reviewers; those added assertions still await Linux execution. Full SQL, query-cost and browser acceptance remain pending. Do not mark ordering complete from component tests or source review.

## File recovery

The successor's staged **Recover original files** action remains available when hosting history is completely absent or validated and empty. Once an offer has been published, automatic file recovery is blocked, including after withdrawal. Older files do not protect images referenced only by retained offer history. Incomplete, inconsistent or unrecognized hosting storage also blocks recovery.

The updater rechecks current owner access and password after acquiring the same mutex used by publication. It holds that mutex through the entire file recovery and rejects an inherited database transaction so an older database snapshot cannot hide a publication. The adapter makes read-only database queries and does not prepare, erase or repair hosting history. The original filesystem engine still handles interruption and recovery errors; this guard does not make filesystem changes atomic. Existing login/rate and update-lock bookkeeping precedes the guard.

Keep this successor's exact staged updater. Previously downloaded updater copies and manual file replacement do not contain this protection. If recovery is necessary after publication, restore a matching backup of website files, database and configured private storage through the hosting provider. A future compatible rollback target requires a separately verified contract.

The current regression executes exact adapter/readiness/mutex function bodies against modeled SQL, authentication and recovery seams: 142 checks pass in each normal and minimal PHP mode. Actual database, interrupted-filesystem, concurrent-connection and authenticated staged HTTP cases remain required before release. The focused native matrix now opts into 44 additional checks across absent, prepared-empty, published and withdrawn history. Each child uses a fresh isolated installed root and real SQL; positive cases generate actual backups with the unchanged filesystem engine. A separate connection samples lock ownership during restoration. These cases are implemented but have not yet executed. Complete synthetic-file recovery does not establish interrupted or published-package upgrade acceptance.

## Verification and next work

The focused packaged job also enables a separate staged-owner HTTP continuation after building its real COMPLETE and UPDATE packages. Each of the four native history phases gets a fresh installed copy, private-storage copy and session directory. The actual owner page verifies its original UPDATE package and processes fixed loopback requests. Tests exercise package mismatch, CSRF, non-owner/wrong-password refusal, owner login and inspection, fresh role/password and backup confirmation, corrupt history, successful absent/empty recovery and blocked published/withdrawn recovery.

The journal is explicitly synthetic and complete: only three inert asset operations and server-policy text differ. Business rows, table definitions, private media and unchanged file bytes/modes must remain exact. Only verified owner login, rate-limit and updater audit effects are restored in the disposable database; auto-increment allocation gaps are not reset. No installed configuration, cookie/session data, saved rows or private runtime directory is uploaded. Source/ZIP/file checks pass 57 cases and bookkeeping models pass 18 per PHP mode. Real packaged HTTP/SQL execution is still pending, as are interrupted recovery and upgrades from an actual published baseline. The PHP test server does not establish Apache policy enforcement.

The pure document suite passes 119 named checks in normal and minimal PHP 8.4.25 against the successor tree. PHP syntax and whitespace checks pass for the current new modules and extracted writer. Independent source review verified the original agreement writer body and examined publication/request replay and conversion boundaries. None of this establishes native SQL, HTTP, provider, payment or release acceptance.

The storage proposal also passes 85 modeled checks in each PHP mode; its modeled SQL is not native evidence. The focused workflow uses four actual environments: PHP 8.2/8.4 with MariaDB 10.11/MySQL 8.4. It runs the exact document suite, combined offer/request fixture and existing native service-plan regression. Its synthetic transport never accesses a provider account.

The isolated local Windows SQL check passes all 138 existing service-plan assertions against backend `47fc69f6`. The new hosting fixture fails at the existing private-file POSIX 0600 requirement before offer assertions; Windows cannot establish this Linux deployment prerequisite. Its failed report is preserved and the safeguard remains unchanged. GitHub's original Linux native run is [39098575981](https://github.com/afterburn25/Cajun_Web/actions/runs/39098575981).

The additional focused Linux job builds an exact-source COMPLETE package and seeds the real native fixture in its own isolated database. `tests/hosting_catalog_browser.py` then drives real owner image upload, preview/publication, client request, owner conversion and separate client agreement consent. Direct HTTP tests cover CSRF, owner password, stale forms, exact retries, project/client ownership and support-session refusal. Closed failure diagnostics retain source locations and SQLSTATE without SQL arguments or private fixture values. Ten desktop/mobile captures require subsequent pixel review. This job does not replace full application acceptance.

Next: retain and review all original four-environment results; run the packaged owner/public/client browser flow; test the actual agreement/invoice/payment/order path with synthetic provider fixtures. Refresh installer inventory after each combined shipping change. Preserve every original 1.26 run and published 1.25 asset.
