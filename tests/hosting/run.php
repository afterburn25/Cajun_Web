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
    $a->provisionResult(OperationResult::applied('provision', '20i-pkg-77', 120), 'staff-amy', 120);
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

// ── OperationResult ──────────────────────────────────────────────

$ok = OperationResult::applied('provision', 'rcpt-1', 500);
check('applied carries receipt', $ok->ok() && $ok->receipt === 'rcpt-1');
$f = OperationResult::failed('suspend', 'provider refused', true, 510);
check('failed carries error and retryable', !$f->ok() && $f->error === 'provider refused' && $f->retryable);
$t = OperationResult::timeout('provision', 520);
check('timeout is honest unknown', !$t->ok() && $t->outcome === 'timeout' && !$t->retryable);
throws('applied needs receipt', fn () => OperationResult::applied('provision', '', 1));
throws('failed needs error', fn () => OperationResult::failed('suspend', '', false, 1));

// ── StackCpGrant ─────────────────────────────────────────────────

$g = new StackCpGrant('gr-1', 'acct-1', 'client-42', 'manage', 'staff-amy', 0);
check('manage grant active and can manage', $g->active(10) && $g->canManage(10));
$ro = new StackCpGrant('gr-2', 'acct-1', 'staff-ben', 'read', 'staff-amy', 0, 100);
check('read grant active but cannot manage', $ro->active(50) && !$ro->canManage(50));
check('read grant expired at boundary', !$ro->active(100));
throws('unknown level rejected', fn () => new StackCpGrant('g', 'a', 'c', 'admin', 's', 0));
$g->revoke(60);
check('revoked grant inactive', !$g->active(50) && $g->revokedAt() === 60);
throws('double revoke rejected', fn () => $g->revoke(70));

// ── PlanChange ───────────────────────────────────────────────────

$big = pkg(['sku' => 'SH-PRO', 'limits' => ['disk_mb' => 51200]]);
$small = pkg(['sku' => 'SH-LITE', 'limits' => ['disk_mb' => 5120]]);
$up = new PlanChange('pc-1', 'acct-1', 'upgrade', $big, 'client-42', 100, 100);
check('queued upgrade', $up->status() === 'queued');
$up->approve(['disk_mb' => 9000], OperationResult::applied('plan-change', 'rcpt-9', 200), 200);
check('fitting upgrade applied', $up->status() === 'applied');

$down = new PlanChange('pc-2', 'acct-1', 'downgrade', $small, 'client-42', 100, 100);
$down->approve(['disk_mb' => 40000], OperationResult::applied('plan-change', 'rcpt-10', 200), 200);
check('over-limit downgrade refused', $down->status() === 'refused' && $down->reason() !== null);

$down2 = new PlanChange('pc-3', 'acct-1', 'downgrade', $small, 'client-42', 100, 100);
$down2->approve(['disk_mb' => 4000], OperationResult::applied('plan-change', 'rcpt-11', 200), 200);
check('fitting downgrade applied', $down2->status() === 'applied');

$ren = new PlanChange('pc-4', 'acct-1', 'renewal', $big, 'staff-amy', 100, 200);
$ren->cancel('staff-amy', 150);
check('requester can cancel queued', $ren->status() === 'cancelled');
throws('non-requester cannot cancel', fn () => (new PlanChange('p', 'a', 'renewal', $big, 'client-42', 0, 10))->cancel('staff-ben', 5));
throws('decided change cannot re-decide', fn () => $up->approve([], OperationResult::applied('x', 'r', 1), 1));
throws('unknown kind rejected', fn () => new PlanChange('p', 'a', 'resize', $big, 's', 0, 0));

// ── HostingAccount lifecycle ─────────────────────────────────────

$a = new HostingAccount('acct-9', pkg(), 'client-42', 0);
check('new account pending', $a->status() === 'pending' && !$a->terminal());
throws('provision before begin rejected', fn () => $a->provisionResult(OperationResult::applied('p', 'r', 1), 's', 1));
$a->beginProvisioning('staff-amy', 50);
check('provisioning state', $a->status() === 'provisioning');
$a->provisionResult(OperationResult::applied('provision', '20i-1', 60), 'staff-amy', 60);
check('applied provisions to active', $a->status() === 'active' && $a->providerRef() === '20i-1');

$a2 = new HostingAccount('acct-10', pkg(), 'client-42', 0);
$a2->beginProvisioning('s', 1);
$a2->provisionResult(OperationResult::timeout('provision', 5), 's', 5);
check('timeout yields honest failed', $a2->status() === 'failed');
throws('timeout retry needs reconciliation', fn () => $a2->retry('staff-amy', 10));
$a2->retry('staff-amy', 10, true);
check('reconciled timeout retries to pending', $a2->status() === 'pending');

$a5 = new HostingAccount('acct-11', pkg(), 'client-42', 0);
$a5->beginProvisioning('s', 1);
$a5->provisionResult(OperationResult::failed('provision', 'capacity', true, 5), 's', 5);
$a5->retry('staff-amy', 10); // plain failure needs no reconciliation flag
check('plain failure retries directly', $a5->status() === 'pending');
$a2->cancelOrder('client-42', 15);
check('pre-provision cancel terminal', $a2->status() === 'cancelled' && $a2->terminal());
throws('terminal rejects transitions', fn () => $a2->beginProvisioning('s', 20));

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
check('suspended after grace+dispute clear', $a->status() === 'suspended');

// resume
$a->resume(OperationResult::applied('resume', 'rcpt-5', 600), 'staff-amy', 600);
check('resume returns active', $a->status() === 'active');
$a->suspend('billing', 700, 'second lapse');
$a->resume(OperationResult::timeout('resume', 800), 'staff-amy', 800);
check('resume timeout stays suspended', $a->status() === 'suspended');

// two-actor decommission
throws('deprovision needs request first', fn () => $a->approveDeprovision('staff-ben', true, 900));
$a->requestCancellation('staff-amy', 900);
check('cancellation requested', $a->status() === 'cancellation_requested');
throws('same actor cannot approve', fn () => $a->approveDeprovision('staff-amy', true, 910));
throws('retention required', fn () => $a->approveDeprovision('staff-ben', false, 910));
$a->approveDeprovision('staff-ben', true, 910);
check('deprovisioning', $a->status() === 'deprovisioning');
$a->deprovisionResult(OperationResult::applied('deprovision', 'rcpt-6', 920), 'staff-ben', 920);
check('deprovisioned terminal', $a->status() === 'deprovisioned' && $a->terminal());
check('history is attributed', count($a->history()) > 5 && $a->history()[1]['actor'] === 'staff-amy');
check('operations recorded', count($a->operations()) >= 3);

// active account cannot be order-cancelled
throws('provisioned account not order-cancellable', fn () => acct()->cancelOrder('c', 1));

// ── Summary ──────────────────────────────────────────────────────

if ($failures) {
    fwrite(STDERR, "FAIL: " . count($failures) . " of {$checks} checks\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "PASS: {$checks} hosting lifecycle checks on PHP " . PHP_VERSION . "\n";
echo "Scope: isolated model only; no provider calls, credentials, persistence or UI tested.\n";
