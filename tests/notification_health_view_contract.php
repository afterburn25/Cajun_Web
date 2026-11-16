<?php
/** Offline contract for the administration-only notification freshness view. */
declare(strict_types=1);

$root = dirname(__DIR__);
$view = file_get_contents($root.'/site/admin/views/health.php');
$metrics = file_get_contents($root.'/site/app/notifications/metrics.php');
$router = file_get_contents($root.'/site/admin/controllers/router.php');
$adminAuth = file_get_contents($root.'/site/includes/auth/admin.php');
$staffAccounts = file_get_contents($root.'/site/app/staff/accounts.php');
if ($view === false || $metrics === false || $router === false || $adminAuth === false || $staffAccounts === false) {
    fwrite(STDERR, "FAIL: notification health sources unavailable\n");
    exit(1);
}
$healthGate = strpos($router, "if(\$view==='health'){");
$healthActorRead = $healthGate === false ? false : strpos($router, '$healthActor=admin_user();', $healthGate);
$healthDirectRefusal = $healthActorRead === false ? false : strpos($router, "if(!\$healthActor||!admin_session_matches(\$healthActor))fail_page('Access unavailable.',403);", $healthActorRead);
$actionsDispatch = $healthDirectRefusal === false ? false : strpos($router, "require CWP_CODE_ROOT.'/admin/controllers/actions.php';", $healthDirectRefusal);
$ordinaryAdminRedirect = $healthDirectRefusal === false ? false : strpos($router, '$user=require_admin();', $healthDirectRefusal);

$checks = [
    'Health view loads the retained metrics implementation' => str_contains($view, "require_once CWP_CODE_ROOT.'/app/notifications/metrics.php'"),
    'Health route refuses a missing or mismatched direct administrator session before actions or ordinary login redirects' => $healthGate !== false && $healthActorRead !== false && $healthDirectRefusal !== false && $actionsDispatch !== false && $ordinaryAdminRedirect !== false && $healthDirectRefusal < $actionsDispatch && $actionsDispatch < $ordinaryAdminRedirect,
    'Direct administrator matching rejects support query and active support context' => str_contains($adminAuth, "array_key_exists('support', \$_GET)") && str_contains($adminAuth, "function_exists('support_current') && support_current()"),
    'Health remains restricted to the current staff management permission' => str_contains($staffAccounts, "in_array(\$view,['email','health','studio-upgrade'],true))return 'staff.manage';"),
    'Health view labels the operational freshness section' => str_contains($view, '<h2>Operational freshness</h2>'),
    'Health view takes one render-time reference for every status' => substr_count($view, '$metricNow=time()') === 1,
    'Health view reads every persisted metric sample' => str_contains($view, 'foreach(metric_samples() as $m)'),
    'Health view evaluates each sample against its own maximum age' => str_contains($view, "status(\$metricNow,\$m['max_age'])"),
    'Health view never presents an expired value as current' => str_contains($view, "currentValue(\$metricNow,\$m['max_age'])"),
    'Health view explains stale value suppression' => str_contains($view, 'Recorded value held back — outside freshness window'),
    'Health view distinguishes fresh stale and absent badges' => str_contains($view, "\$status==='fresh'?'passed':(\$status==='stale'?'needs_attention':'absent')"),
    'Health view escapes labels and metric identifiers' => str_contains($view, "esc(\$m['label'])") && str_contains($view, "esc(\$m['sample']->kind)"),
    'Health view renders recorded timestamps explicitly as UTC' => str_contains($view, "gmdate('Y-m-d H:i',\$m['sample']->checkedAt).' UTC'"),
    'Metrics use stored row times rather than render time' => str_contains($metrics, 'metric_epoch($audit[\'last\']??null)') && !str_contains($metrics, 'new MetricSample(\'audit.activity\',$audit&&$audit[\'n\']?$audit[\'n\'].\' recorded actions\':null,time())'),
    'Notification delivery count is truthfully labeled as records, never dispatch attempts' => str_contains($metrics, "\$delivery['n'].' delivery records'") && !str_contains($metrics, 'recorded attempts'),
    'Unprepared delivery and notification features remain honestly absent' => substr_count($metrics, "new MetricSample('delivery.published',null,null)") === 1 && substr_count($metrics, "new MetricSample('notification.deliveries',null,null)") === 1,
    'The view remains read-only and performs no delivery or provider action' => !preg_match('/notification_(?:emit|outbox|mark|migrate)|provider_[a-z_]+\s*\(/', $view),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, "FAIL: ".count($failed)." notification health contract checks\n - ".implode("\n - ", $failed)."\n");
    exit(1);
}

echo 'PASS: '.count($checks)." notification health contract checks; offline and read-only.\n";
