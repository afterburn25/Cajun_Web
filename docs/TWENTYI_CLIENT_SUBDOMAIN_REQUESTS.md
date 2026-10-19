# Client subdomain requests

## Development scope

Branch `codex/twentyi-client-subdomain-requests` starts from owner subdomain source `b8c316b6fd138167b11c251d5db24a6d31f2bd88` in [PR234](https://github.com/afterburn25/Cajun_Web/pull/234). It extends that [owner workflow](TWENTYI_SUBDOMAIN_ADDITIONS.md) with client intake, studio review and request progress. This is an unreleased candidate. Native database, installed application and release acceptance remain required.

## Client and owner workflow

After preparing the 20i connection and existing owner subdomain workflow, the owner enables local intake in **Hosting & Domains → Subdomain requests**. This enables intake for currently assigned primary clients. They can open **Hosting services → Subdomain requests**, select an assigned website and request one immediate child such as `shop.example.com` beneath `example.com`. Submitting a request saves it for review. Clients may withdraw a pending request. A new installation without provider settings shows setup guidance and a link to the connection page.

Available parent websites come from the latest authenticated complete hosting inventory for the current assignment and provider account. An older successful read is not substituted for a later failed, unfinished or incomplete read. This list identifies saved website names; it does not establish current provider capacity. Clients see no private website-folder paths or provider credentials.

The owner sees the requesting client's name and client number, reviews the exact request, and may decline it with a message or approve it using the current owner's password. Approval also requires the existing fresh hosting inventory and complete website-folder observation, including the selected parent and the new child's absence. A normal inventory refresh leaves the client's pending request intact when its assignment and account authority remain unchanged.

Approval records the client request and its one provider attempt together. A separate provider read checks whether the new name is reported. The client can follow that saved result. A reported name is not a claim that this request caused the change or that DNS, certificates or the website are working.

## Authority and retained history

Only the primary client of the current assignment may submit or withdraw a request. Contact and support sessions cannot perform these actions. Original client account authority is checked again when the owner approves and at the provider execution boundaries. Reassignment or changed credentials cannot redirect an old request to a new context.

One pending request per provider account, package and child hostname prevents duplicate intake. The rolling daily limit is ten new requests per client. Reposting the same request identity preserves the original request; it does not create another provider attempt. Existing uncertain owner attempts continue to fence that hostname under the original owner workflow.

The local request history and provider approval retain reciprocal origin references. Missing or altered records require recovery of their original evidence. GET requests do not create tables or contact 20i. Preparation is an explicit owner action. The feature adds no client provider-write permission, automatic DNS changes, certificate setup, deletion or custom document-root editing.

## Verification

The [local verification receipt](evidence/client-subdomain-requests-local.json) binds source and named reports. Local PHP 8.4.25 checks pass in default and minimal modes: 102 document, 92 storage, 51 service, and 101 controller/renderer checks. The existing 65 owner service, 39 owner view, 127 typed transport and 230 operation checks also pass in both modes. These use modeled records and boundaries; they do not establish native SQL or provider acceptance.

Local Chromium layout checks pass for six synthetic desktop/mobile views using the actual renderers and shipping stylesheet order. These are not installed login or database-driven browser flows. The native fixture is prepared for Linux MariaDB/MySQL and refuses before bootstrap on Windows. Its real database, synthetic gateway, two-process contention, audit rollback and separate-readback scenarios remain unexecuted locally. Published 1.25.0 and the live site remain unchanged.

The canonical installer refresh and all 25 touched PHP syntax checks pass. The portable acceptance helper passes 429 named outcomes, retaining all 297 predecessor objects in order. Nine additive commands/reports bring the required inventories to 168 native commands, 180 browser-phase reports and 224 final reports. These inventory labels do not turn the new PHP/native reports into browser evidence. The new installed owner/client workflow still needs dedicated HTTP/browser coverage before release. Original workflow branch triggers, concurrency settings and prior captures remain unchanged.
