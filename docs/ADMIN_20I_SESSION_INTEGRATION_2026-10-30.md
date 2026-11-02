# Admin session UX and 20i catalog/storefront integration — 2026-10-30

**Status: local development candidate; not a production release, GitHub commit, deployment, or end-to-end acceptance.**

## Owner request

Reconnect the owner's existing 20i hosting plans to a discoverable backend product list and public hosting offers; remove repeated administrator/owner password prompts after sign-in; replace the long, confusing 20i connection page with task-specific workspaces. The owner did not authorize live provider orders, customer changes, charges, migrations or deployment in this work.

## Exact source provenance

- Installed RC runtime: artifact `12988737538`, workflow run `41077725683`; workflow-source commit `7bfa55c13437a1ebf2c64165b69cb950cd74f128`. Its build overlaid selected admin files; it is not simply that commit's unmodified site tree.
- Recovered hosting catalog/publication/checkout stack: `d2903bfb81c93eb9da756c128536f23a3d34e822`, PR248 (`codex/twentyi-installed-billing-options`), including the direct package-type catalog from PR216 and monthly/annual selector. Source recovered from artifact `12221806544`, run `39231739716`. Historical acceptance of a component does not certify this combined candidate.
- Licensing development tools/tests: PR307 `7047e90c11b7fa10e3a11d7ec4d555df744f79fc`, artifact `12919005665`, run `40920875022`. These supplied the local development scaffolding; their older workflow files are NOT part of the delivered patch.
- Exact Git common ancestor: `dcd6966c4e0c209d15f9d88c8832ef601765c336`, site tree `80599bb6f9efe13308990e031e131ebe1f5caec2`. The recovered published 1.25 site fixture was checked against this tree before runtime three-way reconciliation.
- Local baseline commit `a37f649` is explicitly a LOCAL snapshot of the actual installed RC site plus PR307 development files. It is not a commit on GitHub and must not be described as a remote release source.

The runtime merge preserved both hosting and current software/financing routes and the owner-support changes. Shared transport allowlists retain the package-type read and password-reset-template read; no arbitrary provider URL input was introduced. Generated installer files were regenerated, not hand-merged. Canonical foundation mirrors were included.

## Implemented changes

### Catalog and storefront

- Recover direct `package.types` observation, schema/storage, parsers, selected/batch imports, and existing-draft identity preservation. The default importer source is the provider's plan catalog, not hosting accounts already provisioned.
- Recover hosting offer mapping, publication, monthly/annual browsing, portal selection, checkout documents and invoice-first purchase machinery. The public Hosting page now queries the hosting-offer catalog rather than displaying only consultation copy.
- Publication is a separate explicit action with product, service-plan, provider-mapping and current-identity guards. Import does not publish, charge, provision hosting, or invent prices, images or terms.
- Put **Your products** above import and create/edit forms. Add name/SKU search, type/status filters, newest-first ordering, 50-row pagination, stable filtered navigation and explicit empty-state guidance. SQL clauses are fixed and values are bound; wildcard characters in search are escaped as literals.

### No repeated admin password entry

- Add `admin_session_matches()` which re-reads the authenticated actor through existing session validity and enabled-account checks, compares actor identity and canonical authorization version, and refuses support context. A password string is not accepted as an alternative to an active session.
- Replace repeated-password checks at admin/owner mutation boundaries, keeping role/permission, CSRF, transaction/lock, audit, revision, confirmation and provider/payment safeguards.
- Omit only repeated `current_password`/`owner_password` inputs from the shared admin renderer and remove direct duplicate controls. Existing legacy arguments remain temporarily for caller compatibility, but are not authentication proof.
- Preserve administrator and customer sign-in verification, password-reset/new-password controls, API keys, mailbox/SMTP secrets and other actual credentials.
- Change the relevant setup-view regression from "wrong repeat password" to "revoked current owner session" rather than deleting the denial assertion.

### 20i interface

- Seven allowlisted sections: Overview, Connection, Existing services, Cloud, Domains & mail, Website controls, Advanced.
- Overview renders a cached status and clear next step without rendering every advanced form. A saved API key is not described as a verified connection; verification is tied to the current profile revision.
- Explicit shortcuts for importing plans, managing products/prices, publishing hosting offers and viewing the public hosting page.
- Explain the difference between plans for sale and already-created customer services. Load only the selected section via a literal filename map; request input never creates an arbitrary include path.
- Preserve the relevant section in action redirects and pagination links; preserve the existing focused cloud-assignment route.
- Desktop/mobile layout and existing navigation script exercised with synthetic, non-sensitive fixture data.

## Current verification

All results below apply to the final local source, not the live site or a remote CI build.

- 28 focused PHP suites: **1,441 checks passed**. This comprises 33 session/role/CSRF/field checks, 43 route/search-policy checks, 929 recovered hosting catalog/checkout checks, and 436 licensing/financing regression checks.
- Offline Chromium rendering: **63 checks passed**, using actual PHP templates, local CSS and the real admin menu script with modeled rows/readiness. This checks layout, links, form visibility, CSRF fields and menu behavior; it does not execute browser HTTP navigation, SQL, provider calls or form submissions.
- Supported `python tools/build.py --refresh`: passed; linted **2,795 shipping PHP files**, checked JavaScript, canonical dependency bundles and mirrors.
- Final installer inventory: **3,011 entries matched**, zero missing/mismatched files. There are 3,012 site files; the generated inventory file deliberately does not hash itself.
- `git diff --check`: passed. Patch application and exact after-byte checks are separately recorded in the review bundle.

## Preserved limitations and earlier attempts

- Native PDO/MySQL driver and MariaDB/MySQL server are unavailable here. Native database integration, populated upgrades, real save/readback, tenant boundaries and migrations have NOT run for this candidate.
- A real loopback browser-navigation attempt was blocked with `ERR_BLOCKED_BY_ADMINISTRATOR` before any assertions. No browser policy was changed or bypassed. Offline `set_content` rendering was used instead and is labeled accordingly.
- Initial local test invocations incorrectly supplied report-file paths to several finance tests whose first argument is an input source file. Corrected documented invocations passed; original logs remain in evidence. Two finance boundary scripts require their native database fixture and are not standalone passes.
- An older hosting-plan-view test initially expected a repeated password field; the replacement test proves absence of that control and denial after owner-session revocation while retaining other guards.
- Early synthetic auth-version and query-input failures were corrected by using canonical auth-version fixtures and rejecting malformed page input. No production authorization checks were disabled.
- Not all historical native/browser fixtures have yet been migrated from repeated-password expectations to current-session expectations. Acceptance source pins and combined workflow wiring require deliberate review. Do not simply refresh every pin, remove denials, or call the entire application suite green.
- No real 20i catalog was refreshed, no live import ran, no product was saved/published, no customer account changed, and no payment/provider operation occurred.
- The current connector exposes no GitHub write action and local GitHub network access failed. No commit, push, PR update or workflow dispatch was made by this change set.

## Required integration / release gates

1. Preserve all source branch histories and the #308/#309 sandbox security boundary. Normally merge the hosting stack into the current reviewed licensing candidate, reconcile shared files, then apply/review the new session and workspace changes. Do not overwrite current root workflows using the older PR307 scaffold.
2. Use the before/after manifest to detect drift. The supplied patch targets the exact LOCAL baseline, not arbitrary `main` or an unknown server directory. Apply only in a disposable development checkout; never run it against live customer files.
3. Review session-only confirmation for account/key/payment changes and ensure revoked/stale/nonowner/support sessions still fail at service boundaries. Keep explicit confirmation and audit records for destructive operations.
4. Complete PHP 8.2/8.4 with MariaDB/MySQL native integration; fresh and populated upgrade from published 1.25 and the deployed 1.26 RC; preserve encryption keys/configuration/customer records, invoices, license domains and permanent payoff rights.
5. Exercise actual plan retrieval with zero hosted instances, named/unnamed/empty/error responses, batch and repeated imports, duplicate avoidance, editable prices/images, product search/pagination, explicit publication and public monthly/annual offers.
6. Run installed desktop/mobile browser journeys, CSRF/tenant/owner permission denial, checkout/invoice replay, payment/financing regressions and upgrade/recovery. Any external sandbox merchant/provider action needs its separately authorized credentials and run scope.
7. Select and consistently set a NEW candidate/release version before producing an UPDATE. This local source still inherits 1.26.0 and is not a new tagged release; do not assume an updater will accept a same-version replacement.
8. Regenerate inventory, build from one clean recorded remote commit, verify standalone archives with no nested ZIPs, then review before deployment. Rerunning the old RC workflow does NOT incorporate this local work.

## Delivery status

Historical upgrade-fixture ZIP files are intentionally excluded from both the delivered patch and bundle; retain their original repository versions. The review bundle contains no nested ZIPs or font files.

The attached ZIP is a **developer review bundle**, not an upload/install package. It contains the source patch, changed source files, pre/post hashes, focused reports, preserved limitations, and synthetic UI previews. The live website remains unchanged.
