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
