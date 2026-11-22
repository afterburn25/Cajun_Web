# Next CajunWebPro release: published 1.26.1 extraction diagnostic

## Why this independent branch exists

Parent verified-main checkpoint: `1e79ef57d576cda6291cc540ee3c76ac109f071e`. PR #398 remains a separate mail integration and must not be edited while its exact-head tests run. The published version is `v1.26.1`; the development `VERSION` on this parent is also `1.26.1`.

The existing manually-dispatched `release-next-from-125` check covers the published 1.25 baseline. The immediate prior published version is now 1.26.1, so a 1.27.0 release candidate also needs an unambiguous current-published upgrade input and first-pass extraction diagnostic.

## Added test-only tools

- `tests/release_next_from_126.py` pins the publicly published 1.26.1 COMPLETE-UPLOAD to exact 17,429,306 bytes and SHA-256 `7d7117a25fab556cb3f8dfce7f0b859d072cfc992b98fc91026d96b490e1dad3` from GitHub release metadata.
- The release candidate must have a strictly increasing semantic version, matching `VERSION` and `site/config/defaults.php`.
- Refuse dirty or mismatched Git source, unexpected archive members/symlinks/case collisions, oversized expansions, altered publisher archive bytes, and a mismatching candidate COMPLETE-UPLOAD runtime version.
- Use the repository's existing exact-commit package builder. Unpack original COMPLETE-UPLOAD into an isolated disposable target; unpack the candidate UPDATE only as a versioned staging directory. Do not execute its owner updater, import data, or claim a database/hosted upgrade from this extraction.
- `tests/release_next_from_126_test.py` exercises strict version and unsafe-archive refusal without networking.
- `.github/workflows/release-next-from-126.yml` runs the offline guards on relevant PRs. The full download/build/extraction check is a manual future-candidate action, not an automatic release.

## Honest status and next gates

The source version has **not** been bumped to 1.27.0 by this branch. Consequently the manually-dispatched candidate diagnostic will intentionally refuse the current 1.26.1 branch until a release candidate is assigned.

No hosted test or independent review is claimed by this note. Require normal CI for offline guards, reviewed source-provenance composition/receipt integration, exact-main candidate preparation, release migrations and supported fresh/install/upgrade/browser/database matrix before publication. The diagnostic explicitly writes `release_clearance: false`. The existing published 1.25 upgrade gate remains unchanged and required.

No customer email, production payment, provider operation, live host installation, current customer data, or published release asset is changed.
