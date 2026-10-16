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

The exact Windows3bbc package proof remains verified (FULL5494/COMPLETE2697/UPDATE2684;11 checksums). [Combined full38829910791](https://github.com/afterburn25/Cajun_Web/actions/runs/38829910791), job116746050262, passed package/native/browser integration and is running unprivileged recovery. Preserve that handle. Its browser checkpoint is now independently verified as detailed below; recovery/Apache, final archive comparison and exact-main release gates remain pending. Main remainsb8f8528b, already normally preserved. Following documentation-only commits intentionally differ from the frozen FULL package while leaving shipping, tests, workflows and installer inventory unchanged. Published1.24.0 remains unchanged;1.25.0 is unreleased.

## Three active next branches

- `codex/twentyi-scheduled-refresh`: owner-approved scheduled package reads. Root reviewed fixes for retained document/result hashes and original start/approval/result bindings. Initial60f95ca4 native38831603128 passed65 cases on both MySQL combinations but failed both MariaDB combinations on incompatible collations. Agent preserved failed evidence and is correcting exact-byte SQL comparison, uncached staff-marker authority and retained normalized headers. Corrected native/browser acceptance is pending. Shared inventory-writer compatibility and rollback still require native evidence.
- `codex/twentyi-service-observations`: client hosting history. Root found a pre-assignment exposure: the latest fresh read can predate the current link/relink because the existing context matcher compares only revision/fingerprint. Agent added exact paired assignment-audit ordering versus refresh-start audit, including same-second tests, inside the new module. Root re-reviewed core SHA256f6b314e33c019089a41f5ba103f443e9a273eada9425eab52507f077f9b0012a and found the identified boundary closed in source. Frozen corrected sourcee5c8aba856813cf294de31b59e22a3e837a4bc5c has native38832233011 and browser38832257408 running; native/browser acceptance remains pending.
- `codex/twentyi-forwarding-requests`: separate forwarding request permission, client request/cancel and owner review/decline. Root found that loss of a later relink event with its audit retained can revive an older permission because only the selected event kind was counted. Agent added all-kind assignment-history coverage and three loss regressions; root re-reviewed core SHA256aa389ae2cfa959e2ca0180e90a9ca7811bc71d569f0d351575ff104370f1a742 and found the identified revival closed in source. Native/browser acceptance remains pending. No dispatch registration exists; acknowledgement/readback semantics remain unverified.

All three agents are active in separate checkouts. These new branches are outside3bbc acceptance and are not counted completed. Root integrates reviewed increments sequentially and owns release checks. No live provider call, payment, customer mail, SSH or deployment occurred.


## Combined packaged browser checkpoint — verified

Artifact12017850896,105,826,920 bytes, SHA256 `8ba2550bb38c033555ed08ce042381ab494a855fd3f891e4b8f6ec68b8304cd4`, belongs to the same frozen3bbc/PR-mergea99 tree above. Root downloaded and verified273 safe unique ZIP members/CRC,69 named-result reports,11 diagnostic reports and1,945 passing HTTP/Chromium cases. The combined run includes34 catalog,32 renewal and21 cloud-confirmation cases, plus the corrected foreign-client mailbox refusal. This checkpoint has167 actual screenshots and no final distribution. Its three feature screenshot families differ from parent checkpoint bytes and receive fresh visual inspection.

Root inspected all four actual catalog history/list desktop/mobile screenshots at original resolution. Historical version1 retains12.5000GBP and one website; the latest withdrawn mapping and editable new review remain distinct at19.2500GBP/two websites/zero disk. Readiness reasons, labels, forms and navigation fit their viewports. Domain/cloud agents inspected all eight exact combined originals and readable owner-section crops. Renewal screens show pending review, literal escaped messages and withdrawal/review/decline controls; long IDs wrap. Cloud screens retain original accepted-pending evidence separately from observed/assigned package evidence and clearly qualify current location, health and billing. No clipping or overlapping controls was found. These are pending/review and post-confirmation captures; no uncaptured intake/pre-submit screen is claimed. Renewal visual proof: work/20i-service-observations/qa/combined3bbc-renewal-screens.json. Archive `work/combined3bbc-browser-12017850896.zip`; proof `work/20i-combined-3bbc-browser-verification.json`; images `work/combined3bbc-browser-review`. Full recovery/Apache/final packages remain pending. No live provider acceptance is inferred.

Cloud parent0ea full38827979412 is separately terminal SUCCESS. Its author downloaded and verified final12017179352 SHA256 `39bba16bb95d9dd2be9180b5ac806fcdb62cfd500712ac8a59853b6265aa5a16`:385 members,107 named-result reports,11 diagnostics,1899HTTP/289recovery/1923Apache,11 checksums and3 nested package/member/directory/source manifests. Proof/archive in `work/20i-forwarding-requests/qa/cloud-0ea-full-verification.json` and `cloud-0ea-full-12017179352.zip`. Parent acceptance remains separate from3bbc and from the newer forwarding implementation.
