# 20i password-reset email template

This increment starts from main `a86f6f4fa592e6f715a9420aec8de4622258642e` on `codex/twentyi-reset-email-template`. The enclosing implementation commit is **feat: review and confirm 20i reset email templates**. It adds an owner-only section under **20i account → StackCP password-reset email**. It is not yet a release or a live-account acceptance result.

## Owner workflow

1. Prepare the private template history with the current owner password.
2. Explicitly read the current template from the saved, enabled 20i account.
3. Edit the replacement and select **Review replacement**. The current and proposed text are shown as escaped source; HTML is never executed as a preview.
4. Approve the exact replacement with the current owner password and select **Save reviewed template to 20i**.
5. Use **Check current template** to make a separate read and compare the exact requested text with what 20i reports.

A positive affected-row acknowledgment is a request acknowledgment, not confirmation of the final template. Zero, unexpected responses and interrupted requests remain unresolved. The application does not automatically retry a write. A different or unavailable readback does not release that request's fence. A later matching read establishes that the provider reported the requested text; it does not prove causation, rendered email appearance or delivery.

Incorrect passwords and rejected submissions retain the original proposal when its review is still current. A stale review cannot silently approve new text. The final confirmation transports the exact reviewed text in a canonical encoding, preserving its bytes across HTML form processing. Editing uses the browser's ordinary textarea behavior, including form line-ending handling; the subsequent review and send are bound to the resulting submitted text.

## Contract and scope

The [retained owner reference](reference/20i-owner-api-34e713-redacted.txt), lines1005–1019, specifies `GET /reseller/{resellerId}/passwordResetEmail` returning the template string and `POST` with `{content:string}` returning an integer affected-row count. This implementation uses only the fixed authenticated reseller self alias. It does not guess an envelope, placeholder language or empty-content reset behavior. Reads may contain an empty string; replacement writes must be nonempty. The64KiB decoded text limit is an application safeguard, not a claimed provider limit.

The feature neither sends email nor generates password-reset tokens, changes passwords or grants access. It does not provide a mail renderer or a default-template reset action. Existing mailbox composition templates remain separate.

## Storage and authorization

Every owner view and effect checks current authorization, excluding client and support sessions. Read and review context binds the owner, authentication version, provider profile revision and encrypted credential identity. The dedicated write adapter requires a private content resolver; generic operation approval cannot authorize this operation. The durable ledger and private journal record one admitted request before any POST. Missing or inconsistent history requires recovery and cannot be mistaken for a fresh installation.

Template bodies are encrypted with the existing private provider key and per-record authenticated context. The general audit and operation grant contain identifiers and digests, not template text. Keep the original provider key and database together. This increment never replaces the key to bypass damaged history.

Where an exact, finished ledger record survives an interrupted local history write, **Restore saved request history** restores the local record without resending. Provider confirmation remains a separate read. An unresolved claim without sufficient completion evidence stays fenced.

## Verification and remaining gates

The [foundation receipt](evidence/password-reset-template-foundation-review.json) binds169 named offline checks passing in each of default and no-INI PHP, ten syntax checks, and the exact canonical source files. Existing operation/nameserver, mailbox, cloud-site and transport-policy regression reports also pass locally. These use injected responses and the real request validators; no provider connection or SQL execution is claimed.

The full application recipe adds two contract executions and one guarded native fixture. All152 predecessor commands and the entire earlier browser driver remain present. Three reports are added to each required report set. The installed browser workflow adds template review, retry and explicit readback coverage, with desktop/mobile review captures.

Native SQL, installed HTTP/Chromium, actual screenshot inspection, supported-environment and combined-main acceptance remain mandatory before release. Local syntax, modeled data and source review cannot replace these gates. No existing queued run is rerun solely because it is waiting.

Final local review records are committed with this increment:

- [Runtime/storage review](evidence/password-reset-template-runtime-review.json): 75 modeled checks per PHP mode, with explicit OpenSSL configuration for Windows no-INI execution; six syntax checks. The initial extension-path harness error remains recorded.
- [Independent integration and form review](evidence/password-reset-template-integration-review.json): 20 inspected source files and 16 modeled controller/renderer checks per mode. The first-newline display correction covers both the editor and source review.
- [Fixture review](evidence/password-reset-template-fixture-review.json) and [handler proposal](evidence/password-reset-template-handler-review.json): 17 local fixture checks and 37 offline handler checks; all 82 earlier handler outcomes retained exactly. The native fixture uses the workflow's actual assigned database port. Browser newline normalization is accounted for before the exact final confirmation.
- [Root source checks](evidence/password-reset-template-root-static.json): 68 syntax, source-binding, reviewed-handler and shipping-mirror checks. [Acceptance helper](evidence/password-reset-template-acceptance-helper.json): 230 named checks, including preservation of every earlier command, report and browser flow.

These counts describe their named local scopes and are not native or installed acceptance totals.

The root installer refresh completed successfully, regenerated the shipping inventory and provider mirrors, and checked every shipping PHP file plus application/installer JavaScript. Documentation and whitespace checks pass. This refresh creates no release archive from the uncommitted working tree; a new published version remains gated by exact-source application acceptance.

## Other current work

Payment-link companion [PR253](https://github.com/afterburn25/Cajun_Web/pull/253) is pushed at `b1948eebee0f254611b1fc908d27da226b2cd118`, targeting Devin's PR202. Its first exact-source full run is [39247478772](https://github.com/afterburn25/Cajun_Web/actions/runs/39247478772); database/browser acceptance was queued at handoff. Separate domain-renewal invoice [PR252](https://github.com/afterburn25/Cajun_Web/pull/252) remains at `632362f6a4bfe2592bde991ee630a1947a5656dd`.

Published1.25.0 remains immutable. Complete20i, automatic full-catalog/provider-image imports, registration/transfer purchase contracts, remote support login and SSH are not declared complete. The missing current catalog/registrar contracts still require access to the My20i documentation; the owner sign-in request remains pending.
