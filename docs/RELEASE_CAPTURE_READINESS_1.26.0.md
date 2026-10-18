# Release 1.26 capture readiness

Recorded 2026-10-18. Branch `codex/release126-capture-readiness` starts at `f67b1631683b29096dd8ec1daee405e104dc0b83`, tree `a24876428ada7d4f7c764d28650d208b8eddf8f2`. The owner asked to continue the roadmap and use the saved 20i documentation. This bounded change addresses evidence gaps found while reviewing the actual release candidate. It adds no application capability and does not certify publication.

## Original application evidence

[Run39098482550](https://github.com/afterburn25/Cajun_Web/actions/runs/39098482550), attempt1, uses the f67 candidate and CI merge source `ee131c1f65ecf2fe3ac8faaf55d04485d8edec71` with PHP8.3/MariaDB10.11. Build117589718966, Apache117604185659, native/browser117604185670 and recovery117604185689 passed. Final acceptance117675017917 remains queued at this checkpoint. The four other original runtime runs remain separate requirements.

The retained browser artifact12136592269 is 375,382,583 bytes, SHA256 `de20b6ecb2802f226ca5ec5f414c5b7f94bf69b8d659a839f41e0a09a5bbb149`. Its source/partition verification establishes 167 reports, 20,749 passing named executions including 3,149 HTTP checks, and 324 original browser PNGs. The complete image requirement is still 340 including 16 Apache PNGs. All original failed, cancelled and queued runs remain preserved.

Root inspected the complete captured extents of eight originals: Administrators-Desktop/Narrow, Fresh-Root-Mobile, Homepage-Desktop/Mobile and Installer-Complete/Desktop/Mobile. Native-resolution strips preserve every original pixel; padding is outside the original extents. Installer checks are on the left at desktop, website details follow files, and the required form/action remains visible. Administrator forms and permission lists fit; native controls truncate long values internally. The desktop homepage has Hosting, Web Design and Products navigation. These observations are visual checks, not new interaction tests.

Domains completed its exact 40-image domain/registration assignment, including 33 new originals or previous gaps and seven previously complete originals. No material application obstruction was established. A long renewal-review button extends beyond an inner card at 320px but remains readable inside the viewport. Domain-Search-Mobile has a partially closing drawer over its left region; Registration-Periods-Failure-Mobile has a sticky header over the section heading, while its full-page companion shows the heading. Covered pixels receive no hidden-content credit. Other image groups and the complete 340-image review are not certified by these receipts.

VPS completed the captured extents of all 30 Cloud-/Provider-Cloud- originals: 979 newly viewed crops on 153 original-scale sheets plus 937 exact RGB reuse regions. Of those reuse regions, 69 refer to previously viewed boxes and 868 to newly viewed crops; coordinates and byte comparisons are retained. This is exact-pixel reuse, not inference from similar pages or resized thumbnails. A grouped minor finding records long mobile renewal/directory/setup buttons exceeding inner-card padding while remaining readable inside the viewport. No material cloud assignment/confirmation display obstruction was established. Hidden controls, areas outside original capture boundaries, behavior and all other images remain outside that visual claim.

## Confirmed capture gaps and corrections

`Fresh-Root-Mobile.png` contains blank below-fold service/project/promise regions and an unloaded footer image. The original test takes a full-page screenshot without visiting that content. The exact application CSS hides unrevealed cards and the exact JavaScript reveals them on intersection. The new helper scrolls through the actual content, waits for visible opacity, decodes every image and returns to the origin. It keeps motion enabled and changes no DOM classes or shipping CSS. Broken image decoding fails the capture.

The original 320px imported-image card capture loses its bottom edge. A file-only Chromium diagnostic using the exact f67 PHP card/layout and CSS reproduces the clipped region: the card exceeds the viewport and interacts with the existing 108px scroll padding. At the unchanged 320x900 viewport, scrolling the Save button to the center makes its whole 42px height reachable; all five hit points and Playwright's trial click pass. The correction retains the original viewport checks, adds those actionability checks, then takes the same-width card screenshot at 320x1100 with its complete bounds below the sticky header. This is evidence about capture positioning, not proof of an application upload defect.

The domain-search test resizes directly from desktop to mobile and captures while the existing sidebar's 0.25-second transition can still cross the viewport. The two-line correction uses the existing Playwright assertion to wait until `.admin-sidebar` is outside the mobile viewport. Exact static CSS/JavaScript with synthetic HTML reproduces the overlap; the proposed capture reaches hidden, inert, zero-intersection state under normal and reduced motion. Ten original viewport outcomes pass across the controlled original/proposed cases. All 28 original application check ASTs are retained exactly. The registration-period section capture remains limited by its sticky header; its unchanged full-page companion supplies the heading view.

The three changes add seven named outcomes and preserve every original assertion, real multipart flow, provider action, report name and screenshot name. The source contract records the changed files and retains their original hashes. No native command, workflow, shipping file or release archive is changed.

## Local verification and limits

The exact root helper passes 25 named checks in real local Chromium with the original CSS/JavaScript at 1440x1000, 390x844 and 320x800. Synthetic file-only markup supplies offscreen cards and a lazy image. Full-page capture alone leaves cards hidden; the helper reveals them, loads images, preserves motion and restores the origin without an external request. A broken image prevents successful capture. These checks use the exact helper AST with real Playwright assertions; they are not a full application, database or HTTP acceptance run.

The first local probe stopped before browser execution because the bare Python runtime lacked the unrelated `requests` import. The next probe sampled the original animation partway through its transition. Both failed diagnostics are retained. The final probe waits for the actual original CSS state before comparison; no application or helper behavior was relaxed. The existing acceptance-artifact suite passes all 262 checks after the contract update. Python syntax and documentation/inventory checks are recorded in the enclosing session log after execution.

External receipts are under the workspace `work/` directory. They are retained evidence, not source dependencies:

| Receipt | SHA256 |
| --- | --- |
| `release126-f67-public-admin-review/review.json` | `b22345a85d607bd63916611ca011a2ca73737781fde03664cbdbd624bad8c77b` |
| `release126-f67-domain-visual-completion/review.json` | `347e7ef6de96ad5b184eb436f4096830eefd41aa404e713af481407ec4ede6a1` |
| `release126-f67-image-control-diagnostic/review.json` | `9e31634a2220971540ffc0bd81f3fe89093c55011d164122b84d9b10cb1a80b2` |
| `release126-capture-readiness-catalog-review/review.json` | `e71d9c1eb60e10c63b08d8d338f877a53d69db30e435e80556f6241281857d5f` |
| `release126-capture-readiness-catalog-review/closure.json` | `9c6873c9f899b977f37b8fe6f6d97cb6f333a3bc994aebce27757fa70c677c64` |
| `release126-root-capture-diagnostic-v3/results.json` | `c28f6a21c747ce7bc6e9a6ed59c8b311c52a714ea6b38a3825b436ff1854e809` |
| `release126-f67-cloud-visual-completion/review.json` | `6c17a6f10bb175fbc83d719ae3514f4906690cc2053a4f0d9488d22320cbca61` |
| `domain-search-capture-readiness-proposal/source-review.json` | `1e5a3fa8130ac71ab6c5c9388b3cb27a69cc8388ca138e284586fd3740bae558` |
| `release126-capture-readiness-catalog-review/final-closure.json` | `24891c0d03992945e664a956488d8fe5885bbe224b75c60b124a97493f37e8ba` |

The local root probe is `release126-root-capture-diagnostic-v3/results.json`; its earlier failed attempts remain in separate original directories. The initial source review confirms the root/import changes and preservation of the original browser callback after removing the new helper invocation. A separate closure review verifies those two pins and their historical metadata in 14 finite source checks; restoring only those pins and removing the added metadata recovers the complete parent contract. Root subsequently verifies all three final source hashes against their original f67 bytes, Python syntax and the exact two-line domain inverse. No application workflow was rerun for these source reviews.

Independent final closure also confirms the exact domain inverse, all 28 original domain check ASTs, preservation of the first two fixture hashes, and recovery of the prior contract by removing only the third metadata pair and its scope sentence. Final combined verification passes 262 artifact-helper checks and documentation checks covering 276 Markdown files and 1,896 local links. Generated installer inventory remains current at 2,901 entries; shipping files are unchanged, so no deployment package regeneration is needed for this test-only change.

## Next acceptance

Complete independent review and the source checkpoint, then run the changed source's first full application workflow. Inspect the resulting actual screenshots before granting their missing visual coverage. Preserve original runs without reruns or replacements. Full supported-runtime candidate acceptance, maintained compatibility, complete image review, normal integration, exact-main acceptance and fresh package/download verification remain required. Published1.25.0 stays immutable and no production deployment occurred.
