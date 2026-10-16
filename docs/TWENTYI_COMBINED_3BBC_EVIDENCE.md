# Combined 20i acceptance evidence — 2026-10-15

Frozen source `3bbc42bd56270d1c754ff2e72ea039b9c557460e`; PR test merge `a99beac7db7322ac61b53368c52d95ed8a2d416f` has the identical Git tree `4481fe21886f5403d31003de728e1301db6e4ae2`. Root independently downloaded all 20 focused archives and checked exact published sizes/SHA256, unique safe ZIP members, CRC, every named outcome and report verdict. All five workflows and all four PHP 8.2/8.4 × MariaDB 10.11/MySQL 8.4 combinations completed successfully. This is native/offline testing with synthetic provider transport, not live provider acceptance.

| Workflow | Reports in each of four archives |
|---|---|
| [Mailbox 38829910826](https://github.com/afterburn25/Cajun_Web/actions/runs/38829910826) | 91 native |
| [Registrar 38829910918](https://github.com/afterburn25/Cajun_Web/actions/runs/38829910918) | 63 native; 64 reader cases with default configuration and 64 without INI |
| [Cloud 38829910945](https://github.com/afterburn25/Cajun_Web/actions/runs/38829910945) | 45 creation; 74 confirmation |
| [Recurring/catalog 38829910970](https://github.com/afterburn25/Cajun_Web/actions/runs/38829910970) | 13 reports: 543 combined; service links42, orders82, link views38, fulfillment76, fulfillment forms32, hosting confirmation38, catalog78, catalog documents51 per mode, ledger108, recurring forms68 per mode |
| [Renewal 38829910816](https://github.com/afterburn25/Cajun_Web/actions/runs/38829910816) | 78 native; 28 document cases per mode |

Catalog and other focused reports overlap the combined report; counts must not be summed into unique coverage or a completion percentage. Local proof `work/20i-combined-3bbc-native-verification.json` retains every checked name and report hash. Archives and public metadata remain in `work/combined3bbc-native/`.

## Downloaded archive identities

| Artifact | Matrix archive name | SHA256 |
|---|---|---|
| 12017201059 | `provider-mailboxes-8.2-mysql-8.4` | `3be1d198467999cedbed32b412341f7b7cfabe1471b00e88a2bdb2c2b3158f5c` |
| 12017136025 | `provider-mailboxes-8.4-mariadb-10.11` | `9b236429c35ff132c49a88e4edeb60a358cb09462c8bf1732547451fde3bf32d` |
| 12016846955 | `provider-mailboxes-8.4-mysql-8.4` | `66561dd807f5a1596a292bb5887372d49dacdeb052d5ad2049fbaec365f56b64` |
| 12016672267 | `provider-mailboxes-8.2-mariadb-10.11` | `adc6d09f532c9123643565b217d901614e193a47d494c89966a589e7963b14fa` |
| 12016877190 | `provider-registrar-8.4-mysql-8.4` | `c5fb163e233345dfdb2180b3da354d1ea524ab8908c77356ac20ca3e43e101f5` |
| 12016618880 | `provider-registrar-8.4-mariadb-10.11` | `f195b737376583d4e2e520d5f20b21a3d4e3c310f9e47f288ed1580a899c2cf8` |
| 12016483942 | `provider-registrar-8.2-mariadb-10.11` | `e1085073128c7eb78cf9f25c1af6a7560f44c08e52f342d5b03c9a48e9d482c6` |
| 12016283217 | `provider-registrar-8.2-mysql-8.4` | `85c1c4a4bfad8c58e8522a8fb94380e9b26dfd69042c0b80ecb05362e4cb1986` |
| 12016852333 | `provider-cloud-sites-8.4-mariadb-10.11` | `059ae6998df8d171131c9d26571d3da171a418a36c2d480795c85ec1d6ac711c` |
| 12016832056 | `provider-cloud-sites-8.4-mysql-8.4` | `5b10074c70483ef1548526e8bf1a40460f1fe1d7ce7f7821420dcbc11ee1c6bd` |
| 12016672574 | `provider-cloud-sites-8.2-mysql-8.4` | `a590a78bab3aa890bd94db44a9679a6a7f892196d9f5bc0be775128afd67357d` |
| 12016228206 | `provider-cloud-sites-8.2-mariadb-10.11` | `6c8754db086c66132e3e1009bc49f86f287b720018b3b981e962bf4ad1b25298` |
| 12017380004 | `recurring-billing-native-8.2-mysql-8.4` | `7a8d7a2720f83af73f7a71c469584308f2f9aa8b508e010e7389e8c6045660c6` |
| 12017141810 | `recurring-billing-native-8.2-mariadb-10.11` | `e5e68cb56f33140cc1b2d0e73c6ded8c29586be038f6b22a5b17737ed2eb2a01` |
| 12016897761 | `recurring-billing-native-8.4-mariadb-10.11` | `be813e6e5bc3ef35856d6fa91280329de8fc1cf9e7041f150e78366eb886c6f1` |
| 12016174275 | `recurring-billing-native-8.4-mysql-8.4` | `1778a90ce4e79692f48662221fee8a6d8751b7983b7e09778b5b1bc89bd04346` |
| 12017076574 | `domain-renewals-8.2-mariadb-10.11` | `8ddd1cc8cf4bf68cf16faae873b254fe2bd4b2cb77606eac822689140bee8faf` |
| 12016877130 | `domain-renewals-8.4-mysql-8.4` | `016c355f5e0f2f4e1e1e9ca827c840cb6dd73ec5a4dabe531fc76fe7af6248e4` |
| 12016473244 | `domain-renewals-8.2-mysql-8.4` | `8d13fdc8854c343cefde47abd0e236810b2ef17365b3869ec166661b2c6866ea` |
| 12016153454 | `domain-renewals-8.4-mariadb-10.11` | `f9732cc16ae844f5dc52fd406f764de3509865001a91c3cf2245e90bc0c95463` |

## Corrected earlier full acceptance is complete

Source `2239a1569dfa691fe18f23a512fb93b1905079ef`, [full run38826150967](https://github.com/afterburn25/Cajun_Web/actions/runs/38826150967), is terminal SUCCESS including browser, unprivileged recovery, Apache and source-mutation verification. Final artifact12017335601 is112,443,594 bytes, SHA256 `f4bda754a2ddc30a15a0490775f49fea5162978e0a5c6f41c2d0f8b19521bb86`. Root independently verified380 outer members,106 named-result reports,11 clear diagnostic reports,1,878 HTTP,289 recovery and1,903 Apache outcomes. All155 screenshot bytes equal the previously inspected browser checkpoint. The former foreign-client mailbox permission case passes.

All11 checksums and exact Git-source/package/member/directory/decoded-update inventories pass: FULL5468, COMPLETE2683, UPDATE2670. The2239 CI merge1af9c005 has identical tree8a05a9cdef8c27df501c8d8f73203e006af4cf90. Cross-platform comparison is explicitly against preserved Windows38bb: COMPLETE is byte-identical, UPDATE differs only by source-commit metadata, and FULL differs on precisely the14 changed Git paths. No exact2239 Windows build is claimed. Archive `work/combined2239-final-12017335601.zip`; proof `work/20i-combined-2239-final-verification.json`. This parent acceptance does not certify newer3bbc features. Retained38bb full run remains failed at its browser fixture.

## Remaining combined gates

The exact Windows3bbc package proof remains verified (FULL5494/COMPLETE2697/UPDATE2684;11 checksums). [Combined full38829910791](https://github.com/afterburn25/Cajun_Web/actions/runs/38829910791), job116746050262, passed package/native/browser integration and passed unprivileged recovery and is running Apache protection checks. Preserve that handle. Its browser checkpoint is now independently verified as detailed below; recovery/Apache, final archive comparison and exact-main release gates remain pending. Main remainsb8f8528b, already normally preserved. Following documentation-only commits intentionally differ from the frozen FULL package while leaving shipping, tests, workflows and installer inventory unchanged. Published1.24.0 remains unchanged;1.25.0 is unreleased.

## Three active next branches

- `codex/twentyi-scheduled-refresh`: owner-approved scheduled package reads. Root reviewed fixes for retained document/result hashes and original start/approval/result bindings. Initial60f95ca4 native38831603128 passed65 cases on both MySQL combinations but failed both MariaDB combinations on incompatible collations. Agent preserved failed evidence and is correcting exact-byte SQL comparison, uncached staff-marker authority and retained normalized headers. Corrected native/browser acceptance is pending. Shared inventory-writer compatibility and rollback still require native evidence.
- `codex/twentyi-service-observations`: client hosting history. Root found a pre-assignment exposure: the latest fresh read can predate the current link/relink because the existing context matcher compares only revision/fingerprint. Agent added exact paired assignment-audit ordering versus refresh-start audit, including same-second tests, inside the new module. Root re-reviewed core SHA256f6b314e33c019089a41f5ba103f443e9a273eada9425eab52507f077f9b0012a and found the identified boundary closed in source. Frozen corrected sourcee5c8aba856813cf294de31b59e22a3e837a4bc5c has native38832233011 and browser38832257408 running; native/browser acceptance remains pending.
- `codex/twentyi-forwarding-requests`: separate forwarding request permission, client request/cancel and owner review/decline. Root found that loss of a later relink event with its audit retained can revive an older permission because only the selected event kind was counted. Agent added all-kind assignment-history coverage and three loss regressions; root re-reviewed core SHA256aa389ae2cfa959e2ca0180e90a9ca7811bc71d569f0d351575ff104370f1a742 and found the identified revival closed in source. Native/browser acceptance remains pending. No dispatch registration exists; acknowledgement/readback semantics remain unverified.

All three agents are active in separate checkouts. These new branches are outside3bbc acceptance and are not counted completed. Root integrates reviewed increments sequentially and owns release checks. No live provider call, payment, customer mail, SSH or deployment occurred.


## Combined packaged browser checkpoint — verified

Artifact12017850896,105,826,920 bytes, SHA256 `8ba2550bb38c033555ed08ce042381ab494a855fd3f891e4b8f6ec68b8304cd4`, belongs to the same frozen3bbc/PR-mergea99 tree above. Root downloaded and verified273 safe unique ZIP members/CRC,69 named-result reports,11 diagnostic reports and1,945 passing HTTP/Chromium cases. The combined run includes34 catalog,32 renewal and21 cloud-confirmation cases, plus the corrected foreign-client mailbox refusal. This checkpoint has167 actual screenshots and no final distribution. Its three feature screenshot families differ from parent checkpoint bytes and receive fresh visual inspection.

Root inspected all four actual catalog history/list desktop/mobile screenshots at original resolution. Historical version1 retains12.5000GBP and one website; the latest withdrawn mapping and editable new review remain distinct at19.2500GBP/two websites/zero disk. Readiness reasons, labels, forms and navigation fit their viewports. Domain/cloud agents inspected all eight exact combined originals and readable owner-section crops. Renewal screens show pending review, literal escaped messages and withdrawal/review/decline controls; long IDs wrap. Cloud screens retain original accepted-pending evidence separately from observed/assigned package evidence and clearly qualify current location, health and billing. No clipping or overlapping controls was found. These are pending/review and post-confirmation captures; no uncaptured intake/pre-submit screen is claimed. Renewal visual proof: work/20i-service-observations/qa/combined3bbc-renewal-screens.json. Archive `work/combined3bbc-browser-12017850896.zip`; proof `work/20i-combined-3bbc-browser-verification.json`; images `work/combined3bbc-browser-review`. Full recovery/Apache/final packages remain pending. No live provider acceptance is inferred.

Cloud parent0ea full38827979412 is separately terminal SUCCESS. Its author downloaded and verified final12017179352 SHA256 `39bba16bb95d9dd2be9180b5ac806fcdb62cfd500712ac8a59853b6265aa5a16`:385 members,107 named-result reports,11 diagnostics,1899HTTP/289recovery/1923Apache,11 checksums and3 nested package/member/directory/source manifests. Proof/archive in `work/20i-forwarding-requests/qa/cloud-0ea-full-verification.json` and `cloud-0ea-full-12017179352.zip`. Parent acceptance remains separate from3bbc and from the newer forwarding implementation.


## Remaining focused3bbc gates — downloaded evidence

All31 focused PR workflows on frozen3bbc are terminal SUCCESS; the separate application run38829910791 has passed recovery and remains live in Apache. Root downloaded40 additional archives from15 supporting workflows and verified exact published sizes/SHA256, CRC, safe unique ZIP members and all142 named JSON reports. Together with the prior20 archives/88 reports,60 archives/230 named reports are verified. Reports overlap and repeat across matrices; this is not a unique-case or completion percentage. Eleven remaining focused workflows are console-only and contribute no downloaded named count. Four supplied service-plan text reports are retained separately, without inventing named results.

Proof `work/20i-combined-3bbc-supporting-verification.json` retains all names, hashes and text reports; archives/public metadata in `work/combined3bbc-supporting/`. The earlier exact source/tree proof applies.

| Run | Artifact | SHA256 |
|---|---|---|
| 38829910910 | 12016194373 | `123e478875dbc9902525d6bd2ea51fac1f9fc73943cb217eac10ee53ae942d82` |
| 38829910910 | 12016119496 | `df94ad3f5eb92b5ec0656f064dd9bd689a0fad7cb248f8eaaa02f759b2367c2a` |
| 38829910950 | 12016896774 | `4a40dd373f89143291b9e5d0753cf1cf6f1d3ca5dff81f15adb47616e8f261ec` |
| 38829910950 | 12016457933 | `4a40dd373f89143291b9e5d0753cf1cf6f1d3ca5dff81f15adb47616e8f261ec` |
| 38829910933 | 12016831953 | `9ba0ab5e78ef11ee3ed27aed80f97bc59842945f356e8105e35fb4d577e88b32` |
| 38829910933 | 12016767327 | `e4729b272a8b583facdd6ea9a9a45d5c60e822c96447e3264416b65c50b236ac` |
| 38829910915 | 12016896949 | `dd993818ff7cf9807ef4ccfedc8cbf4327fca28d419c68fe8d75e1c2f74b346d` |
| 38829910915 | 12016876961 | `02ead106ced6b726ac1538c5da7036ebcd7854fd77878b01eaaf0ec2ab9f6df4` |
| 38829910937 | 12016479280 | `a6cca4a733f3fc8b63947eb9cada6920311d75c3360e51cb09d07b00544f0991` |
| 38829910937 | 12016464068 | `2324909225df373b3bd02567dd4c15f4c4b2ba2f34a9116c67d4ba8c3d99644e` |
| 38829910937 | 12016388983 | `9ab7f6d72f42dbe6ea84788b6898b1a10c50b778295ebe8a9bf058ab506fbbe2` |
| 38829910937 | 12016048709 | `45970ff67ddc1be4e626e942731c880a9c9186731ecbd99388798beb230881c3` |
| 38829910931 | 12017061088 | `48d8e37d1849bfc6153302149e2a257d1072b30baf981e27bcb6e7defc018e99` |
| 38829910931 | 12016399454 | `1a28c89808540c9eba947cf799997b64b9710721d733465ec18536b0705fb6ab` |
| 38829910952 | 12016752842 | `49ae873c23a75070e69fd5a76358a2530adc481fca879b292acf08abfa68d9d0` |
| 38829910952 | 12016578965 | `5232f0b10b1efb0bf4adee5ef2062f6c3c78e532462dda37bc512e92aa77804e` |
| 38829910926 | 12017270985 | `b067b0817fe37e47c7d26a9bc19b77f210940b129a5869a2e8ca206232fe0d9e` |
| 38829910926 | 12017230992 | `5bc7efad2e839b037ea9915a93710d9834c3ef56fd304511fd3ed43fcc2fb6bc` |
| 38829910988 | 12017056612 | `3258efd84184b4b92a014aaf5d9cc189ba269b2d3b9312a55f168c39fb1bc3a9` |
| 38829910988 | 12016253378 | `e9952e26aaadbf2e02aba16b1d7100f62dda5f1b28d2ff4e8db49d84287aeef0` |
| 38829910988 | 12016188078 | `54422aa8c9990d12cf1a2d0d8a2e6a7b67e6a2e6d1362b2985aa72afb4f37833` |
| 38829910988 | 12016184741 | `bdd0a29620fb12423120969298a0fa6b252a32090b9d887852cb4c388d891f46` |
| 38829911040 | 12016448253 | `eaa5cea3edf0e1643dc8d389c046f04587e1a9052340aea77c7ea291b6dff674` |
| 38829911040 | 12016323443 | `2bbdfe2327a5e90b804950a9f79363c01b5c32174031254737d8f1eb72c14acd` |
| 38829910943 | 12017230865 | `bdf965bf5584d9968b06ab269aa7dd92bfef539440163d625806c2ec258d71b1` |
| 38829910943 | 12016827696 | `223174d58d57dc9d1901a5a8d94e5f529d942d06d64d3030c9b8bef206d2dba5` |
| 38829910943 | 12016767357 | `8f9964857a9190c3d09b77070ac718cf1f81a107e8105b2840bc535ed1186220` |
| 38829910943 | 12016563209 | `7c7ae8b31b4f0019399cbeaa782edd26beaa35165ac941beb450cf556b91d3b6` |
| 38829911010 | 12016971880 | `26ab6f165bd0232d11e19ae3aa9b3e7a1dbbf93b8f33f0cad260e549737d5ec0` |
| 38829911010 | 12016967104 | `020348682218312259f373d7a7af4196e8205cdad5ac5f445894bc8de72a8734` |
| 38829911010 | 12016619040 | `7661d432430e19185135bd9e0878827be9e237b664476ecbd39bb0e639c86d24` |
| 38829911010 | 12016363239 | `234a05a38f4c2f37d7684991c63614166bd5ec35e4e3f9db14630ae6207f1854` |
| 38829910787 | 12017250611 | `e0db66d0a775432bd1487023b20082557521b24b5af3841c98d01714382bdde6` |
| 38829910787 | 12016692286 | `26e665352be1ed1894536092554c88771f1bab07fbe55e744b0beecc8cf9db60` |
| 38829910828 | 12017016664 | `c8e7b870b6266e11864eeda4d5a97bdb96bdfab37b66df7991c9cc65b64e14ae` |
| 38829910828 | 12016632501 | `cb47191a0409b9ad6e051dfcd76d27efaf11ce0bdd91c4dc90c3c7ce5a702c5b` |
| 38829910800 | 12017111392 | `220e8700188673da89d552c0bcdf0129aba6cee658e7bb2fb666d09efceda240` |
| 38829910800 | 12016961710 | `b5abee953870d6ebd08a137cd0e53fb42d3f331564aeefa7c577622b8d05e2e1` |
| 38829910800 | 12016308185 | `ea4dd73094410ab1663a90ec0890278cda57444add53e9b2e399a1a08fc15a93` |
| 38829910800 | 12016188111 | `577ba3a602ebdb82439bfb48f77c3a550824a9ae12c45b5760cec049363af292` |

Console-only successful runs: 38829910955, 38829910966, 38829910977, 38829910942, 38829910967, 38829911048, 38829910793, 38829910810, 38829910803, 38829910784, 38829910815.

Next-branch native evidence is separate: schedulere59770native+42pure per mode across four combinations, hosting-history65771native+33pure per mode across four, forwardinga0285native+15contract across four. Their authors downloaded/digest/CRC/named-verified exact artifacts. Scheduler full failed packaging (CRLF disk inventory versus LF Git blobs) and independent review found missing retained observation/audit recovery witnesses; both corrections remain required. Hosting-history initial68-case fixture failure is preserved, with test-only include correction; shipping remains reviewedf6b. No new branch is integrated or accepted from native-only evidence.

## Subsequent independent review and parallel evidence

The independent b55 review verifies shipping/tests/workflows/tools identical to3bbc, but finds a cloud-confirmation recovery blocker: loss of a later package assignment event with its audit retained can revive an older current-assignment claim. Root's bounded correction and regressions are outside this frozen source and require fresh native/full acceptance. Parent3bbc's run and artifacts remain preserved. No additional concrete blocker was found in the bounded catalog/renewal/routing/schema integration review.

Catalog872 full38826955926 is terminal SUCCESS. Its author verified final12018535216 SHA256 `b1ccda52c50608ccd92ede3ea5ac4aa5ede099998aa14721a1d7514c7570d011`,1892 HTTP/289 recovery/1903 Apache,5467 FULL/2683 COMPLETE/2670 UPDATE members matching exact Git, and all11 output bytes identical to its preserved Windows build. Seven actual catalog captures match the previously inspected checkpoint. Proofs work/catalog-readiness872-full-verification.json and work/catalog-readiness872-crossplatform-verification.json. This is parent-specific evidence.

Scheduler corrected5fbafab8913cbe9796584e0c9515fd7c8cb52960 native38833639848 passes all four combinations. Author and independent domain reviewer verified four actual archive digests/CRCs/source/run/named outcomes:102 native,42 default and42 no-INI cases each. Storage recovery now checks retained package counts and exact original actor/start/finish audits; no replay is added. Proofs work/schedule-5fb-native-verification.json and work/20i-service-observations/qa/scheduler-5fb-independent-verification.json. Full38833639837 passed exact package checks and continues; no browser/release clearance yet. Preserve failed60f/e597 evidence and the local omitted--commit invocation68214; corrected Windows33450 is separate.

Hosting-history657 checkpoint38832527134 is terminal SUCCESS. Agent downloaded12018383407 SHA256 `111dbed44229c420bfe52a0d2361a440b2a2c1a4788ad1d986f29087498823a6`:282 members,69 named reports,1989 HTTP including44 hosting-history cases, no unexpected diagnostics. Four full owner/client desktop/mobile screens were inspected and complete. Two auxiliary mobile element-only screenshots have overlay/cropping issues and are excluded from visual acceptance. Proof work/20i-service-observations/qa/hosting-history-657-browser-verification.json. This checkpoint does not include full recovery/Apache acceptance or root integration.

New mainb8c432ca master-license runtime remains unmerged pending the separate [Devin integration findings](LICENSING_INTEGRATION_REVIEW.md). No licensing code was changed by the20i review.

## Terminal combined3bbc full evidence

Run38829910791/job116746050262 is terminal SUCCESS through source-mutation verification. Final artifact12018482950 is124,152,238bytes, SHA256 `ba9807af5c02642cd4524120457f001d04206370c5d19f7c9d0bc1fce07f28c1`. Root downloaded the actual archive and independently verified394 safe unique members, ZIP CRC,108 named reports and11 clear diagnostics, including1945 HTTP,289 unprivileged recovery and1959 Apache outcomes. All167 final screenshots are byte-identical to the inspected checkpoint.

All11 checksummed files and three nested ZIP inventories match:5494 FULL,2697 COMPLETE,2684 UPDATE. FULL equals exact Git3bbc; COMPLETE equals its site tree; UPDATE contains the exact filtered payload and decoded metadata. Preserved Windows3bbc FULL/COMPLETE member bytes agree exactly; UPDATE differs solely at its generated manifest source-commit field, with identical Git trees independently established for3bbc/a99. Local archive work/combined3bbc-final-12018482950.zip and proof work/20i-combined-3bbc-final-verification.json retain the evidence.

The subsequent independently discovered cloud history-recovery bug is not exercised by these older tests. This full pass is preserved parent evidence, not release clearance or acceptance of the corrective descendant. Root's installer refresh33253 now completed successfully and closed; all shipping PHP/JS syntax and regenerated inventory pass. Catalog and VPS reviewers found no additional concrete blocker in the bounded repair after historical relink regression coverage was restored. Corrected native/full execution remains required.
