<?php
/** Offline contract for the administration-only notification freshness view. */
declare(strict_types=1);

$root = dirname(__DIR__);
$view = file_get_contents($root.'/site/admin/views/health.php');
$metrics = file_get_contents($root.'/site/app/notifications/metrics.php');
if ($view === false || $metrics === false) {
    fwrite(STDERR, "FAIL: notification health sources unavailable\n");
    exit(1);
}

$checks = [
    'Health view loads the retained metrics implementation' => str_contains($view, "require_once CWP_CODE_ROOT.'/app/notifications/metrics.php'"),
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
    'Unprepared delivery and notification features remain honestly absent' => substr_count($metrics, "new MetricSample('delivery.published',null,null)") === 1 && substr_count($metrics, "new MetricSample('notification.deliveries',null,null)") === 1,
    'The view remains read-only and performs no delivery or provider action' => !preg_match('/notification_(?:emit|outbox|mark|migrate)|provider_[a-z_]+\s*\(/', $view),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, "FAIL: ".count($failed)." notification health contract checks\n - ".implode("\n - ", $failed)."\n");
    exit(1);
}

echo 'PASS: '.count($checks)." notification health contract checks; offline and read-only.\n";
