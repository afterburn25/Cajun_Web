# Client access after hosting confirmation

This development change is on `codex/twentyi-hosting-portal-handoff`, based on hosting successor `512656850e1d9855b921ab4e3b9b4a891b636b16`. It is not in a published release.

A confirmed hosting order records that the exact purchased package was observed enabled. The client Hosting page separately uses an explicit package-to-client assignment. Previously, the confirmed order gave no direct guidance for completing that assignment.

The owner’s confirmed-order card now shows **Client hosting access**, the expected client and provider package, and the saved assignment state. **Review client access** opens the package card in the existing 20i provider inventory when that package is available under the original connection; otherwise it opens the inventory section for review. Observed and previously linked package cards have stable anchors.

Refresh the inventory and verify the package belongs to the expected client. Use the existing **Link to client** or re-approval form and current owner password as appropriate. A package linked to another client requires ownership review. An inactive client, changed connection, missing observation or stale link is identified explicitly.

The card reads saved records under the existing owner authorization and lock. It does not establish current live availability or grant access. Existing POST checks, CSRF protection, password verification, assignment conflict checks and transaction behavior are unchanged. Client views and provider operations are unchanged. The existing linking action uses a last good observation from the current profile revision; it has no new observation-age guarantee.

## Validation and remaining work

The three changed PHP files pass syntax checks. The exact proposed projection and renderer passed 68 synthetic checks in normal PHP 8.4.25 and the same 68 in minimal PHP. These cover assignment states, identity restrictions, escaped client text, owner denial, wrong-client denial, read-only query behavior and package navigation. They are modeled dependency checks, not native SQL, HTTP or visual acceptance.

The canonical build refresh updates the installer dependency inventory for the changed shipping files. This branch changes no test workflow, existing assertion count, schema, API request or version label. The frozen 1.26 candidate and current focused hosting source remain unchanged.

Before integration/release, run existing native fulfillment and inventory tests on the combined source and inspect actual desktop/mobile navigation. Complete one joined imported offer through client request, agreement consent/activation, billing consent/activation, first invoice, verified synthetic receipt, hosting order, synthetic fulfillment, exact confirmation, explicit owner package assignment and client Hosting visibility. Verify other-client exclusion, replay, concurrent assignment, audit rollback and recovery. The prepared hosting-offer fixtures currently stop before that entire joined flow.
