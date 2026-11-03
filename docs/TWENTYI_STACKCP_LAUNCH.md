# Owner StackCP browser handoff

## Candidate status — 2026-10-18

This increment starts from main `f688f79ee838e86e4774bfa5ec71fc7fb79126a5` on `codex/twentyi-stackcp-launch`. It connects the existing protected authentication key, local assignment and access observations to an actual owner-requested StackCP session exchange and browser redirect. It is a development candidate, pending native database and complete application acceptance. Published 1.25.0 remains unchanged; this is not a deployment or confirmation of remote sign-in.

The implementation deliberately supports the default `https://www.stackcp.com/login/sso` destination. The provider-authored SDK's retained source, credential roles, exact endpoints and provenance are documented in the [original handoff contract](TWENTYI_STACKCP_HANDOFF_CONTRACT.md). That source is a historical redistribution, not current target-account acceptance. Custom branded control-panel domains, client/delegated launch, provider session revocation and interactive SSH remain outside this increment.

## Owner workflow

In **20i connection**, an owner prepares the separate authentication configuration, records the intended Stack-user assignment and explicitly refreshes both package membership and whole-user grants. The current observation must contain exactly the selected package grant. This local admission rule does not restrict the resulting session to one package: it is a **whole Stack-user session**, and provider grants can change after observation.

The new **Open StackCP** section prepares its protected access history through an explicit password-confirmed POST. Viewing the page makes no provider request and creates no database admission. A ready form identifies the package and Stack user, requires fresh consent and the owner's current password, and opens a new tab. Its signed approval expires after five minutes.

On submission, the application checks the current owner, client, provider profile and keys, package link, assignment and both observations. It records a unique admission before any exchange. The general-key customisations read happens first; a custom branded destination is refused before creating a token. The separate OAuth key then requests a token, which is exchanged for a StackCP session using the actual validated brand object. The session is delivered once through a 303 redirect, with no token or destination in page HTML or flash messages.

Only the authorized provider-settings page adds the exact StackCP form destination to its browser policy. Other pages retain their existing policy. The source page and redirect disable caching and referrer forwarding; the new tab has no opener. Current local authority is checked repeatedly, including after the committed handoff record immediately before delivery. Remote sign-in itself cannot be confirmed by a redirect.

## Repeated submissions and recovery

An admission is never resumed or sent again. Reposting the original signed form returns its safe retained outcome without opening credentials, sending requests or redelivering the session. Network uncertainty, process interruption and delivery failure consume that admission. The application records phase intent before the brand, token and session operations, then records accepted phases separately. It keeps the shared authority mutex across the exchange, with only short database transactions.

History displays finite outcomes: interrupted, no browser handoff issued, remote outcome unknown, or handoff prepared without confirmed browser delivery/sign-in. Preparation is recorded before the final authority check and delivery callback, so the retained terminal cannot prove the browser received a redirect. Interruption after token/session intent is conservatively unknown. No retry policy infers that a timed-out request was unsent.

A fresh review is bound to the latest previous admission and event for that profile/package. A form left open in another window becomes invalid after an intervening attempt. When the previous result is unknown, the new form warns that a provider session may already exist and requires explicit consent to create a **new** session. This neither recovers nor cancels an earlier session. There is no permanent account-wide lock after one unknown result.

The append-only event family has authenticated documents, ordered audit records and independent preparation/count/head proofs. Missing, modified or inconsistent established history requires restoration of the original records and private key; preparation cannot reset it. History is bounded to 10,000 events, with all eight possible events reserved before a new admission. Reaching capacity refuses new admissions and requires an application update, not deletion of old history. Removing a local assignment or disabling authentication stops future eligible launches but does not sign out an existing provider session.

## Transport and data handling

The dedicated sender fixes all three HTTPS hosts, ports and paths. It validates public DNS addresses before opening the relevant credential, pins the selected address, verifies TLS, disables proxies and redirects, discards response cookies and bounds response bytes. It does not widen the generic provider transport. Test resolver/transport callbacks are internal PHP parameters; HTTP input or environment configuration cannot enable private destinations.

The brand object is retained in memory and forwarded without invented fields. Duplicate decoded names, oversized/nested limits, custom `brandDomain` values and credential echoes are refused. The accepted default subset allows `brandDomain` to be absent, null or an empty string. A review caught that very large numeric credential echoes could change representation during JSON decoding; the correction compares both original bytes and decoded values, with a preserved failing witness and regression cases.

Token/session parsing uses the existing deliberately narrow accepted response subset. There is no claim that token expiry describes session lifetime. Provider credentials, access tokens, session identifiers, destination URLs and raw responses are absent from persistent event/audit/public history. Ephemeral delivery refuses serialization, cloning and a second consumption.

## Verification and remaining gates

Actual local PHP 8.4.25 checks pass in default and minimal modes: 155 transport/default-brand cases, 315 retained handoff cases, 46 new renderer cases, 46 prerequisite renderer cases and 50 access renderer cases. The native runtime proposal separately passed 47 pure document/context/schema cases per mode; these are not database results. Independent reviews covered runtime history/current authority and transport/UI integration.

The [local verification receipt](evidence/stackcp-launch-local.json) binds source files, named local reports, review receipts and preserved failures. It is not a replacement for the required CI evidence.

Thirty actual PHP HTTP-helper and Chromium checks pass against loopback. A closed TLS proxy impersonates only the synthetic StackCP destination and never forwards requests. The test verifies the 303, empty response body, privacy headers, strict URL/policy refusals, new-tab behavior and the failure of the unchanged self-only form policy. It does not authenticate an installed application or contact a provider. The earlier route-interception test timed out and is retained as a test-harness failure; only the corrected closed-proxy run supplies browser evidence.

The additive Linux native fixture uses a disposable SQL namespace, real application preparation/assignment/observation wrappers, genuine cooldowns, synthetic typed transports, audit-failure triggers, exact history restoration and a separately observed blocked-mutex worker. It is guarded against execution outside its intended test environment. Its database assertions have not passed locally. Full application recipes retain all previous commands/reports and add the new native, portable and browser checks; the focused workflow covers supported PHP/database combinations.

The applied acceptance helper passed 323 offline checks, preserving all 221 original outcomes. Native commands increase from 152 to 157; required native/browser reports from 164 to 170; final reports from 208 to 214. The original full-workflow triggers/jobs remain intact, and the new focused suite covers PHP 8.2/8.4 with MariaDB10.11/MySQL8.4 plus a PHP8.3 browser job. Initial external helper-ownership and patch line-ending proof failures were retained and corrected before the final source review. Browser fixture exceptions now write a sanitized failed report; an actual missing-executable probe confirms that behavior.

Before integration or publication: obtain the original exact-candidate native and full-application results, fix failures without discarding their evidence, review the combined application and complete the normal release process. Target-account login remains unverified and requires separately authorized live testing. Other hosting/nameserver candidates and Devin's non-20i work remain separate.
