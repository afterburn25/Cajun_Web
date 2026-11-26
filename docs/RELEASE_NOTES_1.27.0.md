# CajunWebPro 1.27.0 release notes

CajunWebPro 1.27.0 is the compatible minor successor to published 1.26.1. These notes describe the qualification candidate; no tag or public release has been created.

## Customer identity and hosting

Customers can register publicly, verify an email-bound identity, request a new verification message after expiry, recover a password, and resume the checkout they started. Shared-hosting plans now have an installed storefront and checkout with durable gateway evidence and fulfillment states. VPS checkout remains explicitly unavailable until its separate product and provisioning requirements are accepted.

## Catalog, licensing, and administration

The catalog and licensing workspace now covers software upload onboarding, license issuance, product readiness, managed artwork, and customer/admin review. Cloud-site intake, notification health, and immutable staff-only project notes improve administration without broadening live-provider authority.

## Mail

The administration mail workspace now supports account-scoped inbox folders, message views and flags; plain-text and sanitized HTML drafts; Reply, Reply All, and Forward; bounded private attachments; local submission history; Sent-folder copy attempts; and auditable failure states. Setup and qualification do not contact a mailbox or send customer mail.

## Upgrade and packaging

The 1.27.0 UPDATE is staged below `cwp-update-1.27.0/` and is intended only for a verified 1.26.1 installation. It preserves configuration, installed locks, external private storage, media, customer records, keys, licenses, payments, projects, mailbox metadata, provider assignments, and operation journals. The database dashboard now offers every additive 1.27.0 schema change, including the formatted-draft column that published 1.26.1 does not contain.

FULL-PROJECT, COMPLETE-UPLOAD, and UPDATE packages are produced from one clean recorded commit with per-package manifests, inventories, aggregate manifest, and SHA-256 inventory.

## Security and operational boundaries

Package and upgrade acceptance uses the exact immutable published 1.26.1 asset and manifest. The verified archive bytes are snapshotted once and those same bytes are extracted, closing the predecessor time-of-check/time-of-use gap. ZIP traversal, case collisions, reserved Windows names, symlinks, encryption, nonregular members, unexpected hosts, unsafe redirects, ambient proxies, and overwrite attempts are rejected.

No live charge, refund, provisioning, provider write, customer message, SSH/support session, production deployment, tag, or publication is part of qualification.

## Known limitations

- VPS checkout and automated paid provisioning remain unavailable.
- Live provider, gateway, mailbox, and SMTP behavior requires separately authorized environment-specific validation.
- UPDATE supports published 1.26.1 only; older installations must follow their published upgrade sequence.
- A qualified GitHub release does not establish a production deployment.
