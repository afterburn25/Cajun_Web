# Saved website folder observations

This isolated application branch, `codex/twentyi-document-roots-observations`, starts from accepted pure head `0ddd5101ad3537de1015420c1d23807010d2165c` and normally merges reviewed expiry `18d296b714adc24fa967926ffeadc28616ac6915` at `83851011`. It is outside the frozen release candidate. The pure [document-roots contract](TWENTYI_DOCUMENT_ROOTS_CONTRACT.md) and its verified source remain unchanged.

## Owner and client behavior

An active owner explicitly enables local observation storage with their current password. Opening settings or a client page never prepares storage or calls the provider. The owner refreshes one assigned package using an exact current form. The existing profile cooldown and per-owner hourly budget apply. The reported website names and folder values are saved with their observation time; they are displayed only as escaped text.

Sharing is off by default and separate from PHP-version or hosting-history sharing. After reviewing a fresh complete map, the owner can share that observation and later maps for the exact approved assignment with its client. Revocation removes client visibility immediately. A reassignment, reapproval, disabled client/profile, profile revision or sealed credential change invalidates prior authority. Support sessions and other clients cannot use the direct client view. The client projection contains only a finite status, bounded name/path text pairs, their time and historical/current label.

A newer failed or unfinished read cannot make an older map current. The prior valid map may remain explicitly historical under the same sharing approval. `{}` creates an explicitly empty saved map and is never evidence that no websites, names or folders exist. The documented `[]` response is unavailable and saves no map. Duplicate decoded keys, unsupported values, malformed JSON and raw/exact-Base64 credential echoes retain only finite unavailable reasons.

No observed path is normalized, joined, opened, linked as a filesystem target or executed. Empty strings and unusual relative/absolute-looking path text retain their original meaning as observations only. This feature provides no DNS editing, file manager, ZIP delivery, provider write or live account acceptance.

## Shared execution contract

The distinct `DocumentRootsBinding` carries the structural package witness under operation `package.document-roots`; it does not inherit PHP-version permission. New application authority independently validates the current explicit package/client link, exact profile/revision/sealed-credential digest, link/relink event and audit, and latest complete post-assignment package observation with its original history. The credential-owning reader checks the saved account and fingerprint before its fixed GET `/package/{allocated packageId}/web/documentRoots`.

The existing shared executor appends named `documentRootsContext` at read argument9 / refresh argument11, after the preserved domain, PHP and expiry slots. Wrong/missing contexts are refused before credential resolution. Current authority is rechecked at intent creation, before credential/transport access and after the response. The fixed transport adds only this exact GET with its existing headers, null body and timeout; no generic path prefix or new provider client is introduced.

The existing guarded recorder admits the exact typed observation exclusively. Its roots readiness check runs before common result insertion. The scheduler's owning transaction/mutex entry and original public recorder behavior remain intact. Three new tables hold operation-bound read witnesses, complete map documents and append-only sharing revisions. The latter reference a reviewed complete observation and its immutable proof, without changing existing client assignments.

## Storage and recovery

Preparation is explicit and rejects caller-owned transactions before DDL. Empty interrupted preparation can be retried; surviving marker, counters, audits or data require recovery instead of silent history replacement. The three families use InnoDB, strict schema/index/foreign-key validation and the existing account mutex and transaction.

Independent started/finished/permission counters reconcile shared intents, results, dedicated witnesses and exact actor/audit rows. Fixed SQL digests bind the original sequence, context, canonical map bytes and permissions, including failed/unfinished attempts. HMAC witnesses bind retained original package sources and the exact assignment. A missing or changed row, case-altered audit, sequence rollback or paired loss refuses access. No read path repairs data. Restore the original consistent database history before another refresh or sharing approval; there is no replay or erase-history recovery button.

Sharing revisions bind the previous document hash, original anchor and exact client assignment. Canonical JSON keeps opaque UTF-8 paths safely represented as ASCII escapes at rest; rendering always escapes decoded text. Provider response bodies, credentials and passwords are never retained in these observation/audit documents.

## Current evidence and limits

Root approved the bounded shared contract before edits. Independent domain-agent source review finds no blocker in the initial runtime manifest `qa/document-roots-runtime-review.json`; it is source review only. Local PHP8.4 normal/minimal runs each pass143 accepted pure cases plus24 production transport-admission checks with no network. Changed PHP syntax and Python AST checks pass. The first local admission fixture constructed a rejected HTTP descriptor outside its catcher; its test-only boundary was corrected, with no production change.

Prepared native tests cover independent preparation, transaction/audit rollback, exact authority, immutable SQL/PHP byte witnesses, client/support isolation, sharing/revocation, credential change/restoration, failed/unfinished order and paired-loss recovery, map/empty/error/duplicate/credential semantics and the unchanged quota. They now pass with synthetic transport on all four actual supported PHP/database combinations in exact source e2b9fd6d. Prepared browser tests use a distinct owner activated through existing forms, the reviewed post-login baseline, actual cooldowns and five exact reads while preserving inherited intent/audit bytes. They inspect owner/client/history/empty desktop/mobile views, private route refusals and safe text rendering. Browser execution is pending.

The focused matrix also runs the existing PHP-version, expiry and scheduler native suites against this shared executor. Root reviewed the exact runtime and corrected fixtures before the one automatic focused matrix. Installer refresh succeeds;2,783 staged shipping files match disk and2,782 generated hashes. The native matrix passes; actual packaged browser/screens, combined acceptance and root integration remain required. No live request, release or deployment occurred.

Root review identified two native paired-loss audit patterns using the wrong textual prefix. The fixture now deletes the exact durable `Document-root read` / `Document-root permission` audits and explicitly verifies one affected row and absence, so a silent pattern mismatch cannot pass. All reviewed shipping bytes remain unchanged. Full/browser execution is deliberately deferred to root's later normal integration with corrected period/PHP setup baselines in03b28476; this isolated branch retains expiry18 history and must not launch its inherited standalone full workflow.

## Exact native evidence — 2026-10-16

Frozen source `e2b9fd6d4b8705feec0cf71973a97600f531d61b` (tree `d7aa223dfd4317769e27e93cc4ec89a9d162561d`) passes its single automatic push [run38859581032](https://github.com/afterburn25/Cajun_Web/actions/runs/38859581032). Every downloaded artifact matches the GitHub source, SHA256 and byte size, has a valid CRC and safe unique ZIP members, and contains all eight expected reports with no failed outcome. Local proof is `qa/38859581032/verification.json`; complete named checks and original ZIPs are retained alongside it.

| PHP/database | Artifact | Bytes | SHA256 |
|---|---|---:|---|
|8.2 / MariaDB10.11|12029021369|13355|`6b13ac86882dd5338e58ecb0263ac0b63704f312880a41fa3a6e78d6f05a0817`|
|8.2 / MySQL8.4|12028108339|13358|`4371119214e881fe61e40b23ceba4505d0cab542af174f97a46ad08e6b50f641`|
|8.4 / MariaDB10.11|12028083473|13362|`19a6a27bea0a3e17f751a1d35456239ea2a3cd7e31f9e378d9a2682b12f87a2d`|
|8.4 / MySQL8.4|12028078607|13363|`5734ad9753b92c8dc345a0c0e404b9b6c2e5bf091b7347c881e9363d9ec2c29d`|

Each environment passes115 roots executions/114 distinct labels,143 default+143 minimal pure checks,24+24 transport checks,99 PHP-version executions/98 labels,111 expiry and102 scheduler checks:761 executions per environment,3,044 total. The roots and inherited PHP fixtures each repeat one stale-value assertion for failed and unfinished hosting reads; the verifier records both executions and the exact duplicate label rather than overstating unique cases. The initial local verifier treated the roots duplicate as unexpected despite every case passing; its initial proof is preserved and its exact duplicate policy was corrected without changing tests or production source.

These are actual Linux PHP/database and application-renderer checks with synthetic transport. They do not certify the unexecuted HTTP/Chromium flow, packages, live provider account or release. Root owns the later normal merge and combined browser/package acceptance; preserve its03b28476/659 period/PHP login baselines and the expiry18 baseline. The evidence-only descendant leaves shipping, tests, workflows and generated installer byte-identical to tested e2b9.
