# Financing replacement and receipt recovery

## Scope and predecessor

This correction starts at `84e1f4c61f5ef2b63cd4f565b0f5daf3683346fd`, tree
`a206e95562ab751cd9461db3a8ed32c4305ecbec`. It addresses FIN-01, FIN-02 and
the separately identified inherited FIN-AUTH-01 replacement admission issue,
and the subsequently confirmed initial-enrollment competing-charge admission
gap.
The application, schema and fixture changes are bounded by
`FINANCE_RECOVERY_SOURCE.json` and `tests/finance_recovery_provenance.py`.
The independent notification and software authority corrections are outside
this standalone source proof.

The original finance review and the subsequent review of intermediate code
identified these requirements:

| Boundary | Required behavior |
| --- | --- |
| Replacement create has no returned ID, or its reply is lost, while payoff commits | Preserve an independent unresolved successor obligation, the exact original signed remaining contract, and the predecessor identity. Payoff must remain permanent. |
| A manual or separately admitted receipt reduces a period covered by an existing recurring subscription | Commit a retirement obligation with the receipt, including when the gateway is disabled or cancellation fails. |
| A late external recurring collection is verified after that receipt | Retain the external outcome for accounting without allocating it twice; prevent another charge or enrollment while accounting remains unresolved. |
| Gateway permission changes during predecessor retirement | Recheck current enablement, environment, owner review and account before admitting create. Preserve the already completed retirement. |
| A recovery-backed subscription is replaced again | Require its retained account and environment before reserving or contacting the predecessor. A credential revision for the same account remains usable. |
| Client authority is revoked before reservation or during predecessor I/O | Use the existing current-client authority reader at both admission points. Do not discard an external result already admitted before revocation. |
| An initial recurring subscription is requested while a one-time covered-period charge has an unresolved outcome | Refuse a new initial create inside the existing financial transaction; keep the prior charge identity and allow recovery of an already-admitted external result. |

No source for the historical reported 454-check successor correction was found
in the retained ref/object search. The historical 4/8 and 6/30 raw probes were
also unavailable. This work does not claim to reuse or rerun those results.
It retains a fresh exact84 reproduction and its actual source copies instead.

## Durable replacement evidence

`license_finance_replacement_attempts` is an additive journal beside the existing
gateway v3 subscription and history tables. Reservation occurs inside the
existing financial mutex and transaction, before any replacement create can be
sent. It stores the predecessor identity and exact immutable predecessor
contract, signed remaining contract, agreement binding, account hash,
environment and credential revision. It stores no transaction key, credential
payload or opaque payment token.

The nullable unique `open_offer_id` admits at most one unresolved reservation
for an agreement. Its presence is checked independently of the current
subscription status and the permanent payoff state. An old subscription being
paid off, a delayed lifecycle event, an audit rollback, or a later owner retry
therefore cannot erase an unknown successor or admit a second create.

| Journal state | Meaning and recovery |
| --- | --- |
| `prepared` with no dispatch timestamp | No replacement create has been admitted. Current authority and gateway permission are rechecked after predecessor I/O. Owner or scheduler recovery can close this reservation only after verifying the exact predecessor is terminal. |
| `dispatching` | The transaction admitting one create has committed. A crash or lost reply leaves an unresolved outcome, never an automatic permission to create again. |
| `unknown` | The create outcome remains unknown. An owner may identify the existing successor for verification against the stored original contract. |
| `observed` | A known candidate identity has been committed independently of subsequent readback, binding and retirement audits. |
| `bound` | The candidate is the current subscription and the predecessor history is retained. The original account binding remains evidence for later recovery or replacement. |
| `not_dispatched` | A proven unsent reservation has been closed with retained terminal predecessor evidence. |

Known IDs are recorded before later operations that can fail. Owner recovery
uses the original stored contract even if receipts have since paid the entire
agreement. It verifies the existing candidate, preserves predecessor history,
binds the identity atomically, and retires an obsolete successor with terminal
readback. It never sends a replacement create. Receipt, license, domain,
commercial-rights and permanent-payoff records remain unchanged by that
recovery.

Existing ambiguous replacement rows predating this journal are preserved. Their
original reservation contract cannot be reconstructed safely from current
balances or a guessed provider ID. The owner view and error therefore state the
manual reconciliation limit explicitly; upgrade does not invent a historical
contract or mark these rows resolved.

## Receipts and external collections

The receipt hook runs within the existing serialized `ledger_receive` or
verified one-time charge settlement transaction. It identifies the signed
installment and exact current subscription. For a newly covered receipt it
records `retirement_pending`, or sets a sticky `retirement_required` flag on an
in-flight replacement reservation. The strict audit and real receipt commit or
roll back together. Network cancellation and readback occur after that
transaction has released the staff mutex.

Disabled settings, provider timeouts, error replies, lost cancellation replies,
nonterminal readback, incorrect contract readback and audit failure retain the
obligation and the real committed receipt. Delayed failed, suspended, cancelled
or expired lifecycle observations do not clear it. Owner and scheduler retries
use the exact saved identity and require terminal confirmation. Ordinary
verified recurring settlements remain healthy, and repayment of a previously
collected and reversed period retains its established recovery behavior.

The first qualifying receipt attempts retirement immediately after its commit.
Later receipts can retry the same retained identity using a fresh readback. A
lost cancellation reply therefore remains uncertain until a subsequent verified
terminal observation; it does not justify another create or another receipt.
Once the plan reaches permanent payoff, synchronization uses the existing
payoff retirement path, which reserves `review` and requires terminal proof
before recording `paid_off`. It preserves any separate unknown successor
reservation throughout.

The new `license_finance_collection_reviews` table retains a separately verified
external collection that cannot safely be allocated to the changed ledger.
Its unique transaction ID, subscription, signed period, amount, contract hash
and nonsecret account context make replay idempotent. The receipt is not
duplicated or silently altered. A strict audit failure rolls the new observation
back so the original event can be retried.

Unresolved accounting records fence both a separate installment charge and new
automatic enrollment, including otherwise valid future unpaid periods. Current
active automatic billing and unresolved retirement also fence previously
prepared separate payment links. Conversely, terminal retirement with no
unresolved external collection still permits a legitimate separate charge; an
admitted unresolved separate charge prevents a competing recurring replacement.

The owner view lists these accounting obligations. This correction deliberately
does not infer a refund, credit allocation or accounting disposition from a
provider status. It supplies no unaudited “clear” action. A retained collection
continues to require explicit accounting reconciliation; automatic new-charge
admission stays blocked until a separately verified disposition is available.

## Initial enrollment and an already-admitted one-time charge

A separate independent native witness confirmed that a processing, uncertain
or review one-time period charge can still have a zero recorded ledger balance.
That balance cannot authorize a new full recurring contract. The locked initial
covered-period loop now calls the existing exact invoice/attempt guard. Both
opaque-token and saved-profile initial callers reach this same serialization
boundary. An explicitly declined attempt remains a positive admission control.

The initial finalizer retains its separate responsibility for an external result
that was already admitted. If a current admission condition is no longer true,
it retains the known candidate identity and retires that contract; it does not
discard the observation or silently resolve the earlier charge.

Four `prepared_gateway` cases in the retained initial receipt suite previously
assumed the unsafe overlapping new admission. They now state their narrower
historical scope explicitly: canonical synthetic `creating` rows and existing
provider candidates represent already-admitted results, then current owner
reconciliation and current finalization process them while actual separate PHP
charge writers commit. Other current initial callers are unchanged. All 13
assertion call sites, which expand to 358 runtime checks, remain byte-identical;
the report marks the four historical entries. The first adaptation manifest's
count of 14 regex ranges included the `fep_ok` declaration; an explicit
clarification retains that metadata correction without rewriting the original
manifest.

The unchanged independent witness has 11 checks: two fail on frozen04, and all
11 pass with only the reviewed initial-admission guard added. The owning native
driver adds 26 current admission and historical-result checks after its prior
387 checks, including both caller paths, all three unresolved states, declined
controls, exact retained charge/receipt identity, and recovery of an existing
candidate without resolving an uncertain charge.

## Current authority and account permission

Replacement admission calls the existing `service_plans_current_client` reader
before preflight projection, inside the replacement reservation, and again
before admitting create after predecessor I/O. The first check avoids changing
financial projections for an already invalid session. The later checks cover
completed version revocation, deactivation, idle and maximum session age, and
session/client identity mismatch.

If authority changes after the old subscription has been retired, the unsent
reservation and terminal proof remain visible for owner or scheduler recovery.
If create was already admitted, subsequent authority loss does not prevent
durable recording of the observed external outcome. These are distinct
boundaries, exercised separately by the regression.

The actual adapter always reads current settings, including when supplied a
captured account context. It refuses disabled or changed environment/account
permission before transport, while a valid key revision for the same account
uses the current credentials. The replacement dispatch transaction also
refreshes the request settings cache. This closes the reviewed cached-context
bypass without claiming a universal ordering guarantee for every unrelated
settings writer.

For a subscription created by this recovery path, a later replacement checks
the prior attempt's retained account before any new reservation or predecessor
request. This does not retroactively establish original-account provenance for
legacy subscriptions whose original account was never recorded (the separate
OBS-03 concern).

## Additive preparation and entitlement documentation

The existing owner financing-gateway preparation invokes the separate recovery
v1 migration after its unchanged v3 preparation. The base v3 schema bytes,
markers and upgrade assertions are retained. Recovery preparation rejects an
inherited transaction, unauthorized administrators, unknown versions,
unmarked tables, missing established tables and populated interrupted setup.
It can resume explicitly marked empty additive tables. Repeated preparation
validates populated canonical storage without rewriting historical rows.

An unchanged source invariant also required a missing explanatory sentence in
the license service. The added comment states that financing payoff removes
only payment-based restrictions and does not reactivate a license deactivated
before payoff. The handler checks `is_active` first, and financing does not set
it to one. Existing permanent-payoff deactivation restrictions remain intact.
The before/after PHP token streams, excluding comments and whitespace, have the
identical SHA-256
`a2d15211fc5f1e09925dc8d085ef7576a046104db47c8f4be0ccf680570afae5`.
All 54 original source-contract assertions pass after this comment-only repair.

## Evidence and execution limits

`FINANCE_RECOVERY_VERIFICATION.json` records the final command results,
assertion mapping, source identities and diagnostic classification. The raw
reports, diagnostic streams and immutable source copies are retained under
`finance-recovery-evidence/`.

The fresh exact84 FIN-01/02 run contains 217 checks: all original 138 pass, while
20 of the additional 79 failure/control checks fail. The first correction
clears the eight unknown-successor failures; the combined intermediate run
clears all 20. Original check names and order remain the first 217 entries of
the final retained native report. The original runner and assertion bodies
remain byte-identical after removing the one appended recovery hook.

The older 292-check payoff suite assumed that its three sequential manual
receipts produced only a final-payoff cancellation. Immediate FIN-02 retirement
invalidated that timing assumption and produced eight retained failures. The
bounded fixture adaptation explicitly pauses synthetic gateway settings for
the first two manual receipts and enables them for the final receipt. Every
original assertion remains intact, and the suite still isolates its original
final-payoff faults. Twelve additional native/process checks exercise repeated
receipts with the gateway enabled, including cancellation timeout and lost
reply. They retain immediate first-receipt cancellation coverage and verify
fresh readback, truthful terminal/review state, exact receipt count, no new
create, and owner recovery without changing payoff. The finite fixture edit
and its independent review are retained with the evidence.

The separate exact84 FIN-AUTH baseline has 25 checks and 11 failures after
adding the requested zero-down deactivation case. Its authority writer uses
actual owner authority, the shared mutex and a native transaction with explicit
synthetic SQL authority changes. It does not claim to exercise
`studio_client_save` or an HTTP tenant exploit. The final authority cases run
through the unchanged existing payoff-native workflow command; the helper
entry also runs the separate authority/settings writer processes.

The native evidence uses PHP 8.3 and disposable MariaDB 10.11 storage. Current
application functions are loaded or extracted without modification. The
provider boundary, credential unsealing and curl transport in the adapter
probe are synthetic. No real merchant, provider, customer payment, production
credential, email or HTTP/browser operation was performed. Local verification
does not claim the future PHP 8.2/8.4 and MySQL/MariaDB hosted lanes have run on
this correction.

The final 413-check owning driver and adapted 358-check initial suite bind their
captured files to final frozen05 source. The earlier 387-check driver and
292/232/25 compatibility results remain explicit frozen04 checkpoints. For
those compatibility files, the only subsequent application difference is the
reviewed two-line initial-admission guard; the source proof reverses exactly
those two lines for comparison with the retained snapshot and verifies equality
of every other captured application file. It never executes the predecessor
view. The unchanged full and focused CI commands still execute all existing
suites against the eventual current combined source.

Intermediate failures are preserved and classified, including the original
defects, explicit fixture dependency updates, one broad fixture-snapshot
comparison, unavailable synthetic private token setup, and the client-session
redirect in an added migration negative. An exit code of zero without the
required JSON report is not accepted as a passing native result.

An intermediate replacement-receipt run also recorded one generic PDO `HY000`
before create dispatch. The original diagnostic did not contain a numeric
driver code. The reservation remained proven unsent and no receipt or candidate
was created in that case. A later isolated run passed all 232 checks without
reproducing the exception. This is non-reproduction, not a diagnosis: concurrent
DDL remains an unproved hypothesis, and the original failure is explicitly
unresolved. A test-only logger now captures numeric PDO code, operation trace
and a SQL-text hash if a new PDO error occurs, without recording SQL arguments,
credentials, keys or provider payloads. No generic database retry or timeout
change was added.

A later compatibility sequence encountered a distinct, diagnosed server exit.
The owned MariaDB 10.11.14 process recorded signal 11 while executing the
unchanged foreign-key metadata join. Its backtrace entered temporary schema
table cleanup, and PHP recorded numeric driver 2006 followed by driver 2002 in
the next command. The completed 292-check result, both incomplete commands,
raw error log and source snapshot are retained. Container counters did not show
an OOM kill; this evidence does not identify the engine's underlying defect.
After preserving the stopped server's evidence, the same owned datadir and
options were restarted and only the incomplete 232/25 suites were run again
from their immutable frozen04 runtime. No application retry, timeout change or
assertion relaxation was introduced. This server exit does not retroactively
diagnose the earlier generic `HY000`.

The diagnostic streams are not empty. Synthetic nonzero-initial-payment seeds
intentionally sign while payment-link preparation is unavailable, producing
the retained `financing_offers.php:261` diagnostic. The original six readback
and cancellation failure controls also remain. The final verification manifest
classifies each observed line against its exact source and expected count;
unexpected diagnostics are not ignored.

## Source admission and release handling

Canonical mirrors and installer inventory are generated with the unchanged
builder. The FIN proof checks the entire exact84-relative path delta, every
current correction and evidence hash, regular-file modes, unchanged historical
tests/builders/workflows/JSON receipts, and canonical current inventory. Each
modified file has a finite, unique, reversible edit anchored to the actual
exact84 blob. Negative cases reject broader scope, missing or altered source,
forged predecessors, omitted inverse edits and changed evidence.

Only the existing ticket source-comparison hook receives reconstructed exact84
bytes after complete current FIN verification. Historical ticket/proposal
receipts and their expectations are unchanged. Native fixture execution,
package construction and browser acceptance always use current source. No
workflow matrix, timeout, job command or minimum assertion gate is lowered.

This standalone branch must be frozen and independently reviewed before local
commit and publication. Any later composition with notification or software
corrections requires its own exact outer source proof; this receipt is not an
allowlist for unrelated changes.
