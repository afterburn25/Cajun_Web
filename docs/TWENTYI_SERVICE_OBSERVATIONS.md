# Client hosting observation history

Unreleased on `codex/twentyi-service-observations`, based on combined `3bbc42bd56270d1c754ff2e72ea039b9c557460e`. The preceding registrar725, nameserver9ea and renewal ed4/f62 branches remain unchanged. Root owns integration, combined acceptance and releases. No live provider request, write, charge, customer mail, SSH or deployment occurs.

## Verified provider contract

The primary [20i retrieve-packages reference](https://docs.20i.com/api/retrieve-packages-api), retrieved 2026-10-15, documents `GET /package` returning an array of package objects. Existing `InventoryPackage` validation retains the documented identity, enabled flag, name/names, type reference/name and creation instant. This increment adds no endpoint, reader, response field or provider behavior. It projects existing immutable saved inventory only. A complete returned response does **not** establish account-wide coverage; coverage remains unknown. `enabled` is an observed provider setting, not live uptime, successful provisioning, billing or website health. Unknown product specification contents, provider stack users, private labels, credentials and sibling packages are never projected to clients.

## Product flow

The owner opens Provider settings → Client hosting history, explicitly prepares its local storage with the current owner password, then chooses **Share from latest saved read** for an existing explicit package/client assignment. Sharing requires the exact current account revision/key context and the latest package intent to be a complete saved response within15minutes. Its package must be present. The refresh-start audit must follow the exact current link/relink audit by insertion ID, even within the same second. A read captured before assignment/reapproval cannot become an anchor. Current event/audit counts are paired with exact actor/resource/detail identity, and the audit identities are included in the retained fingerprints. The owner-approved read is the first visible observation; earlier snapshots are excluded. Assigning a package alone does not share its history.

Clients open Hosting services → Service history. Each card shows dated saved observations and the latest read state. New owner/scheduled inventory reads appear through projection; this feature initiates no reads. A higher intent sequence wins even when its clock timestamp is earlier. New failed, unfinished, partial, missing-package or stale outcomes remain explicit, with older success labeled historical. A missing package in an unknown-coverage response does not prove deletion or cancellation.

Current unlink, reapproval, reassignment, disabled client/profile, profile revision or credential-context change removes the old sharing authority. Re-enabling explicitly selects a new visibility boundary. Turning off sharing immediately removes client history. Direct client sessions are required; support sessions cannot reuse this route. Owner views preview the same safe projection. Private cache/referrer headers, escaping, pagination and root/subfolder internal-path denial apply.

## Storage and recovery

New immutable `service_observation_permissions` rows hold the approval identity/revision, actor/time, exact client/link/account/key binding, current link-event fingerprint and saved refresh ID/sequence/hash. They hold no copied provider snapshots. An append-only hash chain, exact audit records and an independent settings count witness protect the approval history; paired permission/audit loss cannot reset authority. All earlier anchors remain validated after replacement/revocation. Writes share the existing staff mutex and transaction, including count and audit. Replayed owner submissions are idempotent; stale forms cannot replace a newer permission. Preparation rejects existing transactions before any DDL and refuses missing established storage, markers, count witnesses or incompatible schema.

Saved source reads remain in existing inventory tables. Projection validates intent/outcome audit witnesses, package counts, typed selected-package fields and exact approved anchors. Current link-event/audit counts prevent a lost latest reapproval event silently reviving an earlier permission. Missing results with surviving finished audits are recovery failures, not unfinished reads. Restore original rows, markers and audits; do not reconstruct consent or reset counters.

Limits: existing post-anchor inventory rows do not carry cryptographic source hashes. Structural/audit checks detect tested partial history loss and malformed fields, but this is not proof against coordinated deletion or rewriting of every associated row and witness. No new journal or inventory append hook was added. Native source provenance never substitutes for a live provider verification.

## Ownership and verification

New shipping files are the `service_observations*` schema, provider modules and dedicated admin/portal controllers/views. Minimal shared edits add provider-admin dispatch/view, portal dispatch/private headers and Hosting services navigation. Catalog owns scheduler `inventory_record` extraction; this branch does not edit that persistence function. Tests add typed-document checks, native database cases, actual packaged HTTP/Chromium owner/client desktop/mobile flows and Apache internal paths. The focused matrix covers PHP8.2/8.4 with MariaDB10.11/MySQL8.4. Installer hashes must be refreshed from final shipping source.

Frozen shipping source is `e5c8aba856813cf294de31b59e22a3e837a4bc5c`. Corrected checkpoint `65725ad12c5e563ec15f227cc35ea3b680f3eef0` only adds the missing standalone test layout include and its documentation; `git diff e5c8aba8 65725ad1 -- site` is empty. Local installer refresh validates 2,706 exact entries; PHP/JS syntax, Python AST and 33 pure document checks per PHP mode pass. Local Windows native execution remains blocked by the unchanged vault0600 guard.

The initial focused run38832233011 reached 68 passing cases before its final standalone owner-render fixture failed with undefined `admin_action()`. The real owner route already loaded the layout helper. Failed artifact12017890922 was downloaded and verified against SHA256 `c7c3ac356057893cdda5177701ecda29c960534712d28ef934ba686d1f37f8ea`, including ZIP CRC and named failure. Initial application checkpoint38832257408 failed at the same fixture before browser execution. Both failures remain preserved.

Corrected native run [38832431406](https://github.com/afterburn25/Cajun_Web/actions/runs/38832431406) succeeds in all four combinations. All four actual archives have SHA256, ZIP CRC and every named report checked: each contains 71 native, 33 default-PHP pure and 33 no-INI pure passing checks. The cases include same-second initial/relink visibility boundaries, exact actor/client audit identities, latest-intent clock rollback, current revocation, pre-assignment/sibling privacy and original-history restoration.

| Runtime and database | Artifact | SHA256 |
|---|---|---|
| PHP8.2 / MariaDB10.11 | 12018105751 | `f21138d228bfe4754b061fb7b34fb6de641e34239911daafecd47b6c049b5142` |
| PHP8.4 / MariaDB10.11 | 12017911928 | `93c0535a568a3745ea90cb2d7ff992cf089f0d3b440ca75626594ed9881100cd` |
| PHP8.2 / MySQL8.4 | 12017906171 | `1833b82ac13ca0fa523bfc288400daccb3a54770bd151b165e66a56a0695144e` |
| PHP8.4 / MySQL8.4 | 12018375622 | `bb566a58c62b998284b2e94d2a1a0a35001ddb4d6795d4d4a9231cd29891b784` |

Local proof is `qa/hosting-history-657-native-verification.json`. Root independently reviewed the corrected source boundary; core SHA256 is `f6b314e33c019089a41f5ba103f443e9a273eada9425eab52507f077f9b0012a`.

Exact corrected application checkpoint [38832527134](https://github.com/afterburn25/Cajun_Web/actions/runs/38832527134), job116754283896, completed successfully: package/native/actual HTTP/Chromium and unchanged-shipping checks pass. Browser artifact12018383407 was downloaded: 111,492,050 bytes, 282 ZIP members, SHA256 `111dbed44229c420bfe52a0d2361a440b2a2c1a4788ad1d986f29087498823a6`, matching metadata and valid ZIP CRC. Every check in 69 named reports passes, including 1,989 HTTP outcomes and 44 contiguous hosting-history cases. Eleven diagnostics contain no unexpected errors; the retained estimator422 is the expected invalid-input response.

All four full owner/client desktop/mobile captures were visually inspected. Current owner password, sharing controls, history boundary explanation, enabled/disabled dated observations and literal escaped type text are visible without blocking clipping. Long owner screenshots are additionally inspected through unchanged-pixel bottom crops; original bytes remain preserved. Two auxiliary mobile element-only screenshots have capture defects: the owner's fixed topbar crosses its automatic-scroll crop, and the client's second card is clipped. Those two extra images are not counted as visual clearance; both full mobile captures show the complete section. No UI was hidden or altered for capture.

| Actual full capture | Dimensions | SHA256 |
|---|---|---|
| Owner desktop | 1440 × 18315 | `4ced11ca9913dfa7f5175d55358b05f378c7ba0073fa26c86e5c8583800d04cd` |
| Owner mobile | 320 × 27561 | `4345d5553c09b9b0615bfcd06590341718d3c9d24258caa0306d13c7054fdb90` |
| Client desktop | 1440 × 1389 | `5e19535eff8417e3811c088d73d8f88ef2cad273a30550e5b40a5deaf7468a49` |
| Client mobile | 320 × 1787 | `7c944fa948fa5ec238523288810c551626f1fec622b894fc90a373f1f585089e` |

Local archive is `qa/hosting-history-657-browser.zip`; named proof and exact screenshot paths are in `qa/hosting-history-657-browser-verification.json`. The source/file handoff is `qa/service-observations-handoff.json`. This checkpoint intentionally omits extended recovery and Apache, so it cannot establish full release acceptance. Root owns normal integration, combined tests and publication through [PR149](https://github.com/afterburn25/Cajun_Web/pull/149). Final evidence documentation changes no shipping files, tests, workflow or generated inventory. Next: root normally integrates this branch and repeats full acceptance on the combined source, preserving the original fixture failures and all prior branches.
