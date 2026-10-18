# Service agreements — preserved 20i commercial dependency

This workflow is an unreleased dependency in PR #130, preserved from service-plan #90–#92 and recovery #101. Its inherited 1.24 development label is not a release assignment. Codex owns the complete 20i P0 integration; the separately prepared 1.24 inventory release does not contain these agreements.

The owner opens **Service plans** in administration and enables service agreements with the current password. Existing clients, projects and invoices are preserved. Create a draft with its price and cadence, included work, exclusions, usage limits and support expectations. Review and publish it. Published terms stay fixed; use a new plan when you need different terms.

Offer a published plan to an existing active client. The client opens **Service agreements** in their portal to review the exact offered scope and price. They must explicitly accept those terms themselves, or decline the offer. A staff support session cannot accept on their behalf. Acceptance records a service agreement; it does not authorize automatic payments or create an invoice.

After the client agrees, the owner can activate the local agreement and later pause, resume or end it. Clients can end eligible agreements with a reason. The portal retains the agreed terms and attributed history. These actions do not automatically provision or cancel hosting, charge a payment method, or promise a refund.

If another action changes the record while a form is open, the old form cannot replace the newer state. Review the current record before submitting again. Unsaved draft terms remain safely displayed, while passwords are never redisplayed.

Existing installations must use the published UPDATE package once this candidate passes acceptance. Keep recoverable file/database/private-storage backups and follow [installation and updates](INSTALLATION_AND_UPDATES.md). Candidate tests use the actual 1.23 package and retain saved terms, client consent, activation history, invoices, email submission history and private keys through file recovery/reapplication. These new combined recovery cases are prepared, not yet accepted.

Technical contracts: [screens](SERVICE_PLAN_SCREENS.md), [durable terms and consent](SERVICE_PLAN_RECORDS.md). Complete 20i delivery is owned by Codex under the [owner takeover](TWENTYI_CODEX_TAKEOVER.md).
