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

function check(bool $cond, string $label): void
{
    global $checks, $failures;
    $checks++;
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

function activeServer(): VpsServer
{
    $s = server();
    $s->beginProvisioning('vps-ref-1');
    $s->markActive();
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
$s->markActive();
check($s->status() === VpsServer::ACTIVE, 'provisioning completes to active');

// --- VpsServer: failure / retry ---------------------------------------------------------
$s = server();
$s->beginProvisioning('vps-ref-1');
$s->fail('provider rejected');
check($s->status() === VpsServer::FAILED, 'in-flight provisioning can fail');
$s->retry();
check($s->status() === VpsServer::PENDING && $s->providerRef() === null, 'retry clears stale ref');
$s->beginProvisioning('vps-ref-2');
check($s->providerRef() === 'vps-ref-2', 'retry reprovisions with new ref');

// --- VpsServer: suspend / resume -----------------------------------------------------------
$s = activeServer();
$s->suspend();
check($s->status() === VpsServer::SUSPENDING, 'active server enters suspending');
expectThrows(fn () => $s->suspend(), 'in-flight suspension cannot re-suspend');
expectThrows(fn () => $s->resume(), 'in-flight suspension cannot resume');
$s->suspendFailed();
check($s->status() === VpsServer::ACTIVE, 'failed suspension returns to active');
$s->suspend();
$s->markSuspended();
check($s->status() === VpsServer::SUSPENDED, 'suspension completes');
$s->resume();
check($s->status() === VpsServer::RESUMING, 'suspended server enters resuming');
$s->resumeFailed();
check($s->status() === VpsServer::SUSPENDED, 'failed resume returns to suspended');
$s->resume();
$s->markActive();
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
$s->markTerminated();
check($s->status() === VpsServer::TERMINATED && $s->terminal(), 'teardown completes as terminal');
expectThrows(fn () => $s->suspend(), 'terminal server cannot transition');
expectThrows(fn () => $s->requestDecommission('x'), 'terminal server cannot re-decommission');

// Decommission from suspended also allowed.
$s = activeServer();
$s->suspend();
$s->markSuspended();
$s->requestDecommission('staff-1');
check($s->status() === VpsServer::DECOMMISSION_REQUESTED, 'suspended server can be decommissioned');

// Withdrawal returns to prior state.
$s->cancelDecommission('staff-1');
check($s->status() === VpsServer::SUSPENDED, 'withdrawn decommission returns to suspended');
$s->requestDecommission('staff-1');
$s->approveDecommission('staff-2');
$s->decommissionFailed();
check($s->status() === VpsServer::SUSPENDED, 'failed teardown rolls back to stable state');
expectThrows(fn () => $s->markTerminated(), 'rolled-back teardown cannot complete');

// --- VpsServer: cancellation ------------------------------------------------------------------
expectThrows(fn () => activeServer()->cancel('staff-1'), 'active server cannot be silently cancelled');
$suspended = activeServer();
$suspended->suspend();
$suspended->markSuspended();
expectThrows(fn () => $suspended->cancel('staff-1'), 'suspended server cannot be silently cancelled');
$s = server();
$s->cancel('staff-1', 'order withdrawn');
check($s->status() === VpsServer::CANCELLED && $s->terminal(), 'pending order cancels');
expectThrows(fn () => $s->beginProvisioning('x'), 'cancelled order is terminal');
$s = server();
$s->beginProvisioning('r');
$s->fail('nope');
$s->cancel('staff-1');
check($s->status() === VpsServer::CANCELLED, 'failed order can be cancelled');

// --- History -----------------------------------------------------------------------------------
$s = server();
$s->beginProvisioning('r');
$s->markActive();
$s->requestDecommission('staff-1');
$history = $s->history();
check(count($history) === 4, 'each transition appends history');
check($history[3]['actor'] === 'staff-1', 'history records the actor');
check($history[0]['status'] === 'pending' && $history[3]['status'] === 'decommission_requested', 'history preserves order');

// ---------------------------------------------------------------------------
if ($failures) {
    fwrite(STDERR, count($failures) . " of $checks checks failed\n");
    exit(1);
}
echo "$checks vps checks passed\n";
