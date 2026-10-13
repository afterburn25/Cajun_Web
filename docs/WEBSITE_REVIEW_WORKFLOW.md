# Website review workflow — bounded L-02 application contract

Codex owns `codex/website-review-workflow`, continuing verified 1.13.0. Preserve Devin #31 and #36 ancestry; reuse the canonical review classes rather than a parallel lifecycle. This document defines implementation and acceptance, not completion.

## User workflow

- Public website-review requests retain CSRF, consent, honeypot, one-use request identity, message/name/email validation, request limits and save-before-notification behavior. Choose contract option A: accept a bare hostname/path by normalizing to HTTPS. Keep the raw 500-byte cap, reject control characters/userinfo/fragments and unsupported schemes, validate the resulting absolute URL. Change the address input to permit that intentional broadening. No website fetch or automated audit occurs.
- Owners enable additive review storage on an older installation; fresh installation creates empty tables. Existing inquiries are never guessed from their free text. Staff with current `website.manage` may explicitly start review tracking for a Website review inquiry, supplying the validated address; existing contact/consent remain authoritative. New intake captures structured review identity with the lead atomically when storage is enabled.
- Staff opens the review from its existing inquiry. Show a separate review stage and retained history; ordinary inquiry sales status stays separate. Start received, move to in-review, save a report with 1–20 findings and a summary, mark report-drafted, explicitly record external delivery, then close. Closing is also available earlier through the canonical lifecycle. A drafted report alone never counts as delivery.
- Every finding labels human review, lab measurement, field measurement or missing data. Nonhuman findings require source and observed UTC time; reject future observation times. Never invent measurements or scores. Render all prose as escaped plain text. No unsafe remote content, attachment, visitor link or mail is generated.
- External delivery is a staff assertion: require current password, contact consent, the exact saved report revision, actual past/present UTC delivery time no earlier than the draft, channel and reference/note. Record it atomically with the delivered transition. The UI must say it records delivery outside CajunWebPro and sends nothing. No fake provider confirmation.
- Editing is allowed only before delivery/closure; editing a drafted report returns to in-review. Delivered report contents and their history are immutable. Closing retains the report and delivery record. Existing explicit permanent inquiry deletion also removes linked review content; its prompt names that consequence.

## Data and authorization

Use additive InnoDB side tables for current review state, current report content and immutable review revisions. Unique lead identity and monotonically increasing revision protect stale forms. Foreign keys tie review rows to the existing inquiry and enable explicit inquiry deletion without orphaned personal reports. Setup validates columns, indexes and relationships, refuses unknown populated tables and retains original inquiry bytes. Setup readiness and strict audit are transactional after DDL.

All staff reads/mutations recheck current authenticated staff `website.manage`; clients, support identities, disabled users and stale sessions are denied. Owner setup additionally requires current password. Mutation and lead deletion share the existing staff mutex; row locks, expected revision, canonical transitions, snapshot and strict audit commit together. Submitted revisions survive error rendering so stale writes cannot turn into a silent overwrite. Current contact consent is checked when recording delivery, not trusted from the form or a snapshot. History is paginated and access-controlled.

No new root business module, framework, daemon, customer CLI or Composer requirement. Code belongs under `site/app/marketing/`, schema/migrations under database, controller/views under admin, canonical mirrors under includes/foundation. Preserve system email transport and all previous mail/account work.

## Acceptance before publication

- Component and independent URL/lifecycle checks; explicitly enumerate bare-host broadening, fragment narrowing, retained byte cap/control/userinfo/scheme boundaries.
- Native database setup/idempotence/incompatible schema and unknown data, current permissions/support/session denial, atomic intake/deduplication, explicit legacy adoption, evidence validation, stage/report/delivery gates, audit rollback, stale forms, concurrent editors/delivery/delete, immutable history and permanent-delete cascade.
- Real HTTP/Chromium public intake and staff lifecycle, validation preservation, escaped reports, denied forged actions, desktop/mobile screenshots and current public visual comparison.
- Exact generated installer and deterministic three-package build; actual immutable 1.13.0 update/recovery preserves email credentials/assignments/templates and added review records. Retain all earlier supported update/protection tests, then test merged main and verify draft/public release bytes.

Real automatic audits, sending, client report delivery, appointments and production activation remain separate work. No live customer message, provider operation or deployment is authorized by this increment.
