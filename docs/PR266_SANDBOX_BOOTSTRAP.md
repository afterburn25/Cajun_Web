# PR266 sandbox workflow registration

## Purpose and boundary

The software and financed-site real Authorize.Net sandbox workflows are manual-only and currently exist on the licensing source branch, while default main lacks both definitions. [GitHub's manual-dispatch requirement](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow) requires registration on the default branch.

This main-based proposal copies the two existing manual workflow definitions, their two standalone sandbox-only provider probes and this guide. It does not add application PHP, credentials, a new product version or a production-payment switch. The probe bytes match the reviewed integration candidate. They use contents:read, explicit sandbox confirmation, isolated dependencies, exact source hashes, non-overlapping execution and checkout without persisted Git credentials.

The manual jobs accept secrets only when dispatched from `main`. The test drivers do not load candidate application PHP, so selecting another branch would add no application assurance and would unnecessarily expose the sandbox environment to branch-selected code. These runs are provider-contract evidence only; candidate application behavior remains covered by the separate installed/native suites.

## Reviewed execution sequence

1. Devin or the repository owner reviews this isolated registration and completes its required checks/independent approval before normal integration into current main. No review or main merge is claimed by this proposal.
2. In GitHub Actions, choose Software Authorize.net real sandbox acceptance or License financing Authorize.net real sandbox acceptance. In Run workflow, select `main`. Any other ref is rejected before checkout or secret use.
3. Confirm sandbox-only execution. The existing secret configuration must provide ANET_SANDBOX_API_LOGIN_ID, ANET_SANDBOX_TRANSACTION_KEY and ANET_SANDBOX_PUBLIC_CLIENT_KEY. Keep them in GitHub's approved secret storage. No secret is needed in this document, PR or chat.
4. Inspect the actual run and its non-secret report. The software test exercises Accept.js, one-time sandbox charge/readback and ARB creation/cancellation. Financing covers sandbox initial payment, finite enrollment, cancellation/readback and finite replacement. These provider-contract results do not substitute for installed ledger, failure, webhook, reconciliation or full release acceptance.
5. Continue PR266's installed/native/root/subfolder/populated upgrade, payment/PCI/legal/security review and exact merged-main/release gates. Production charging and deployment remain separately owner gated.

## Validation and current state

The proposed provider probes are byte-identical to the reviewed integration-source counterparts and are pinned by SHA-256 in their manual-only workflows. A source contract requires the `main`-only dispatch boundary, exact checkout SHA, disabled credential persistence, serialization, driver path and driver digest. The workflows execute only from `main`, do not persist checkout credentials and serialize sandbox runs. The bootstrap has no automatic gateway job. Local documentation checks and source-delta review are recorded with its PR. Real sandbox execution, secret availability and independent approval are not claimed.

Current default main observed: fdf40a25be3b9a7c2e154a1fad17ab4c7ae55827. Licensing corrections: [PR268](https://github.com/afterburn25/Cajun_Web/pull/268), targeting [PR266's branch](https://github.com/afterburn25/Cajun_Web/pull/266). Existing main, Devin's checkout, published v1.25.0 and all production settings are preserved.
