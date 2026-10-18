<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
define('CWP_BOOT', true);
require __DIR__.'/../site/includes/bootstrap.php';
require_once CWP_CODE_ROOT.'/includes/layout/admin.php';
require_once CWP_CODE_ROOT.'/app/providers/registered_nameservers_views.php';
$GLOBALS['cwp_config'] = ['site_url' => 'https://studio.synthetic.invalid/subfolder'];
$_SESSION = ['csrf' => str_repeat('a', 64)];
$checks = [];
function rnv_check(string $name, bool $passed): void { global $checks; $checks[] = ['name' => $name, 'passed' => $passed]; }
function rnv_html(callable $render): string { ob_start(); try { $render(); return ob_get_contents(); } finally { ob_end_clean(); } }
function rnv_inputs(string $html): array {
    preg_match_all('/<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/', $html, $matches, PREG_SET_ORDER);
    $out = [];
    foreach ($matches as $match) $out[$match[1]][] = html_entity_decode($match[2], ENT_QUOTES|ENT_HTML5, 'UTF-8');
    return $out;
}
$projection = ['domain' => 'example.test', 'status' => 'observed', 'nameservers' => ['ns1.example.test', 'ns2.example.test'],
    'observed_epoch' => 1760000000, 'history' => [], 'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0]];
$html = rnv_html(fn() => registered_nameservers_render($projection));
rnv_check('Current list includes both hosts and its exact UTC observation time', str_contains($html, 'data-nameserver-current') && str_contains($html, 'ns1.example.test') && str_contains($html, 'ns2.example.test') && str_contains($html, gmdate('Y-m-d H:i:s', 1760000000).' UTC'));
rnv_check('Saved observation is not described as propagation or change permission', str_contains($html, 'does not confirm propagation or permission to change nameservers'));
$history = ['id' => str_repeat('d', 32), 'state' => 'complete', 'nameservers' => ['old.example.test'], 'observed_epoch' => 1759999000, 'current_assignment' => true];
foreach (['not_observed', 'stale', 'unfinished', 'unavailable', 'refused', 'access_changed', 'credential', 'assignment_changed', 'unknown'] as $status) {
    $p = $projection; $p['status'] = $status; $p['history'] = [$history];
    $html = rnv_html(fn() => registered_nameservers_render($p));
    rnv_check($status.' suppresses current values even if an inconsistent caller supplies them', !str_contains($html, 'data-nameserver-current') && !str_contains($html, 'ns1.example.test') && !str_contains($html, 'ns2.example.test'));
    rnv_check($status.' retains dated prior values only inside history', str_contains($html, '<details data-nameserver-history><summary>Saved nameserver history</summary>') && str_contains($html, 'old.example.test') && str_contains($html, gmdate('Y-m-d H:i:s', 1759999000).' UTC'));
}
$p = $projection; $p['nameservers'] = [];
$html = rnv_html(fn() => registered_nameservers_render($p));
rnv_check('Valid empty result is explicitly observed rather than unavailable', str_contains($html, 'data-nameserver-current') && str_contains($html, 'No nameservers were returned in this observation.') && !str_contains($html, 'unavailable'));
$p = $projection; $history['current_assignment'] = false; $p['history'] = [$history]; $p['pagination'] = ['page' => 2, 'pages' => 3, 'total' => 42];
$html = rnv_html(fn() => registered_nameservers_render($p));
rnv_check('Old assignments are labeled and selected older history opens', str_contains($html, 'Previous assignment') && str_contains($html, '<details data-nameserver-history open>'));
rnv_check('History page does not replace the current observation', str_contains($html, 'ns1.example.test') && str_contains($html, 'old.example.test'));
$p['history'][0]['state'] = 'unavailable';
$html = rnv_html(fn() => registered_nameservers_render($p));
rnv_check('Failed history never renders an inconsistent supplied nameserver list', !str_contains($html, 'old.example.test') && str_contains($html, 'Refresh attempted '));
$p = $projection; $p['domain'] = '</h3><script>domain</script>'; $p['nameservers'] = ['<img src=x onerror=bad>'];
$p['history'] = [$history]; $p['history'][0]['nameservers'] = ['<script>history</script>'];
$p['binding_json'] = 'PRIVATE_BINDING'; $p['key_fingerprint'] = 'PRIVATE_FINGERPRINT';
$html = rnv_html(fn() => registered_nameservers_render($p));
rnv_check('Projected domain, current nameservers and history are HTML escaped', !str_contains($html, '<script>') && !str_contains($html, '<img ') && str_contains($html, '&lt;script&gt;domain') && str_contains($html, '&lt;img') && str_contains($html, '&lt;script&gt;history'));
rnv_check('Private binding fields and original refresh ID are never displayed', !str_contains($html, 'PRIVATE_') && !str_contains($html, $history['id']));
$binding = ['link_id' => str_repeat('b', 32), 'link_revision' => 7, 'profile_revision' => 9, 'source_refresh_id' => str_repeat('c', 32), 'key_fingerprint' => 'PRIVATE_FINGERPRINT', 'binding_json' => 'PRIVATE_BINDING'];
$_POST = ['current_password' => 'PRIVATE_PASSWORD', 'desired_ns' => 'unapproved.example.test'];
$html = rnv_html(fn() => registered_nameservers_prepare_form()); $inputs = rnv_inputs($html);
rnv_check('Prepare form has real CSRF and explicit local prepare action', ($inputs['csrf'] ?? []) === [str_repeat('a', 64)] && ($inputs['action'] ?? []) === ['provider_inventory_nameservers_prepare']);
rnv_check('Prepare password stays blank after failed POST', ($inputs['current_password'] ?? []) === [''] && !str_contains($html, 'PRIVATE_PASSWORD'));
$html = rnv_html(fn() => registered_nameservers_refresh_form($binding)); $inputs = rnv_inputs($html);
rnv_check('Refresh form has the real CSRF and read-only refresh action', ($inputs['csrf'] ?? []) === [str_repeat('a', 64)] && ($inputs['action'] ?? []) === ['provider_inventory_nameservers_refresh']);
rnv_check('Refresh form binds the exact displayed link and source revisions', ($inputs['link_id'] ?? []) === [$binding['link_id']] && ($inputs['link_revision'] ?? []) === ['7'] && ($inputs['profile_revision'] ?? []) === ['9'] && ($inputs['refresh_id'] ?? []) === [$binding['source_refresh_id']]);
rnv_check('Refresh exposes no desired-set field, private binding or replayed password', array_keys($inputs) === ['csrf', 'action', 'link_id', 'link_revision', 'profile_revision', 'refresh_id', 'current_password'] && ($inputs['current_password'] ?? []) === [''] && !str_contains($html, 'PRIVATE_') && !str_contains($html, 'unapproved.example.test'));
$html = rnv_html(fn() => registered_nameservers_history_navigation(['page' => 2, 'pages' => 3], $binding['link_id'], 4));
$decoded = html_entity_decode($html, ENT_QUOTES|ENT_HTML5, 'UTF-8');
rnv_check('History navigation keeps domain page and selected link while moving history only', str_contains($decoded, 'nameserver_page=4') && substr_count($decoded, 'nameserver_link='.$binding['link_id']) === 2 && str_contains($decoded, 'nameserver_history_page=1') && str_contains($decoded, 'nameserver_history_page=3') && substr_count($decoded, '#nameserver-'.$binding['link_id']) === 2);
$html = rnv_html(fn() => registered_nameservers_history_navigation(['page' => 1, 'pages' => 1], $binding['link_id'], 1));
rnv_check('A single history page does not add redundant controls', $html === '');
foreach ([[['nested'], 1], [null, 1], ['-1', 1], ['0', 1], ['1e2', 1], ['2x', 1], ['99999999999', 1], ['2', 2], ['9999999', 10000]] as $index => [$value, $expected]) {
    rnv_check('Query page rejects malformed input without warnings '.$index, registered_nameservers_query_page($value, 10000) === $expected);
}
$passed = !array_filter($checks, fn($check) => !$check['passed']);
$path = $argv[1] ?? __DIR__.'/../qa/registered-nameservers-views-results.json';
if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
file_put_contents($path, json_encode(['passed' => $passed, 'scope' => 'Actual pure nameserver renderers, real CSRF helper and form projection. No database, authorization, HTTP, browser or provider acceptance.', 'checks' => $checks], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo ($passed ? 'PASS' : 'FAIL').': '.count($checks).' nameserver renderer checks'.PHP_EOL;
exit($passed ? 0 : 1);
