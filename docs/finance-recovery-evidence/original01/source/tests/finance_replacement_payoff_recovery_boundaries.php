<?php
/** Actual source bodies and native SQL; only the gateway boundary is synthetic. */
declare(strict_types=1);
namespace FinanceRecovery;
if (PHP_SAPI !== 'cli' || !defined('CWP_BOOT') || getenv('CWP_TEST_DATABASE') !== 'cwp_test') exit(2);

function license_finance_anet_settings(): array {
    if ($GLOBALS['frgDisabled'] ?? false) throw new \RuntimeException('Synthetic gateway is disabled.');
    return ['mode' => 'production', 'login' => 'synthetic-finance-account', 'key' => 'synthetic-key',
        'client_key' => 'synthetic-client-key', 'revision' => 1];
}

function report_error(\Throwable $error): void {
    $GLOBALS['frgDiagnostics'][] = get_class($error).': '.$error->getMessage();
}

function license_finance_anet_execute(string $method, array $request, ?array $context = null): array {
    license_finance_anet_settings();
    $id = (string)($request['subscriptionId'] ?? '');
    $GLOBALS['frgCalls'][] = ['method' => $method, 'id' => $id, 'pid' => getmypid(),
        'connection' => (int)\query('SELECT CONNECTION_ID()')->fetchColumn(),
        'transaction' => \db()->inTransaction()];
    if ($method === 'ARBCreateSubscriptionRequest') {
        $id = (string)++$GLOBALS['frgNextGateway'];
        $GLOBALS['frgProvider'][$id] = $request['subscription'] + ['status' => 'active'];
        // The external successor exists before the independent receipt commits;
        // its reply has not returned to the application yet.
        if ($GLOBALS['frgDuringCreate'] ?? null) {
            $work = $GLOBALS['frgDuringCreate'];
            $GLOBALS['frgDuringCreate'] = null;
            $work();
        }
        if (($GLOBALS['frgScenario'] ?? '') === 'missing_create_id') return ['messages' => ['resultCode' => 'Ok']];
        if (($GLOBALS['frgScenario'] ?? '') === 'lost_create_reply') throw new \RuntimeException('Synthetic lost replacement-create reply.');
        return ['messages' => ['resultCode' => 'Ok'], 'subscriptionId' => $id];
    }
    if (!isset($GLOBALS['frgProvider'][$id])) throw new \RuntimeException('Unknown synthetic finance subscription.');
    $fault = $GLOBALS['frgScenario'] ?? 'normal';
    if ($method === 'ARBCancelSubscriptionRequest') {
        if ($fault === 'cancel_timeout') throw new \RuntimeException('Synthetic cancellation timeout.');
        if ($fault === 'cancel_error') return ['messages' => ['resultCode' => 'Error']];
        if ($fault !== 'still_active') $GLOBALS['frgProvider'][$id]['status'] = 'canceled';
        if ($fault === 'lost_cancel_reply') throw new \RuntimeException('Synthetic lost cancellation reply.');
        return ['messages' => ['resultCode' => 'Ok']];
    }
    if ($method !== 'ARBGetSubscriptionRequest') throw new \RuntimeException('Unexpected synthetic finance operation.');
    if ($fault === 'readback_timeout') throw new \RuntimeException('Synthetic subscription readback timeout.');
    $reply = $GLOBALS['frgProvider'][$id];
    $reply['profile'] = ['customerProfileId' => '1001', 'customerPaymentProfileId' => '2001'];
    if ($fault === 'wrong_readback') $reply['amount'] = '999.00';
    return ['messages' => ['resultCode' => 'Ok'], 'subscription' => $reply];
}

$groups = [
    'app/billing/finance_subscription.php' => null,
    'app/billing/finance_authorize_net.php' => ['license_finance_anet_retire_paid_off_subscription', 'license_finance_anet_retire_subscription'],
    'app/licenses/financing.php' => ['license_financing_sync_by_invoice'],
    'app/licenses/finance_installments.php' => ['license_finance_scheduler_run'],
    'app/billing/ledger.php' => ['ledger_receive'],
];
$GLOBALS['frgSourceHashes'] = [];
foreach ($groups as $path => $names) {
    $source = file_get_contents($root.'/site/'.$path);
    $GLOBALS['frgSourceHashes'][$path] = hash('sha256', $source);
    preg_match_all('/^function ([a-zA-Z0-9_]+)\(.*?(?=^function |\z)/ms', $source, $functions, PREG_SET_ORDER);
    foreach ($functions as $function) {
        if ($names !== null && !in_array($function[1], $names, true)) continue;
        eval('namespace FinanceRecovery; use \\RuntimeException; use \\InvalidArgumentException; use \\LogicException; use \\Throwable; use \\PDOException; '.$function[0]);
    }
}
