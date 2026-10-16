# Combined hosting history, forwarding and scheduler evidence

Recorded 2026-10-15. Exact source `4f683f883d0ce809de64be5c97f78c73c0b89d39` is the normal integration of forwarding `1992e376`, hosting history `17593061`, cloud recovery `a9637f2b` and scheduled inventory `2b9b0dc0` on [PR151](https://github.com/afterburn25/Cajun_Web/pull/151). CI merge `c92340eccc7a793bf3d38c4bb1bc7e09e86e3fab` has the identical Git tree. Later continuity commits change Markdown only. Published 1.24.0 remains immutable; this is not release or live-provider acceptance.

## Actual combined browser checkpoint

[Original full run38839969235](https://github.com/afterburn25/Cajun_Web/actions/runs/38839969235), job116777563713, passed package, native, HTTP/Chromium and unprivileged recovery stages and entered Apache checks with no failed step. Apache and final full-artifact verification remain pending at this checkpoint; current later-stage activity alone is never used to infer an earlier stage passed.

Downloaded artifact12021785526 is122,000,992 bytes, SHA256 `35044b76b5ffcdde034e81ebc60e901ff6e1c84ca1f84f43c89bdae5f0024900`. Verification checks source/run identity, digest, size, safe unique ZIP members, CRC,75 named-result reports and11 clean diagnostic reports. Its HTTP report contains2,065 passing executions with2,054 distinct labels. Eight reused labels account for11 additional executions; these are not represented as2,065 unique assertions. There are186 actual screenshots. Local complete named-outcome proof: `work/20i-queue4f-browser-verification.json`.

The new combined UI was reviewed from those actual artifact bytes:

- Root inspected seven forwarding captures: client desktop/mobile and completed form, owner details and bounded crops from both original full owner pages. Request-only consent, pending state, cancellation, separate permission, review and decline remain visible. Long values wrap. The narrow native select abbreviates its label. The auxiliary owner-mobile element capture has a sticky-navigation composition artifact; the original full-page crop does not. No blocking finding in the inspected views. Proof: `work/20i-queue4f-forwarding-visual-verification.json`.
- Domains independently inspected four hosting-history owner/client desktop/mobile captures, using original owner crops `[280,18890,1410,19830]` and `[0,28230,320,29780]`. Sharing/password controls, dated observations and saved-read limitations are readable; client views do not expose owner controls. No blocking finding in these sections. Proof: `work/domain-agent-queue4f-hosting-visual-verification.json`.
- Catalog independently inspected all four scheduler element captures, including failure/backoff and revoked approval. Blank password fields, unchecked approval, current state and history are readable without observed clipping/overlap. No blocking finding. This review does not cover whole-page navigation or collapsed setup instructions. Proof: `work/catalog-agent-queue4f-scheduler-visual-review.json`.

This is scoped visual review, not a claim that all186 screenshots or all tall owner-page content were visually inspected.

## Native and Windows package evidence

All31 focused workflows on exact4f are terminal SUCCESS. Sixteen actual matrix archives have been downloaded and independently verified across PHP8.2/8.4 and MariaDB10.11/MySQL8.4:

| Lane | Run | Named checks per combination |
|---|---|---|
| Scheduler | 38839969152 | 102 native,42 default and42 minimal-PHP document cases |
| Hosting history | 38839969054 | 71 native,33 default and33 minimal-PHP cases |
| Forwarding requests | 38839969171 | 85 native and15 contract cases |
| Cloud creation/confirmation | 38839969219 | 45 creation and87 confirmation cases |

Proofs: `work/20i-queue4f-scheduler-verification.json` and `work/domain-agent-combined4f-native-verification.json`. Success statuses for other focused workflows are not represented as downloaded-artifact verification here.

Exact4f Windows build completed successfully with all11 checksums, manifests, exact Git source/member/directory comparison and decoded UPDATE verification. FULL has5,542 files, COMPLETE2,723 and UPDATE2,710. Preserved directory: `work/build-evidence/4f683f883d0ce809de64be5c97f78c73c0b89d39`; proof: `work/20i-queue4f-windows-verification.json`. The initial missing-commit invocation refused before packaging; it remains a failed invocation, not a successful build.

## Incoming readers: preserved failures and bounded corrections

These branches are not yet part of4f. Their original failed runs remain retained and are never relabeled as successful.

| Branch | Observed failure | Correction and current evidence |
|---|---|---|
| [Domain renewal-date observation PR152](https://github.com/afterburn25/Cajun_Web/pull/152) | Original078 full38839208232 passed1,992 HTTP checks before the browser fixture failed to find the registrar form. Aging all retained read timestamps broke earlier exact cloud-confirmation evidence while rendering the page. Failed artifact12021267711 is retained. | Test/docs-only `6ba2fb4e9f59fccc0c57d58554f743d55270f19d` waits for the real shared cooldown before each registrar/contract read, never rewrites retained timestamps, and checks inherited epochs plus a new complete registrar receipt. Root reviewed the bounded diff. Shipping remains exact078; its four native archives pass60 native and79/79 pure cases. Corrected native38841973697/full38841973723 are running or queued; actual corrected browser evidence is still required. |
| [Registration periods PR153](https://github.com/afterburn25/Cajun_Web/pull/153) | Original3d0 native38840929063 fails all four combinations after15 native passes because a fixture expects the reverse of the existing canonical suffix order. Original packaged38840945414 fails at native integration; browser never ran. All failure archives and exact3d0 Windows packages are preserved. | Test/docs-only `ee3d76a169bbe71a998346ceb9018e75c34133dd` asserts exact canonical `.co.uk`,`.uk` rows and year arrays. Shipping remains exact3d0. Corrected native38841634058 passes80 native and62/62 pure cases in all four actual downloaded archives, independently reverified by domains. Packaged38841642068 remains active; screenshots are required before integration. |
| [Cloud-provider identifiers PR154](https://github.com/afterburn25/Cajun_Web/pull/154) | Original0e2 native38841123530 passes MySQL97 native and85/85 pure cases but MariaDB fails after8 native passes with mixed-collation CONCAT. Original full38841123460 also fails native integration and continues its original recovery path. | `6eeeb020f3aa095b56aa81e7f4b2be6aeb2a0175` applies a four-line binary-coercion correction to common/dedicated start/finish expressions, including the inner finish hash and adds an explicit SQL/PHP witness-byte equality assertion. Root and domains independently reviewed storage SHA256 `62915fdd5e8a750c8911157b5856bebea029d937df29a98013427bb6514b7af1`; counters, witness contents and predicates remain unchanged. Corrected push native38841913897/full38841913938 are active or queued; actual corrected matrix/browser proof remains required. |

The cloud correction follows documented [MariaDB CONCAT binary-result semantics](https://mariadb.com/docs/server/reference/sql-functions/string-functions/concat) and the [BINARY cast](https://mariadb.com/docs/server/reference/sql-functions/string-functions/binary-operator). Documentation and source review do not substitute for execution on the actual supported databases.

## Coordination and next action

Follow-up after the browser documentation checkpoint: corrected cloud-provider run38841913897 is now terminal SUCCESS. All four actual downloaded archives verify98 native and85/85 pure cases, including SQL/PHP witness-byte parity on both database engines. Proof: `work/20i-cloud-providers/qa/38841913897/verification.json`. Its corrected full/browser acceptance remains pending. Registration-period checkpoint38841642068 has passed native integration and entered HTTP/Chromium; corrected domain full38841973723 is queued. Preserve the original failed runs described above.

Domains independently checked64 exact shared-source invariants across frozen6ba/ee3/6eee and root4f, with no source edits or speculative merge. The union must preserve both list operation/register/reader entries, both snapshot types, fixed transport paths, domain scalar binding and return unions, and all dedicated readiness/start/result hooks. Root scheduler shipping and its guarded recorder extraction remain authoritative because the incoming branches predate them. Scheduled reads stay hard-coded to PACKAGES_LIST; no incoming operation enables authCode. Keep dedicated sequence-first history checks and run the scheduler browser fixture last. Local review: `work/domain-agent-incoming-shared-boundary-review.json`. This source checklist does not certify a future combined tree.

Latest main `79434bb87cf3535c61ccd6182a88aa08dea7919c` fixes the licensing schema parser's missing TINYINT support. Pure PHP source diagnostics now recognize all current columns and normalize numeric display widths. Populated pre-master upgrade and internal master-key delivery findings remain open in unchanged source; [licensing review](LICENSING_INTEGRATION_REVIEW.md) records the precise scope. Devin retains licensing ownership, and this newer main is not imported into4f.

Follow the original4f full run, verify final recovery/Apache evidence and exact packages, finish corrected reader browser/native checks, then normally integrate accepted readers under root review. Preserve the reviewed shared recorder, typed scalar context and additive list readers; scheduler browser fixture stays last. Regenerate combined installer inventory and run exact combined acceptance after integration. The [owner API reference](TWENTYI_OWNER_API_REFERENCE_INTAKE.md) remains retained; no example, live provider request, charge, customer mail, SSH or deployment was executed by this checkpoint.
