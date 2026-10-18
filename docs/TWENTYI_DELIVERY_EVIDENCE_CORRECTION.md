# Retain exact synthetic delivery evidence

## Observed failure

Original full38889625340 for5560635b passed native database, HTTP/Chromium and mutation steps, then failed `tools/acceptance_artifacts.py seal` with `Evidence crosses partition/private boundary`. Actual retained browser artifact12044866390 contains449 regular files; SHA256 is2f0ec1509709b5d779f052faf9c5aad63b105150e5e43e0a1726e052bace7e97. Independent CRC/path/hash review found exactly three old-boundary refusals:

- `qa/client-website-extracted/HANDOVER.md`
- `qa/client-website-extracted/LICENSES.md`
- `qa/client-website-extracted/index.html`

Their bytes equal the unchanged `tests/delivery_cases.py` pure archive generator. The actual delivery browser flow downloads and verifies the ZIP, extracts these synthetic public files and opens the static page. The previous evidence helper rejected all nested QA paths. The helper and delivery driver are byte-identical in556 and4791; an isolated actual old-seal invocation reproduces the error. This is an evidence-packaging defect, not proof that any unverified application assertion passed.

## Bounded correction

External proposal641648063247ba23c7b623f876a10d657b328853de84867ee329dbf69b3d3993 adds an exact three-path allowlist after the unchanged private-name guard, only for native/browser. Every other helper function is unchanged. No wildcard or arbitrary extracted file is accepted. Existing regular-file/symlink, canonical path/case, private name, digest, identity, partition ownership and named-report rules remain required. No test output is deleted or moved to conceal the failure.

All112 original helper outcomes are preserved in order and51 new offline cases pass. New cases use the actual pure archive generator and actual seal/checkpoint/four-partition aggregation. They reject wrong lanes/pristine inputs, extra/private/noncanonical paths, a fourth extracted file, rehashed outer archives with altered inner bytes and an allowlisted pathname used for a symlink. These163 checks do not execute application/database/browser/provider workflows. Independent reviewbe46dd38937f7621e395cdc1d21f236f0b84d008f0e35fe5a7bf17f55a237e90 confirms source/provenance boundaries.

## Remaining acceptance

Apply only to a new descendant of frozen4791; retain original556 failure and ongoing a63/4791 attempts. No original run is rerun or relabelled accepted. A future independently reviewed verifier must bind the new helper642aa827,163 outcomes, exact source/run/attempt and matching Windows packages. The new combined application full, actual screenshots, normal current-main integration and exact-main release acceptance remain outstanding. Published1.24.0 and all historical releases are unchanged.
