<?php
/** FIN-01/02: native committed receipts across controlled external boundaries. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !defined('CWP_BOOT') || getenv('CWP_TEST_DATABASE') !== 'cwp_test') exit(2);
require __DIR__.'/finance_replacement_payoff_recovery_boundaries.php';
require __DIR__.'/finance_replacement_payoff_recovery_safety.php';
require_once __DIR__.'/finance_replacement_payoff_authority_cases.php';
require_once __DIR__.'/finance_replacement_payoff_gateway_cases.php';

if (($argv[3] ?? '') === 'recovery-writer') {
    $job = json_decode(base64_decode((string)($argv[4] ?? ''), true), true, 32, JSON_THROW_ON_ERROR);
    $GLOBALS['frgProvider'] = $job['provider'];
    $GLOBALS['frgScenario'] = $job['fault'] ?? 'normal';
    $GLOBALS['frgDisabled'] = $job['disabled'] ?? false;
    $GLOBALS['frgCalls'] = $GLOBALS['frgDiagnostics'] = [];
    rb_staff();
    $entries = [];
    foreach ($job['payments'] as [$invoice, $amount]) {
        $entries[] = \FinanceRecovery\ledger_receive((int)$invoice, (int)$amount, 'bank',
            'Synthetic recovery receipt '.$invoice.' amount '.$amount, gmdate('Y-m-d'),
            hash('sha256', 'finance-recovery-'.$job['key'].'-'.$invoice), ledger_review_hash(ledger_invoice((int)$invoice)));
    }
    echo json_encode(['pid' => getmypid(), 'connection' => (int)query('SELECT CONNECTION_ID()')->fetchColumn(),
        'committed' => !db()->inTransaction(), 'entries' => $entries,
        'provider' => $GLOBALS['frgProvider'], 'calls' => $GLOBALS['frgCalls'], 'diagnostics' => $GLOBALS['frgDiagnostics']], JSON_THROW_ON_ERROR);
    exit(0);
}

function frg_receipt_process(array $payments, string $key, string $fault = 'normal', bool $disabled = false): array {
    global $prefix, $root;
    if (db()->inTransaction()) throw new RuntimeException('Receipt worker cannot inherit parent transaction.');
    $job = ['payments' => $payments, 'key' => $key, 'fault' => $fault, 'disabled' => $disabled,
        'provider' => $GLOBALS['frgProvider']];
    $pipes = [];
    $process = proc_open([PHP_BINARY, __DIR__.'/finance_replacement_payoff_native.php', $prefix, $root,
        'recovery-writer', base64_encode(json_encode($job, JSON_THROW_ON_ERROR))],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start independent finance receipt process.');
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 30); stream_set_timeout($pipes[2], 30);
    $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
    $timeout = stream_get_meta_data($pipes[1])['timed_out'] || stream_get_meta_data($pipes[2])['timed_out'];
    fclose($pipes[1]); fclose($pipes[2]);
    if ($timeout) proc_terminate($process);
    $status = proc_close($process);
    if ($timeout || $status !== 0 || $stderr !== '') throw new RuntimeException('Independent receipt worker failed: '.$stderr);
    $proof = json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
    if ($proof['pid'] === getmypid() || $proof['connection'] === (int)query('SELECT CONNECTION_ID()')->fetchColumn()
        || $proof['committed'] !== true) throw new RuntimeException('Receipt worker did not prove independent commit.');
    $GLOBALS['frgProvider'] = $proof['provider'];
    $GLOBALS['frgCalls'] = array_merge($GLOBALS['frgCalls'], $proof['calls']);
    return $proof;
}

function frg_settlement(array $offer, string $gateway, int $period, string $transaction): array {
    return ['messages' => ['resultCode' => 'Ok'], 'transaction' => ['transId' => $transaction,
        'transactionStatus' => 'settledSuccessfully', 'authAmount' => '10.00',
        'subscription' => ['id' => $gateway, 'payNum' => (string)($period + 1)],
        'order' => ['invoiceNumber' => license_finance_anet_subscription_ref($offer['id'])]]];
}

function frg_seed(string $name, bool $active = false): array {
    $GLOBALS['frgProvider'] = $GLOBALS['frgCalls'] = $GLOBALS['frgDiagnostics'] = [];
    $GLOBALS['frgScenario'] = 'normal'; $GLOBALS['frgDisabled'] = false;
    $GLOBALS['frgGatewayContext'] = []; $GLOBALS['frgBeforeLock'] = null;
    $GLOBALS['frgAfterTerminal'] = null;
    $GLOBALS['frgDuringCreate'] = null;
    $offer = fep_seed('recovery-'.$name, 500);
    $document = recurring_billing_validate(recurring_billing_row($offer['schedule_id']));
    $terms = \CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms::fromArray($document->data()['terms']);
    $invoices = [];
    for ($period = 0; $period < 3; $period++) {
        $issued = license_finance_issue_period($offer['id'], $period, strtotime($terms->period($period)['start'].' 12:00:00 UTC'));
        $invoices[] = (int)$issued['invoice_id'];
    }
    if ($GLOBALS['frgBeforeInitial'] ?? null) {
        $work = $GLOBALS['frgBeforeInitial']; $GLOBALS['frgBeforeInitial'] = null; $work($offer, $invoices);
    }
    $result = \FinanceRecovery\license_finance_subscription_create_profile($offer['id'],
        ['customer_profile_id' => '1001', 'payment_profile_id' => '2001']);
    if ($result !== 'pending_first_payment') throw new RuntimeException('Recovery fixture did not complete initial enrollment.');
    $gateway = (string)license_finance_subscription_row($offer['id'])['gateway_subscription_id'];
    if ($active) {
        $transaction = (string)(980000000 + $invoices[0]);
        $result = \FinanceRecovery\license_finance_subscription_settle(frg_settlement($offer, $gateway, 0, $transaction), $transaction);
        if ($result !== 'finance_subscription_payment_recorded'
            || license_finance_subscription_row($offer['id'])['status'] !== 'active') {
            throw new RuntimeException('Recovery fixture did not establish active state through a real verified settlement.');
        }
    }
    return [$offer, $gateway, $invoices];
}

function frg_recovery_cases(): void {
    global $observations, $password;
    $GLOBALS['frgNextGateway'] = 9600000;
    $observations[] = ['family' => 'FIN source binding', 'files' => $GLOBALS['frgSourceHashes'],
        'scope' => 'Actual function bodies and PDO; synthetic transport/mode and future invoice timing; separate real receipt processes.'];
    foreach (['missing_create_id', 'lost_create_reply'] as $fault) {
        foreach ([false, true] as $payoff) {
            $name = 'FIN-01 '.$fault.($payoff ? ' with committed payoff' : ' unpaid control');
            [$offer, $old, $invoices] = frg_seed($fault.($payoff ? '-payoff' : '-unpaid'));
            $GLOBALS['frgProvider'][$old]['status'] = 'suspended';
            \FinanceRecovery\license_finance_subscription_lifecycle_review('net.authorize.customer.subscription.suspended', $old);
            $before = fep_snapshot(); $proof = null;
            if ($payoff) $GLOBALS['frgDuringCreate'] = static function () use ($invoices, $name, &$proof): void {
                $proof = frg_receipt_process(array_map(fn($id) => [$id, 1000], $invoices), $name);
            };
            $GLOBALS['frgScenario'] = $fault;
            rb_client();
            $result = \FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'], 1, 'COMMON.ACCEPT.INAPP.PAYMENT', 'synthetic');
            $new = (string)$GLOBALS['frgNextGateway'];
            $sub = license_finance_subscription_row($offer['id']);
            $plan = license_financing_plan((int)$offer['license_id']);
            fep_ok($name.' reports an unresolved create honestly', $result === 'review' && $new !== $old && $GLOBALS['frgProvider'][$new]['status'] === 'active');
            fep_ok($name.' retains a visible unresolved successor obligation', in_array($sub['status'], ['replacing', 'review', 'retirement_pending'], true));
            fep_ok($name.' preserves the permanent payoff boundary', $payoff
                ? $plan['state'] === 'paid_off' && $plan['paid_off_at'] !== null && count($proof['entries']) === 3
                : $plan['paid_off_at'] === null);
            $retained = fep_snapshot(); $start = count($GLOBALS['frgCalls']); $refused = false;
            try { \FinanceRecovery\license_finance_subscription_reenroll_opaque($offer['id'], 1, 'COMMON.ACCEPT.INAPP.PAYMENT', 'synthetic'); }
            catch (Throwable) { $refused = true; }
            fep_ok($name.' refuses any second create', $refused && count($GLOBALS['frgCalls']) === $start);
            $GLOBALS['frgScenario'] = 'normal'; $error = null; $recovered = null;
            rb_staff();
            try { $recovered = \FinanceRecovery\license_finance_subscription_reconcile($offer['id'], $new, $password); }
            catch (Throwable $exception) { $error = get_class($exception).': '.$exception->getMessage(); }
            $current = license_finance_subscription_row($offer['id']); $after = fep_snapshot();
            fep_ok($name.' owner recovers the existing successor using its original contract', $error === null
                && $recovered === ($payoff ? 'paid_off' : 'pending_first_payment') && $current['gateway_subscription_id'] === $new);
            fep_ok($name.' verifies the required successor terminal state', $GLOBALS['frgProvider'][$new]['status'] === ($payoff ? 'canceled' : 'active'));
            $history = license_finance_subscription_history_row($old);
            fep_ok($name.' retains predecessor identity and terminal history', $history !== null
                && $history['gateway_subscription_id'] === $old && $history['replaced_by_gateway_subscription_id'] === $new
                && $history['terminal_status'] === 'canceled');
            fep_ok($name.' preserves exact committed receipts and financing state during recovery',
                $retained['invoice_payments'] === $after['invoice_payments'] && $retained['license_financing'] === $after['license_financing']);
            fep_ok($name.' preserves license identity and domains', $before['website_licenses'] === $after['website_licenses']
                && $before['license_domains'] === $after['license_domains']);
            fep_ok($name.' performs no network boundary under a transaction', !array_filter($GLOBALS['frgCalls'], fn($call) => $call['transaction']));
            $observations[] = ['family' => 'FIN-01', 'case' => $name, 'result' => $result, 'state_after_reply' => $sub['status'],
                'old' => $old, 'new' => $new, 'writer' => $proof, 'recovery' => $recovered, 'recovery_error' => $error,
                'calls' => $GLOBALS['frgCalls'], 'diagnostics' => $GLOBALS['frgDiagnostics']];
        }
    }
    foreach ([false, true] as $active) {
        foreach (['partial_next', 'paid_later', 'partial_later'] as $kind) {
            $name = 'FIN-02 '.($active ? 'established active ' : 'returned pending ').$kind;
            [$offer, $gateway, $invoices] = frg_seed(($active ? 'active-' : 'pending-').$kind, $active);
            $period = $kind === 'partial_next' ? ($active ? 1 : 0) : 2;
            $amount = $kind === 'paid_later' ? 1000 : 500;
            $before = fep_snapshot();
            $proof = frg_receipt_process([[$invoices[$period], $amount]], $name);
            $sub = license_finance_subscription_row($offer['id']); $after = fep_snapshot();
            fep_ok($name.' keeps the actual separately committed receipt', count($proof['entries']) === 1
                && ledger_balance(ledger_invoice($invoices[$period]))['paid_cents'] === $amount);
            fep_ok($name.' retains or completes exact automatic-contract retirement',
                in_array($sub['status'], ['retirement_pending', 'billing_suspended'], true)
                && $sub['gateway_subscription_id'] === $gateway);
            fep_ok($name.' stops collecting the original full amount', $GLOBALS['frgProvider'][$gateway]['status'] === 'canceled');
            fep_ok($name.' retains license identity domains and original signed terms', $before['website_licenses'] === $after['website_licenses']
                && $before['license_domains'] === $after['license_domains'] && $before['license_finance_offers'] === $after['license_finance_offers']);
            fep_ok($name.' does not invent payoff', license_financing_plan((int)$offer['license_id'])['paid_off_at'] === null);
            fep_ok($name.' separates retirement I/O from receipt transactions', !array_filter($GLOBALS['frgCalls'], fn($call) => $call['transaction']));
            $observations[] = ['family' => 'FIN-02', 'case' => $name, 'writer' => $proof,
                'subscription_state' => $sub['status'], 'provider_state' => $GLOBALS['frgProvider'][$gateway]['status']];
        }
    }
    [$offer, $gateway, $invoices] = frg_seed('ordinary-settlement-control');
    $start = count($GLOBALS['frgCalls']);
    foreach ([0, 1] as $period) {
        $transaction = (string)(970000000 + $invoices[$period]);
        $result = \FinanceRecovery\license_finance_subscription_settle(frg_settlement($offer, $gateway, $period, $transaction), $transaction);
        fep_ok('FIN-02 ordinary recurring settlement '.$period.' remains healthy', $result === 'finance_subscription_payment_recorded'
            && ledger_balance(ledger_invoice($invoices[$period]))['due_cents'] === 0
            && license_finance_subscription_row($offer['id'])['status'] === 'active');
    }
    fep_ok('FIN-02 legitimate ordinary settlements do not retire a healthy contract',
        $GLOBALS['frgProvider'][$gateway]['status'] === 'active' && count($GLOBALS['frgCalls']) === $start);
    frg_recovery_safety_cases();
    frg_gateway_cases();
    frg_authority_cases();
    $observations[]=['family'=>'FIN retained diagnostic expectation',
        'payment_link_preparation_failures'=>$GLOBALS['expectedPreparationDiagnostics']??0,
        'original_synthetic_readback_cancellation_failures'=>6,
        'scope'=>'Each synthetic nonzero-initial-payment seed intentionally signs while payment-link storage is disabled; its retained RuntimeException originates at financing_offers.php:261. The original six readback/cancellation negatives remain explicit. FinanceRecovery exceptions are captured separately in case diagnostics.'];
}
