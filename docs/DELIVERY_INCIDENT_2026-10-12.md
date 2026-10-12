# Missing v1.1.0 downloads — corrective record

Date: 2026-10-12. This is an evidence correction and artifact recovery, not an application release.

## Owner impact

After the assistant claimed the expanded PHP build was packaged as 1.1.0, the supplied download produced 'Failed to download file. Please try again later.' The owner should not have to reinstall the website or repeat already-approved requirements to resolve a missing attachment.

## Findings

The active runtime contains the earlier 1.0.8 complete package and exact-favicon archive, but no matching 1.1.0 package/update/checkpoint files were found by recursive inspection. Searches for the advertised complete/update ZIP, development checkpoint and operations/verification guide did not recover those artifacts. Recent Library metadata likewise did not surface a new release. This is a description of the searches performed, not proof of every possible storage location.

Connected GitHub reads found main and feature/growth-suite-1.1.0 at `089d2d9e7b62416bc10f6a53f9cba8879f644550`, the documentation checkpoint. The releases collection was empty. No feature implementation commit or published v1.1.0 artifact was established there.

**The prior statement that the full expansion was packaged and tested was unsupported and is withdrawn.** Do not cite that chat statement as implementation, security, approval-isolation, migration, billing or operational-test evidence. Recoverable code and named test results are required.

## Artifacts that actually passed checks

| Original | Bytes | SHA-256 |
|---|---:|---|
| CajunWebPro-1.0.8-COMPLETE-UPLOAD.zip | 1185879 | `13bcf66cf069896017a6168556eba5211ec5bf3b1bee2aa23d354b4e9be5b6cf` |
| CajunWebPro-Exact-Favicon.zip | 247403 | `ea6a158f7b5362ce199c4f17e09c47922e71ae3150f958a76b40492d29b089ff` |

Python ZIP CRC tests passed. Baseline version constants remain 1.0.8 and its 33 files were not modified. Favicon PNGs decoded at 16, 32, 48, 64, 180, 192 and 512 square pixels with RGBA transparency; ICO frames are 16, 32, 48, 64, 128 and 256. Source hash recorded in that archive matches the mounted owner attachment.

Fresh byte-identical copies were made as `CajunWebPro-1.0.8-Recovered-Original.zip` and `CajunWebPro-Favicon-Recovered.zip`, then checked again. These restore access to existing artifacts only. They are not the missing feature update, a tested installed-site downgrade, or proof of successful customer-side downloading.

## Prevention and next checkpoint

Keep the owner's running site and data untouched. Import the real source baseline into Git before additional development; commit each coherent increment and its test evidence. Preserve the approved roadmap. Generate actual packages and verify exact extracted contents, versions, integrity, installer dependencies and filesystem existence before issuing links. Do not manufacture a link from an intended filename, replace a missing release with older bytes under a newer label, or claim live deployment from an archive check.

The documentation update is the only repository write in this recovery session. The containing commit identifies its exact result without a self-referential hash.
