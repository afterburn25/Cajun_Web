# CajunWebPro 1.27.0 publication disposition

Status: qualified, published, and public-download-verified. PR #402 merged normally as `7d9c8aa7d934117a494b19a32c58c071c190d56f`, tree `4cdc3b85ee0efb6ab5dfb3dde99f2bedaad2254b`; its resulting-main cohort passed 29/29 workflows on attempt 1. Release `459005437` was published at `2026-11-26T05:43:44Z`, and all 18 public assets were fetched back and hash-verified. Principal SHA-256 values: COMPLETE `928956f0e9fff29b616c5ceccaac425880a78c3dc80c3a2c55e2511c92f53f5f`; FULL `bd5745eceaace5bf9abf54250f278f6bbb914686f6f7ae768799743c34211597`; UPDATE `4e31fdd7e8af27dea537c8437a8f506b8192b5f3f345e62f135fccd8884a4c35`; TEST-EVIDENCE `dc039c5a96845b6ccf0ed0c9048e85d9451903b10c602386dab54b126af39183`.

The report below is the preserved pre-publication local candidate record. Its pending states document what had not yet run at that checkpoint; they do not supersede the successful exact-head, resulting-main, and publication disposition above. Publication is not production deployment and does not authorize live payments, provider operations, customer messages, SSH/support access, or deployment.

# Historical CajunWebPro 1.27.0 qualification report

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
- Published-input, updater-history, candidate-identity, and extraction refusal coverage passed 29 named checks.
- Upgrade workflow, signed-package-source, provenance, and immutable-archive refusal coverage passed 25 named checks.
- The updater history entry was derived from the pinned published manifest and matches all 3,072 v1.26.1 members, the exact release source, asset IDs, archive hash, manifest hash, and `config/defaults.php` identity.
- The real-baseline fixture now retains a published-v1.26.1 plain-text mail draft and requires the reviewed migration to add `body_html` as `NULL` without changing the legacy row.
- Builder rejection coverage passed 94 named checks.
- Database schema registry, view, and authorization policies passed 405, 12, and 14 named checks respectively.
- A second `tools/build.py --refresh` pass reported 40 locked packages, 2,188 dependency/license files, 2,207 mail-receiving runtime files, six PHPMailer source/license/metadata files, 15 submission runtime files, and no generated drift.
- Python syntax, workflow YAML syntax, JSON syntax, PHP syntax, and `git diff --check` passed for the changed release surfaces.

The combined artifact/composition test is intentionally rerun only from a clean recorded commit because its provenance preflight refuses modified tracked source. Passing that gate, deterministic candidate package evidence, hosted database/Apache/browser results, independent reviews, merge identity, and resulting-main evidence will be appended without replacing or concealing earlier failures.
