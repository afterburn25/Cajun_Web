# Owner-reviewed 20i plan mappings

This increment connects a published CajunWebPro retail plan to a reusable, owner-reviewed 20i account/package type. It is not automatic provider catalog discovery, a provider quote or proof of entitlement. The published release remains 1.24.0; this branch is unreleased.

On a published **Service plan**, the owner can prepare mapping history, enter the exact reseller/type/location and known limits, and save an enabled or withdrawn review for 1–30 days. Package defaults must be reviewed in the provider account: welcome mail, automatic applications, SSH keys and Website Turbo can have effects beyond creating an empty site. The application sends no provider request from this form.

Provider cost is optional private reference evidence. An exact decimal string in **major currency units**, its explicit currency, whole-month period, tax treatment and per-package/shared-account basis are retained separately from the immutable client retail price in USD cents. Unknown cost stays unknown. No currency conversion, per-client allocation, margin, provider quote, invoice change or charge is inferred. Reference text must not contain credentials.

An eligible accepted hosting order offers **Prepare from saved mapping**. Submission binds the exact client-accepted retail plan, current mapping fingerprint, profile revision/credential digest, original reviewer authorization and unexpired review inside the same locked transaction that creates the existing local service association. Payment or explicit manual fulfillment and current owner password remain required. Different plans, replaced/withdrawn/expired reviews, retired retail plans, changed connections and revoked reviewers cannot supply a new preparation.

The association stores the immutable mapping ID and digest. Existing format-1 manual associations retain their original contract. New format-2 catalog associations validate their historical source; withdrawal or retirement preserves history without rewriting service terms. A mapping is checked when preparing a service. The existing separate, current fulfillment review still checks package defaults, costs and account availability before any provider setup; catalog selection is never dispatch authority.

Mapping revisions are append-only with predecessor hashes, strict setup/table/index/foreign-key checks, audit correlation and one-use request identity. Missing records, setup markers or tables cause refusal and require original database restoration; GET never prepares tables. Staff support sessions and ordinary clients cannot manage or view private catalog evidence. Client service progress remains the existing restricted projection.

## Source and limitations

Read 2026-10-15: the official [addWeb guide](https://docs.20i.com/api/provision-hosting-package-api) identifies `type` as a packageTypes ID. The [package type guide](https://docs.20i.com/reseller-hosting/create-hosting-package-types) documents limits and automatic package defaults. The [API documentation guide](https://docs.20i.com/api/20i-api-documentation) directs account documentation access through My20i. The retained owner-reference summary does not supply the packageTypes response schema; no schema, response, offering, price or account entitlement is invented here. Automatic discovery and authenticated account acceptance remain unfinished.

## Verification checkpoint

Branch `codex/twentyi-catalog-controls` starts at `0ebc46237fce565005b581fe5a96a2691b9840b5`. The enclosing implementation commit records its resulting SHA in Git history. Local PHP 8.4 default/no-INI each pass **51 named pure catalog document checks** in `tests/provider_plan_catalog_document.php`; every changed/new PHP source passes syntax checks. These checks make no network or database request.

`tests/provider_plan_catalog_cases.php` extends the real native recurring/order suite with additive setup, schema refusal, atomic audit rollback, replay/conflicts, owner/client/support isolation, profile/reviewer invalidation, expiry, mapping replacement/withdrawal, actual rendered preparation intent, original retail terms, immutable association provenance and missing-history recovery. It is pending Linux PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 execution at this checkpoint. The Windows private-key protection is preserved; no native pass is claimed locally. Actual HTTP/Chromium, independent review and exact packaged acceptance remain required. No live API read/write, payment, customer mail, SSH, deployment or release occurred.
