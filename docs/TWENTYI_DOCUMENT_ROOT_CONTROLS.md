# Website folder change implementation

Branch `codex/twentyi-document-root-controls` starts from main `e397d9a6e293850e8dff61a593e3ba39ee178400`. The enclosing commit is **Add the documented single-website folder request boundary**. This is the first write-contract increment; it does not add a usable owner/client control or enable generic provider execution. Published 1.25.0 remains unchanged.

## Documented request and local limits

The retained [owner API reference](reference/20i-owner-api-34e713-redacted.txt), lines 432–442, documents POST `/package/{packageId}/web/updateDocRoot` with an `update` object mapping existing attached hostnames to folder strings. Lines 371–378 document the separate GET `/package/{packageId}/web/documentRoots`; its empty-array error is already handled by the [saved observation feature](TWENTYI_DOCUMENT_ROOTS_OBSERVATIONS.md).

`DocumentRootChange` admits one hostname per request. Local parameters are exactly `packageId`, `name`, and `path`; the emitted body is exactly `{"update":{"example.test":"public_html/newsite"}}`. The package identifier is a positive integer of at most 18 digits. The hostname uses the observation reader's existing narrow lowercase ASCII DNS-name rule. These are structural validations, not evidence of current ownership or hostname membership.

The local writable path subset is nonempty ASCII, at most 1,024 bytes, with slash-separated segments beginning with an ASCII letter, digit, underscore or hyphen; later characters may also include dots. Empty, dot and parent segments, leading/trailing slashes, backslashes, whitespace, percent escapes and controls are refused. No value is normalized and no empty/default-root convention is invented. This intentionally narrow product policy is not the provider's complete path grammar or proof of filesystem confinement. Existing observed paths remain opaque text, including values outside this writable subset.

The [official folder guide](https://docs.20i.com/web-hosting/change-document-root), inspected 2026-10-18, gives `public_html/newsite` and `website` examples and requires the destination directory to exist. It excludes Managed WordPress. The current saved package model does not expose an authenticated platform field; a package label cannot establish eligibility. This increment neither proves directory existence nor adds a guessed platform check.

## Execution and acknowledgement

The fixed transport admits only that exact POST route, canonical one-host JSON, existing exact JSON headers, bounded timeout and existing authorization handling. Additional fields, multiple hostnames, duplicate decoded properties, alternate JSON encodings and route variations are refused. No hostname or folder can become a URL path override.

Registration and request construction confer no application authority. Generic approval still permits only the existing package-label subset; both grant creation and execution independently refuse this new operation. No route, form, storage family, credential lookup or provider request is added here. The unchanged result classifier treats undocumented successful-status replies as uncertain, including true, false, null and arbitrary result identifiers.

## Acceptance and continuation

The additive synthetic cases run through the existing operation test entry point in focused and full acceptance. They exercise request validation, reflection-only transport admission and generic application refusal without network or a real database. Local execution and independent source review are recorded in the status and session entry. Native database, installed browser, live provider and release acceptance are not implied by these tests.

Next implement durable owner review against the latest complete authenticated map: exact existing hostname/current path, current assignment/profile/credential context, password confirmation, explicit intent and the existing one-send ledger. Preserve uncertain outcomes and prevent blind replay. A separately authorized fresh map may record whether the exact hostname reports the desired folder; it cannot prove directory contents, public routing or filesystem confinement. Platform/directory eligibility and safe interrupted-write recovery remain explicit requirements before enabling the control. Add native, packaged browser and upgrade/recovery acceptance before release.
