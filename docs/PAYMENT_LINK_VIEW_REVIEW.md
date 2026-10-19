# Payment-link view isolation

This correction starts at Devin PR202 source `750f1ab8f4cf03f92585209e3880298df9b8aa90`, on separate branch `codex/payment-link-view-isolation`. The enclosing commit is **fix: isolate stale payment links from invoice details**. It targets the existing payment-link branch, not current main. Published1.25.0 and the separate renewal-invoicing PR252 remain unchanged.

## Reproduced problem

The original anonymous bearer-link page selected current invoice metadata before checking request state. It then displayed the current reference/title and the saved request amount for every recognized token. Revoked, superseded, expired and changed requests therefore continued to expose edited invoice details. A superseded link for a subsequently paid invoice also displayed the old amount as due beside a message saying no further payment was due.

Independent default and no-INI subprocesses reproduced those behaviors from the actual original page, state and invoice-hash function bodies with explicitly modeled database responses. This is a bounded privacy/display correctness finding, not evidence of portal access, payment execution or a real customer incident. Project/client reassignment is a stored-state recovery defense: the existing studio form already refuses ordinary project ownership transfer.

## Corrected behavior

The public page loads only the request by token hash. A dedicated projection returns only a terminal state for revoked, superseded, expired or unknown request states. A prepared request reads its current invoice once, verifies the original project/client and active client, and checks the original invoice fingerprint against that same captured invoice. Only a valid prepared projection includes the reference, title, instructions, request purpose, amount and expiry.

Paid requests show a metadata-free paid notice without the previous amount due. Moved, inactive, changed, missing or nonpayable invoices expose no invoice details. Rendering uses only the closed projection and escapes its text. It does not reread unchecked invoice metadata after validation.

The existing token entropy, encrypted storage, lookup hashing, no-store/no-referrer/noindex headers, rate limit, invalid-token handling, staff preparation, revocation, ledger and provider execution are unchanged. No schema or migration changes are introduced.

## Verification and continuation

The original reproduced failures are retained outside the checkout under `work/payment-link-review-2026-10-19`; the original result SHA256 is `6aff7a0649667046d8706a9b6ace3ab2b1e845f7d431330fd9ac1b6309161b3c`. The [source-bound receipt](evidence/payment-link-view-review.json), SHA256 `ab433dcf213708506367e9438566f2f11d66902e615bf63c098722232c346094`, retains119 named corrected modeled outcomes across48 PHP subprocesses in default/no-INI modes,11 independent source-review checks and34 root source bindings/inverse checks. They all pass with no PHP diagnostics; counts are separated by scope and are not native application counts.

The additive native cases run inside the existing `payments-native-results.json` producer. They exercise the real joined invoice read and rendered card, terminal/changed/paid states, client disablement and stored project/client moves, then restore all synthetic invoice/client/project rows. Existing native assertions remain in place. The installed HTTP/browser proposal retains all53 original assertion expressions and adds nine binding-change scenarios, exact restoration and four registered desktop/mobile captures. Its35 syntax/source/URL-fence checks pass; actual installed execution remains pending. The local acceptance-artifact helper also passes its221 named checks; these validate the helper, not the installed application.

Local syntax, modeled function executions and installer generation do not establish native SQL or installed-browser acceptance. Those original-source CI gates, independent actual-image inspection, normal integration through Devin's branch, combined-main acceptance and release-download verification remain required. Do not rerun unchanged queued jobs for delay.

## Parallel continuation context

The required 20i renewal, catalog and capture gates were freshly inspected on19October. Their remaining jobs were unassigned; two other original component jobs were running. No dependency deadlock or required approval was established. This is an observed hosted-runner wait, not a passing result.

The official20i current module download resolves through My20i sign-in, as does the API documentation console. No module archive or new registration/cloud catalog response contract was obtained from public sources. Root requested a browser sign-in without asking for passwords or API keys in chat. Existing provider read/write limits remain unchanged; the remaining 20i schemas are not guessed.

