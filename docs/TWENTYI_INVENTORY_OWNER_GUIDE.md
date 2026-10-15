# 20i inventory and operation recovery

This guide describes published1.24.0, verified at7190b582. [Release evidence](TEST_REPORT_1.24.0.md). Earlier1.23 assets remain unchanged; complete20i workflows beyond this increment are still in progress.

## Refresh and client ownership

Open the owner-only 20i connection page, save an enabled General API key and prepare inventory storage with your current password. Existing version1/2 storage is upgraded additively; back up the database and original private keys first. Page views do not change the schema.

Refresh explicitly to obtain an observation. An incomplete or failed observation is not proof a resource has disappeared. Link a package or one of its observed domains to an existing client explicitly. A domain-only link exposes only that domain. Registrar purchases and renewals require separate authority; a hosting domain is not registrar ownership.

Changing the saved connection invalidates prior approvals. Refresh under the new configuration and approve the link again. If a domain moved to a different package, its original binding stays visible to the owner; unlink the old pair and approve the intended new pair. A client cannot see private details of the new package through the old link.

## Package label requests

On 20i operations, prepare operation storage, enter the package ID and label, choose an approval duration and confirm your password. Review the stored fields, then use a unique request key and confirm your password to send once. Used, expired and revoked approvals cannot send. Reusing a request key cannot send the same operation again.

This screen admits label-only changes. Purchases, renewal, deletion, billing, plan changes and email need dedicated order and consent workflows that remain under development. Older approvals for those actions are retained but cannot be sent here.

## Unknown results and recovery

An uncertain result may have reached20i. Do not repeat it blindly. The history preserves the original attempt. Check the provider now performs a bounded observation where supported. A matching domain name alone cannot prove a request created a service. A provider-issued ID can identify a historical pending package, but its presence does not certify active fulfillment. Label changes cannot currently be confirmed from package-list evidence and remain inconclusive.

If an established ledger or approval table is missing, restore the original database together with its private keys. Preparing storage must not create empty replacement history: that would forget requests and could allow duplicate external effects. Do not reinstall over customer data. Keep backup and original operation records until recovery is verified.
