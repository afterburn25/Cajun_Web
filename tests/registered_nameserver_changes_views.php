<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
define('CWP_BOOT', true);
require __DIR__.'/../site/includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/includes/layout/admin.php';
require_once CWP_CODE_ROOT.'/app/providers/registered_nameservers_views.php';
$GLOBALS['cwp_config'] = ['site_url' => 'https://studio.synthetic.invalid/subfolder'];
$_SESSION = ['csrf' => str_repeat('a', 64)];
$_POST = ['current_password' => 'PRIVATE_PASSWORD', 'desired_nameservers' => 'unreviewed.example.test'];
$checks = [];
function rncv_check(string $name, bool $passed): void { global $checks; $checks[] = ['name' => $name, 'passed' => $passed]; }
function rncv_html(callable $render): string { ob_start(); try { $render(); return ob_get_contents(); } finally { ob_end_clean(); } }
function rncv_inputs(string $html): array {
    preg_match_all('/<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/', $html, $matches, PREG_SET_ORDER);
    $out = [];
    foreach ($matches as $match) $out[$match[1]][] = html_entity_decode($match[2], ENT_QUOTES|ENT_HTML5, 'UTF-8');
    return $out;
}
$form = ['ready' => true, 'eligible' => true, 'reason' => 'ready', 'link_id' => str_repeat('b', 32),
    'link_revision' => 2, 'domain' => 'example.test', 'anchor_id' => str_repeat('c', 32), 'binding_sha256' => str_repeat('d', 64),
    'old_nameservers' => ['ns1.old.test', 'ns2.old.test'], 'observed_epoch' => 1760000000, 'pending' => null,
    'binding_json' => 'PRIVATE_BINDING', 'key_fingerprint' => 'PRIVATE_FINGERPRINT'];
$html = rncv_html(fn() => registered_nameserver_changes_prepare_form()); $inputs = rncv_inputs($html);
rncv_check('Setup is collapsed and requires the actual CSRF and explicit prepare action', str_starts_with($html, '<details data-nameserver-change-setup>') && ($inputs['csrf'] ?? []) === [str_repeat('a', 64)] && ($inputs['action'] ?? []) === ['provider_nameserver_change_prepare']);
rncv_check('Setup password is blank despite a submitted password', ($inputs['current_password'] ?? []) === [''] && !str_contains($html, 'PRIVATE_PASSWORD'));
$html = rncv_html(fn() => registered_nameserver_changes_form_render($form)); $inputs = rncv_inputs($html);
rncv_check('Change control is grouped in one collapsed domain disclosure', str_starts_with($html, '<details data-nameserver-change>') && substr_count($html, '<form') === 1);
rncv_check('Approval form includes real CSRF and dedicated submit action', ($inputs['csrf'] ?? []) === [str_repeat('a', 64)] && ($inputs['action'] ?? []) === ['provider_nameserver_change_submit']);
rncv_check('Approval binds exact original link, observation and binding digest', ($inputs['link_id'] ?? []) === [$form['link_id']] && ($inputs['anchor_id'] ?? []) === [$form['anchor_id']] && ($inputs['binding_sha256'] ?? []) === [$form['binding_sha256']]);
rncv_check('Request identity is a fresh 32-character hexadecimal value', count($inputs['request_id'] ?? []) === 1 && preg_match('/\A[a-f0-9]{32}\z/', $inputs['request_id'][0]) === 1);
$again = rncv_inputs(rncv_html(fn() => registered_nameserver_changes_form_render($form)));
rncv_check('Independent rendered forms have distinct request identifiers', $again['request_id'] !== $inputs['request_id']);
rncv_check('Whole old set and its observation time are displayed together', str_contains($html, 'ns1.old.test') && str_contains($html, 'ns2.old.test') && str_contains($html, gmdate('Y-m-d H:i:s', 1760000000).' UTC'));
rncv_check('Old nameservers are never writable posted fields', array_keys($inputs) === ['csrf', 'action', 'request_id', 'link_id', 'anchor_id', 'binding_sha256', 'reviewed', 'current_password']);
rncv_check('Desired set is an explicit blank bounded textarea, with complete disjoint-list explanation', str_contains($html, 'name="desired_nameservers" rows="4" maxlength="16384" required') && str_contains($html, 'autocomplete="off"></textarea>') && str_contains($html, 'new list must not contain any current nameserver'));
rncv_check('One request requires unchecked explicit review and blank current password', str_contains($html, 'type="checkbox" name="reviewed" value="1" required>') && !str_contains($html, 'checked') && ($inputs['current_password'] ?? []) === ['']);
rncv_check('Private inputs, credentials and stale proposed values are not echoed', !str_contains($html, 'PRIVATE_') && !str_contains($html, 'unreviewed.example.test'));
foreach (['not_prepared', 'unavailable', 'pending', 'unknown'] as $reason) {
    $blocked = $form; $blocked['eligible'] = false; $blocked['reason'] = $reason;
    $html = rncv_html(fn() => registered_nameserver_changes_form_render($blocked));
    rncv_check($reason.' cannot render an approval form or old values', !str_contains($html, '<form') && !str_contains($html, 'ns1.old.test') && !str_contains($html, 'textarea'));
}
$view = ['id' => str_repeat('e', 32), 'link_id' => $form['link_id'], 'domain_id' => 14702, 'domain' => 'example.test',
    'old_nameservers' => $form['old_nameservers'], 'desired_nameservers' => ['ns1.new.test', 'ns2.new.test'], 'requested_epoch' => 1760000030,
    'status' => 'uncertain', 'current_assignment' => true, 'can_verify' => true, 'recovery_ledger_sha256' => null, 'recovered_dispatch' => false, 'last_check' => null,
    'binding_json' => 'PRIVATE_BINDING', 'key_fingerprint' => 'PRIVATE_FINGERPRINT'];
$html = rncv_html(fn() => registered_nameserver_changes_summary_render($view)); $inputs = rncv_inputs($html);
rncv_check('Uncertain request offers only explicit GET verification with actual CSRF', ($inputs['action'] ?? []) === ['provider_nameserver_change_verify'] && ($inputs['request_id'] ?? []) === [$view['id']] && ($inputs['csrf'] ?? []) === [str_repeat('a', 64)]);
rncv_check('Uncertain status never claims success and explains no automatic resend', str_contains($html, 'result not yet confirmed') && str_contains($html, 'does not resend the change') && !str_contains($html, 'provider_nameserver_change_submit'));
rncv_check('History retains both complete sets and exact UTC request date', str_contains($html, 'ns1.old.test') && str_contains($html, 'ns2.old.test') && str_contains($html, 'ns1.new.test') && str_contains($html, 'ns2.new.test') && str_contains($html, gmdate('Y-m-d H:i:s', 1760000030).' UTC'));
rncv_check('Safe history omits private binding and keeps password blank', !str_contains($html, 'PRIVATE_') && ($inputs['current_password'] ?? []) === ['']);
$unverifiable = $view; $unverifiable['can_verify'] = false;
$html = rncv_html(fn() => registered_nameserver_changes_summary_render($unverifiable));
rncv_check('An unfinished dispatch ledger cannot expose a verification action', !str_contains($html, '<form') && str_contains($html, 'saved dispatch record needs review'));
$old = $view; $old['current_assignment'] = false;
$html = rncv_html(fn() => registered_nameserver_changes_summary_render($old));
rncv_check('Changed or removed assignments retain history without a send or verify form', !str_contains($html, '<form') && str_contains($html, 'assignment or provider connection has changed') && str_contains($html, 'ns1.new.test'));
$interrupted = $view; $interrupted['status'] = 'interrupted';
$html = rncv_html(fn() => registered_nameserver_changes_summary_render($interrupted));
rncv_check('Interrupted request without eligible durable witness has no recovery or verification form', !str_contains($html, '<form') && str_contains($html, 'Another change remains blocked'));
$interrupted['recovery_ledger_sha256'] = str_repeat('f', 64);
$html = rncv_html(fn() => registered_nameserver_changes_summary_render($interrupted)); $inputs = rncv_inputs($html);
rncv_check('Eligible recovery binds exact request and reviewed ledger digest with CSRF', ($inputs['action'] ?? []) === ['provider_nameserver_change_recover'] && ($inputs['request_id'] ?? []) === [$view['id']] && ($inputs['ledger_sha256'] ?? []) === [str_repeat('f', 64)] && ($inputs['csrf'] ?? []) === [str_repeat('a', 64)]);
rncv_check('Recovery requires unchecked review, blank password and explains no resend', ($inputs['reviewed'] ?? []) === ['1'] && ($inputs['current_password'] ?? []) === [''] && !str_contains($html, 'checked') && str_contains($html, 'does not resend the change or confirm its result'));
$interrupted['current_assignment'] = false;
$html = rncv_html(fn() => registered_nameserver_changes_summary_render($interrupted));
rncv_check('An inconsistent recovery digest cannot expose recovery for a changed assignment', !str_contains($html, '<form'));
foreach (['not_sent', 'observed'] as $status) {
    $done = $view; $done['status'] = $status;
    $html = rncv_html(fn() => registered_nameserver_changes_summary_render($done));
    rncv_check($status.' history contains no action capable of retrying the original request', !str_contains($html, '<form'));
}
$done['recovered_dispatch'] = true;
$html = rncv_html(fn() => registered_nameserver_changes_summary_render($done));
rncv_check('Observed result disclaims both causality and propagation', str_contains($html, 'does not prove this request caused the change or confirm DNS propagation'));
rncv_check('Recovered history is explicitly identified without claiming provider success', str_contains($html, 'Missing request history was restored without resending the change'));
foreach (['observed', 'different', 'unavailable', 'unknown'] as $disposition) {
    $checked = $view; $checked['last_check'] = ['disposition' => $disposition, 'nameservers' => ['followup.example.test'], 'recorded_epoch' => 1760000060];
    $html = rncv_html(fn() => registered_nameserver_changes_summary_render($checked));
    rncv_check($disposition.' follow-up displays dated values only for a recognized completed set', str_contains($html, gmdate('Y-m-d H:i:s', 1760000060).' UTC') && str_contains($html, 'followup.example.test') === in_array($disposition, ['observed', 'different'], true));
}
$blocked = $form; $blocked['eligible'] = false; $blocked['reason'] = 'pending'; $blocked['pending'] = $view;
$html = rncv_html(fn() => registered_nameserver_changes_form_render($blocked));
rncv_check('Pending domain exposes its own request even when global history is on another page', str_contains($html, 'data-nameserver-change-history') && str_contains($html, 'provider_nameserver_change_verify') && !str_contains($html, 'provider_nameserver_change_submit'));
rncv_check('Inline pending summary avoids duplicate global request anchors', !str_contains($html, 'id="nameserver-request-'));
$evil = $view; $evil['domain'] = '</h4><script>domain</script>'; $evil['old_nameservers'] = ['<img src=x onerror=bad>']; $evil['desired_nameservers'] = ['<script>new</script>'];
$html = rncv_html(fn() => registered_nameserver_changes_summary_render($evil));
rncv_check('Domain and both history sets are escaped as text', !str_contains($html, '<script>') && !str_contains($html, '<img ') && str_contains($html, '&lt;script&gt;domain') && str_contains($html, '&lt;img') && str_contains($html, '&lt;script&gt;new'));
$evil = $form; $evil['domain'] = '<img src=x>'; $evil['old_nameservers'] = ['<script>old</script>'];
$html = rncv_html(fn() => registered_nameserver_changes_form_render($evil));
rncv_check('Approval domain and current list are escaped as text', !str_contains($html, '<script>') && !str_contains($html, '<img ') && str_contains($html, '&lt;img') && str_contains($html, '&lt;script&gt;old'));
$passed = !array_filter($checks, fn($check) => !$check['passed']);
$path = $argv[1] ?? __DIR__.'/../qa/registered-nameserver-changes-views-results.json';
if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
file_put_contents($path, json_encode(['passed' => $passed, 'scope' => 'Actual PHP nameserver change renderers and real CSRF helper using synthetic projections. No SQL, authorization, HTTP, browser or provider acceptance.', 'checks' => $checks], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo ($passed ? 'PASS' : 'FAIL').': '.count($checks).' nameserver change renderer checks'.PHP_EOL;
exit($passed ? 0 : 1);
