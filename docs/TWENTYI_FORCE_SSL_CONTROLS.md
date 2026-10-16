# Force SSL change requests — isolated implementation

This lane starts from root candidate `c1743664c9ea6d81abb0c2784c20a2fdee4720fd` on `codex/twentyi-force-ssl-controls`. Dedicated source and the exact reviewed shared application changes are applied locally. Independent runtime, browser and amended native-fixture source reviews are clear; no live provider action, release or deployment has occurred. Root owns integration. Directory-listing observation arguments and Devin's software licensing lane remain separate.

The owner-supplied [reference](reference/20i-owner-api-34e713-redacted.txt), original SHA256 `34e713c3c0b4145b6a5e3f5929d0bd470605793f98d2d917c2b28f21814c3e95`, lines1671–1678 documents `POST /package/{packageId}/web/forceSSL` with one JSON property, `value`, whose value is a boolean. It supplies no acknowledgement schema. The existing strict boolean GET contract remains the independent observation source. Local positive-integer and body-size limits are defensive validation, not claimed provider limits.

The new internal operation is `packages.forcessl`, preserving the shared operation-name validator. It is excluded from generic owner grants. Its builder accepts exactly `packageId` and boolean `value`; the reviewed transport admission permits only the exact fixed-host path, canonical single-property JSON and existing fixed headers. No generic package POST, response classifier expansion, schedule or customer permission is added.

An active owner must supply their current password and explicitly review a different desired flag against the exact latest complete, fresh Force SSL observation and current hosting assignment/account. The request consumes a single existing operation grant. Independent immutable consent, dispatch-completion and readback witnesses use the original shared provider gateway and operation ledger. They do not create a second provider execution journal.

One staff mutex remains owned continuously from approval consumption through DNS, credential validation, gateway execution and the terminal witness. The provider call runs outside a database transaction. Replaying the request returns saved history; a new nonce cannot bypass an unresolved earlier request. `not_sent` is independently witnessed only when execution never entered the provider-perform boundary. HTTP4xx, a failed ledger status, zero attempts, a missing claim or an expired reservation alone do not prove no send. After entry, the effect remains uncertain regardless of an HTTP200 body.

The owner can explicitly perform a new quota-limited GET after a terminal attempted dispatch. The exact newly returned receipt must still be the latest eligible read when atomic confirmation rechecks current owner, account, assignment, binding and history. A later failed or unfinished read supersedes that receipt. A matching boolean records **desired flag observed after the request**. It does not prove the POST caused that value, that a certificate is valid, or that a redirect or HTTPS works. Null, unavailable or a different flag remains unresolved. The original shared operation result is unchanged.

Client observation sharing remains its own explicit permission. A pending change makes an older saved observation historical. No client write capability or private approval history is exposed. The new projection hook is a no-op only when control storage was genuinely never prepared; surviving evidence with a missing marker, counter, row or matching audit fails closed without GET repairing storage.

## Interrupted dispatch remains unfinished

A crash before the immutable terminal witness leaves an owner-visible interrupted request. Replaying it never resumes a delayed worker, and neither a timeout nor an expired claim releases its fence. Ordinary Force SSL observations remain available, but this implementation does not reconcile or resend the interrupted change. A separately reviewed operator recovery transition must durably establish that delayed local dispatch is impossible without automatically retrying the provider action. That transition is not implemented; database edits are not an operator workflow. The complete controls capability must not be declared finished before recovery is reviewed and accepted.

## Evidence at this source checkpoint

Seven dedicated runtime files pass PHP syntax inspection. Local request/classifier76, exact transport37, retained ForceSSL165 and roots-transport24 checks pass in both normal and minimal PHP modes; the existing operation contract has230 passing named checks. This proves typed request construction, refusal boundaries and lack of invented acknowledgement semantics only. The dedicated native fixture was invoked by the original focused run but stopped during bootstrap before native setup; browser fixtures remain unexecuted. The native fixture covers independent counters/witnesses, paired loss with affected-row assertions, immutable shared ledger outcomes, phase-specific refusal, interrupted claims, latest-receipt supersession and separate sharing. The browser fixture reserves a dedicated synthetic owner and package2101, preserves prior history/account bytes, and budgets six GETs plus one loopback POST with the existing cooldown.

Root cleared the bounded two-test dependency correction (proof3692af96). Original installer refresh83310 completed successfully; staged Git parity is checked before the feature commit. Next commit/push once under root approval and inspect the exact four-combination focused artifacts. The reviewed workflow preserves12 named reports and unchanged full-acceptance triggers. Root will integrate sequentially and own eventual combined browser/package acceptance. No live provider account or production authorization is implied.

## Preserved first focused failure and fixture correction

Frozen source `c33150c2650818b2d404f3fc7b257e068a9b314e` ran [38868819957](https://github.com/afterburn25/Cajun_Web/actions/runs/38868819957), attempt1, on PHP8.2/8.4 with MariaDB10.11/MySQL8.4. All four jobs pass the request and transport pure reports, then fail before native setup on a repeated plain require of the controls view already loaded by the provider controller. The correction changes only that test include to require_once; runtime/workflow/installer bytes remain identical. Exact original bootstrap execution reproduces exit255, while corrected bootstrap exits0 with284 source files loaded, without DB or provider execution. This is fixture/bootstrap proof, not native acceptance.

All four original archives contain the same four fully passing named reports:76 default/+76 minimal request and37 default/+37 minimal transport cases. Digests, sizes, unique safe paths, CRC and every named outcome were checked. Native result files are absent.

| Environment | Artifact | SHA256 |
|---|---|---|
| 8.4-mariadb-10.11 | 12032273384 (3238 bytes) | `cdbaf74faeebbc5edccb0714643c43dc714bf952df0a64548e272591c92d257e` |
| 8.4-mysql-8.4 | 12032183535 (3238 bytes) | `1226fd86150860b7f46e204b885da66d9cf86945deab77cc3823b1b4b07aaa44` |
| 8.2-mysql-8.4 | 12031834974 (3238 bytes) | `e34d5ff5c8567f3e218b7bb1e3c3bd4cc4e7be951f3a974fca62f5ad223f340f` |
| 8.2-mariadb-10.11 | 12031524680 (3238 bytes) | `676b42189ad79aa856f6bdd09150c9774146d6c34f67ecc1299bd84a5c33684f` |

Original logs/metadata/archives and offline correction proof remain in local qa/38868819957 and qa/force-ssl-controls-native-loader-correction.json. The corrected source requires its own four-environment native acceptance. No automatic retry, dispatch recovery, browser completion or provider success is inferred.
