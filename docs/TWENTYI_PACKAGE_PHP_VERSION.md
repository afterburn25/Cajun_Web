# Assigned-package PHP-version observation

## Source and bounded contract

The owner-supplied full reference has original SHA256 `34e713c3c0b4145b6a5e3f5929d0bd470605793f98d2d917c2b28f21814c3e95`, 195,404 bytes and 3,116 lines. [Retained reference](reference/20i-owner-api-34e713-redacted.txt) lines 2484–2490 describe GET `/package/{packageId}/web/phpVersion`, returning the active platform version string (example `php82`). Lines 2491–2498 describe a separate setter but do not supply its acknowledgement; that operation is excluded.

The JSON-scalar interpretation also uses the retained provider-authored PHP SDK 1.0.6, redistributed at commit `696a5f1299cd2b1e67ae7f688d644ac800f41b67`: `lib/TwentyI/API/REST.php` lines 168–171 decode the response directly without envelope extraction. Its SHA256 is `0f9b724e34bd26fa0cc85dc3d266e97472e2ae70396d2b7451f5995330860031`; Services SHA256 is `ef33bfb747e5c2917ab57c0c9f73403c31af70007bd66c7b08571a3803cde0c1`. This is retained documentation/source evidence, not a current account response capture. Original examples are never executed.

The typed reader accepts exactly one bounded JSON string in an HTTP 200 response, preserving its exact value. An object/envelope, null, array, non-string, malformed JSON, empty/blank/control-bearing value, unsupported status or excessive response becomes unavailable. Body 4,096-byte and value 128-byte limits are defensive local policy, not claimed provider limits. The result does not enumerate available versions, claim compatibility or update PHP.

## Owner and client flow

An owner explicitly prepares the dedicated observation tables, then refreshes a currently assigned package from provider settings. Current password, owner role, current profile/revision and sealed credential identity, exact client/package link/event/audit, complete zero-skipped hosting inventory less than 15 minutes old, and a hosting observation after the assignment/reapproval are required. The existing shared 30-second cooldown applies. One read uses the existing fixed transport and durable inventory executor, with authority checked before credentials/transport and after response.

The owner can separately share a reviewed saved observation with its client. A client sees only the saved scalar, date and current/historical label, never the credential, account metadata or owner refresh controls. Support impersonation cannot open this direct-client view. Sharing can be turned off immediately; later link changes, unlink, client reassignment or credential changes revoke the old authority. Reapproval requires a fresh hosting read, fresh PHP observation and explicit new sharing. Ordinary views do not contact 20i.

A later failed or unfinished intent prevents an older observation being labelled current. The older value may remain historical under the unchanged approved assignment. Failed, missing, corrupt or incomplete original histories do not silently authorize a new current read. No operation-dispatch result is written for this read.

## Storage and integration

New `provider_php_reads`, `provider_php_observations` and `provider_php_permissions` retain canonical typed bindings, immutable value evidence and a versioned permission chain. Actual inserted common sequence, independent start/finish/permission counters, exact common/dedicated audit correspondence, HMAC-bound source/value evidence, and SQL/PHP byte witnesses detect lost or altered retained history. Every outcome is recorded within the existing guarded writer transaction. Read paths never repair tables, markers or counters.

The single executor adds a distinct optional named `packageContext` and typed scalar result. Calls use named arguments so integration can preserve the separate domain context. The shared scheduler wrapper/private writer guards remain unchanged; scheduling continues to allow package inventory only. Closed transport admits only the canonical numeric package ID GET path. No parallel runner/journal, generic URL, SDK execution, secret in stored records, PHP POST or license change is introduced.

## Verification status

Starting commit: `9112cb8c71e31ab761be52a9d1ee2f92d708cc15`; isolated branch `codex/twentyi-package-php-version`. Root and domain source review identified and corrected missing later hosting-intent reconciliation and scalar/list writer mismatch; the direct reader also refuses a binding supplied to a list operation. Native regressions preserve exact restoration and refusal before DNS.

Local pure tests: 87 named passes in each default and no-INI mode; existing inventory-reader 28 and scheduler-document 42 named checks also pass. PHP syntax and Python fixture compilation are separate local checks. Native four-way execution and packaged owner/client browser acceptance remain pending; fixture presence is not proof of execution. `.github/workflows/provider-package-php.yml` covers PHP 8.2/8.4 with MariaDB 10.11/MySQL 8.4. The full application workflow invokes the same native fixture and the six desktop/mobile owner/client/historical captures. Browser setup uses real cooldown waits and runs before the scheduler fixture. Apache protection includes each new private source path.

Linux native acceptance is required because the local Windows credential-vault permission guard remains unchanged. All provider responses are synthetic; no live API, charges, mail, SSH, deployment or release is authorized by these results. Root retains integration/release ownership.

## PHP-version packaging correction — 2026-10-15

The first exact PHP source `1c5005663bb892e3c5569914547f154be4620ffc` is retained in [PR155](https://github.com/afterburn25/Cajun_Web/pull/155). Focused run38846465351 and full38846467235 remain intact, along with PR runs38846423585/38846423574. The first focused job116798016213 stopped at installer inventory verification before PHP/native execution; it emitted no native report.

Git-archive comparison reproduced six stale installer hash entries: Windows working files used CRLF while committed shipping bytes used LF. The affected provider controller/view, shared inventory module, PHP observation core, service-link view and portal router have no logical change. Their working bytes were restored exactly from `1c500566` blobs, then installer metadata regenerated. The correction is verified against staged Git tree bytes, not just the working checkout. Original source-review hashes are retained with the LF identities in local evidence; no credential/authorization/parser/schema logic is altered. This is a packaging failure, not a failed native runtime assertion.

Local87/87 pure, reader28, scheduler42, syntax and docs results remain accurately scoped. Actual four-way native and packaged owner/client/browser acceptance must run on the corrected descendant; root integration and release remain separate. No live action occurred.

## PHP hosting-history order guard — 2026-10-15

Source review after the packaging-only `c7f0ab73bcd4826e11283e1cc1813c46af3b8b5b` checkpoint found an additional bounded authority gap. A later failed or unfinished hosting inventory intent could have its retained sequence moved below an earlier complete H1 while its common start audit remained intact; the profile count/detail checks would still pass, and an H1-backed PHP observation could appear current again. Root and domain review confirmed this source path; it is distinct from the earlier CRLF installer metadata failure.

The correction compares the latest package-inventory intent by sequence with the latest intent by its exact paired common-start audit ID, after existing profile-wide count/actor/detail correspondence. Both selections use the identical profile and packages.list/legacy-null filter, so unrelated domain/cloud reads do not become package authority. Any disagreement requires recovery. No shared history machinery, signatures, transport or client data scope changes.

Native fixture cases cover both failed and unfinished later hosting intents with sequence rollback, stale projection, refusal before DNS/request, and exact restoration in a finally block outside a caller transaction. Actual execution remains pending on the corrected descendant; presence of these fixtures is not acceptance. Original1c500 and c7f0 focused/full runs remain intact. The installer is regenerated from normalized LF source and checked against staged Git bytes before freezing. Root owns combined integration and release; cloud6eee final acceptance remains separately verified.

## PHP native retry assertion correction — 2026-10-15

The first actual native result on packaging-corrected `c7f0ab73` came from focused38847342463, PHP8.4/MySQL8.4 job116800759725. Artifact12024192616 (3,279 bytes; SHA256 `dd4ce9e2091c98522ae5a84290b3be4896a828b2928132f0d26a93bd6221ed51`) was downloaded and verified against exact source/run metadata, digest, size, safe case-unique ZIP paths and CRC. It contains25 passing native checks followed by the failed exact-sharing-retry assertion and its unexpected-failure wrapper; both87-case pure reports pass. This is not native acceptance.

The assertion compared complete PHP arrays containing newly hydrated immutable binding objects with strict identity. The corrected assertion checks the persisted canonical document and its hash, binding hash, anchor proof, unchanged audit count and unchanged permission-row count. Runtime, authority, storage and installer hashes remain exactly those in `cd38a78a8ee39c99fea3e6f6abeadc2845a149b7`; only the fixture and continuity documentation change. The earlier hosting-order safeguard remains core77ca32d7. Original1c500/c7f0/cd38 runs are preserved, not retried or cancelled.

Next: actual four-way native and exact full owner/client browser acceptance of this corrected test descendant. Previous syntax/pure/source review is not a substitute. Root retains integration/release ownership; no live provider action occurred.

## PHP-version credential-echo safeguard — 2026-10-15

Starting source `e6ce001b448af85674cf90af076b1ab26dc3759f`, [PR155](https://github.com/afterburn25/Cajun_Web/pull/155). An offline scripted transport with a synthetic credential reproduced a bounded issue: a valid HTTP 200 JSON scalar containing the raw credential or its Base64 authorization form was accepted as a complete PHP-version value. No real credential, provider request or persistent database was involved in that reproduction.

The credential-owning typed read now checks the decoded scalar before returning it. A raw or Base64 credential substring, including surrounding text or JSON escapes, becomes the existing `unavailable` / `invalid_value` outcome with no value. Reasons, labels, stored results and logs receive no credential material. Other reader types, accepted ordinary version strings, shared signatures, authority and the complete-state label are unchanged; this review makes no claim about unassessed readers or other encodings.

Local verification passes99 named pure checks in each default and no-INI mode, PHP syntax and exact canonical/shipping mirror comparison. The original synthetic reproduction remains preserved separately; the same corrected cases return unavailable with no echoed value. A new native regression uses the actual executor, a synthetic response and the real cooldown, checking one request, no new saved value row, a redacted unavailable result, unchanged historical client value, and no raw/Base64 credential in persisted outcome/audit/value/permission data. Actual four-way native and packaged browser acceptance remain pending on the corrected descendant; the fixture itself is not execution evidence.

Original e6ce focused38848617278/full38848617330, cd38 focused38848350192/full38848350248, c7f0 focused38847342463/full38847316629, and 1c500 focused38846465351/full38846467235 are retained without retry or cancellation. The original installer failure and hydrated-object test-identity failure remain documented separately. Root and catalog independently cleared the exact reader/mirror63905d05, purede9c5e8b and native6d5a8ca8 delta. Normalized installer refresh passes; all2,733 inventory hashes match2,734 staged shipping files, with only the typed reader mirror and generated inventory changed in shipping. Next: fresh native/full acceptance on the frozen descendant. Root owns integration and release; no live action occurred.

## PHP native rollback-fixture correction — 2026-10-15

Starting source `c1b9eb5f2e4a8660f43d368ad26e4abbc7ee8833`, [PR155](https://github.com/afterburn25/Cajun_Web/pull/155). Actual preserved e6ce native run38848617278 now reached45 passing checks, then failed with `Account transaction must not be nested.` Artifact12024733543 (PHP8.4/MySQL8.4; SHA256 `2c04dce04b8c5b027e235f0a12c2b6f3d0e8d65c8b1161934726b55569984948`) and artifact12024608741 (PHP8.2/MariaDB10.11; SHA256 `6bcd5212d48a5ebdaa0b8802619f60fbfa0691ae00e505e6cf930cf9513fd2c9`) are downloaded and verified against exact source/run, digest, size, safe unique members and CRC. Each contains the same46-entry native report with one failure and passing87/87 pure reports.

The actual stack identifies a fixture call to the normal transaction-opening projection helper while the paired-history-loss test already holds a rollback transaction. The corrected assertion calls the internal projection under the staff lock in that existing transaction. Exact recovery reason, no-DNS/request counters, stale-value assertion and finally rollback remain; the normal helper and production transaction guard are unchanged. Only the native fixture and continuity docs change from c1b9. Shipping, installer, authority and credential-echo protection remain byte-identical.

PHP syntax and whitespace checks pass. Actual corrected native/full acceptance is pending. Preserve c1b9 focused38850218141/full38850218170, e6ce and all earlier originals without retry/cancellation; root retains integration/release ownership. Root independently cleared the exact one-expression correction at native fixture SHA256 `d19fb38ab0654c062d61262d47bf7e736ffd001fcd9716c90abc5e397a3fa342`. Next: fresh exact-source four-way native/full evidence. No live action occurred.

## Completed original acceptance — 2026-10-16

The final tested source is `cfc6ecd034ad93cb317fe156ed0621fa50436c10`, tree `174f0f829115e8f4f6583a7673dadd79e094b68c`, on `codex/twentyi-package-php-version` / [PR155](https://github.com/afterburn25/Cajun_Web/pull/155). This enclosing checkpoint changes documentation only. Runtime remains c1b9, including credential-echo refusal and the hosting-intent order safeguard; cfc6 repairs only the rollback fixture's inappropriate nested transaction. The earlier pending statements above are historical checkpoints.

[Focused run38850899544](https://github.com/afterburn25/Cajun_Web/actions/runs/38850899544) passed all four PHP/database combinations. Each downloaded archive contains99 passing native executions (98 distinct labels),99 default pure checks and99 no-INI pure checks. These overlapping reports are not summed as unique coverage. Metadata source/run, digest, size, safe case-unique paths, CRC and every named outcome were verified.

| PHP / database | Artifact | SHA256 |
| --- | --- | --- |
| 8.4 / MySQL8.4 | 12025981625 | `033de88854ccec8cac69a9db5b2b4a328366163a62547dbf9482a3c8b51ea573` |
| 8.4 / MariaDB10.11 | 12025237391 | `bdedfff48077b2c6655356cf1b7302fc3c5644eb0e67f19babf0b89e97de82f7` |
| 8.2 / MariaDB10.11 | 12025084184 | `6abb58eda904ce3c3e46a36533c42008224b46c6af17d9b434fd249843ce7cdf` |
| 8.2 / MySQL8.4 | 12024979311 | `43d314d72bac9649838919dbe145d43b37d6ae3aa5557e89cea5da6ff8130b17` |

[Original full38850899573](https://github.com/afterburn25/Cajun_Web/actions/runs/38850899573) is terminal success through unchanged-source verification. Final artifact12026887934 is147,990,762 bytes, SHA256 `32d28fdc2e984280083a07be863a4e16aae3ab201116928d35291b021f20771b`:428 safe unique members, CRC clear,117 named reports all passing,11 diagnostics inspected,2,093 HTTP executions (2,082 distinct labels),289 recovery and2,107 Apache checks. Eleven checksum entries agree; FULL5,562 files, COMPLETE2,734 files and UPDATE2,721 payload files match exact Git source and package/update rules. All192 earlier checkpoint screenshots are unchanged.

The browser checkpoint12026415411 is129,336,282 bytes, SHA256 `f5e570525b7bff3b7649759ed748c5267bac893a5051d70ef624a1fd1e0d3641`. Its28 PHP-specific checks pass. All six actual desktop/mobile owner, client and historical captures were inspected, including retained-coordinate crops of the very long owner pages. They show explicit owner refresh/sharing, the assigned client's saved scalar/date, and the older value labelled historical after a failed refresh. No clipped PHP controls or private raw response appeared in those views. This is real Chromium against the extracted local package with synthetic loopback provider responses; it is not a live20i account test. Diagnostics contain only the existing expected estimator422 event, without unexpected requests/page errors/PHP diagnostics.

Reusable local proofs are `qa/38850899544/verification.json`, `qa/38850899573/php-browser-visual-review.json`, and `qa/38850899573/final-package-verification.json`; archive bytes and original checkpoint images are retained alongside them. These local evidence files are not shipping content.

Original failures remain distinctly verified:1c500 full38846467235 stops at installer hash mismatch before native execution; c7f0 full38847316629 and cd38 full38848350248 fail the hydrated-object retry assertion; e6ce full38848617330 and c1b9 full38850218170 fail the nested-transaction fixture. Their original artifacts/runs were neither cancelled nor retried. Actual recovered package/Apache success in those failed runs is not full acceptance. The corrected final source above is the accepted isolated lane.

Root's combined427 has separate source, dedicated PHP browser-owner fixture and acceptance run38853750274. No combined result is inferred from this isolated source. Root retains current-main/licensing reconciliation, final acceptance and immutable release/download ownership. No PHP change operation, version enumeration, scheduling, provider write or deployment was added.
