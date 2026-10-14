<?php
declare(strict_types=1);

/**
 * CLI worker for the real cross-process reservation test. Spawned by
 * run.php via proc_open; prints one claim state to stdout and exits.
 *
 *   php file_ledger_worker.php <file> claim <key> <fp>
 *   php file_ledger_worker.php <file> hold <key> <fp> [age_seconds]
 *   php file_ledger_worker.php <file> finish <key> <fp>
 *
 * 'claim' performs claim() and echoes the LedgerClaim state.
 * 'hold' claims and exits without record() — optionally backdating the
 *   reservation, simulating a crashed executor.
 * 'finish' claims then records a terminal 'succeeded' result.
 */

use CajunWebPro\Foundation\Provider\OperationResult;

require __DIR__ . '/../../foundation/provider/TransportRequest.php';
require __DIR__ . '/../../foundation/provider/TransportResponse.php';
require __DIR__ . '/../../foundation/provider/TransportException.php';
require __DIR__ . '/../../foundation/provider/HttpTransport.php';
require __DIR__ . '/../../foundation/provider/ProviderOperation.php';
require __DIR__ . '/../../foundation/provider/OperationResult.php';
require __DIR__ . '/../../foundation/provider/LedgerClaim.php';
require __DIR__ . '/../../foundation/provider/OperationLedger.php';
require __DIR__ . '/FileLedger.php';

[, $file, $action, $key, $fp] = array_pad($argv, 5, null);
if ($file === null || !in_array($action, ['claim', 'hold', 'finish'], true) || $key === null || $fp === null) {
    fwrite(STDERR, "usage: file_ledger_worker.php <file> claim|hold|finish <key> <fp> [age]\n");
    exit(2);
}

$ledger = new FileLedger($file);
$claim = $ledger->claim($key, $fp, time());
$state = $claim->state;

if ($action === 'hold' && $state === 'claimed') {
    $age = (int) ($argv[5] ?? 0);
    if ($age > 0) {
        // Backdate the reservation under the same lock discipline.
        $handle = fopen($file, 'c+');
        flock($handle, LOCK_EX);
        $data = json_decode(stream_get_contents($handle) ?: '', true) ?: [];
        $data['reservations'][$key] = time() - $age;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
    // Exit without record() — a crashed/abandoned executor.
} elseif ($action === 'finish' && $state === 'claimed') {
    $ledger->record($key, $fp, OperationResult::succeeded('x.y', 'worker'), time());
}

echo $state, "\n";
