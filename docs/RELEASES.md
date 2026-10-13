# Release register

Updated: 2026-10-12. Record only real artifacts and actual verification. This public repository currently has no published application release; the source and build pipeline remain pending recovery of the owner's current version.

| Version | Source / artifact status | GitHub release | Verification limits |
|---|---|---|---|
| 1.1.0 | Owner-confirmed current version; source/package not yet located in this workspace or repository | Not published here; no tag created | No current-build runtime or package test established |
| 1.0.8 | Historical local complete ZIP; provenance in [baseline inventory](BASELINE_INVENTORY.md) | Not published here; no tag created | Earlier archive/hash/syntax checks are historical evidence, not current-version or target-host acceptance |

Do not publish the older archive as the latest version. Recover 1.1.0 and reconcile its source, assets and provenance before selecting the next release.

For each future release add: exact version/tag, source commit, release URL, complete upload asset name and SHA-256, release date, relevant CI/test-report links, supported upgrade origins, known limitations and deployment status. Preserve prior entries. Follow [the release workflow](RELEASE_WORKFLOW.md).
