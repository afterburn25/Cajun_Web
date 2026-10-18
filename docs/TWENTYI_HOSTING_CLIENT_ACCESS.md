# Client access after hosting confirmation

This development change is on `codex/twentyi-hosting-portal-handoff`, based on hosting successor `512656850e1d9855b921ab4e3b9b4a891b636b16`. It is not in a published release.

A confirmed hosting order records that the exact purchased package was observed enabled. The client Hosting page separately uses an explicit package-to-client assignment. Previously, the confirmed order gave no direct guidance for completing that assignment.

The owner’s confirmed-order card now shows **Client hosting access**, the expected client and provider package, and the saved assignment state. **Review client access** opens the package card in the existing 20i provider inventory when that package is available under the original connection; otherwise it opens the inventory section for review. Observed and previously linked package cards have stable anchors.

Refresh the inventory and verify the package belongs to the expected client. Use the existing **Link to client** or re-approval form and current owner password as appropriate. A package linked to another client requires ownership review. An inactive client, changed connection, missing observation or stale link is identified explicitly.

The card reads saved records under the existing owner authorization and lock. It does not establish current live availability or grant access. Existing POST checks, CSRF protection, password verification, assignment conflict checks and transaction behavior are unchanged. Client views and provider operations are unchanged. The existing linking action uses a last good observation from the current profile revision; it has no new observation-age guarantee.

## Validation and remaining work

The three changed PHP files pass syntax checks. The exact proposed projection and renderer passed 68 synthetic checks in normal PHP 8.4.25 and the same 68 in minimal PHP. These cover assignment states, identity restrictions, escaped client text, owner denial, wrong-client denial, read-only query behavior and package navigation. They are modeled dependency checks, not native SQL, HTTP or visual acceptance.

The initial implementation refreshed the installer dependency inventory for the changed shipping files. The later test continuation below adds focused workflow coverage; existing assertions, schema, API requests and version labels are unchanged. The frozen 1.26 candidate and original focused hosting source remain unchanged.

Before integration/release, run existing native fulfillment and inventory tests on the combined source and inspect actual desktop/mobile navigation. Complete one joined imported offer through client request, agreement consent/activation, billing consent/activation, first invoice, verified synthetic receipt, hosting order, synthetic fulfillment, exact confirmation, explicit owner package assignment and client Hosting visibility. Verify other-client exclusion, replay, concurrent assignment, audit rollback and recovery. The joined continuation below exercises that purchase sequence; actual execution remains required.

## Preserve the original offer mapping

The follow-up starts at `bde6cc1c4fa8a4230085ed3e4f99f05d6df0f5da`. For a catalog-origin agreement, preparation derives the mapping ID and digest from the validated original decision, request and publication. It rejects a replacement mapping or manual bypass before constructing the request. Existing saved links are checked against that same history before fulfillment; inconsistent links require recovery rather than silent reassignment. Historical validation retains the existing historical-only mapping check, while new preparation retains current eligibility checks.

The owner screen offers only the original mapping and links to the original project. An expired or superseded mapping requires a new offer and client agreement before a different setup. Ordinary non-catalog orders and separately accepted billing project choices are unchanged. No new provider operation, schema, consent rule or automatic purchase is introduced.

The five production files pass PHP syntax, independent source review, 37 exact-function/renderer checks per normal/minimal PHP mode and six actual original-fixture dependency loads. Dependency rows in the function checks are modeled; fixture bodies and SQL were not executed. Each origin lookup revalidates retained hosting history, so repeated validation still scales with that history. Measure the joined native path before claiming acceptable runtime cost.

## Current recurring purchase timing

A newly accepted recurring schedule must activate on a future billing date, and its first invoice cannot be issued before that period begins. The planned joined test uses an isolated process clock only for preparation, then real time for invoicing and fulfillment. It demonstrates an eventual recurring purchase, not immediate checkout. Billing semantics are unchanged; immediate first-payment hosting checkout remains separate unfinished work.

## Joined purchase and browser continuation

The test continuation starts from `26b57f4412db12117729901280372e2fd58f6cde`. A fresh disposable `hpci_` database namespace contains only bootstrap client/project/owner identities. Publications, requests, agreements, schedules, invoices, verified synthetic bank receipts, orders, provider-operation records and package assignments use the production wrappers. A finite synthetic transport handles the exact package creation and inventory read; no live purchase or payment is performed.

Only the preparation PHP child receives Ubuntu's process-local `libfaketime` offset of two days. The database, parent process, due phase and portal renderers keep real time. The test checks clock isolation, early-invoice refusal, replay, audit rollback, wrong-client denial and original mapping continuity. A second real accepted order must refuse replacement, manual and superseded mappings. Wrapper query counts and elapsed time are recorded without a performance threshold. This demonstrates the eventual recurring path described above, not immediate checkout.

The separate packaged Chromium continuation starts after the complete native first-link path. An owner signs in, uses the existing Unlink form, follows **Review client access** to the exact package and explicitly re-links it. The client follows **Hosting services** to see the package; another client cannot see it, including with a forged client selector. Ten new desktop/mobile captures require visual review. This is browser re-link coverage, not a claim that the original first assignment occurred in the browser.

The focused workflow preserves its original native, recovery and browser tests. New reports and screenshots are separate from private test configuration, sessions, provider keys and raw native output. Portable runner checks, syntax and source review are local evidence only. Actual Linux clock/database, installed HTTP/Chromium, screenshots, full application and release acceptance remain pending.
