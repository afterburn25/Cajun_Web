<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Domains\DomainName;
use CajunWebPro\Foundation\Domains\DomainOrder;
use CajunWebPro\Foundation\Domains\DomainPricing;
use CajunWebPro\Foundation\Domains\RegistrantContact;
use CajunWebPro\Foundation\Domains\TldOffering;

require __DIR__ . '/../../foundation/domains/DomainName.php';
require __DIR__ . '/../../foundation/domains/TldOffering.php';
require __DIR__ . '/../../foundation/domains/RegistrantContact.php';
require __DIR__ . '/../../foundation/domains/DomainQuote.php';
require __DIR__ . '/../../foundation/domains/DomainPricing.php';
require __DIR__ . '/../../foundation/domains/DomainOrder.php';

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

// Fixture prices are synthetic — not real registrar pricing.
function offerings(): DomainPricing
{
    return new DomainPricing([
        new TldOffering('fxcom', 1299, 1499, 999, 1, 10, true, 300),
        new TldOffering('fxnet', 1099, 1199, null, 1, 5, true, null),
        new TldOffering('fxorg', null, 999, 899, 2, 10, false),
    ]);
}

function registrant(bool $privacy = false): RegistrantContact
{
    return new RegistrantContact('Fixture Person', 'reg@fixture.test', '+1 555 0100', '1 Test Way', 'Fixturetown', 'LA', '70500', 'US', null, $privacy);
}

function order(string $op = 'register', int $years = 1): DomainOrder
{
    return new DomainOrder('ord-1', new DomainName('example.fxcom'), registrant(), $op, $years);
}

// --- DomainName ---------------------------------------------------------------
check((new DomainName('Example.COM'))->name === 'example.com', 'name normalized to lowercase');
check((new DomainName('example.com.'))->name === 'example.com', 'trailing dot stripped');
check((new DomainName('shop.example.fxcom'))->sld === 'example', 'sld is the label left of TLD');
check((new DomainName('a-b.fxcom'))->name === 'a-b.fxcom', 'interior hyphen allowed');
expectThrows(fn () => new DomainName('example'), 'single label rejected');
expectThrows(fn () => new DomainName(''), 'empty name rejected');
expectThrows(fn () => new DomainName('-bad.fxcom'), 'leading hyphen rejected');
expectThrows(fn () => new DomainName('bad-.fxcom'), 'trailing hyphen rejected');
expectThrows(fn () => new DomainName('bad_name.fxcom'), 'underscore rejected');
expectThrows(fn () => new DomainName('exámple.fxcom'), 'non-ASCII rejected (adapter must punycode)');
expectThrows(fn () => new DomainName(str_repeat('a', 64) . '.fxcom'), 'over-63 label rejected');
expectThrows(fn () => new DomainName('example.123'), 'numeric TLD rejected');
expectThrows(fn () => new DomainName('.example.fxcom'), 'empty label rejected');

// --- TldOffering ---------------------------------------------------------------
expectThrows(fn () => new TldOffering('!', 1, 1, 1), 'invalid TLD rejected');
expectThrows(fn () => new TldOffering('fx', -1, 1, 1), 'negative price rejected');
expectThrows(fn () => new TldOffering('fx', 1, 1, 1, 5, 2), 'min years cannot exceed max');
expectThrows(fn () => new TldOffering('fx', 1, 1, 1, 1, 11), 'max years capped at 10');
expectThrows(fn () => new TldOffering('fx', 1, 1, 1, 1, 10, false, 300), 'privacy price requires support');
$offering = new TldOffering('fxcom', 1299, 1499, 999);
check($offering->priceFor('register') === 1299, 'register price mapped');
check($offering->priceFor('renew') === 1499, 'renew price mapped');
check($offering->priceFor('transfer') === 999, 'transfer price mapped');
expectThrows(fn () => $offering->priceFor('delete'), 'unknown operation rejected');
check(TldOffering::validOperation('renew') && !TldOffering::validOperation('delete'), 'operation whitelist');

// --- RegistrantContact -----------------------------------------------------------
expectThrows(fn () => new RegistrantContact('', 'a@b.c', '1', 'x', 'y', 'z', 'p', 'US'), 'name required');
expectThrows(fn () => new RegistrantContact('N', 'not-an-email', '1', 'x', 'y', 'z', 'p', 'US'), 'email validated');
expectThrows(fn () => new RegistrantContact('N', 'a@b.c', '1', 'x', 'y', 'z', 'p', 'USA'), 'country is alpha-2');
check(registrant(true)->privacyRequested, 'privacy preference recorded');

// --- DomainPricing ---------------------------------------------------------------
expectThrows(fn () => new DomainPricing([new TldOffering('fx', 1, 1, 1), new TldOffering('fx', 2, 2, 2)]), 'duplicate TLD rejected');
$pricing = offerings();
check(!$pricing->offered('nope'), 'unknown TLD not offered');
check($pricing->offered('fxcom'), 'configured TLD offered');
check($pricing->quote(new DomainName('a.nope'), 'register', 1) === null, 'unknown TLD unquotable');
check($pricing->quote(new DomainName('a.fxorg'), 'register', 2) === null, 'null price means not offered');
check($pricing->quote(new DomainName('a.fxorg'), 'register', 1) === null, 'below min years unquotable');
check($pricing->quote(new DomainName('a.fxnet'), 'register', 9) === null, 'above max years unquotable');
check($pricing->quote(new DomainName('a.fxnet'), 'transfer', 1) === null, 'null transfer price unquotable');
check($pricing->quote(new DomainName('a.fxorg'), 'register', 2, true) === null, 'privacy on unsupported TLD unquotable');
check($pricing->quote(new DomainName('a.fxcom'), 'teleport', 1) === null, 'unknown operation unquotable');

$q = $pricing->quote(new DomainName('a.fxcom'), 'register', 3, true);
check($q !== null && $q->totalCents === 1299 * 3 + 300 * 3, 'register quote totals unit+years privacy');
check($q->unitCents === 1299 && $q->years === 3, 'quote preserves terms');
$q = $pricing->quote(new DomainName('a.fxnet'), 'renew', 2, true);
check($q !== null && $q->privacyCents === 0 && $q->totalCents === 1199 * 2, 'null privacy price means free');
$q = $pricing->quote(new DomainName('a.fxcom'), 'transfer', 1);
check($q !== null && $q->totalCents === 999, 'transfer quote uses transfer price');

// --- DomainOrder: creation ---------------------------------------------------------
expectThrows(fn () => new DomainOrder('', new DomainName('a.fxcom'), registrant(), 'register', 1), 'order needs an id');
expectThrows(fn () => order('renew'), 'renewals are not created as orders');
expectThrows(fn () => order('register', 0), 'order years lower bound');
expectThrows(fn () => order('register', 11), 'order years upper bound');
$o = order('transfer');
check($o->status() === DomainOrder::PENDING && count($o->history()) === 1, 'order starts pending with history');

// --- DomainOrder: submission --------------------------------------------------------
$o = order();
expectThrows(fn () => $o->submit('ref-1'), 'submit requires recorded consent');
$o->recordConsent();
$o->submit('ref-1');
check($o->status() === DomainOrder::SUBMITTED && $o->providerRef() === 'ref-1', 'consented order submits with provider ref');
expectThrows(fn () => $o->submit('ref-2'), 'submitted order cannot resubmit');

// --- DomainOrder: activation ---------------------------------------------------------
expectThrows(fn () => order()->markActive('2030-01-01'), 'cannot activate an unsubmitted order');
$o->markActive('2030-01-01');
check($o->status() === DomainOrder::ACTIVE, 'submitted order activates');
check(str_starts_with($o->expiresAt(), '2030-01-01'), 'expiry recorded');
expectThrows(fn () => $o->markActive('2031-01-01'), 'active order cannot re-activate');
expectThrows(fn () => $o->submit('ref-x'), 'active order cannot submit');

// --- DomainOrder: renewal/expiry ------------------------------------------------------
expectThrows(fn () => $o->renew('2029-01-01'), 'renewal must move expiry forward');
expectThrows(fn () => $o->renew('not-a-date'), 'renewal needs a parseable date');
$o->renew('2032-06-01');
check(str_starts_with($o->expiresAt(), '2032-06-01'), 'renewal extends expiry');
check($o->status() === DomainOrder::ACTIVE, 'renewal keeps active state');
expectThrows(fn () => $o->cancel(), 'active domain cannot be silently cancelled');

// --- DomainOrder: transfer out ----------------------------------------------------------
expectThrows(fn () => $o->completeTransferOut(), 'transfer completion requires transfer state');
$o->beginTransferOut();
check($o->status() === DomainOrder::TRANSFERRING, 'active order begins transfer out');
expectThrows(fn () => $o->renew('2035-01-01'), 'transferring order cannot renew');
expectThrows(fn () => $o->cancel(), 'transferring order cannot cancel');
$o->abortTransferOut();
check($o->status() === DomainOrder::ACTIVE, 'aborted transfer returns to active');
$o->beginTransferOut();
$o->completeTransferOut();
check($o->status() === DomainOrder::TRANSFERRED && $o->terminal(), 'completed transfer is terminal');
expectThrows(fn () => $o->beginTransferOut(), 'terminal order cannot transition');
expectThrows(fn () => $o->expire(), 'terminal order cannot expire');

// --- DomainOrder: failure/retry ---------------------------------------------------------
$o = order();
$o->recordConsent();
$o->submit('ref-9');
$o->fail('provider rejected');
check($o->status() === DomainOrder::FAILED, 'submitted order can fail');
$o->retry();
check($o->status() === DomainOrder::PENDING, 'failed order retries to pending');
check($o->providerRef() === null, 'retry clears stale provider ref');
expectThrows(fn () => $o->submit('ref-9'), 'retry requires fresh consent');
$o->recordConsent();
$o->submit('ref-10');
check($o->providerRef() === 'ref-10', 'retry resubmits with new ref');

$o = order();
$o->fail('pre-submit failure');
check($o->status() === DomainOrder::FAILED, 'pending order can fail');

// --- DomainOrder: expiry/cancellation -----------------------------------------------------
$o = order();
$o->recordConsent();
$o->submit('r');
$o->markActive('2020-01-01');
$o->expire();
check($o->status() === DomainOrder::EXPIRED, 'active order expires');
expectThrows(fn () => $o->renew('2033-01-01'), 'expired order cannot renew');
$o->cancel('lapsed cleanup');
check($o->status() === DomainOrder::CANCELLED && $o->terminal(), 'expired order can be cancelled');
expectThrows(fn () => $o->cancel(), 'cancelled order is terminal');

$o = order();
$o->cancel('customer changed mind');
check($o->status() === DomainOrder::CANCELLED, 'pending order can be cancelled');

$history = order()->history();
check(count($history) === 1 && $history[0]['status'] === 'pending', 'history starts at creation');
$o = order();
$o->recordConsent();
$o->submit('r');
$o->markActive('2030-01-01');
check(count($o->history()) === 3, 'each transition appends history');
check($o->history()[2]['status'] === 'active', 'history records transition target');

// ---------------------------------------------------------------------------
if ($failures) {
    fwrite(STDERR, count($failures) . " of $checks checks failed\n");
    exit(1);
}
echo "$checks domain checks passed\n";
