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

## Further image review — 2026-10-18

The first full execution of correction78f45bd8 is [run39134909145](https://github.com/afterburn25/Cajun_Web/actions/runs/39134909145), attempt1, currently queued at build117697345926. Original f67 run39098482550 still has successful build/Apache/native-browser/recovery and queued acceptance117675017917. Both originals remain intact. This follow-up changes documentation only and does not dispatch another run.

Root completed the full captured extents of another63 browser originals: StackCP14, mail23, website delivery/licensing12 and client/activity/support14. Catalog completed52 hosting-commerce originals using190 actually viewed native-scale sheets,319 direct regions and78 exact-RGB reused strips; its finite closure verifies complete captured-extent coverage. These are115 additional reviewed images, with no inference about hidden content or unreviewed groups.

Five mobile StackCP element screenshots have the sticky topbar over their title or upper copy: Access-Empty, Access-Matched, Access-Unavailable, Setup-Key and Setup-Unverified. Access-Matched also has a partly clipped focused skip link. No exact-state full-page companions exist in that14-image group. The obscured content remains unverified; source inspection shows tall mobile targets captured in a740px-high viewport. This establishes a capture gap, not a persistent application obstruction. Collect the remaining groups' findings before a consolidated capture correction; no test change is made in this follow-up.

Mail account setup, templates, drafts, inboxes, message variants, notices and sending-history displays have no observed material visual blocker in their23 captured extents. Several are viewport-only; hidden iframe/page/select content is not credited. Client/activity/support14 covers update cards, retained conflict details, projects, support policy and portal banners. The narrow support audit table shows only its visible scrolling region. Static display does not establish authentication or permission behavior.

Delivery/licensing12 covers private ZIP delivery controls, client release history/manifests, licensing forms and website review. A minor spacing issue joins the synthetic domain and its Active label visually; no control obstruction was established. Hosting-commerce52 retains two section-capture gaps (a lower solid band and skip-link/topbar overlays), with separately viewed unobscured full-page companions. Long renewal buttons exceed inner-card padding but remain readable within the viewport. No hit testing, actual download, customer message, charge or provider action was performed in these visual reviews.

The existing Apache review was recovered and its referenced records, original archive and all16 image hashes were rechecked. It had already viewed24 native-scale sheets spanning the root/subfolder and rewrite-on/off upgraded-client pages for the published1.24 and1.25 baselines. This is reuse of an existing exact-source review, not a new Apache execution or new visual pass. Its minor touching-button-edge finding remains recorded.

| External receipt under workspace `work/` | SHA256 |
| --- | --- |
| `release126-f67-stackcp-visual-review/review.json` | `61fbb4107a1e8eb8032235ab4a345af39d64799562d7ce1e39b00192f8a25a93` |
| `release126-f67-mail-visual-review/review.json` | `b2caf0fbd055885058a94c8085029d1dd9342e675170d6da6e654a57253308a1` |
| `release126-f67-delivery-license-visual-review/review.json` | `47ddc1df73b6ea8c8e6c7a3d98f3d5a317926f428f40ecaa9e6d612f5da7815e` |
| `release126-f67-client-visual-review/review.json` | `a7bfac5f7b33ab00270ae79d25cca5c68f60b581dbe8f411e9f81c41981bbad5` |
| `release126-f67-hosting-commerce-visual-review/review.json` | `20e7aef53bd3949aec9f537e14c4827c5b26971fda1c9bbe67dc03e7a7359a8b` |
| `release126-f67-apache-visual-review/review.json` (prior completed review) | `75913e89176831b90d37ced45bfeb5b78581dba3855465e67993a9684275df3e` |

The owner reference remains present at[its retained location](reference/20i-owner-api-34e713-redacted.txt), with checksum `af13e6fccbc779db91dfbf9b32580213de48660cdd8fa0a8a9d963abfaf9ff0c`. Its package-types and cloud-product sections were reread; they do not supply the missing artwork or complete cloud response mapping. Current official[HostShop product setup](https://docs.20i.com/products-services/adding-hosting-products),[image setup](https://docs.20i.com/hostshop/hostshop-product-image) and[managed-cloud API guide](https://docs.20i.com/api/managed-cloud-hosting-api-guide) were checked on2026-10-18. They document separate commercial/image setup and cloud provisioning, not the missing catalog GET/image contract. No request to resend documentation or repeated cloud diagnostic is needed for this finding.

## Complete captured-image inventory and StackCP correction

Recorded 2026-10-18 from parent `7a65b9ecd6b5b70fda58258fcb20c8c3f0935554`. The remaining package38, directory30, billing17, public15 and provider17 reviews are complete. A finite disjoint union of all receipts verifies all324 browser PNGs against original archive bytes, plus the existing16-image Apache receipt: 340 captured extents total, no duplicate or missing browser assignment, and358 referenced files matched. This verifies completed review records; it is not another viewing session or application execution.

The remaining reviews found no material visible application blocker. Wix's mobile comparison has right-hand table columns outside its capture; the narrow payment table hides its right-hand actions in a scrolling region and wraps an amount. These limitations do not demonstrate failed scrolling. The forwarding mobile detail has a focused skip link at[0,805,181,850] and toolbar at[0,885,288,950]; its separately viewed full-page companion at y26400–27180 shows the confirmation, password and button unobscured. Minor tight/touching buttons and native select truncation remain recorded. Closed controls, offscreen iframe/table content and interaction are outside the visual claim.

Apply the reviewed two-file StackCP correction only. Each original layout/action predicate runs unchanged at1440x1050 or320x740 before capture preparation. For Access-Empty/Matched/Unavailable and Setup-Key/Unverified, the screenshot then uses320x1500, with the original target centered below the header. The new predicate requires the entire target inside the viewport, no overlap with the skip link, and nine hit samples within the target. It changes no DOM content, classes, CSS, focus, motion or provider action. Desktop and Setup-Stale/Revoked screenshot dimensions remain unchanged. The original14 screenshot names and every report name remain.

The external proposal passed12 actual local Chromium cases using exact pure renderer bodies and CSS/JavaScript with synthetic projected facts/surrounding panels. Original and proposed loops are exercised, including a keyboard-focused skip link that remains focused. Five new application outcome names are authored; the local cases do not execute the installed application or grant replacement-image credit. Independent review passes69 finite source checks, including exact whole-file inverses and original check/action preservation. Root applies the exact reviewed hashes and records the additional source pairs/five outcomes in the contract. All262 artifact-helper checks then pass. Fresh actual application captures are still required for all changed capture fixtures.

| External receipt under workspace `work/` | SHA256 |
| --- | --- |
| `release126-f67-package-visual-completion/review.json` | `41c1d76274e1b53db9b2759f138396714548406f8f3e22392e657830148da36d` |
| `release126-f67-directory-visual-completion/review.json` | `b79c17e5f7d8143d161499503d20ab971510c804246edfe7d022d960d98ae4a8` |
| `release126-f67-billing-visual-review/review.json` | `e53d38af3f9c11b2fd88b6f461bb38e2ac287701e88a6a89508ddbce383b1d77` |
| `release126-f67-public-pages-visual-review/review.json` | `193860140dde5c08d2739ca3fb5e1d90869dc60e9525a6539c95fd5c9e779523` |
| `release126-f67-provider-visual-completion/review.json` | `54f66b022eb4f138aa8be4bb5ab9b28259f4c7ce3ebdb130a7c7329a78f672f3` |
| `release126-captured-image-closure/closure.json` | `4b7cad236d82b43d542816900434efdc72617eb2d8af831fcc053716d2c099b0` |
| `release126-stackcp-capture-proposal/source-review.json` | `16d66dd3845db51addb3a2ab083150ca3143a29cb54fdd28ed32652fefe45db3` |
| `release126-stackcp-capture-vps-review/review.json` | `4baf1670ba2ad5c2d2b255fb1b03af50c71959179ce8929a10e704e46124b507` |

## Next acceptance

The original78f45bd8 full application run39134909145 remains queued and must be preserved. After committing this additional coherent two-fixture correction, execute its first full default application workflow once. Review the actual changed captures when available; use exact-byte identity to reuse already completed unchanged-image reviews where valid. Full supported-runtime candidate acceptance, maintained compatibility, normal integration, exact-main acceptance and fresh package/download verification remain required. Published1.25.0 stays immutable and no production deployment occurred.
