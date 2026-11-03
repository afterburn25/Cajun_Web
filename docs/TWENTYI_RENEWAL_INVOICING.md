# Create an invoice from accepted renewal terms

This increment starts at `a86f6f4fa592e6f715a9420aec8de4622258642e` on `codex/twentyi-renewal-invoicing`. An owner can create the invoice directly on the domain-renewal review screen, selecting an existing project belonging to the same client. The accepted agreement supplies the exact one-time USD fee, domain, years and privacy choice. The existing option to reserve a manually created invoice remains available.

The invoice, original version-one allocation and authenticated creation audit commit together. An identical captured submission returns its original invoice. Changed client/project/terms, lost history or a competing allocation cannot create a replacement. A late failure rolls back the complete local operation. Existing payments and invoices retain their original purpose.

Ordinary older references such as `DR-legacy` remain valid. The generated reference is exactly `DR-` plus the 32-character creation identity. A previously signed ordinary invoice with that exact shape and a different allocation identity remains readable. A historical same-identity collision without the new creation audit requires recovery because it cannot be distinguished safely from lost creation history.

The action requires current owner and payment access, current password, CSRF, explicit confirmation, current client authority and direct client consent to active renewal terms. A failed form keeps its original selection only while that selection and terms still match the review; otherwise it requires a reload. Passwords and confirmation are never repopulated. Projects have independent bounded pagination.

Creating an invoice makes no provider request, sends no email and does not charge a customer or the reseller balance. Payment verification and the existing separate one-send renewal approval remain mandatory. This increment does not implement automatic renewal, registration, transfer or provider price quoting. [Existing renewal contract and safeguards](TWENTYI_DOMAIN_RENEWAL_DISPATCH.md) remain applicable.

## Verification and delivery

Root owns integration, controller/view, installer inventory, shared acceptance wiring and documentation. Domains provides the backend and focused cases; Catalog independently reviews source and actual rendered desktop/mobile layouts; VPS prepares the installed browser scenario. Devin retains the separate non-20i assignment.

The [source-bound local receipt](evidence/twentyi-renewal-invoicing.json) lists every passing named check and retained report digest. Full local evidence is retained under `work/twentyi-renewal-invoicing-2026-10-19` outside deployment. Initial UI and helper failures are preserved beside corrected results. Modeled rendering and portable suites do not substitute for native database or installed browser acceptance. Hosted candidate acceptance, exact-main checks and release delivery remain separate gates. Published 1.25.0 assets are unchanged.

Local results: 153 renewal contract/view checks in each PHP mode, 55 actual-body creation/model checks in each PHP mode, 224 acceptance-helper checks, and 144 modeled Chromium layout/form checks across 16 captures. The corrected form uses a 44px create button, readable labels and separated project links. Native execution is pending on Linux: the actual provider-key writer requires retained `0600` permissions, so the fixture refuses this Windows host. The temporary loopback database was shut down without running application SQL tests. No permission protection was bypassed.

The full acceptance recipe retains all 152 previous commands and adds three required creation reports. The focused workflow retains its four PHP/database combinations. The installed browser extension preserves the manual-allocation success case and uses direct creation for the existing provider-failure case; synthetic transport counts must still be one registrar read and two explicitly approved renewal submissions.

## Other 20i work

The same review examined remaining database, domain and VPS contracts. The retained SDK and supplied references establish database routes but do not establish complete response identifiers or write acknowledgements. Registration and transfer still require their missing verified wire contracts. The retained cloud diagnostic returned `null`, which proves neither an empty catalog nor account entitlement; it does not supply product/spec/region/OS, price-unit or image mappings. These are concrete remaining contracts, not completed features. No new authenticated provider call was made to guess them.

Preserve the original queued catalog/capture and PR249/250 acceptance runs and their frozen source heads. This branch adds a separate increment and does not redispatch those gates or replace their existing evidence.
