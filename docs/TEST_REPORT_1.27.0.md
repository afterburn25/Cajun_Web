# CajunWebPro 1.27.0 qualification report

Status: local candidate qualification in progress; hosted exact-source acceptance and independent review remain required. No release tag, publication, deployment, or live operation has occurred.

## Candidate lineage

- Published baseline: v1.26.1, source `f2d98579b7e18c3dbadf2ad2f2f5a63f757f3391`.
- Baseline COMPLETE-UPLOAD: 17,429,306 bytes; SHA-256 `7d7117a25fab556cb3f8dfce7f0b859d072cfc992b98fc91026d96b490e1dad3`; 3,072 members.
- Baseline manifest: 482,435 bytes; SHA-256 `a903b2850ab4ff25ed94ceddd1794370341865183ee515a72bd816cd5324d0ee`.
- Verified starting main: `f73515c681cafc0d46fb117f0999b597fa05eed5`, tree `92c64a1c204ecda4befd8e255af271390d28f6b5`.
- Exact 1.27.0 candidate commit/tree/run/artifact identities will be recorded after review and first-attempt hosted acceptance.

## Acceptance matrix

| Gate | Required result | Current state |
|---|---|---|
| Version/generated source | 1.27.0 everywhere; clean refresh | Local pass; hosted pending |
| Deterministic packages | FULL, COMPLETE, UPDATE plus manifests/inventories/checksums | Pending |
| Local security/refusal tests | Safe ZIP, immutable snapshot, trusted fetch, no source drift | Pass |
| Full Application | All jobs and exact same-attempt aggregate pass | Pending |
| PHP/database checkout/payment | PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 | Pending |
| Published 1.26.1 upgrade | Root/subfolder, interruption, recovery/reapply, retained data | Pending |
| Apache fresh/upgrade | Rewrite/no-rewrite root/subfolder | Pending |
| Browser | Installed customer, checkout/payment, administration, and mail journeys | Pending |
| Independent reviews | Implementation and security clear | Pending |
| Resulting main | Natural workflows and full exact-main acceptance pass | Pending |
| Publication | Separately authorized tag/assets/download-back verification | Not authorized |

Failures, infrastructure interruptions, skipped lanes, mocks, and sandbox evidence remain distinct from passing product assertions and from live acceptance.

## Local evidence before the review commit

- The strict baseline fetcher downloaded release asset `711656635` and manifest asset `711656643`, then verified the pinned source, byte counts, SHA-256 values, 3,072 member paths, and every member hash.
- Published-input refusal coverage passed 23 named checks.
- Upgrade workflow and immutable-archive refusal coverage passed 19 named checks.
- Builder rejection coverage passed 94 named checks.
- Database schema registry, view, and authorization policies passed 405, 12, and 14 named checks respectively.
- A second `tools/build.py --refresh` pass reported 40 locked packages, 2,188 dependency/license files, 2,207 mail-receiving runtime files, six PHPMailer source/license/metadata files, 15 submission runtime files, and no generated drift.
- Python syntax, workflow YAML syntax, JSON syntax, PHP syntax, and `git diff --check` passed for the changed release surfaces.

The combined artifact/composition test is intentionally rerun only from a clean recorded commit because its provenance preflight refuses modified tracked source. Passing that gate, deterministic candidate package evidence, hosted database/Apache/browser results, independent reviews, merge identity, and resulting-main evidence will be appended without replacing or concealing earlier failures.
