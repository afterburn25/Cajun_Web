<?php
/** Disposable Linux SQL + actual owner launch services; every remote response is synthetic. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Linux' || getenv('CWP_TEST_STACKCP_LAUNCH') !== '1'
    || getenv('CWP_TEST_DATABASE') !== 'cwp_test' || (getenv('CWP_TEST_HOST') ?: '127.0.0.1') !== '127.0.0.1') exit(2);
$worker = count($argv) === 2 && $argv[1] === '--worker';
if (count($argv) !== ($worker ? 2 : 1)) exit(2);
$port = getenv('CWP_TEST_PORT') ?: '33306';
if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) exit(2);
$root = realpath(__DIR__.'/..'); if ($root === false) exit(2);
$runtime = $root.'/test-runtime'; $qa = $root.'/qa';
$private = $runtime.'/stackcp-launch-private'; $report = $qa.'/stackcp-launch-native-results.json';
foreach ([$runtime, $qa] as $path) {
    if (is_link($path) || (file_exists($path) && !is_dir($path))) exit(2);
    if (!is_dir($path) && !mkdir($path, 0700)) exit(2);
}
if (is_link($private) || ($worker ? !is_dir($private) : file_exists($private))
    || (!$worker && (is_link($report) || file_exists($report)))) exit(2);
if (!$worker && !mkdir($private, 0700)) exit(2);
$sessions = $private.'/sessions';
if (is_link($sessions) || (file_exists($sessions) && !is_dir($sessions))) exit(2);
if (!$worker && !mkdir($sessions,0700)) exit(2);
if (!is_dir($sessions)) exit(2);
session_save_path($sessions);
define('CWP_BOOT', true);
require $root.'/site/includes/bootstrap.php';
require $root.'/site/database/schema/base.php';
require $root.'/site/app/providers/stackcp_access.php';
require_once $root.'/site/app/clients/accounts.php';
require $root.'/site/app/providers/stackcp_launch.php';
use CajunWebPro\Foundation\Provider\{HttpTransport, ProviderCredential, TransportRequest, TransportResponse, TransportException};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead, InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIStackCpAccess\{StackCpAccessBinding as B, StackCpAccessObservation as O, StackCpAccessRead as AccessReader};
use CajunWebPro\Foundation\TwentyISupportHandoff\StackCpLaunchTransport;
const SCLN_PREFIX = 'scln_';
const SCLN_PASSWORD = 'Synthetic StackCP launch owner passphrase 2026';
const SCLN_GENERAL = 'SYNTHETIC_STACKCP_LAUNCH_GENERAL_KEY';
const SCLN_AUTH = 'SYNTHETIC_STACKCP_LAUNCH_AUTH_KEY';
const SCLN_TOKEN = 'SYNTHETIC_STACKCP_LAUNCH_ACCESS_TOKEN';
const SCLN_SESSION = 'SYNTHETIC_STACKCP_LAUNCH_SESSION_ID';
$GLOBALS['cwp_config'] = ['site_url'=>'http://127.0.0.1:8091/stackcp-launch', 'storage'=>realpath($private),
    'key'=>str_repeat('synthetic-stackcp-launch-', 4), 'db'=>['host'=>'127.0.0.1', 'port'=>(int)$port,
    'name'=>'cwp_test', 'user'=>getenv('CWP_TEST_USER') ?: 'cwp_test',
    'pass'=>getenv('CWP_TEST_PASSWORD') ?: 'local-ci-only', 'prefix'=>SCLN_PREFIX]];
$_SERVER += ['REMOTE_ADDR'=>'127.0.0.1', 'HTTP_HOST'=>'127.0.0.1:8091', 'SCRIPT_NAME'=>'/index.php', 'REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT'] = $root.'/site'; start_session();
$checks = []; $stage = 'preflight'; $readCalls = 0; $readDns = 0; $readCredentials = 0;
$membershipBody = '[{"identity":"stack-user:351"}]'; $grantsBody = '["package:3501"]'; $readStatus = 200;
$launchCalls = ['brand'=>0, 'token'=>0, 'session'=>0, 'browser'=>0]; $launchDns = 0;
$launchMode = 'ok'; $duringLookup = null; $duringLaunch = null; $activeReview = null;

function sl_ok(string $name, bool $passed): void {
    global $checks;
    foreach ($checks as $check) if ($check['name'] === $name) throw new LogicException('Duplicate native check name.');
    $checks[] = ['name'=>$name, 'passed'=>$passed];
    if (!$passed) throw new RuntimeException($name);
}
function sl_deny(string $name, callable $call, ?string $message = null): void {
    $caught = null;
    try { $call(); } catch (LogicException|RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        $caught = $e;
    }
    sl_ok($name, $caught !== null && ($message === null || $caught->getMessage() === $message));
}
function sl_owner(int $id = 1): void {
    $u = one('SELECT * FROM '.table('users').' WHERE id=?', [$id]);
    if (!$u) throw new LogicException('Missing synthetic owner.');
    $_SESSION = ['uid'=>$id, 'auth_version'=>$u['auth_version'], 'login_at'=>time(), 'last_activity'=>time()];
    $_GET = []; unset($GLOBALS['cwp_support'], $GLOBALS['cwp_settings']);
}
function sl_client(int $id = 1): void {
    $c = one('SELECT * FROM '.table('studio_clients').' WHERE id=?', [$id]);
    if (!$c) throw new LogicException('Missing synthetic client.');
    $_SESSION = ['client_id'=>$id, 'client_auth'=>$c['auth_version'], 'client_login'=>time(), 'client_last'=>time()];
    $_GET = []; unset($GLOBALS['cwp_support'], $GLOBALS['cwp_settings']);
}
function sl_mutex(): bool {
    $name = 'cwp-staff-'.substr(hash('sha256', 'cwp_test|'.SCLN_PREFIX), 0, 40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()', [$name])->fetchColumn() === 1;
}
function sl_locked(callable $call): mixed { return staff_locked(fn()=>staff_transaction($call)); }
function sl_exists(string $name): bool {
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=BINARY ?', [SCLN_PREFIX.$name]) !== null;
}
function sl_count(string $name): int { return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn(); }
function sl_digest(): string {
    $rows = [];
    foreach (all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME', [strlen(SCLN_PREFIX), SCLN_PREFIX]) as $row) {
        $name = $row['TABLE_NAME'];
        if (!preg_match('/\Ascln_[a-z0-9_]+\z/', $name)) throw new LogicException('Unexpected fixture table.');
        $data = all('SELECT * FROM `'.$name.'`');
        usort($data, fn($a, $b)=>strcmp(json_encode($a, JSON_THROW_ON_ERROR), json_encode($b, JSON_THROW_ON_ERROR)));
        $rows[$name] = $data;
    }
    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
}
function sl_calls(): array {
    global $readCalls, $readDns, $readCredentials, $launchCalls, $launchDns;
    return [$readCalls, $readDns, $readCredentials, $launchCalls, $launchDns];
}
function sl_ready(): bool { return staff_locked(fn()=>provider_stackcp_launch_ready()); }
function sl_state(string $id): array {
    return staff_locked(function() use ($id): array {
        $events = provider_stackcp_launch_events();
        if ($events === null) throw new LogicException('Missing native launch storage.');
        $states = provider_stackcp_launch_project($events);
        return $states[$id] ?? throw new LogicException('Missing synthetic admission.');
    });
}
function sl_phase(string $phase, string $kind = 'started'): void {
    global $activeReview;
    if (!is_array($activeReview)) throw new LogicException('Missing exact fixture review.');
    $state = sl_state($activeReview['admission_id']); $last = $state['events'][array_key_last($state['events'])];
    if ($last['phase'] !== $phase || $last['kind'] !== $kind) throw new LogicException('Durable native phase does not precede its external boundary.');
}
function sl_damage(string $name, callable $mutate): void {
    $before = sl_digest(); $calls = sl_calls();
    staff_locked(function() use ($name, $mutate): void {
        db()->beginTransaction();
        try { $mutate(); sl_deny($name, fn()=>provider_stackcp_launch_ready()); }
        finally { db()->rollBack(); }
    });
    sl_ok($name.' exact restoration and zero external calls', sl_ready() && sl_digest() === $before && sl_calls() === $calls);
}
/** Trigger observes the real inserted row before forcing its audit transaction to roll back. */
function sl_audit_fault(string $kind, string $phase, callable $call): bool {
    if (!in_array([$kind,$phase], [['prepared','admission'],['admitted','admission'],['accepted','brand'],['accepted','session'],['terminal','delivery']], true)) throw new LogicException('Unapproved synthetic audit target.');
    $trigger = 'scln_audit_fault';
    if (one('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND BINARY TRIGGER_NAME=?', [$trigger])) throw new RuntimeException('Existing synthetic audit trigger.');
    $prepared = $kind === 'prepared';
    $action = $prepared ? 'provider.stackcp_launch_prepared' : 'provider.stackcp_launch_event';
    $condition = $prepared ? '1=1' : "EXISTS (SELECT 1 FROM ".table('provider_stackcp_launch_events')." WHERE BINARY kind='".$kind."' AND BINARY phase='".$phase."' AND BINARY NEW.detail=BINARY CONCAT('StackCP launch event ',id,' ',document_sha256,' ',proof))";
    $before = sl_exists('provider_stackcp_launch_events') ? sl_count('provider_stackcp_launch_events') : 0;
    db()->exec('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF BINARY NEW.action='".$action."' AND ".$condition." THEN SET @scln_fault_hits=COALESCE(@scln_fault_hits,0)+1; SET @scln_pending_rows=(SELECT COUNT(*) FROM ".table('provider_stackcp_launch_events')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic launch audit fault'; END IF; END");
    query('SET @scln_fault_hits=0,@scln_pending_rows=0'); $caught = false;
    try { $call(); } catch (PDOException $e) {
        $row = one('SELECT @scln_fault_hits AS hits,@scln_pending_rows AS pending_rows');
        $caught = $e->getCode() === '45000' && str_contains($e->getMessage(), 'Synthetic launch audit fault')
            && (int)$row['hits'] === 1 && ($prepared ? (int)$row['pending_rows'] === 0 : (int)$row['pending_rows'] > $before);
    } finally { db()->exec('DROP TRIGGER '.$trigger); }
    return $caught;
}
/** Genuine profile cooldown only; no clock offsets, history rewrite or wait while locked. */
function sl_cool(string $profile): void {
    if (db()->inTransaction() || sl_mutex()) throw new LogicException('Cooldown inside mutation boundary.');
    $epoch = (int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?', [$profile])->fetchColumn();
    if ($epoch < 0 || $epoch > time()) throw new RuntimeException('Unexpected future fixture read.');
    $deadline = microtime(true) + 32;
    while (time() < $epoch + 31) {
        if (microtime(true) > $deadline) throw new RuntimeException('Fixture cooldown bound exceeded.');
        usleep(100000);
    }
}
function sl_read_dns(): string {
    global $readDns;
    if (db()->inTransaction()) throw new LogicException('Read DNS inside transaction.');
    ++$readDns; return '8.8.8.8';
}
final class SlReadTransport implements HttpTransport {
    public function __construct(private string $operation, private $authorize, private ?B $binding = null) {}
    public function send(TransportRequest $request): TransportResponse {
        global $readCalls, $membershipBody, $grantsBody, $readStatus;
        ($this->authorize)();
        if (db()->inTransaction() || !sl_mutex() || $request->method !== 'GET' || $request->body !== null
            || ($request->headers['Authorization'] ?? null) !== 'Bearer '.base64_encode(SCLN_GENERAL)) {
            throw new LogicException('Unexpected synthetic read boundary.');
        }
        $expected = $this->binding ? AccessReader::request($this->binding) : InventoryRead::request(InventoryRead::PACKAGES_LIST);
        if ($request->url !== $expected->url) throw new LogicException('Synthetic read route changed.');
        ++$readCalls;
        if ($this->operation === B::MEMBERSHIP) return new TransportResponse($readStatus, [], $membershipBody);
        if ($this->operation === B::GRANTS) return new TransportResponse($readStatus, [], $grantsBody);
        $items = [];
        foreach ([3501, 3502] as $id) $items[] = ['id'=>$id, 'name'=>'launch'.$id.'.example.invalid',
            'names'=>['launch'.$id.'.example.invalid'], 'enabled'=>true, 'created'=>'2026-01-01T00:00:00+00:00',
            'typeRef'=>12, 'packageTypeName'=>'Synthetic hosting', 'stackUsers'=>[], 'packageLabels'=>[], 'productSpec'=>null];
        return new TransportResponse(200, [], json_encode($items, JSON_THROW_ON_ERROR));
    }
}
function sl_read(array $row, string $secret, string $address, callable $authorize, string $operation, ?B $stackcpAccessContext = null): InventorySnapshot|O {
    if ($secret !== SCLN_GENERAL || $address !== '8.8.8.8' || !in_array($operation, [InventoryRead::PACKAGES_LIST, B::MEMBERSHIP, B::GRANTS], true)) throw new LogicException('Unexpected synthetic reader input.');
    $credential = new ProviderCredential('twenty-i', $row['id'], function() use ($secret, $authorize): string {
        global $readCredentials; $authorize(); ++$readCredentials; return $secret;
    });
    $transport = new SlReadTransport($operation, $authorize, $stackcpAccessContext);
    if ($stackcpAccessContext) return AccessReader::read($transport, $credential, $stackcpAccessContext, time());
    return InventoryReader::read($transport, $credential, $operation, 0, time());
}
function sl_packages(string $profile): array {
    sl_cool($profile); sl_owner();
    return provider_inventory_refresh_attempt($profile, (int)provider_settings_load($profile)['revision'], SCLN_PASSWORD, 'sl_read_dns', 'sl_read');
}
function sl_access(string $profile, string $operation = B::MEMBERSHIP, int $package = 3501): array {
    sl_cool($profile); sl_owner();
    $binding = staff_locked(fn()=>provider_stackcp_access_binding($profile, $package, $operation));
    return provider_stackcp_access_refresh($profile, $package, $operation, $binding->sha256, SCLN_PASSWORD, 'sl_read_dns', 'sl_read');
}
function sl_auth(string $profile, bool $enabled = true, string $key = SCLN_AUTH): array {
    return provider_stackcp_auth_change($profile, provider_stackcp_auth_status($profile)['review_sha256'], $enabled, $enabled ? $key : '', SCLN_PASSWORD);
}
function sl_assign(string $profile, int $package = 3501, string $identity = 'stack-user:351', bool $revoke = false): array {
    $link = one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=?', [$profile, $package]);
    if (!$link) throw new LogicException('Missing synthetic package assignment.');
    return provider_stackcp_assignment_change($profile, $package, provider_stackcp_assignment_status($profile, $package)['review_sha256'], $link['id'], $identity, $revoke, SCLN_PASSWORD);
}
function sl_launch_lookup(string $host): array {
    global $launchDns, $duringLookup;
    if (!sl_mutex() || db()->inTransaction() || !in_array($host, ['api.20i.com','auth-api.20i.com','www.stackcp.com'], true)) throw new LogicException('Unexpected launch DNS boundary.');
    sl_phase(match ($host) {'api.20i.com'=>'brand','auth-api.20i.com'=>'token','www.stackcp.com'=>'session'});
    ++$launchDns;
    if ($duringLookup !== null) { $fn = $duringLookup; $duringLookup = null; $fn(); }
    return [['type'=>'A','ip'=>'8.8.8.8']];
}
final class SlLaunchTransport implements HttpTransport {
    public function __construct(private string $phase, private $authorize) {}
    public function send(#[SensitiveParameter] TransportRequest $request): TransportResponse {
        global $launchCalls, $launchMode, $duringLaunch;
        ($this->authorize)();
        if (!sl_mutex() || db()->inTransaction()) throw new LogicException('Launch phase lost mutex or entered a transaction.');
        sl_phase($this->phase);
        // Run the actual fixed-transport admission method without invoking curl or an external endpoint.
        (new ReflectionMethod(StackCpLaunchTransport::class, 'validate'))->invoke(new StackCpLaunchTransport($this->phase, '8.8.8.8', $this->authorize), $request);
        $expected = match ($this->phase) {'brand'=>SCLN_GENERAL, 'token'=>SCLN_AUTH, 'session'=>SCLN_TOKEN};
        if (($request->headers['Authorization'] ?? null) !== 'Bearer '.base64_encode($expected)) throw new LogicException('Launch phase credential role changed.');
        if ($this->phase === 'token' && $request->body !== '{"grant_type":"client_credentials","scope":"stack-user:351"}') throw new LogicException('Wrong selected Stack user scope.');
        if ($this->phase === 'session' && json_decode((string)$request->body, true, 8, JSON_THROW_ON_ERROR) !== ['brand'=>['brandDomain'=>null,'brandName'=>'Synthetic Studio','theme'=>['accent'=>'gold']]]) throw new LogicException('Changed default brand body.');
        ++$launchCalls[$this->phase];
        if ($duringLaunch !== null) { $fn = $duringLaunch; $duringLaunch = null; $fn($this->phase); }
        if ($launchMode === $this->phase.'-timeout') throw new TransportException('timeout');
        if ($launchMode === $this->phase.'-invalid') return new TransportResponse(200, [], '{"unexpected":"safe synthetic response"}');
        return match ($this->phase) {
            'brand'=>new TransportResponse(200, [], $launchMode === 'brand-custom'
                ? '{"brandDomain":"custom.example.invalid","brandName":"Synthetic Studio"}'
                : '{"brandDomain":null,"brandName":"Synthetic Studio","theme":{"accent":"gold"}}'),
            'token'=>new TransportResponse(200, [], json_encode(['access_token'=>SCLN_TOKEN,'token_type'=>'bearer','expires_in'=>300,'scope'=>'interactive'], JSON_THROW_ON_ERROR)),
            'session'=>new TransportResponse(200, [], json_encode(['session_id'=>SCLN_SESSION], JSON_THROW_ON_ERROR)),
        };
    }
}
function sl_launch_factory(string $phase, string $address, callable $authorize): HttpTransport {
    if ($address !== '8.8.8.8' || !in_array($phase, ['brand','token','session'], true)
        || !sl_mutex() || db()->inTransaction()) throw new LogicException('Unexpected launch factory boundary.');
    $authorize(); return new SlLaunchTransport($phase, $authorize);
}
function sl_deliver(#[SensitiveParameter] string $url): void {
    global $launchCalls, $launchMode;
    if (!sl_mutex() || db()->inTransaction() || $url !== 'https://www.stackcp.com/login/sso?PHPSESSID='.rawurlencode(SCLN_SESSION)) throw new LogicException('Unexpected synthetic browser handoff.');
    sl_phase('delivery', 'terminal');
    ++$launchCalls['browser'];
    if ($launchMode === 'delivery-throw') throw new RuntimeException('Synthetic browser callback failed after receiving its one-use destination.');
}
function sl_open(array $review, string $password = SCLN_PASSWORD, bool $confirmed = true): array {
    global $activeReview;
    $activeReview = $review;
    return provider_stackcp_launch_open($review['admission_id'], $review['profile_id'], $review['package_id'],
        $review['expires_epoch'], $review['review_proof'], $confirmed, $password, 'sl_deliver', 'sl_launch_lookup', 'sl_launch_factory');
}

/** A child must actually be blocked on this connection's staff mutex before revocation. */
function sl_spawn(array $review): array {
    $process = proc_open([PHP_BINARY, __FILE__, '--worker'], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException('Could not create native launch worker.');
    $json = json_encode($review, JSON_THROW_ON_ERROR);
    if (fwrite($pipes[0], $json) !== strlen($json)) throw new RuntimeException('Could not write bounded worker input.');
    fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
    return ['process'=>$process, 'pipes'=>$pipes, 'stdout'=>'', 'stderr'=>'', 'connection'=>null];
}
function sl_blocked(array &$child): void {
    if (!sl_mutex() || db()->inTransaction()) throw new LogicException('Contention observer requires mutex without transaction.');
    $deadline = microtime(true) + 2.5; $parent = (int)query('SELECT CONNECTION_ID()')->fetchColumn();
    do {
        $child['stdout'] .= stream_get_contents($child['pipes'][1]); $child['stderr'] .= stream_get_contents($child['pipes'][2]);
        if (strlen($child['stdout']) > 8192 || $child['stderr'] !== '') throw new RuntimeException('Invalid worker readiness.');
        if ($child['connection'] === null && str_contains($child['stdout'], "\n")) {
            [$line, $child['stdout']] = explode("\n", $child['stdout'], 2); $ready = json_decode($line, true, 4, JSON_THROW_ON_ERROR);
            if (!is_array($ready) || array_keys($ready) !== ['ready','connection'] || $ready['ready'] !== true
                || !is_int($ready['connection']) || $ready['connection'] < 1 || $ready['connection'] === $parent) throw new RuntimeException('Invalid independent worker identity.');
            $child['connection'] = $ready['connection'];
        }
        if ($child['connection'] !== null) {
            $row = one('SELECT ID,STATE,INFO FROM information_schema.PROCESSLIST WHERE ID=?', [$child['connection']]);
            if ($row && stripos((string)$row['INFO'], 'GET_LOCK') !== false && stripos((string)$row['STATE'], 'lock') !== false && sl_mutex()) return;
        }
        if (!proc_get_status($child['process'])['running']) throw new RuntimeException('Worker left before observed mutex contention.');
        if (microtime(true) > $deadline) throw new RuntimeException('Actual blocked worker was not observed.');
        usleep(20000);
    } while (true);
}
function sl_join(array &$child): array {
    $exit = null; $deadline = microtime(true) + 12;
    try {
        do {
            $child['stdout'] .= stream_get_contents($child['pipes'][1]); $child['stderr'] .= stream_get_contents($child['pipes'][2]);
            if (strlen($child['stdout']) > 8192 || strlen($child['stderr']) > 2048) throw new RuntimeException('Worker result exceeds bounds.');
            $status = proc_get_status($child['process']);
            if (!$status['running']) { $exit = $status['exitcode']; break; }
            if (microtime(true) > $deadline) throw new RuntimeException('Native worker timed out.');
            usleep(20000);
        } while (true);
        $child['stdout'] .= stream_get_contents($child['pipes'][1]); $child['stderr'] .= stream_get_contents($child['pipes'][2]);
    } finally {
        if (proc_get_status($child['process'])['running']) proc_terminate($child['process']);
        fclose($child['pipes'][1]); fclose($child['pipes'][2]); $closed = proc_close($child['process']);
        if ($exit === null) $exit = $closed;
    }
    if ($exit !== 0 || $child['stderr'] !== '' || strlen($child['stdout']) > 8192) throw new RuntimeException('Native worker failed.');
    $result = json_decode(trim($child['stdout']), true, 5, JSON_THROW_ON_ERROR);
    if (!is_array($result) || array_keys($result) !== ['outcome','launch_dns','launch_calls']
        || !in_array($result['outcome'], ['completed','refused'], true) || !is_int($result['launch_dns'])
        || $result['launch_dns'] < 0 || !is_array($result['launch_calls']) || array_keys($result['launch_calls']) !== ['brand','token','session','browser']) throw new RuntimeException('Invalid native worker result.');
    foreach ($result['launch_calls'] as $n) if (!is_int($n) || $n < 0) throw new RuntimeException('Invalid native worker call count.');
    return $result;
}

function sl_review(string $profile): array {
    $review = provider_stackcp_launch_review($profile, 3501);
    if ($review['available'] !== true || $review['ready'] !== true) {
        $GLOBALS['sl_review_failure'] = ['ready' => $review['ready'], 'available' => $review['available'], 'reason' => $review['reason']];
        throw new LogicException('Synthetic launch prerequisites are not current.');
    }
    return $review;
}
function sl_replay(string $name, array $review, array $expected): void {
    $before = sl_digest(); $calls = sl_calls();
    sl_ok($name, sl_open($review) === $expected && sl_digest() === $before && sl_calls() === $calls);
}
function sl_read_fault(callable $call): bool {
    $trigger = 'scln_read_fault';
    if (one('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND BINARY TRIGGER_NAME=?', [$trigger])) throw new RuntimeException('Existing synthetic read trigger.');
    db()->exec('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF BINARY NEW.action='provider.stackcp_access_finished' THEN SET @scln_read_fault_hits=COALESCE(@scln_read_fault_hits,0)+1; SET @scln_read_pending=(SELECT COUNT(*) FROM ".table('provider_stackcp_observations')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic launch prerequisite audit fault'; END IF; END");
    query('SET @scln_read_fault_hits=0,@scln_read_pending=0'); $before = sl_count('provider_stackcp_observations'); $caught = false;
    try { $call(); } catch (PDOException $e) {
        $row = one('SELECT @scln_read_fault_hits AS hits,@scln_read_pending AS pending_rows');
        $caught = $e->getCode() === '45000' && str_contains($e->getMessage(), 'Synthetic launch prerequisite audit fault')
            && (int)$row['hits'] === 1 && (int)$row['pending_rows'] === $before + 1;
    } finally { db()->exec('DROP TRIGGER '.$trigger); }
    return $caught;
}
function sl_current_refuses(string $name, string $profile, callable $mutate): void {
    $before = sl_digest(); $calls = sl_calls();
    staff_locked(function() use ($name, $profile, $mutate): void {
        db()->beginTransaction();
        try { $mutate(); sl_deny($name, fn()=>provider_stackcp_launch_resource($profile, 3501)); }
        finally { db()->rollBack(); unset($GLOBALS['cwp_settings']); }
    });
    sl_owner(); // An actual failed current-owner check may invalidate the PHP session; restore only this fixture's current session.
    sl_ok($name.' preserves exact original rows and no effects', sl_digest() === $before && sl_calls() === $calls && sl_review($profile)['available']);
}

if ($worker) {
    try {
        $raw = stream_get_contents(STDIN, 8193);
        if ($raw === false || strlen($raw) > 8192) exit(2);
        $review = json_decode($raw, true, 6, JSON_THROW_ON_ERROR);
        if (!is_array($review) || !isset($review['admission_id'],$review['profile_id'],$review['package_id'],$review['expires_epoch'],$review['review_proof'])) exit(2);
        sl_owner();
        echo json_encode(['ready'=>true,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()], JSON_THROW_ON_ERROR)."\n"; fflush(STDOUT);
        $outcome = 'completed';
        try { sl_open($review); } catch (RuntimeException $e) { if ($e instanceof PDOException) throw $e; $outcome = 'refused'; }
        echo json_encode(['outcome'=>$outcome,'launch_dns'=>$launchDns,'launch_calls'=>$launchCalls], JSON_THROW_ON_ERROR); exit(0);
    } catch (Throwable) { exit(1); }
}

try {
    sl_ok('Disposable namespace is empty before fixture setup', (int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?', [strlen(SCLN_PREFIX), SCLN_PREFIX])->fetchColumn() === 0);
    foreach (schema_statements(SCLN_PREFIX) as $name=>$sql) if (!str_starts_with($name, 'provider_')) db()->exec($sql);
    foreach (default_settings() as $name=>$value) save_setting($name, $value);
    save_setting('studio_schema_version', '2');
    // Only staff bootstrap lacks an existing invitation-based fixture identity. Positive client/provider records use their actual writers below.
    foreach ([1=>'owner',2=>'administrator',3=>'owner'] as $id=>$role) {
        query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',
            ['Launch staff '.$id,'launchstaff'.$id.'@example.invalid',password_hash(SCLN_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
        if ($id === 1) staff_seed_owner(1);
        else query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)', [$id,$role,json_encode(array_keys(staff_permissions()),JSON_THROW_ON_ERROR)]);
    }
    sl_owner();
    sl_ok('Primary and other clients are created by the actual client writer', studio_client_save(0,'','Launch client one','launchclient1@example.invalid',true) === 1 && studio_client_save(0,'','Launch client two','launchclient2@example.invalid',true) === 2);
    provider_settings_migrate(SCLN_PASSWORD); provider_inventory_migrate(SCLN_PASSWORD);
    $profile = provider_settings_save('',0,'Synthetic StackCP launch',true,SCLN_GENERAL,SCLN_PASSWORD);
    $stage = 'prerequisites';
    $before = sl_digest(); $calls = sl_calls();
    sl_ok('Unprepared status and review remain read only', provider_stackcp_launch_status() === ['ready'=>false] && provider_stackcp_launch_review($profile,3501)['reason'] === 'unprepared' && !sl_exists('provider_stackcp_launch_events') && sl_digest() === $before && sl_calls() === $calls);
    sl_deny('Launch preparation requires the separate real access prerequisites', fn()=>provider_stackcp_launch_prepare(SCLN_PASSWORD));
    provider_stackcp_auth_prepare(SCLN_PASSWORD); sl_auth($profile); provider_stackcp_assignment_prepare(SCLN_PASSWORD);
    sl_ok('Actual package executor records the first complete observation', sl_packages($profile)['state'] === 'complete');
    provider_inventory_link($profile,3501,1,1,SCLN_PASSWORD); provider_inventory_link($profile,3502,2,1,SCLN_PASSWORD);
    sl_assign($profile); sl_assign($profile,3502,'stack-user:352'); provider_stackcp_access_prepare(SCLN_PASSWORD);
    sl_ok('Actual package executor records a distinct post-assignment observation', sl_packages($profile)['state'] === 'complete');
    $member = sl_access($profile); $grant = sl_access($profile,B::GRANTS);
    sl_ok('Actual typed membership and whole-user grant reads establish exact selected package access', $member['state'] === 'complete' && $grant['state'] === 'complete' && $readCalls === 4 && $readDns === 4 && $readCredentials === 4);
    $stage = 'preparation';
    db()->beginTransaction();
    try {
        sl_deny('Preparation refuses inherited transaction before DDL', fn()=>provider_stackcp_launch_prepare(SCLN_PASSWORD));
        sl_ok('Caller transaction and absent launch table survive refusal', db()->inTransaction() && !sl_exists('provider_stackcp_launch_events'));
    } finally { db()->rollBack(); }
    sl_owner(2); sl_deny('Ordinary administrator cannot prepare owner launch storage', fn()=>provider_stackcp_launch_prepare(SCLN_PASSWORD));
    sl_client(); sl_deny('Primary client cannot prepare owner launch storage', fn()=>provider_stackcp_launch_prepare(SCLN_PASSWORD));
    sl_owner(); sl_deny('Preparation requires current owner password', fn()=>provider_stackcp_launch_prepare('wrong'));
    // Exact empty table models a prior committed DDL interruption, never a positive business/history record.
    foreach (provider_stackcp_launch_schema(SCLN_PREFIX) as $sql) db()->exec($sql);
    db()->exec('ALTER TABLE '.table('provider_stackcp_launch_events').' ADD COLUMN unexpected INT NULL');
    try { sl_deny('Malformed interrupted empty table is not adopted', fn()=>provider_stackcp_launch_prepare(SCLN_PASSWORD)); }
    finally { db()->exec('ALTER TABLE '.table('provider_stackcp_launch_events').' DROP COLUMN unexpected'); }
    $before = sl_digest();
    sl_ok('Real preparation audit fault rolls back every marker and proof', sl_audit_fault('prepared','admission',fn()=>provider_stackcp_launch_prepare(SCLN_PASSWORD)) && !sl_ready() && sl_digest() === $before);
    provider_stackcp_launch_prepare(SCLN_PASSWORD); $before = sl_digest(); provider_stackcp_launch_prepare(SCLN_PASSWORD);
    sl_ok('Exact empty interruption resumes once and repeat preparation is immutable', sl_ready() && sl_digest() === $before && sl_count('provider_stackcp_launch_events') === 0);
    $stage = 'review and authority';
    $review = sl_review($profile); $staleOtherWindow = sl_review($profile); $before = sl_digest(); $calls = sl_calls();
    sl_ok('Review states whole-user scope without claiming provider restriction', $review['provider_session_scope'] === 'whole-stack-user' && $review['remote_scope_restricted'] === false && $review['stack_identity'] === 'stack-user:351' && $review['previous_state'] === 'none');
    sl_ok('Repeated review and empty history do not write or open credentials', sl_review($profile)['available'] && provider_stackcp_launch_history($profile,3501)['items'] === [] && sl_digest() === $before && sl_calls() === $calls);
    sl_deny('Unchecked whole-user confirmation prevents admission', fn()=>sl_open($review,SCLN_PASSWORD,false));
    sl_deny('Wrong owner password prevents admission', fn()=>sl_open($review,'wrong'));
    foreach (['profile_id'=>str_repeat('f',32),'package_id'=>3502,'expires_epoch'=>time()-1,'review_proof'=>str_repeat('0',64)] as $field=>$value) {
        $changed = $review; $changed[$field] = $value;
        sl_deny('Captured review refuses changed '.$field, fn()=>sl_open($changed));
    }
    foreach (['contact_id'=>1,'contact_auth'=>'synthetic-contact-marker'] as $key=>$value) {
        $_SESSION[$key] = $value;
        sl_deny('Contact marker '.$key.' cannot normalize into an owner launch', fn()=>sl_open($review)); sl_owner();
    }
    $_GET['support']='synthetic-support-context'; sl_deny('Support context cannot launch',fn()=>sl_open($review)); sl_owner();
    sl_owner(2); sl_deny('Ordinary administrator cannot launch',fn()=>sl_open($review));
    sl_client(); sl_deny('Primary client cannot launch',fn()=>sl_open($review));
    sl_client(2); sl_deny('Other client cannot launch',fn()=>sl_open($review));
    sl_owner(3); sl_deny('Different current owner cannot reuse the original review',fn()=>sl_open($review)); sl_owner();
    sl_ok('Every pre-admission refusal leaves exact rows and zero launch effects', sl_digest() === $before && sl_calls() === $calls);
    sl_ok('Late admitted-event audit failure observes the inserted event then atomically rolls back', sl_audit_fault('admitted','admission',fn()=>sl_open($review)) && sl_digest() === $before && sl_calls() === $calls);
    $stage = 'one use';
    $result = sl_open($review); $state = sl_state($review['admission_id']);
    sl_ok('One explicit approval performs exactly brand token session and one browser handoff', $result['state'] === 'handoff_attempted' && $result['phase'] === 'delivery' && $launchCalls === ['brand'=>1,'token'=>1,'session'=>1,'browser'=>1] && $launchDns === 3);
    sl_ok('Eight real immutable events preserve exact original actor and ordered phases', count($state['events']) === 8 && array_map(fn($d)=>[$d['kind'],$d['phase']],$state['events']) === [['admitted','admission'],['started','brand'],['accepted','brand'],['started','token'],['accepted','token'],['started','session'],['accepted','session'],['terminal','delivery']] && $state['admission']['actor_id'] === 1 && $state['admission']['payload']['approval']['binding']['client_id'] === 1);
    sl_replay('Same completed admission can never resend or redeliver', $review, $result);
    $before = sl_digest(); $calls = sl_calls();
    sl_deny('Another window signed before the launch must review its new history', fn()=>sl_open($staleOtherWindow));
    sl_deny('Replay still requires the current owner password', fn()=>sl_open($review,'wrong'));
    $changed = $review; ++$changed['expires_epoch']; sl_deny('Replay cannot change original approval expiry',fn()=>sl_open($changed));
    sl_ok('Rejected replay and stale second window have no mutation or effects',sl_digest() === $before && sl_calls() === $calls);
    $stage = 'phase outcomes';
    foreach (['brand-custom'=>['not_issued','brand',1,0,0], 'token-timeout'=>['unknown','token',1,1,0], 'session-invalid'=>['unknown','session',1,1,1]] as $mode=>$expected) {
        $r = sl_review($profile); $launchMode = $mode; $counts = $launchCalls; $out = sl_open($r); $launchMode = 'ok';
        sl_ok($mode.' retains conservative exact phase and no browser effect', $out['state'] === $expected[0] && $out['phase'] === $expected[1] && $launchCalls['brand']-$counts['brand'] === $expected[2] && $launchCalls['token']-$counts['token'] === $expected[3] && $launchCalls['session']-$counts['session'] === $expected[4] && $launchCalls['browser'] === $counts['browser']);
        sl_replay($mode.' admission is consumed and cannot retry', $r, $out);
        if ($out['state'] === 'unknown') sl_ok($mode.' fresh form explicitly discloses previous uncertainty', sl_review($profile)['previous_unknown'] === true);
    }
    foreach ([['accepted','brand','interrupted'],['accepted','session','unknown'],['terminal','delivery','unknown']] as [$kind,$phase,$expected]) {
        $r = sl_review($profile); $counts = $launchCalls;
        sl_ok($kind.' '.$phase.' audit fails after the real row insertion', sl_audit_fault($kind,$phase,fn()=>sl_open($r)));
        $s = sl_state($r['admission_id']); $out = provider_stackcp_launch_summary($s);
        sl_ok($kind.' '.$phase.' audit rollback leaves consumed authentic incomplete history', $s['terminal'] === null && $out['state'] === $expected && $launchCalls['browser'] === $counts['browser'] && sl_ready());
        sl_replay($kind.' '.$phase.' interrupted admission never resumes a remote phase', $r, $out);
    }
    $r = sl_review($profile); $launchMode = 'delivery-throw'; $counts = $launchCalls;
    sl_deny('A failing browser callback cannot erase the committed one-use terminal',fn()=>sl_open($r),'Synthetic browser callback failed after receiving its one-use destination.'); $launchMode = 'ok';
    $out = provider_stackcp_launch_summary(sl_state($r['admission_id']));
    sl_ok('Possible browser delivery is retained without claiming a browser login', $out['state'] === 'handoff_attempted' && $launchCalls['browser'] === $counts['browser']+1);
    sl_replay('Throwing delivery cannot be attempted a second time',$r,$out);

    $stage = 'retained history';
    $event = one('SELECT * FROM '.table('provider_stackcp_launch_events').' ORDER BY ordinal LIMIT 1');
    foreach (['schema_version','prepared','prepared_proof','count','admission_count','head','checkpoint_proof'] as $key) {
        sl_damage('Missing launch setting '.$key.' refuses retained history',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_stackcp_launch_'.$key]));
    }
    sl_damage('Unknown launch setting refuses history',fn()=>save_setting('provider_stackcp_launch_unknown','1'));
    sl_damage('Unexpected launch audit refuses history',fn()=>query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (1,?,?,?)',['provider.stackcp_launch_unknown','Synthetic',gmdate('Y-m-d H:i:s')]));
    sl_damage('Changed indexed client resource cannot borrow an original HMAC',fn()=>query('UPDATE '.table('provider_stackcp_launch_events').' SET package_id=3502 WHERE id=?',[$event['id']]));
    sl_damage('Changed event proof refuses history',fn()=>query('UPDATE '.table('provider_stackcp_launch_events').' SET proof=? WHERE id=?',[str_repeat('0',64),$event['id']]));
    sl_damage('Recomputed unkeyed document hash and matching audit cannot replace the original proof',function()use($event):void {
        $doc=json_decode($event['document_json'],true,16,JSON_THROW_ON_ERROR); ++$doc['payload']['approval']['expires_epoch'];
        $json=provider_stackcp_launch_json($doc); $changed=$event; $changed['document_sha256']=hash('sha256',$json);
        query('UPDATE '.table('provider_stackcp_launch_events').' SET document_json=?,document_sha256=? WHERE id=?',[$json,$changed['document_sha256'],$event['id']]);
        query('UPDATE '.table('audit').' SET detail=? WHERE BINARY action=? AND BINARY detail=?',[provider_stackcp_launch_detail($changed),'provider.stackcp_launch_event',provider_stackcp_launch_detail($event)]);
    });
    sl_damage('Zero ordinal cannot hide a retained event',fn()=>query('UPDATE '.table('provider_stackcp_launch_events').' SET ordinal=0 WHERE id=?',[$event['id']]));
    sl_damage('Missing exact event audit refuses history',fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.stackcp_launch_event',provider_stackcp_launch_detail($event)]));
    sl_damage('Paired event and matching audit loss still contradicts retained count and head',function()use($event):void {
        query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.stackcp_launch_event',provider_stackcp_launch_detail($event)]);
        query('DELETE FROM '.table('provider_stackcp_launch_events').' WHERE id=?',[$event['id']]);
    });
    sl_damage('Missing original membership receipt refuses launch history',fn()=>query('DELETE FROM '.table('provider_stackcp_observations').' WHERE id=?',[$member['id']]));
    sl_damage('Missing original grants receipt refuses launch history',fn()=>query('DELETE FROM '.table('provider_stackcp_observations').' WHERE id=?',[$grant['id']]));
    $before = sl_digest(); $calls = sl_calls();
    db()->exec('RENAME TABLE '.table('provider_stackcp_launch_events').' TO '.table('stackcp_launch_events_retained'));
    try { sl_deny('A missing journal cannot be silently recreated over retained settings',fn()=>provider_stackcp_launch_prepare(SCLN_PASSWORD)); }
    finally { db()->exec('RENAME TABLE '.table('stackcp_launch_events_retained').' TO '.table('provider_stackcp_launch_events')); }
    sl_ok('Exact journal rename restoration preserves every row and callback count',sl_digest() === $before && sl_calls() === $calls && sl_ready());
    $keyPath=$private.'/provider-master.key'; $retainedKey=$private.'/provider-master.retained';
    if (file_exists($retainedKey)||is_link($retainedKey)||is_link($keyPath)||!is_file($keyPath)) throw new RuntimeException('Unexpected protected fixture key state.');
    $keyHash=hash_file('sha256',$keyPath); $before=sl_digest(); $calls=sl_calls();
    if (!rename($keyPath,$retainedKey)) throw new RuntimeException('Could not retain the exact fixture key.');
    try { sl_deny('Missing original protected key cannot regenerate launch history',fn()=>provider_stackcp_launch_prepare(SCLN_PASSWORD)); }
    finally { if (!rename($retainedKey,$keyPath)) throw new RuntimeException('Could not restore the exact fixture key.'); }
    sl_ok('Exact original protected key restores authenticated history without remote work',hash_file('sha256',$keyPath) === $keyHash && sl_digest() === $before && sl_calls() === $calls && sl_ready());
    $stage = 'current authority';
    sl_current_refuses('Current disabled profile is ineligible',$profile,fn()=>query('UPDATE '.table('provider_profiles').' SET enabled=0 WHERE id=?',[$profile]));
    sl_current_refuses('Current profile revision must match original approved source',$profile,fn()=>query('UPDATE '.table('provider_profiles').' SET revision=revision+1 WHERE id=?',[$profile]));
    sl_current_refuses('Current credential ciphertext must match original approved source',$profile,fn()=>query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',['synthetic-invalid-ciphertext',$profile]));
    sl_current_refuses('Inactive current primary client is ineligible',$profile,fn()=>query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1'));
    sl_current_refuses('Current client auth revision must match original assignment',$profile,fn()=>query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]));
    sl_current_refuses('Foreign current package assignment cannot borrow the original launch',$profile,fn()=>query('UPDATE '.table('provider_package_links').' SET client_id=2 WHERE profile_id=? AND package_id=3501',[$profile]));
    sl_current_refuses('Original assigning owner must remain enabled',$profile,fn()=>query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1'));
    sl_current_refuses('Original assigning owner auth cannot be replaced',$profile,fn()=>query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]));
    $before = sl_digest(); $calls = sl_calls();
    sl_ok('Foreign package without its own fresh membership and grants remains unavailable',provider_stackcp_launch_review($profile,3502)['available'] === false && sl_digest() === $before && sl_calls() === $calls);

    $stage = 'latest intent';
    sl_packages($profile);
    $captured = sl_review($profile); $readStatus = 401; $failed = sl_access($profile,B::GRANTS); $readStatus = 200;
    sl_ok('A genuine latest failed grant GET masks older successful grants', $failed['state'] !== 'complete' && provider_stackcp_launch_review($profile,3501)['available'] === false);
    $calls = sl_calls(); $before = sl_digest(); sl_deny('Captured launch cannot bypass a later failed read',fn()=>sl_open($captured));
    sl_ok('Failed latest-source launch refusal has no effects',sl_calls() === $calls && sl_digest() === $before);
    sl_ok('A new genuine complete grant GET restores current eligibility',sl_access($profile,B::GRANTS)['state'] === 'complete' && sl_review($profile)['available']);
    $captured = sl_review($profile); $beforeRead = sl_count('provider_stackcp_reads'); $beforeFinish = sl_count('provider_stackcp_observations');
    sl_ok('Real result audit rollback retains its newer durable grant intent',sl_read_fault(fn()=>sl_access($profile,B::GRANTS)) && sl_count('provider_stackcp_reads') === $beforeRead+1 && sl_count('provider_stackcp_observations') === $beforeFinish);
    sl_ok('An unfinished latest grant intent masks the previous complete receipt',provider_stackcp_launch_review($profile,3501)['available'] === false);
    sl_deny('Captured launch cannot bypass the unfinished grant intent',fn()=>sl_open($captured));
    sl_ok('Next real complete grant GET follows the retained unfinished intent',sl_access($profile,B::GRANTS)['state'] === 'complete' && sl_review($profile)['available']);
    $grantsBody='["package:3501","package:3502"]'; sl_access($profile,B::GRANTS); $grantsBody='["package:3501"]';
    sl_ok('Whole-user sibling access is not treated as selected-package restriction',provider_stackcp_launch_review($profile,3501)['available'] === false);
    sl_ok('Exact single-package read is independently required again',sl_access($profile,B::GRANTS)['state'] === 'complete' && sl_review($profile)['available']);
    $stage = 'late authorization';
    sl_packages($profile); sl_access($profile); sl_access($profile,B::GRANTS);
    $r = sl_review($profile); $counts = $launchCalls;
    $duringLookup = function()use($profile):void { sl_auth($profile,false); };
    $out = sl_open($r);
    sl_ok('Supported auth revocation after durable brand start stops before credential send', $out['state'] === 'not_issued' && $launchCalls === $counts);
    sl_replay('Revoked admission stays historical and cannot send',$r,$out);
    $restoredAuth = sl_auth($profile);
    sl_ok('Restoring key text cannot restore its previous approval or assignment', $restoredAuth['status'] === 'configured' && provider_stackcp_launch_review($profile,3501)['available'] === false);
    sl_assign($profile); sl_packages($profile); sl_access($profile); sl_access($profile,B::GRANTS);
    $r = sl_review($profile); $counts = $launchCalls;
    $duringLaunch = function(string $phase):void {
        if ($phase !== 'brand') throw new LogicException('Unexpected revocation phase.');
        $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');
        studio_client_save(1,$client['auth_version'],$client['name'],$client['email'],false);
    };
    $out = sl_open($r);
    sl_ok('Current client revocation after brand response blocks token and browser work', $out['state'] === 'not_issued' && $launchCalls['brand'] === $counts['brand']+1 && $launchCalls['token'] === $counts['token'] && $launchCalls['session'] === $counts['session'] && $launchCalls['browser'] === $counts['browser']);
    sl_replay('Current client revocation cannot erase or reissue the consumed admission',$r,$out);
    $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=1'); studio_client_save(1,$client['auth_version'],$client['name'],$client['email'],true);
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=3501',[$profile]);
    provider_inventory_unlink($link['id'],SCLN_PASSWORD); provider_inventory_link($profile,3501,1,1,SCLN_PASSWORD); sl_assign($profile); sl_packages($profile); sl_access($profile); sl_access($profile,B::GRANTS);
    $stage = 'mutex race';
    $r = sl_review($profile); $counts = sl_calls();
    $child = staff_locked(function()use($r):array {
        $child=sl_spawn($r); sl_blocked($child); sl_ok('Independent owner process is observed blocked on the actual owning mutex',true);
        sl_owner(3); staff_save(1,'Launch staff 1','launchstaff1@example.invalid','administrator',true,array_keys(staff_permissions()),SCLN_PASSWORD);
        return $child;
    });
    $workerResult = sl_join($child); sl_owner(3);
    sl_ok('Owner revoked while actually blocked is denied before any DNS phase or browser work', $workerResult === ['outcome'=>'refused','launch_dns'=>0,'launch_calls'=>['brand'=>0,'token'=>0,'session'=>0,'browser'=>0]] && sl_calls() === $counts);
    sl_ok('Original owner revocation preserves readable history but makes new launch unavailable',provider_stackcp_launch_history($profile,3501)['items'] !== [] && provider_stackcp_launch_review($profile,3501)['available'] === false && sl_ready());
    $stage = 'confidentiality';
    $retained=json_encode([all('SELECT * FROM '.table('provider_stackcp_launch_events')),all('SELECT * FROM '.table('audit')),provider_stackcp_launch_history($profile,3501)],JSON_THROW_ON_ERROR);
    $safe=true; foreach ([SCLN_GENERAL,SCLN_AUTH,SCLN_TOKEN,SCLN_SESSION,base64_encode(SCLN_GENERAL),base64_encode(SCLN_AUTH),base64_encode(SCLN_TOKEN),'PHPSESSID=','/login/sso?'] as $secret) $safe=$safe&&!str_contains($retained,$secret);
    sl_ok('Launch rows audits and safe history never retain keys tokens sessions or destination URLs',$safe);
    sl_ok('All prerequisite reads stayed within the real owner quota without rewriting timestamps',$readCalls === 20 && $readDns === 20 && $readCredentials === 20);
    sl_ok('Dedicated StackCP launch does not create a generic operation or grant ledger',!sl_exists('provider_operations') && !sl_exists('provider_operation_grants'));
} catch (Throwable $e) {
    // Public evidence is finite and sanitized. No exception text, SQL, credentials or URL is exported.
    $failedCheck=['name'=>'Unexpected native failure during '.$stage,'passed'=>false,'error_class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'call_line'=>$e->getTrace()[0]['line']??null];
    if(isset($GLOBALS['sl_review_failure']))$failedCheck['review']=$GLOBALS['sl_review_failure'];
    $checks[]=$failedCheck;
}
$passed = $checks !== [] && !array_filter($checks,fn($c)=>$c['passed']!==true);
$output = ['passed'=>$passed,'scope'=>'Actual disposable Linux SQL, owner wrappers, encrypted credentials, signed histories, blocked mutex process, real cooldowns and synthetic fixed transport/browser callback only. No provider-account request, real OAuth session, browser login or live StackCP effect.','checks'=>$checks];
$bytes=json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
$handle=fopen($report,'xb'); if (!$handle) exit(1);
$written=fwrite($handle,$bytes); fclose($handle); if ($written!==strlen($bytes)) exit(1);
echo ($passed?'PASS':'FAIL').': '.count($checks).' StackCP launch native checks'.PHP_EOL;
exit($passed?0:1);
