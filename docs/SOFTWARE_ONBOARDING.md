# Guided software onboarding

The Catalog & Licensing page provides an owner-only path from a local ZIP to a draft software product:

`Upload package → Review product details → Review license settings → Save product`

The setup action is additive and requires confirmation of a current database and private-file backup. It creates only the `software_upload_requests` staging table. Existing catalog products, software assets, policies, licenses, orders, and publication settings are preserved.

## Safety and storage

- Uploads must be real browser uploads and are copied with an exclusive create into the configured private storage root.
- The existing software asset limits remain authoritative: 22 bytes–300 MB compressed, at most 12,000 members, at most 1 GB per member and 2 GB expanded, with traversal, ambiguous paths, nested ZIPs, symlinks, special files, and case collisions rejected.
- Metadata is read only from one unambiguous `cajunwebpro.json`, `package.json`, or `composer.json` at the root or one directory below it. Package code is never included or executed. Name, version, embedded SKU/module key, and declared engine requirements are the only supported detected fields.
- Compatibility is reported as package metadata or “Not detected”; upload does not claim runtime compatibility or inject license enforcement.
- Staging and final asset rows retain a storage-relative reference, checksum, and byte size. The browser never receives a server path.
- Unfinished staging is durable and actor-bound. Failed saves retain a recoverable request. Unfinished bytes older than 48 hours are removed only when deletion succeeds; the audit row remains expired. Completed bytes are never cleaned as staging.

## Atomic save and identity

The final mutation rechecks current owner, catalog, and staff authority while holding the shared staff mutex and one database transaction. It composes the existing `catalog_save_in_transaction`, software policy, and software asset operations. A late failure rolls back all database writes and leaves the validated staging request available for review.

New products use an embedded supported SKU when present. Otherwise, CajunWebPro generates a deterministic `SOFTWARE-*` SKU from the reviewed name. The SKU remains attached to the product across later name and version changes. Existing product selection must match an embedded SKU.

New products are always draft, owner-private, one-time products with no price, public terms, public summary, cost, or provider reference invented. The default policy permits two installations and has no time-limited update window; a detected module key is retained. Existing policies are not overwritten.

An identical repeated SKU/version/checksum resolves to the existing asset. Different bytes for an existing version are rejected. A new version must explicitly target the existing software product. Publication, customer licensing, payments, provisioning, customer messages, and deployment remain separate reviewed actions.
