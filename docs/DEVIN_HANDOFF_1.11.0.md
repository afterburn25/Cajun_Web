# Devin handoff — organized 1.11.0 baseline

The directory correction is merged in [PR #38](https://github.com/afterburn25/Cajun_Web/pull/38), published as [v1.11.0](https://github.com/afterburn25/Cajun_Web/releases/tag/v1.11.0) and downloaded-back verified. **Use `de70c5da2e8f7bd35fbb92b9b829d0ab3e880372` / `v1.11.0` as the immutable accepted source baseline.** [Exact-main acceptance](https://github.com/afterburn25/Cajun_Web/actions/runs/38422422077): 618 native, 782 HTTP/Chromium, 41 filesystem/migration, 203 Apache and 16 package-builder checks. All 16 assets match; Windows/Linux package bytes, directory/member manifests, checksums and tag agree. No live deployment. Publication-record commits after this source are documentation only.

## Permanent paths and package contract

Read [the directory tree and exact map](DIRECTORY_STRUCTURE.md), [machine-readable mapping](../tools/directory-map.json), [migration/recovery](STRUCTURE_MIGRATION.md) and [START-HERE](../START-HERE.txt). Root entries stay thin. Canonical routes use admin/index.php, portal/index.php, api/index.php, api/activity.php, media/index.php and webhooks/authorize-net/index.php. Use existing URL helpers and bootstrap; filesystem code roots must not change installation identity or session scope.

Public images now live in images/branding, icons, backgrounds and portfolio; CSS/JS in assets/css and assets/js. Keep exact original brand bytes. The asset helper preserves existing stored image identifiers. Business code belongs in app/domain areas, rendering in admin/portal/pages views, shared security/auth/filesystem in includes, schemas/migrations in database. Do not add a new functions monolith or restore old root business endpoints.

Runtime private files remain at config()['storage']; upload staging is beneath that same store. Git/package storage/ and upload/ are exact protected metadata only. Canonical foundation files and bundled copies must match, including direct-web guards. Run tools/build.py --refresh after shipping changes; review generated setup bootstrap/inventory and run the full acceptance suite. FULL-PROJECT includes source/docs/tests/tools; COMPLETE-UPLOAD is organized production contents without a wrapper; UPDATE stages a guarded owner migration. It must never become a blind overwrite or new installer.

## Preserved work and next integration

| Work | Preserved head / action |
| --- | --- |
| PR #31 review requests | c1264b9af1ef9bd82c3f0f6fa2a985f3b1c8d307; independent foundation, review and merge after current baseline acceptance |
| PR #32 consultation scheduling | 3475de1c9a2340a67b516aa720f89fde2c5b01cd; independent foundation, same policy |
| PR #34 homepage motion | 60fd2768cbc71fa659daa96a7131bace59877a0b; reapply the reviewed residual behavior at assets/css/site.css and assets/js/site.js; retain existing motion-off convention, regenerate install/inventory.generated.php, port browser checks |
| PR #35 estimator adapter contract | 34e0ec93b6588a7fc1e2476c77a2f49767d9d3d5; preserve pricing contract and new app/marketing/growth.php paths |
| PR #36 review adapter contract | f4e63beb1f7993c69c17d2dd9a3bb93b1987b903; refresh shared continuity docs with a merge |
| PR #37 scheduling adapter contract | fde8aed3e4d74be384338545f8674ff8856dc986; refresh shared continuity docs with a merge |
| PR #42 Windows UTF-8 build | 19afbfc330fa22db6d9e284d08f19254a8462ce1 integrated with original ancestry; stricter CSS containment and combined diagnostic details retained |

No branch was force-reset. Refresh branches with merge commits, review actual final paths, agree bounded ownership and integrate sequentially with combined application checks. The directory release is now immutable; use a new bounded feature branch for subsequent work. Review PR #31/#32 independent components and the bounded #34 reapplication before claiming new adapters. Preserve the owner-approved [external gate policy](DEPENDENCY_GATES.md): pending provider access does not stop independently testable work, but no synthetic result activates real charges, domains, provisioning or SSH.

Existing public portfolio integration is retained. Broader webmail/templates/email bells, client checkout, 20i/domain/VPS operations and SSH remain incomplete or externally gated as recorded in the roadmap. This refactor makes no deployment or live-provider claim.

## Open work at publication

These heads were re-read after publication; newer heads supersede the earlier pre-merge inspection table above. This is an inventory, not acceptance or authority to activate provider calls. Original branches remain intact.

| PR | Branch | Head |
| --- | --- | --- |
| #31 | devin/review-requests | `826fdbfb55a3abca903878662ec6debc4c427cd2` |
| #32 | devin/consultation-scheduling | `34e4334279659efacb0ae14918dc89cadfef1321` |
| #34 | devin/homepage-motion | `e2b2a23dbd4a477778bd3d0e860069b4dfa0aa04` |
| #35 | devin/estimator-adapter | `26968aa1a6bdffd670a25d97f2c28a86e8c9d71b` |
| #36 | devin/review-adapter | `c26f596fbbbdfad22a8accb7dff515f2d236bcee` |
| #37 | devin/scheduling-adapter | `8037afc0e3debd24494020d9bd10f830db1fbfa8` |
| #43 | devin/provider-foundation | `d743c04b5325af9375df6b01c23d0dce03c800f5` |
| #44 | devin/domain-foundation | `4b9e69205d90a301c3d3ee33466a6e9b04915338` |
| #45 | devin/vps-foundation | `fc4104632b2f79666248b2e601cd62c856f25af1` |
