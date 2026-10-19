<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Vps\QuotaUsage;
use CajunWebPro\Foundation\Vps\VpsOffering;
use CajunWebPro\Foundation\Vps\VpsServer;
use CajunWebPro\Foundation\Vps\VpsSpec;

require __DIR__ . '/../../foundation/vps/VpsSpec.php';
require __DIR__ . '/../../foundation/vps/VpsOffering.php';
require __DIR__ . '/../../foundation/vps/QuotaUsage.php';
require __DIR__ . '/../../foundation/vps/VpsServer.php';

$checks = 0;
$failures = [];
$results = [];

function check(bool $cond, string $label): void
{
    global $checks, $failures, $results;
    $checks++;
    $results[] = ['name' => $label, 'passed' => $cond];
    if (!$cond) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: $label\n");
    }
}

function expectThrows(callable $fn, string $label): void
{
    try {
        $fn();
        check(false, $label . ' (no exception)');
    } catch (InvalidArgumentException|LogicException) {
        check(true, $label);
    }
}

// Fixture specs/prices are synthetic — not real provider plans.
function offering(?int $price = 1499): VpsOffering
{
    return new VpsOffering('fx-small', 'Fixture Small', new VpsSpec(2, 4, 80, 'fx-east', 'fixture-linux'), $price);
}

function server(): VpsServer
{
    return new VpsServer('srv-1', offering(), 'client-7');
}

/** Synthetic adapter evidence, not a provider response or an authenticity check. */
function evidence(VpsServer $server, string $outcome = 'applied'): array
{
    static $sequence = 0;
    $attempt = $server->currentAttempt();
    if ($attempt === null) throw new LogicException('The fixture needs an unresolved attempt.');
    $value = ['server_id' => $server->id, 'client_ref' => $server->clientRef,
        'attempt_id' => $attempt['id'], 'provider_ref' => $attempt['provider_ref'],
        'operation' => $attempt['operation'], 'outcome' => $outcome,
        'resulting_state' => $outcome === 'applied' ? $attempt['to']
            : ($attempt['operation'] === 'provision' ? VpsServer::FAILED : $attempt['from']),
        'evidence_id' => substr(hash('sha256', 'synthetic-outcome-'.++$sequence), 0, 32)];
    $value['evidence_sha256'] = hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    return $value;
}

function snapshot(VpsServer $server): array
{
    return [$server->status(), $server->providerRef(), $server->currentAttempt(), $server->attempts(), $server->history()];
}

function refusesUnchanged(VpsServer $server, callable $action, string $label): void
{
    $before = snapshot($server);
    expectThrows($action, $label);
    check(snapshot($server) === $before, $label.' leaves all state unchanged');
}

function activeServer(): VpsServer
{
    $s = server();
    $s->beginProvisioning('vps-ref-1');
    $s->markActive(evidence: evidence($s));
    return $s;
}

// --- VpsSpec ------------------------------------------------------------------
check((new VpsSpec(2, 4, 80, 'fx-east', 'img'))->vcpuCores === 2, 'spec holds values');
expectThrows(fn () => new VpsSpec(0, 4, 80, 'fx', 'img'), 'vcpu lower bound');
expectThrows(fn () => new VpsSpec(65, 4, 80, 'fx', 'img'), 'vcpu upper bound');
expectThrows(fn () => new VpsSpec(2, 0, 80, 'fx', 'img'), 'ram lower bound');
expectThrows(fn () => new VpsSpec(2, 4, 0, 'fx', 'img'), 'disk lower bound');
expectThrows(fn () => new VpsSpec(2, 4, 80, 'BAD REGION', 'img'), 'region must be a slug');
expectThrows(fn () => new VpsSpec(2, 4, 80, 'fx', ''), 'os image required');

// --- VpsOffering -----------------------------------------------------------------
check(offering()->orderable(), 'priced offering is orderable');
check(!offering(null)->orderable(), 'unpriced offering is not orderable');
expectThrows(fn () => new VpsOffering('Bad Slug', 'L', new VpsSpec(1, 1, 10, 'fx', 'i'), 1), 'slug must be lowercase');
expectThrows(fn () => new VpsOffering('fx', '', new VpsSpec(1, 1, 10, 'fx', 'i'), 1), 'label required');
expectThrows(fn () => offering(-1), 'negative price rejected');

// --- QuotaUsage --------------------------------------------------------------------
$q = new QuotaUsage('servers', 3, 10, 'servers');
check($q->percentUsed() === 30.0, 'quota percent computed');
check(!$q->exhausted(), 'quota not exhausted');
check((new QuotaUsage('bw', 500, 500, 'GB'))->exhausted(), 'quota at limit is exhausted');
check((new QuotaUsage('bw', 12, null, 'GB'))->percentUsed() === null, 'unknown entitlement yields null percent');
check((new QuotaUsage('bw', 12, 0, 'GB'))->percentUsed() === null, 'zero entitlement avoids division');
expectThrows(fn () => new QuotaUsage('', 1, 1), 'quota needs a metric');
expectThrows(fn () => new QuotaUsage('m', -1, 1), 'quota rejects negative use');

// --- VpsServer: provisioning ---------------------------------------------------------
$s = server();
check($s->status() === VpsServer::PENDING && !$s->terminal(), 'server starts pending');
expectThrows(fn () => server()->markActive(), 'cannot activate without provisioning');
expectThrows(fn () => server()->suspend(), 'cannot suspend a pending server');
expectThrows(fn () => $s->beginProvisioning(''), 'provisioning needs a provider ref');
$s->beginProvisioning('vps-ref-1');
check($s->status() === VpsServer::PROVISIONING && $s->providerRef() === 'vps-ref-1', 'provisioning records provider ref');
expectThrows(fn () => $s->beginProvisioning('x'), 'cannot re-provision in flight');
$s->markActive(evidence: evidence($s));
check($s->status() === VpsServer::ACTIVE, 'provisioning completes to active');

// --- VpsServer: failure / retry ---------------------------------------------------------
$s = server();
$s->beginProvisioning('vps-ref-1');
$s->fail('timeout after request');
check($s->status() === VpsServer::UNCERTAIN, 'unconfirmed provisioning remains uncertain');
refusesUnchanged($s, fn () => $s->retry(), 'uncertain provisioning cannot retry');
$s->reconcile(evidence($s, 'not_applied'));
check($s->status() === VpsServer::FAILED, 'definitive no-effect evidence establishes failed provisioning');
$s->retry();
check($s->status() === VpsServer::PENDING && $s->providerRef() === 'vps-ref-1', 'deliberate retry retains original provider ref');
$s->beginProvisioning('vps-ref-2');
check($s->providerRef() === 'vps-ref-2', 'retry reprovisions with new ref');

// --- VpsServer: suspend / resume -----------------------------------------------------------
$s = activeServer();
$s->suspend();
check($s->status() === VpsServer::SUSPENDING, 'active server enters suspending');
expectThrows(fn () => $s->suspend(), 'in-flight suspension cannot re-suspend');
expectThrows(fn () => $s->resume(), 'in-flight suspension cannot resume');
$s->suspendFailed();
check($s->status() === VpsServer::UNCERTAIN, 'unconfirmed suspension remains uncertain');
$s->reconcile(evidence($s, 'not_applied'));
check($s->status() === VpsServer::ACTIVE, 'terminal no-effect suspension evidence restores active');
$s->suspend();
$s->markSuspended(evidence($s));
check($s->status() === VpsServer::SUSPENDED, 'suspension completes');
$s->resume();
check($s->status() === VpsServer::RESUMING, 'suspended server enters resuming');
$s->resumeFailed();
check($s->status() === VpsServer::UNCERTAIN, 'unconfirmed resume remains uncertain');
$s->reconcile(evidence($s, 'not_applied'));
check($s->status() === VpsServer::SUSPENDED, 'terminal no-effect resume evidence restores suspended');
$s->resume();
$s->markActive(evidence: evidence($s));
check($s->status() === VpsServer::ACTIVE, 'resume completes to active');

// --- VpsServer: two-actor decommission -------------------------------------------------------
$s = activeServer();
expectThrows(fn () => $s->approveDecommission('staff-2'), 'cannot approve without a request');
expectThrows(fn () => $s->requestDecommission(''), 'request needs an actor');
$s->requestDecommission('staff-1');
check($s->status() === VpsServer::DECOMMISSION_REQUESTED, 'decommission recorded as request');
expectThrows(fn () => $s->approveDecommission('staff-1'), 'requester cannot approve own teardown');
expectThrows(fn () => $s->markTerminated(), 'cannot terminate without approval');
$s->approveDecommission('staff-2');
check($s->status() === VpsServer::DECOMMISSIONING, 'second actor approves teardown');
$s->markTerminated(evidence($s));
check($s->status() === VpsServer::TERMINATED && $s->terminal(), 'teardown completes as terminal');
expectThrows(fn () => $s->suspend(), 'terminal server cannot transition');
expectThrows(fn () => $s->requestDecommission('x'), 'terminal server cannot re-decommission');

// Decommission from suspended also allowed.
$s = activeServer();
$s->suspend();
$s->markSuspended(evidence($s));
$s->requestDecommission('staff-1');
check($s->status() === VpsServer::DECOMMISSION_REQUESTED, 'suspended server can be decommissioned');

// Withdrawal returns to prior state.
$s->cancelDecommission('staff-1');
check($s->status() === VpsServer::SUSPENDED, 'withdrawn decommission returns to suspended');
$s->requestDecommission('staff-1');
$s->approveDecommission('staff-2');
$s->decommissionFailed();
check($s->status() === VpsServer::UNCERTAIN, 'unconfirmed teardown remains uncertain');
$s->reconcile(evidence($s, 'not_applied'));
check($s->status() === VpsServer::SUSPENDED, 'terminal no-effect teardown evidence restores prior state');
expectThrows(fn () => $s->markTerminated(), 'rolled-back teardown cannot complete');

// --- VpsServer: cancellation ------------------------------------------------------------------
expectThrows(fn () => activeServer()->cancel('staff-1'), 'active server cannot be silently cancelled');
$suspended = activeServer();
$suspended->suspend();
$suspended->markSuspended(evidence($suspended));
expectThrows(fn () => $suspended->cancel('staff-1'), 'suspended server cannot be silently cancelled');
$s = server();
$s->cancel('staff-1', 'order withdrawn');
check($s->status() === VpsServer::CANCELLED && $s->terminal(), 'pending order cancels');
expectThrows(fn () => $s->beginProvisioning('x'), 'cancelled order is terminal');
$s = server();
$s->beginProvisioning('r');
$s->fail('nope');
refusesUnchanged($s, fn () => $s->cancel('staff-1'), 'uncertain provisioning cannot cancel');
$s->reconcile(evidence($s, 'not_applied'));
$s->cancel('staff-1');
check($s->status() === VpsServer::CANCELLED, 'failed order can be cancelled');

// --- History -----------------------------------------------------------------------------------
$s = server();
$s->beginProvisioning('r');
$s->markActive(evidence: evidence($s));
$s->requestDecommission('staff-1');
$history = $s->history();
check(count($history) === 4, 'each transition appends history');
check($history[3]['actor'] === 'staff-1', 'history records the actor');
check($history[0]['status'] === 'pending' && $history[3]['status'] === 'decommission_requested', 'history preserves order');

// --- Every uncertain operation remains fenced until bound terminal evidence ---
foreach (['provision', 'suspend', 'resume', 'decommission'] as $operation) {
    $s = $operation === 'provision' ? server() : activeServer();
    if ($operation === 'provision') $s->beginProvisioning('vps-ref-1');
    if ($operation === 'suspend') $s->suspend();
    if ($operation === 'resume') {
        $s->suspend(); $s->markSuspended(evidence($s)); $s->resume();
    }
    if ($operation === 'decommission') {
        $s->requestDecommission('staff-1'); $s->approveDecommission('staff-2');
    }
    $originalAttempt = $s->currentAttempt();
    $originalHistory = $s->history();
    $fail = match ($operation) {
        'provision' => fn () => $s->fail('timeout'),
        'suspend' => fn () => $s->suspendFailed('timeout'),
        'resume' => fn () => $s->resumeFailed('timeout'),
        'decommission' => fn () => $s->decommissionFailed('timeout'),
    };
    $fail();
    check($s->status() === VpsServer::UNCERTAIN && !$s->terminal(), $operation.' timeout stays nonterminal uncertain');
    check($s->currentAttempt() === $originalAttempt && $s->providerRef() === 'vps-ref-1', $operation.' uncertainty preserves exact attempt and provider');
    check(array_slice($s->history(), 0, count($originalHistory)) === $originalHistory, $operation.' uncertainty retains the history prefix');
    foreach (['retry' => fn () => $s->retry(), 'cancel' => fn () => $s->cancel('staff-1'),
        'provision' => fn () => $s->beginProvisioning('replacement'), 'suspend' => fn () => $s->suspend(),
        'resume' => fn () => $s->resume(), 'request teardown' => fn () => $s->requestDecommission('staff-1'),
        'approve teardown' => fn () => $s->approveDecommission('staff-2'),
        'withdraw teardown' => fn () => $s->cancelDecommission('staff-1'),
        'mark active' => fn () => $s->markActive(), 'mark suspended' => fn () => $s->markSuspended(),
        'mark terminated' => fn () => $s->markTerminated(), 'fail again' => $fail] as $name => $action) {
        refusesUnchanged($s, $action, $operation.' uncertainty blocks '.$name);
    }
    $applied = evidence($s);
    $s->reconcile($applied);
    $last = $s->history()[array_key_last($s->history())];
    check($s->status() === $originalAttempt['to'] && $s->currentAttempt() === null, $operation.' exact applied evidence resolves target state');
    check($last['attempt'] === $originalAttempt && $last['evidence'] === $applied, $operation.' resolution retains exact evidence and attempt');
    refusesUnchanged($s, fn () => $s->reconcile($applied), $operation.' terminal evidence replay refuses');
}

// Evidence is scoped to the exact instance attempt, resource, operation and outcome.
$s = server(); $s->beginProvisioning('original-ref'); $s->fail('unknown');
$valid = evidence($s);
foreach (['server_id' => 'other-order', 'client_ref' => 'other-client', 'attempt_id' => str_repeat('0', 32),
    'provider_ref' => 'other-resource', 'operation' => 'suspend'] as $field => $replacement) {
    $bad = $valid; $bad[$field] = $replacement;
    refusesUnchanged($s, fn () => $s->reconcile($bad), 'mismatched '.$field.' refuses');
}
foreach (['absent', 'not_found', 'unchanged', 'timeout', 'failed', 'accepted_pending', 'pending', ''] as $outcome) {
    $bad = $valid; $bad['outcome'] = $outcome;
    refusesUnchanged($s, fn () => $s->reconcile($bad), 'nonterminal outcome '.($outcome ?: 'empty').' refuses');
}
foreach (array_keys($valid) as $field) {
    $bad = $valid; unset($bad[$field]);
    refusesUnchanged($s, fn () => $s->reconcile($bad), 'missing '.$field.' refuses');
    $bad = $valid; $bad[$field] = 1;
    refusesUnchanged($s, fn () => $s->reconcile($bad), 'nonstring '.$field.' refuses');
}
foreach (['state' => 'active', 'http_status' => '404', 'unexpected' => 'value'] as $field => $value) {
    $bad = $valid; $bad[$field] = $value;
    refusesUnchanged($s, fn () => $s->reconcile($bad), 'extra '.$field.' is not terminal evidence');
}
foreach (['evidence_id', 'evidence_sha256'] as $field) {
    foreach (['', 'x', str_repeat('A', strlen($valid[$field]))] as $index => $value) {
        $bad = $valid; $bad[$field] = $value;
        refusesUnchanged($s, fn () => $s->reconcile($bad), $field.' malformed case '.$index.' refuses');
    }
}
$bad = $valid; $bad['resulting_state'] = VpsServer::FAILED;
refusesUnchanged($s, fn () => $s->reconcile($bad), 'applied evidence must establish the expected resulting state');
$bad = evidence($s, 'not_applied'); $bad['resulting_state'] = VpsServer::ACTIVE;
refusesUnchanged($s, fn () => $s->reconcile($bad), 'not-applied evidence cannot assert a provisioned VPS');
$other = server(); $other->beginProvisioning('original-ref');
check($other->currentAttempt()['id'] !== $s->currentAttempt()['id'], 'separate instances receive different attempt identities');
refusesUnchanged($other, fn () => $other->reconcile($valid), 'same order and resource cannot consume another instance attempt');

// Only definitive not-applied evidence permits a deliberate fresh provisioning.
$noEffect = evidence($s, 'not_applied');
$oldAttempt = $s->currentAttempt();
$s->reconcile($noEffect);
$beforeRetry = $s->history();
$s->retry(); $s->beginProvisioning('replacement-ref');
check($s->attempts()[0] === $oldAttempt && $s->attempts()[1]['id'] !== $oldAttempt['id'], 'new attempt retains the full old immutable attempt');
check($s->attempts()[0]['provider_ref'] === 'original-ref' && $s->providerRef() === 'replacement-ref', 'old and replacement provider references are both retained');
check(array_slice($s->history(), 0, count($beforeRetry)) === $beforeRetry, 'deliberate retry retains prior terminal history exactly');
refusesUnchanged($s, fn () => $s->reconcile($noEffect), 'prior attempt evidence cannot resolve a later attempt');
$newEvidence = evidence($s);
$bad = $newEvidence; $bad['evidence_id'] = $noEffect['evidence_id'];
refusesUnchanged($s, fn () => $s->reconcile($bad), 'consumed evidence id cannot be rebound');
$bad = $newEvidence; $bad['evidence_sha256'] = $noEffect['evidence_sha256'];
refusesUnchanged($s, fn () => $s->reconcile($bad), 'consumed evidence digest cannot be rebound');
refusesUnchanged($s, fn () => $s->markActive(), 'in-flight legacy markActive requires evidence');
refusesUnchanged($s, fn () => $s->markActive(evidence: evidence($s, 'not_applied')), 'markActive cannot reinterpret no-effect evidence');
$s->markActive(evidence: $newEvidence);
$s->suspend();
refusesUnchanged($s, fn () => $s->markSuspended(), 'in-flight legacy markSuspended requires evidence');
$s->markSuspended(evidence($s));
$s->requestDecommission('staff-1'); $s->approveDecommission('staff-2');
refusesUnchanged($s, fn () => $s->markTerminated(), 'in-flight legacy markTerminated requires evidence');
$teardownAttempt = $s->currentAttempt();
$s->decommissionFailed(); $s->reconcile(evidence($s, 'not_applied'));
refusesUnchanged($s, fn () => $s->approveDecommission('staff-2'), 'known-no-effect teardown still requires a new request');
$s->requestDecommission('staff-1');
refusesUnchanged($s, fn () => $s->approveDecommission('staff-1'), 'retried teardown still requires a second actor');
$s->approveDecommission('staff-2');
check($s->currentAttempt()['id'] !== $teardownAttempt['id'], 'deliberate second teardown has a new attempt id');
$s->markTerminated(evidence($s));

// Read APIs return copies, and pre-attempt/terminal transitions remain guarded.
$historyCopy = $s->history(); $attemptCopy = $s->attempts();
$before = snapshot($s);
$historyCopy[0]['status'] = 'forged'; $attemptCopy[0]['provider_ref'] = 'forged';
check(snapshot($s) === $before, 'returned history and attempts cannot mutate the server');

// PHP array copies can carry references: every retained evidence scalar must detach.
$s = server(); $s->beginProvisioning('alias-ref');
$original = evidence($s); $backing = $original; $referenced = [];
foreach (array_keys($backing) as $key) $referenced[$key] =& $backing[$key];
$s->reconcile($referenced);
$before = snapshot($s);
foreach (array_keys($backing) as $key) $backing[$key] = 'changed-'.$key;
check(snapshot($s) === $before, 'caller reference-valued evidence cannot alter retained history');
$copy = $s->history(); $last = array_key_last($copy);
foreach (array_keys($copy[$last]['evidence']) as $key) $copy[$last]['evidence'][$key] = 'changed-through-copy';
check(snapshot($s) === $before, 'returned evidence history is detached from caller reference variables');
$copy = $s->history(); $alias = 'new-alias';
$copy[$last]['evidence']['outcome'] =& $alias; $alias = 'changed-new-alias';
check(snapshot($s) === $before, 'references added to returned history cannot affect retained evidence');

$s = server();
refusesUnchanged($s, fn () => $s->fail('no remote attempt'), 'fail before admission cannot invent a failed remote attempt');
refusesUnchanged($s, fn () => $s->retry(), 'pending cannot retry');
refusesUnchanged($s, fn () => $s->reconcile($valid), 'pending cannot accept reconciliation');
$s->cancel('staff-1');
refusesUnchanged($s, fn () => $s->reconcile($valid), 'cancelled cannot accept reconciliation');

// Optional named result artifact; ordinary stdout and exit semantics stay compatible.
if (isset($argv[1])) {
    $report = ['passed' => $failures === [], 'scope' => 'Synthetic VPS lifecycle unit checks; no provider, native database or remote evidence verification.', 'checks' => $results];
    if (file_put_contents($argv[1], json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") === false) {
        fwrite(STDERR, "Cannot write VPS result report\n"); exit(2);
    }
}

// ---------------------------------------------------------------------------
if ($failures) {
    fwrite(STDERR, count($failures) . " of $checks checks failed\n");
    exit(1);
}
echo "$checks vps checks passed\n";
