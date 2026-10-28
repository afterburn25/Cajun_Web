# PR266 sandbox workflow registration

## Purpose and boundary

The software and financed-site real Authorize.Net sandbox workflows are manual-only and currently exist on the licensing source branch, while default main lacks both definitions. [GitHub's manual-dispatch requirement](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow) requires registration on the default branch.

This main-based proposal copies only the two existing manual workflow definitions plus this guide. It does not add application PHP, a gateway driver, credentials, a new product version or a production-payment switch. The workflow copies match the reviewed integration candidate. They use contents:read, explicit sandbox confirmation, isolated dependencies, and existing sandbox-only scripts on the selected source ref.

## Reviewed execution sequence

1. Devin or the repository owner reviews this isolated registration and completes its required checks/independent approval before normal integration into current main. No review or main merge is claimed by this proposal.
2. In GitHub Actions, choose Software Authorize.net real sandbox acceptance or License financing Authorize.net real sandbox acceptance. In Run workflow, select the reviewed licensing integration branch containing the test drivers (currently codex/licensing-integration-review). Selecting main before the drivers are integrated fails the source-contract step before credentials are used.
3. Confirm sandbox-only execution. The existing secret configuration must provide ANET_SANDBOX_API_LOGIN_ID, ANET_SANDBOX_TRANSACTION_KEY and ANET_SANDBOX_PUBLIC_CLIENT_KEY. Keep them in GitHub's approved secret storage. No secret is needed in this document, PR or chat.
4. Inspect the actual run and its non-secret report. The software test exercises Accept.js, one-time sandbox charge/readback and ARB creation/cancellation. Financing covers sandbox initial payment, finite enrollment, cancellation/readback and finite replacement. These provider-contract results do not substitute for installed ledger, failure, webhook, reconciliation or full release acceptance.
5. Continue PR266's installed/native/root/subfolder/populated upgrade, payment/PCI/legal/security review and exact merged-main/release gates. Production charging and deployment remain separately owner gated.

## Validation and current state

The proposed workflow files are byte-identical to their integration-source counterparts, pass Actionlint1.7.12, and retain their manual-only triggers. The bootstrap has no automatic gateway job. Local documentation checks and source-delta review are recorded with its PR. Real sandbox execution, secret availability and independent approval are not claimed.

Current default main observed: fdf40a25be3b9a7c2e154a1fad17ab4c7ae55827. Licensing corrections: [PR268](https://github.com/afterburn25/Cajun_Web/pull/268), targeting [PR266's branch](https://github.com/afterburn25/Cajun_Web/pull/266). Existing main, Devin's checkout, published v1.25.0 and all production settings are preserved.
