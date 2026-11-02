<?php
/** Cross-request settings changes on the actual disposable SQL and account mutex. */
declare(strict_types=1);
if (!defined('CWP_BOOT') || PHP_SAPI !== 'cli') exit(2);

if (isset($argv[1]) && str_starts_with($argv[1], '--settings-worker=')) {
    $mode = substr($argv[1], strlen('--settings-worker='));
    if (!in_array($mode, ['pause', 'resume', 'disable'], true)) exit(2);
    nc_owner();
    try {
        if ($mode === 'disable') {
            // The ordinary outbound-settings writer commits a transaction;
            // it does not use the notification dispatcher account mutex.
            staff_transaction(function (): void {
                notification_owner();
                save_setting('email_mode', 'disabled');
            });
        } else {
            notification_email_configure($mode === 'resume');
        }
        echo json_encode(['mode' => $mode, 'pid' => getmypid(), 'transaction_open' => db()->inTransaction()]);
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, get_class($error).': '.$error->getMessage());
        exit(1);
    }
}

function nc_settings_other_request(string $mode): void {
    $pipes = [];
    $worker = proc_open([PHP_BINARY, __DIR__.'/notification_center_native.php', '--settings-worker='.$mode],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__.'/..');
    if (!is_resource($worker)) throw new RuntimeException('Could not start isolated settings worker.');
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 20);
    stream_set_timeout($pipes[2], 20);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    $timedOut = stream_get_meta_data($pipes[1])['timed_out'] || stream_get_meta_data($pipes[2])['timed_out'];
    fclose($pipes[1]);
    fclose($pipes[2]);
    if ($timedOut) proc_terminate($worker);
    $status = proc_close($worker);
    $result = json_decode($out, true);
    nc_ok('Separate '.$mode.' request commits honestly', !$timedOut && $status === 0 && $err === ''
        && is_array($result) && $result['mode'] === $mode && $result['pid'] !== getmypid()
        && $result['transaction_open'] === false);
}

function nc_notification_claims(): array {
    return all('SELECT * FROM '.table('audit')." WHERE action='notifications.email.claim' ORDER BY id");
}

function nc_settings_freshness_cases(): void {
    nc_owner();
    foreach (['pause', 'disable'] as $mode) {
        notification_email_configure(true);
        save_setting('email_mode', 'smtp');
        $id = nc_emit('fresh-dispatch-'.$mode);
        nc_ok('Dispatch '.$mode.' request starts with cached enabled delivery',
            setting('notification_email_enabled', '0') === '1' && setting('email_mode', 'disabled') === 'smtp');
        nc_settings_other_request($mode);
        $key = $mode === 'pause' ? 'notification_email_enabled' : 'email_mode';
        $paused = $mode === 'pause' ? '0' : 'disabled';
        $stale = $mode === 'pause' ? '1' : 'smtp';
        nc_ok('Dispatch '.$mode.' observes a committed change beyond its request cache',
            (one('SELECT value FROM '.table('settings').' WHERE name=?', [$key])['value'] ?? null) === $paused
            && setting($key) === $stale);
        $before = nc_state();
        $claims = nc_notification_claims();
        $settings = all('SELECT * FROM '.table('settings').' ORDER BY name');
        $calls = 0;
        $state = notification_outbox_one($id, function () use (&$calls): string { $calls++; return 'accepted'; });
        nc_ok('Committed '.$mode.' refuses dispatch before synthetic transport', $state === 'paused' && $calls === 0);
        nc_ok('Committed '.$mode.' preserves every delivery attempt and claim audit',
            nc_state() === $before && nc_notification_claims() === $claims
            && all('SELECT * FROM '.table('settings').' ORDER BY name') === $settings && !db()->inTransaction());
        notification_email_configure(true);
        save_setting('email_mode', 'smtp');
        $state = notification_outbox_one($id, function () use (&$calls): string { $calls++; return 'accepted'; });
        nc_ok('Explicit resume after '.$mode.' retains the pending item and sends once',
            $state === 'sent' && $calls === 1 && (int)nc_delivery($id)['attempts'] === 1);
    }

    foreach (['pause', 'resume'] as $mode) {
        notification_email_configure($mode === 'pause');
        $cached = $mode === 'pause' ? '1' : '0';
        $current = $mode === 'pause' ? '0' : '1';
        nc_ok('Emission '.$mode.' request starts with its original cached choice', setting('notification_email_enabled', '0') === $cached);
        nc_settings_other_request($mode);
        nc_ok('Emission '.$mode.' follows a separate committed owner choice',
            (one('SELECT value FROM '.table('settings').' WHERE name=?', ['notification_email_enabled'])['value'] ?? null) === $current
            && setting('notification_email_enabled', '0') === $cached);
        $before = nc_state();
        $claims = nc_notification_claims();
        $id = nc_emit('fresh-emission-'.$mode);
        $feed = one('SELECT audience,recipient_id,kind,ref FROM '.table('notification_feed').' WHERE id=?', [$id]);
        $portal = one('SELECT state,attempts FROM '.table('notification_deliveries')." WHERE notification_id=? AND channel='portal'", [$id]);
        nc_ok('Emission after '.$mode.' preserves its private portal projection',
            is_array($feed) && $feed['audience'] === 'client' && (int)$feed['recipient_id'] === 1
            && $feed['kind'] === 'project-update' && $feed['ref'] === 'job:1'
            && is_array($portal) && $portal['state'] === 'sent' && (int)$portal['attempts'] === 1);
        $email = nc_delivery($id);
        nc_ok('Emission after '.$mode.' uses the current owner email choice',
            $mode === 'pause' ? $email === [] : ($email['state'] === 'pending' && (int)$email['attempts'] === 0));
        $after = nc_state();
        $after['notification_feed'] = array_values(array_filter($after['notification_feed'], fn($row) => $row['id'] !== $id));
        $after['notification_deliveries'] = array_values(array_filter($after['notification_deliveries'], fn($row) => $row['notification_id'] !== $id));
        nc_ok('Emission '.$mode.' retains all earlier records and creates no send claim',
            $after === $before && nc_notification_claims() === $claims && !db()->inTransaction());
    }
}
