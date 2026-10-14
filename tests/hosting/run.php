<?php
declare(strict_types=1);

/**
 * Synthetic HC-08 shared-hosting lifecycle checks. Fixture accounts and
 * provider results only — no 20i calls, credentials, persistence or UI.
 */

use CajunWebPro\Foundation\Hosting\HostingAccount;
use CajunWebPro\Foundation\Hosting\HostingPackage;
use CajunWebPro\Foundation\Hosting\OperationResult;
use CajunWebPro\Foundation\Hosting\PlanChange;
use CajunWebPro\Foundation\Hosting\StackCpGrant;

require __DIR__ . '/../../foundation/hosting/HostingPackage.php';
require __DIR__ . '/../../foundation/hosting/OperationResult.php';
require __DIR__ . '/../../foundation/hosting/StackCpGrant.php';
require __DIR__ . '/../../foundation/hosting/PlanChange.php';
require __DIR__ . '/../../foundation/hosting/HostingAccount.php';

$checks = 0;
$failures = [];

function check(string $name, bool $actual): void
{
    global $checks, $failures;
    $checks++;
    if (!$actual) {
        $failures[] = $name;
    }
}

function throws(string $name, callable $action): void
{
    global $checks, $failures;
    $checks++;
    try {
        $action();
        $failures[] = $name . ': invalid action was accepted';
    } catch (InvalidArgumentException|LogicException) {
    }
}

function pkg(array $over = []): HostingPackage
{
    return new HostingPackage(...array_replace([
        'sku' => 'SH-STD',
        'label' => 'Standard shared hosting',
        'providerPackageType' => '20i-shared-standard',
        'region' => 'us-central',
        'limits' => ['disk_mb' => 20480, 'mailboxes' => 50, 'bandwidth_gb' => null],
    ], $over));
}

function acct(string $status = 'active'): HostingAccount
{
    $a = new HostingAccount('acct-1', pkg(), 'client-42', 0);
    if ($status === 'pending') {
        return $a;
    }
    $a->beginProvisioning('staff-amy', 100);
    $a->provisionResult(OperationResult::applied('hosting.provision', 'rcpt-prov', 120, 'acct-1', '20i-pkg-77'), 'staff-amy', 120);
    return $a;
}

// ── HostingPackage ───────────────────────────────────────────────

$p = pkg();
check('valid package constructs', $p->sku === 'SH-STD' && $p->limit('disk_mb') === 20480);
check('null limit means unmetered-not-promised', $p->limit('bandwidth_gb') === null);
check('fits within limits', $p->fits(['disk_mb' => 1000, 'mailboxes' => 5]));
check('over-limit does not fit', !$p->fits(['disk_mb' => 30000]));
check('unmetered usage always fits', $p->fits(['bandwidth_gb' => 999999]));
throws('empty SKU rejected', fn () => pkg(['sku' => '']));
throws('bad SKU chars rejected', fn () => pkg(['sku' => 'SH STD!']));
throws('empty provider type rejected', fn () => pkg(['providerPackageType' => ' ']));
throws('empty limits rejected', fn () => pkg(['limits' => []]));
throws('negative limit rejected', fn () => pkg(['limits' => ['disk_mb' => -1]]));
throws('unlimited limit name rejected', fn () => pkg(['limits' => ['unlimited_storage' => null]]));
throws('unmetered limit name rejected', fn () => pkg(['limits' => ['bandwidth_unmetered' => 5]]));
throws('string observation rejected', fn () => $p->fits(['disk_mb' => '999']));
throws('negative observation rejected', fn () => $p->fits(['disk_mb' => -5]));
check('undeclared observed metric cannot prove a fit', !$p->fits(['cpu_seconds' => 1]));
check('declared-unmetered metric still fits', $p->fits(['bandwidth_gb' => 5]));

// ── OperationResult ──────────────────────────────────────────────

$ok = OperationResult::applied('provision', 'rcpt-1', 500, 'acct-1');
check('applied carries receipt', $ok->ok() && $ok->receipt === 'rcpt-1');
$ok2 = OperationResult::applied('provision', 'rcpt-1', 500, 'acct-1', 'res-9');
check('resource ref is distinct from the operation receipt', $ok2->resourceRef === 'res-9' && $ok2->receipt === 'rcpt-1');
$f = OperationResult::failed('suspend', 'provider refused', true, 510, 'acct-1');
check('failed carries error and retryable', !$f->ok() && $f->error === 'provider refused' && $f->retryable);
$t = OperationResult::timeout('provision', 520, 'acct-1');
check('timeout is honest unknown', !$t->ok() && $t->outcome === 'timeout' && !$t->retryable);
throws('applied needs receipt', fn () => OperationResult::applied('provision', '', 1, 'acct-1'));
throws('applied needs subject', fn () => OperationResult::applied('provision', 'r', 1, ''));
throws('failed needs error', fn () => OperationResult::failed('suspend', '', false, 1, 'acct-1'));
throws('timeout needs subject', fn () => OperationResult::timeout('suspend', 1, ' '));

// ── StackCpGrant ─────────────────────────────────────────────────

$g = new StackCpGrant('gr-1', 'acct-1', 'client-42', 'manage', 'staff-amy', 0);
check('manage grant active and can manage', $g->active(10) && $g->canManage(10));
$ro = new StackCpGrant('gr-2', 'acct-1', 'staff-ben', 'read', 'staff-amy', 0, 100);
check('read grant active but cannot manage', $ro->active(50) && !$ro->canManage(50));
check('read grant expired at boundary', !$ro->active(100));
check('grant inactive before issuance', !$ro->active(-1));
$future = new StackCpGrant('gr-3', 'acct-1', 'staff-ben', 'manage', 'staff-amy', 100, 200);
check('future grant cannot manage before issuedAt', !$future->canManage(99) && !$future->active(99));
check('grant authorizes at exact issuance', $future->active(100) && $future->canManage(100));
check('grant expires at exact expiry', !$future->active(200) && $future->active(199));
throws('unknown level rejected', fn () => new StackCpGrant('g', 'a', 'c', 'admin', 's', 0));
$g->revoke(60);
check('revoked grant inactive', !$g->active(50) && $g->revokedAt() === 60);
throws('double revoke rejected', fn () => $g->revoke(70));

// ── PlanChange ───────────────────────────────────────────────────

$big = pkg(['sku' => 'SH-PRO', 'limits' => ['disk_mb' => 51200]]);
$small = pkg(['sku' => 'SH-LITE', 'limits' => ['disk_mb' => 5120]]);
$up = new PlanChange('pc-1', 'acct-1', 'upgrade', $big, 'client-42', 100, 100);
check('queued upgrade', $up->status() === 'queued');
$up->approve(['disk_mb' => 9000], 200);
check('approved upgrade awaits dispatch', $up->status() === 'approved');
$up->recordResult(OperationResult::applied('hosting.upgrade', 'rcpt-9', 210, 'acct-1'), 210);
check('fitting upgrade applied', $up->status() === 'applied');

$down = new PlanChange('pc-2', 'acct-1', 'downgrade', $small, 'client-42', 100, 100);
$down->approve(['disk_mb' => 40000], 200);
check('over-limit downgrade refused before dispatch', $down->status() === 'refused' && $down->reason() !== null);
throws('refused change cannot record a result', fn () => $down->recordResult(OperationResult::applied('hosting.downgrade', 'r', 1, 'acct-1'), 1));

$down2 = new PlanChange('pc-3', 'acct-1', 'downgrade', $small, 'client-42', 100, 100);
$down2->approve(['disk_mb' => 4000], 200);
$down2->recordResult(OperationResult::applied('hosting.downgrade', 'rcpt-11', 210, 'acct-1'), 210);
check('fitting downgrade applied', $down2->status() === 'applied');
throws('downgrade without usage cannot be approved', fn () => (new PlanChange('p', 'a', 'downgrade', $small, 's', 0, 0))->approve([], 5));
throws('early dispatch refused', fn () => (new PlanChange('p', 'a', 'upgrade', $big, 's', 0, 500))->approve(['disk_mb' => 1], 400));
$early = new PlanChange('pc-e', 'acct-1', 'upgrade', $big, 's', 0, 500);
check('effective time admitted at boundary', $early->approve(['disk_mb' => 1], 500) === null && $early->status() === 'approved');
$misbound = new PlanChange('pc-m', 'acct-1', 'upgrade', $big, 's', 0, 0);
$misbound->approve(['disk_mb' => 1], 1);
throws('result for another operation refused', fn () => $misbound->recordResult(OperationResult::applied('hosting.deprovision', 'r', 1, 'acct-1'), 1));
throws('result for another account refused', fn () => $misbound->recordResult(OperationResult::applied('hosting.upgrade', 'r', 1, 'acct-9'), 1));
check('misbound results mutate nothing', $misbound->status() === 'approved');
$unc = new PlanChange('pc-u', 'acct-1', 'downgrade', $small, 'client-42', 0, 0);
$unc->approve(['disk_mb' => 100], 1);
$unc->recordResult(OperationResult::timeout('hosting.downgrade', 2, 'acct-1'), 2);
check('timeout leaves the change uncertain, not refused', $unc->status() === 'uncertain');
throws('uncertain change cannot cancel or re-decide', fn () => $unc->cancel('client-42', 3));
throws('resolve needs evidence', fn () => $unc->resolve(false, '', 3));
$unc->resolve(false, 'provider log shows no change order accepted', 3);
check('unapplied reconciliation returns to approved', $unc->status() === 'approved');
$unc->recordResult(OperationResult::timeout('hosting.downgrade', 4, 'acct-1'), 4);
$unc->resolve(true, 'provider confirms downgrade rcpt-x', 5);
check('confirmed reconciliation marks applied', $unc->status() === 'applied');

$ren = new PlanChange('pc-4', 'acct-1', 'renewal', $big, 'staff-amy', 100, 200);
$ren->cancel('staff-amy', 150);
check('requester can cancel queued', $ren->status() === 'cancelled');
throws('non-requester cannot cancel', fn () => (new PlanChange('p', 'a', 'renewal', $big, 'client-42', 0, 10))->cancel('staff-ben', 5));
throws('decided change cannot re-decide', fn () => $up->approve([], 1));
throws('unknown kind rejected', fn () => new PlanChange('p', 'a', 'resize', $big, 's', 0, 0));

// ── HostingAccount lifecycle ─────────────────────────────────────

$a = new HostingAccount('acct-9', pkg(), 'client-42', 0);
check('new account pending', $a->status() === 'pending' && !$a->terminal());
throws('provision before begin rejected', fn () => $a->provisionResult(OperationResult::applied('hosting.provision', 'r', 1, 'acct-9'), 's', 1));
$a->beginProvisioning('staff-amy', 50);
check('provisioning state', $a->status() === 'provisioning');
throws('wrong-operation result refused without mutation', function () use ($a) {
    $a->provisionResult(OperationResult::applied('hosting.deprovision', 'deletion-operation-receipt', 55, 'acct-9'), 's', 55);
});
check('refused result recorded nothing', $a->status() === 'provisioning' && $a->operations() === []);
throws('result bound to another account refused', fn () => $a->provisionResult(OperationResult::applied('hosting.provision', 'r', 55, 'acct-999'), 's', 55));
$a->provisionResult(OperationResult::applied('hosting.provision', '20i-1', 60, 'acct-9', 'res-acct-9'), 'staff-amy', 60);
check('applied provisions to active with resource id not receipt', $a->status() === 'active' && $a->providerRef() === 'res-acct-9');

$a2 = new HostingAccount('acct-10', pkg(), 'client-42', 0);
$a2->beginProvisioning('s', 1);
$a2->provisionResult(OperationResult::timeout('hosting.provision', 5, 'acct-10'), 's', 5);
check('timeout yields honest failed with unresolved operation', $a2->status() === 'failed' && $a2->awaitingReconciliation());
throws('timeout retry needs reconciliation', fn () => $a2->retry('staff-amy', 10));
throws('unresolved order cannot cancel as never-provisioned', fn () => $a2->cancelOrder('client-42', 10));
throws('reconcile needs evidence', fn () => $a2->reconcileTimeout('staff-amy', 10, false, null, ''));
$a2->reconcileTimeout('staff-amy', 10, false, null, 'provider package list shows no account');
check('reconciled timeout retries to pending', !$a2->awaitingReconciliation() && ($a2->retry('staff-amy', 10) === null) && $a2->status() === 'pending');

$a6 = new HostingAccount('acct-12', pkg(), 'client-42', 0);
$a6->beginProvisioning('s', 1);
$a6->provisionResult(OperationResult::timeout('hosting.provision', 5, 'acct-12'), 's', 5);
throws('adoption needs the provider resource id', fn () => $a6->reconcileTimeout('staff-amy', 6, true, null, 'provider shows the account exists'));
$a6->reconcileTimeout('staff-amy', 6, true, '20i-pkg-88', 'provider shows the account exists');
check('reconciled remote resource is adopted not recreated', $a6->status() === 'active' && $a6->providerRef() === '20i-pkg-88');

$a5 = new HostingAccount('acct-11', pkg(), 'client-42', 0);
$a5->beginProvisioning('s', 1);
$a5->provisionResult(OperationResult::failed('hosting.provision', 'capacity', true, 5, 'acct-11'), 's', 5);
$a5->retry('staff-amy', 10); // plain retryable failure needs no reconciliation
check('retryable failure retries directly', $a5->status() === 'pending');
$a7 = new HostingAccount('acct-13', pkg(), 'client-42', 0);
$a7->beginProvisioning('s', 1);
$a7->provisionResult(OperationResult::failed('hosting.provision', 'permanent', false, 5, 'acct-13'), 's', 5);
throws('non-retryable failure cannot retry', fn () => $a7->retry('staff-amy', 10));
check('non-retryable failure stays failed', $a7->status() === 'failed');
$a7->cancelOrder('client-42', 11);
check('non-retryable failure can still withdraw the order', $a7->status() === 'cancelled');
$a2->beginProvisioning('s', 20);
$a2->provisionResult(OperationResult::failed('hosting.provision', 'capacity', true, 25, 'acct-10'), 's', 25);
$a2->cancelOrder('client-42', 30);
check('pre-provision cancel terminal', $a2->status() === 'cancelled' && $a2->terminal());
throws('terminal rejects transitions', fn () => $a2->beginProvisioning('s', 40));

// domain link
throws('domain link needs active', fn () => (new HostingAccount('x', pkg(), 'c', 0))->linkDomain('a.com', 's', 1));
throws('invalid domain rejected', fn () => $a->linkDomain('not a domain', 's', 1));
$a->linkDomain('Example.COM', 'staff-amy', 70);
check('domain linked lowercase', $a->primaryDomain() === 'example.com');

// grace and dispute protections
$a->setGraceUntil(1000, 'staff-amy', 100);
throws('suspend refused before grace', fn () => $a->suspend('billing', 500, 'nonpayment'));
$a->openDispute('client-42', 110);
check('dispute flag recorded', $a->disputeOpen());
$a->setGraceUntil(200, 'staff-amy', 120); // still active; grace now past
throws('suspend refused during dispute', fn () => $a->suspend('billing', 500, 'nonpayment'));
$a->closeDispute('staff-amy', 130);
check('dispute closed', !$a->disputeOpen());
throws('suspend needs reason', fn () => $a->suspend('billing', 500, ' '));
$a->suspend('billing', 500, 'nonpayment past grace');
check('suspend is intent only until provider confirms', $a->status() === 'suspending' && $a->status() !== 'suspended');
throws('suspend result needs the suspend operation', fn () => $a->suspendResult(OperationResult::applied('hosting.resume', 'r', 501, 'acct-9'), 's', 501));
$a->suspendResult(OperationResult::applied('hosting.suspend', 'rcpt-s', 501, 'acct-9'), 'staff-amy', 501);
check('suspended after provider confirm', $a->status() === 'suspended');

// resume
$a->beginResume('staff-amy', 600);
$a->resumeResult(OperationResult::applied('hosting.resume', 'rcpt-5', 600, 'acct-9'), 'staff-amy', 600);
check('resume returns active', $a->status() === 'active');
$a->suspend('billing', 700, 'second lapse');
$a->suspendResult(OperationResult::applied('hosting.suspend', 'rcpt-s2', 700, 'acct-9'), 'staff-amy', 700);
$a->beginResume('staff-amy', 800);
$a->resumeResult(OperationResult::timeout('hosting.resume', 800, 'acct-9'), 'staff-amy', 800);
check('resume timeout stays resuming and unresolved', $a->status() === 'resuming' && $a->awaitingReconciliation());
$a->reconcileTimeout('staff-amy', 810, false, null, 'provider shows account still suspended');
check('unapplied resume reconciliation restores suspended', $a->status() === 'suspended' && !$a->awaitingReconciliation());
$a->beginResume('staff-amy', 820);
$a->resumeResult(OperationResult::failed('hosting.resume', 'quota lock', false, 820, 'acct-9'), 'staff-amy', 820);
check('failed resume keeps suspended', $a->status() === 'suspended');
$a->beginResume('staff-amy', 850);
$a->resumeResult(OperationResult::applied('hosting.resume', 'rcpt-r2', 850, 'acct-9'), 'staff-amy', 850);
check('resumed for suspend-timeout tests', $a->status() === 'active');

// suspend timeout reconciliation both ways
$a->suspend('billing', 900, 'third lapse');
$a->suspendResult(OperationResult::timeout('hosting.suspend', 900, 'acct-9'), 'staff-amy', 900);
check('suspend timeout stays suspending and unresolved', $a->status() === 'suspending' && $a->awaitingReconciliation());
$a->reconcileTimeout('staff-amy', 910, false, null, 'provider shows account still active');
check('unapplied suspend reconciliation restores active', $a->status() === 'active');
$a->suspend('billing', 920, 'fourth lapse');
$a->suspendResult(OperationResult::timeout('hosting.suspend', 920, 'acct-9'), 'staff-amy', 920);
$a->reconcileTimeout('staff-amy', 930, true, null, 'provider confirms suspended');
check('confirmed suspend reconciliation marks suspended', $a->status() === 'suspended');
$a->beginResume('staff-amy', 940);
$a->resumeResult(OperationResult::applied('hosting.resume', 'rcpt-r3', 940, 'acct-9'), 'staff-amy', 940);
check('resumed again for decommission tests', $a->status() === 'active');

// two-actor decommission
throws('deprovision needs request first', fn () => $a->approveDeprovision('staff-ben', true, 900));
throws('empty requester refused', fn () => $a->requestCancellation('', 900));
$a->requestCancellation('staff-amy', 900);
check('cancellation requested', $a->status() === 'cancellation_requested');
throws('same actor cannot approve', fn () => $a->approveDeprovision('staff-amy', true, 910));
throws('empty approver is not a second actor', fn () => $a->approveDeprovision('', true, 910));
check('failed approval changed nothing', $a->status() === 'cancellation_requested');
throws('retention required', fn () => $a->approveDeprovision('staff-ben', false, 910));
$a->approveDeprovision('staff-ben', true, 910);
check('deprovisioning', $a->status() === 'deprovisioning');
throws('wrong-operation deprovision result refused', fn () => $a->deprovisionResult(OperationResult::applied('hosting.provision', 'r', 915, 'acct-9'), 's', 915));
$a->deprovisionResult(OperationResult::timeout('hosting.deprovision', 918, 'acct-9'), 'staff-ben', 918);
check('deprovision timeout stays deprovisioning unresolved', $a->status() === 'deprovisioning' && $a->awaitingReconciliation());
$a->reconcileTimeout('staff-ben', 919, false, null, 'provider shows account still present');
check('unapplied deprovision allows re-attempt', $a->status() === 'deprovisioning' && !$a->awaitingReconciliation());
$a->deprovisionResult(OperationResult::applied('hosting.deprovision', 'rcpt-6', 920, 'acct-9'), 'staff-ben', 920);
check('deprovisioned terminal', $a->status() === 'deprovisioned' && $a->terminal());
check('history is attributed', count($a->history()) > 5 && $a->history()[1]['actor'] === 'staff-amy');
check('operations recorded', count($a->operations()) >= 3);

// confirmed-remote deprovision reconcile path
$a8 = acct();
$a8->requestCancellation('staff-amy', 100);
$a8->approveDeprovision('staff-ben', true, 110);
$a8->deprovisionResult(OperationResult::timeout('hosting.deprovision', 120, 'acct-1'), 'staff-ben', 120);
$a8->reconcileTimeout('staff-ben', 130, true, null, 'provider confirms removal complete');
check('confirmed deprovision reconciliation marks deprovisioned', $a8->status() === 'deprovisioned' && $a8->terminal());

// active account cannot be order-cancelled
throws('provisioned account not order-cancellable', fn () => acct()->cancelOrder('c', 1));

// ── Summary ──────────────────────────────────────────────────────

if ($failures) {
    fwrite(STDERR, "FAIL: " . count($failures) . " of {$checks} checks\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "PASS: {$checks} hosting lifecycle checks on PHP " . PHP_VERSION . "\n";
echo "Scope: isolated model only; no provider calls, credentials, persistence or UI tested.\n";
