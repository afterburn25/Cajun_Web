<?php
/** Regression checks for strict Authorize.net monetary readback. */
declare(strict_types=1);
define('CWP_BOOT', true);
require dirname(__DIR__).'/site/app/billing/authorize_net_contract.php';

$cases = [
    ['125.00', 12500],
    ['0.01', 1],
    ['1.2', 120],
    ['0', 0],
    ['125.001', null],
    ['125.00junk', null],
    ['1e2', null],
    ['-1.00', null],
    [125.001, null],
    [null, null],
];
foreach ($cases as [$amount, $expected]) {
    if (software_charge_exact_cents($amount) !== $expected) {
        throw new RuntimeException('Gateway currency parser regression for case '.get_debug_type($amount));
    }
}
echo 'Strict gateway amount parsing: PASS'.PHP_EOL;
