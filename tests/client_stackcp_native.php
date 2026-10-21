<?php
/** Disposable Linux SQL + actual client launch and original owner services; every remote response is synthetic. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Linux' || getenv('CWP_TEST_CLIENT_STACKCP') !== '1'
    || getenv('CWP_TEST_DATABASE') !== 'cwp_test' || (getenv('CWP_TEST_HOST') ?: '127.0.0.1') !== '127.0.0.1') exit(2);
$worker = count($argv) === 2 && $argv[1] === '--worker';
if (count($argv) !== ($worker ? 2 : 1)) exit(2);
$port = getenv('CWP_TEST_PORT') ?: '33306';
if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) exit(2);
$root = realpath(__DIR__.'/..'); if ($root === false) exit(2);
$runtime = $root.'/test-runtime'; $qa = $root.'/qa';
$private = $runtime.'/client-stackcp-private'; $report = $qa.'/client-stackcp-native-results.json';
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
require $root.'/site/app/providers/client_stackcp_launch.php';
use CajunWebPro\Foundation\Provider\{HttpTransport, ProviderCredential, TransportRequest, TransportResponse, TransportException};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead, InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIStackCpAccess\{StackCpAccessBinding as B, StackCpAccessObservation as O, StackCpAccessRead as AccessReader};
use CajunWebPro\Foundation\TwentyISupportHandoff\{StackCpLaunchTransport,ClientStackCpAccessTransport};
const CSLN_PREFIX = 'csln_';
const CSLN_PASSWORD = 'Synthetic StackCP launch owner passphrase 2026';
const CSLN_GENERAL = 'SYNTHETIC_STACKCP_LAUNCH_GENERAL_KEY';
const CSLN_AUTH = 'SYNTHETIC_STACKCP_LAUNCH_AUTH_KEY';
const CSLN_TOKEN = 'SYNTHETIC_STACKCP_LAUNCH_ACCESS_TOKEN';
const CSLN_SESSION = 'SYNTHETIC_STACKCP_LAUNCH_SESSION_ID';
$GLOBALS['cwp_config'] = ['site_url'=>'http://127.0.0.1:8091/stackcp-launch', 'storage'=>realpath($private),
    'key'=>str_repeat('synthetic-stackcp-launch-', 4), 'db'=>['host'=>'127.0.0.1', 'port'=>(int)$port,
    'name'=>'cwp_test', 'user'=>getenv('CWP_TEST_USER') ?: 'cwp_test',
    'pass'=>getenv('CWP_TEST_PASSWORD') ?: 'local-ci-only', 'prefix'=>CSLN_PREFIX]];
$_SERVER += ['REMOTE_ADDR'=>'127.0.0.1', 'HTTP_HOST'=>'127.0.0.1:8091', 'SCRIPT_NAME'=>'/index.php', 'REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT'] = $root.'/site'; start_session();
$checks = []; $stage = 'preflight'; $readCalls = 0; $readDns = 0; $readCredentials = 0;
$membershipBody = '[{"identity":"stack-user:351"}]'; $grantsBody = '["package:3501"]'; $readStatus = 200;
$launchCalls = ['membership'=>0,'grants'=>0,'brand'=>0,'token'=>0,'session'=>0,'browser'=>0]; $launchDns = 0;
$launchMode = 'ok'; $duringLookup = null; $duringLaunch = null; $activeReview = null;

function csn_ok(string $name, bool $passed): void {
    global $checks;
    foreach ($checks as $check) if ($check['name'] === $name) throw new LogicException('Duplicate native check name.');
    $checks[] = ['name'=>$name, 'passed'=>$passed];
    if (!$passed) throw new RuntimeException($name);
}

function csn_deny(string $name, callable $call, ?string $message = null): void {
    $caught = null;
    try { $call(); } catch (LogicException|RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        $caught = $e;
    }
    csn_ok($name, $caught !== null && ($message === null || $caught->getMessage() === $message));
}

function csn_owner(int $id = 1): void {
    $u = one('SELECT * FROM '.table('users').' WHERE id=?', [$id]);
    if (!$u) throw new LogicException('Missing synthetic owner.');
    $_SESSION = ['uid'=>$id, 'auth_version'=>$u['auth_version'], 'login_at'=>time(), 'last_activity'=>time()];
    $_GET = []; unset($GLOBALS['cwp_support'], $GLOBALS['cwp_settings']);
}

function csn_client(int $id = 1): void {
    $c = one('SELECT * FROM '.table('studio_clients').' WHERE id=?', [$id]);
    if (!$c) throw new LogicException('Missing synthetic client.');
    $_SESSION = ['client_id'=>$id, 'client_auth'=>$c['auth_version'], 'client_login'=>time(), 'client_last'=>time()];
    $_GET = []; unset($GLOBALS['cwp_support'], $GLOBALS['cwp_settings']);
}

function csn_mutex(): bool {
    $name = 'cwp-staff-'.substr(hash('sha256', 'cwp_test|'.CSLN_PREFIX), 0, 40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()', [$name])->fetchColumn() === 1;
}

function csn_locked(callable $call): mixed { return staff_locked(fn()=>staff_transaction($call)); }

function csn_exists(string $name): bool {
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=BINARY ?', [CSLN_PREFIX.$name]) !== null;
}

function csn_count(string $name): int { return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn(); }

function csn_digest(): string {
    $rows = [];
    foreach (all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME', [strlen(CSLN_PREFIX), CSLN_PREFIX]) as $row) {
        $name = $row['TABLE_NAME'];
        if (!preg_match('/\Acsln_[a-z0-9_]+\z/', $name)) throw new LogicException('Unexpected fixture table.');
        $data = all('SELECT * FROM `'.$name.'`');
        usort($data, fn($a, $b)=>strcmp(json_encode($a, JSON_THROW_ON_ERROR), json_encode($b, JSON_THROW_ON_ERROR)));
        $rows[$name] = $data;
    }
    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
}

function csn_calls(): array {
    global $readCalls, $readDns, $readCredentials, $launchCalls, $launchDns;
    return [$readCalls, $readDns, $readCredentials, $launchCalls, $launchDns];
}

function csn_cool(string $profile): void {
    if (db()->inTransaction() || csn_mutex()) throw new LogicException('Cooldown inside mutation boundary.');
    $epoch = (int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?', [$profile])->fetchColumn();
    if ($epoch < 0 || $epoch > time()) throw new RuntimeException('Unexpected future fixture read.');
    $deadline = microtime(true) + 32;
    while (time() < $epoch + 31) {
        if (microtime(true) > $deadline) throw new RuntimeException('Fixture cooldown bound exceeded.');
        usleep(100000);
    }
}

function csn_read_dns(): string {
    global $readDns;
    if (db()->inTransaction()) throw new LogicException('Read DNS inside transaction.');
    ++$readDns; return '8.8.8.8';
}

final class CsnReadTransport implements HttpTransport {
    public function __construct(private string $operation, private $authorize, private ?B $binding = null) {}
    public function send(TransportRequest $request): TransportResponse {
        global $readCalls, $membershipBody, $grantsBody, $readStatus;
        ($this->authorize)();
        if (db()->inTransaction() || !csn_mutex() || $request->method !== 'GET' || $request->body !== null
            || ($request->headers['Authorization'] ?? null) !== 'Bearer '.base64_encode(CSLN_GENERAL)) {
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

function csn_read(array $row, string $secret, string $address, callable $authorize, string $operation, ?B $stackcpAccessContext = null): InventorySnapshot|O {
    if ($secret !== CSLN_GENERAL || $address !== '8.8.8.8' || !in_array($operation, [InventoryRead::PACKAGES_LIST, B::MEMBERSHIP, B::GRANTS], true)) throw new LogicException('Unexpected synthetic reader input.');
    $credential = new ProviderCredential('twenty-i', $row['id'], function() use ($secret, $authorize): string {
        global $readCredentials; $authorize(); ++$readCredentials; return $secret;
    });
    $transport = new CsnReadTransport($operation, $authorize, $stackcpAccessContext);
    if ($stackcpAccessContext) return AccessReader::read($transport, $credential, $stackcpAccessContext, time());
    return InventoryReader::read($transport, $credential, $operation, 0, time());
}

function csn_packages(string $profile): array {
    csn_cool($profile); csn_owner();
    return provider_inventory_refresh_attempt($profile, (int)provider_settings_load($profile)['revision'], CSLN_PASSWORD, 'csn_read_dns', 'csn_read');
}

function csn_access(string $profile, string $operation = B::MEMBERSHIP, int $package = 3501): array {
    csn_cool($profile); csn_owner();
    $binding = staff_locked(fn()=>provider_stackcp_access_binding($profile, $package, $operation));
    return provider_stackcp_access_refresh($profile, $package, $operation, $binding->sha256, CSLN_PASSWORD, 'csn_read_dns', 'csn_read');
}

function csn_auth(string $profile, bool $enabled = true, string $key = CSLN_AUTH): array {
    return provider_stackcp_auth_change($profile, provider_stackcp_auth_status($profile)['review_sha256'], $enabled, $enabled ? $key : '', CSLN_PASSWORD);
}

function csn_assign(string $profile, int $package = 3501, string $identity = 'stack-user:351', bool $revoke = false): array {
    $link = one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=?', [$profile, $package]);
    if (!$link) throw new LogicException('Missing synthetic package assignment.');
    return provider_stackcp_assignment_change($profile, $package, provider_stackcp_assignment_status($profile, $package)['review_sha256'], $link['id'], $identity, $revoke, CSLN_PASSWORD);
}

function csn_spawn(array $review): array {
    $process = proc_open([PHP_BINARY, __FILE__, '--worker'], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException('Could not create native launch worker.');
    $json = json_encode($review, JSON_THROW_ON_ERROR);
    if (fwrite($pipes[0], $json) !== strlen($json)) throw new RuntimeException('Could not write bounded worker input.');
    fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
    return ['process'=>$process, 'pipes'=>$pipes, 'stdout'=>'', 'stderr'=>'', 'connection'=>null];
}

function csn_blocked(array &$child): void {
    if (!csn_mutex() || db()->inTransaction()) throw new LogicException('Contention observer requires mutex without transaction.');
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
            if ($row && stripos((string)$row['INFO'], 'GET_LOCK') !== false && stripos((string)$row['STATE'], 'lock') !== false && csn_mutex()) return;
        }
        if (!proc_get_status($child['process'])['running']) throw new RuntimeException('Worker left before observed mutex contention.');
        if (microtime(true) > $deadline) throw new RuntimeException('Actual blocked worker was not observed.');
        usleep(20000);
    } while (true);
}

function csn_join(array &$child): array {
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
        || $result['launch_dns'] < 0 || !is_array($result['launch_calls']) || array_keys($result['launch_calls']) !== ['membership','grants','brand','token','session','browser']) throw new RuntimeException('Invalid native worker result.');
    foreach ($result['launch_calls'] as $n) if (!is_int($n) || $n < 0) throw new RuntimeException('Invalid native worker call count.');
    return $result;
}

function csn_ready():bool {return staff_locked(fn()=>provider_client_stackcp_ready());}
function csn_state(string $id):array {return staff_locked(fn()=>provider_client_stackcp_project(provider_client_stackcp_events())['admissions'][$id]);}
function csn_phase(string $phase):void {
    global $activeReview;
    if (!csn_mutex()||db()->inTransaction()) throw new LogicException('Remote effect requires owning mutex and no transaction.');
    if (isset($activeReview['profile_id'])) $s=provider_stackcp_launch_project(provider_stackcp_launch_events())[$activeReview['admission_id']];
    else $s=provider_client_stackcp_project(provider_client_stackcp_events())['admissions'][$activeReview['admission_id']];
    $last=$s['events'][array_key_last($s['events'])];
    if ($last['phase']!==$phase||$last['kind']!==($phase==='delivery'?'terminal':'started')) throw new LogicException('Missing durable pre-effect phase.');
}
function csn_lookup(string $host):array {
    global $launchDns,$duringLookup;
    if (!csn_mutex()||db()->inTransaction()||!in_array($host,['api.20i.com','auth-api.20i.com','www.stackcp.com'],true)) throw new LogicException('Invalid client launch DNS boundary.');
    csn_phase((isset($GLOBALS['activeReview']['profile_id'])?provider_stackcp_launch_project(provider_stackcp_launch_events()):provider_client_stackcp_project(provider_client_stackcp_events())['admissions'])[$GLOBALS['activeReview']['admission_id']]['phase']);
    ++$launchDns;if ($duringLookup!==null) {$callback=$duringLookup;$duringLookup=null;$callback();}
    return [['type'=>'A','ip'=>'8.8.8.8']];
}
final class CsnLaunchTransport implements HttpTransport {
    public function __construct(private string $phase,private $authorize){}
    public function send(#[SensitiveParameter] TransportRequest $request):TransportResponse {
        global $launchCalls,$launchMode,$duringLaunch;
        ($this->authorize)();csn_phase($this->phase);
        $read=in_array($this->phase,['membership','grants'],true);
        $fixed=$read?new ClientStackCpAccessTransport($this->phase,3501,'stack-user:351','8.8.8.8',$this->authorize):new StackCpLaunchTransport($this->phase,'8.8.8.8',$this->authorize);
        (new ReflectionMethod($fixed,'validate'))->invoke($fixed,$request);
        $secret=match($this->phase){'membership','grants','brand'=>CSLN_GENERAL,'token'=>CSLN_AUTH,'session'=>CSLN_TOKEN};
        if ($request->headers['Authorization']!=='Bearer '.base64_encode($secret)) throw new LogicException('Wrong client phase credential.');
        if ($this->phase==='token'&&$request->body!=='{"grant_type":"client_credentials","scope":"stack-user:351"}') throw new LogicException('Wrong Stack user.');
        ++$launchCalls[$this->phase];
        if ($duringLaunch!==null) ($duringLaunch)($this->phase);
        if ($launchMode===$this->phase.'-timeout') throw new TransportException('timeout');
        if ($launchMode===$this->phase.'-auth') return new TransportResponse(401,[],'synthetic untrusted provider response');
        $body=match($this->phase){
            'membership'=>$launchMode==='membership-missing'?'[{"identity":"stack-user:999"}]':'[{"identity":"stack-user:351"}]',
            'grants'=>$launchMode==='grants-extra'?'["package:3501","package:3502"]':'["package:3501"]',
            'brand'=>$launchMode==='brand-custom'?'{"brandDomain":"custom.example.invalid"}':'{"brandDomain":null,"brandName":"Synthetic Studio"}',
            'token'=>json_encode(['access_token'=>CSLN_TOKEN,'token_type'=>'bearer','expires_in'=>300,'scope'=>'interactive'],JSON_THROW_ON_ERROR),
            'session'=>json_encode(['session_id'=>CSLN_SESSION],JSON_THROW_ON_ERROR),
        };
        return new TransportResponse(200,[],$body);
    }
}
function csn_factory(string $phase,string $address,callable $authorize):HttpTransport {if ($address!=='8.8.8.8') throw new LogicException('Wrong pinned address.');csn_phase($phase);return new CsnLaunchTransport($phase,$authorize);}
function csn_deliver(#[SensitiveParameter] string $url):void {
    global $launchCalls,$launchMode;csn_phase('delivery');
    if ($url!=='https://www.stackcp.com/login/sso?PHPSESSID='.rawurlencode(CSLN_SESSION)) throw new LogicException('Unexpected client browser destination.');
    ++$launchCalls['browser'];if ($launchMode==='delivery-throw') throw new RuntimeException('Synthetic browser interruption.');
}
function csn_open(array $review,bool $confirmed=true):array {
    global $activeReview;$activeReview=$review;
    return provider_client_stackcp_open($review['admission_id'],$review['permission_id'],1,$review['expires_epoch'],$review['review_proof'],$confirmed,'csn_deliver','csn_lookup','csn_factory','csn_factory');
}
/** Real rolling admission cooldown, outside the mutex; original timestamps remain untouched. */
function csn_admission_cool(bool $emptyWindow=false):void {
    if (csn_mutex()||db()->inTransaction()) throw new LogicException('Admission cooldown inside mutation boundary.');
    $deadline=microtime(true)+62;
    do {
        $times=staff_locked(function():array {$events=provider_client_stackcp_events();$times=[];foreach (provider_client_stackcp_project($events)['admissions'] as $state) if ($state['admission']['client_id']===1&&$state['admission']['recorded_epoch']>time()-60) $times[]=$state['admission']['recorded_epoch'];return $times;});
        if (($emptyWindow?$times===[]:count($times)<5)) return;
        if (microtime(true)>$deadline) throw new RuntimeException('Real client admission cooldown exceeded.');
        sleep(1);
    }while(true);
}
function csn_review(string $permissionId):array {csn_admission_cool();$r=provider_client_stackcp_review($permissionId,1);if (!$r['available']) throw new LogicException('Client permission is not current.');return $r;}
function csn_enable(string $profile,bool $enabled=true):array {
    csn_owner();$r=provider_client_stackcp_permission_review($profile,3501);
    return provider_client_stackcp_permission_change($profile,3501,$r[$enabled?'enable_review':'revoke_review'],$enabled,true,CSLN_PASSWORD);
}
function csn_replay(string $name,array $r,array $expected):void {$before=csn_digest();$calls=csn_calls();csn_ok($name,csn_open($r)===$expected&&csn_digest()===$before&&csn_calls()===$calls);}
function csn_damage(string $name,callable $mutate):void {
    $before=csn_digest();$calls=csn_calls();staff_locked(function()use($name,$mutate):void {db()->beginTransaction();try {$mutate();csn_deny($name,fn()=>provider_client_stackcp_ready());}finally{db()->rollBack();unset($GLOBALS['cwp_settings']);}});
    csn_ok($name.' exact rows restored with no effects',csn_ready()&&csn_digest()===$before&&csn_calls()===$calls);
}
function csn_fault(string $kind,string $phase,callable $call):bool {
    if (!in_array([$kind,$phase],[['prepared','admission'],['admitted','admission'],['accepted','membership'],['accepted','session'],['terminal','delivery']],true)) throw new LogicException('Unknown audit fault.');
    $trigger='csln_event_fault';if (one('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND TRIGGER_NAME=?',[$trigger])) throw new LogicException('Existing trigger.');
    $before=csn_count('provider_client_stackcp_events');$prepared=$kind==='prepared';
    $action=$prepared?'provider.client_stackcp_prepared':'provider.client_stackcp_event';
    $condition=$prepared?'1=1':"EXISTS (SELECT 1 FROM ".table('provider_client_stackcp_events')." WHERE BINARY kind='".$kind."' AND BINARY phase='".$phase."' AND BINARY NEW.detail=BINARY CONCAT('Client StackCP event ',id,' ',document_sha256,' ',proof))";
    db()->exec('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF BINARY NEW.action='".$action."' AND ".$condition." THEN SET @csln_fault_hits=COALESCE(@csln_fault_hits,0)+1; SET @csln_fault_rows=(SELECT COUNT(*) FROM ".table('provider_client_stackcp_events')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic client launch audit fault'; END IF; END");
    query('SET @csln_fault_hits=0,@csln_fault_rows=0');$caught=false;
    try {$call();}catch(PDOException $e){$w=one('SELECT @csln_fault_hits AS hits,@csln_fault_rows AS rows_seen');$caught=$e->getCode()==='45000'&&str_contains($e->getMessage(),'Synthetic client launch audit fault')&&(int)$w['hits']===1&&($prepared?(int)$w['rows_seen']===0:(int)$w['rows_seen']>$before);}
    finally {db()->exec('DROP TRIGGER '.$trigger);}return $caught;
}
function csn_current_probe(string $name,string $permissionId,callable $mutate):void {
    $before=csn_digest();$calls=csn_calls();staff_locked(function()use($name,$permissionId,$mutate):void {db()->beginTransaction();try {$p=provider_client_stackcp_permission($permissionId);$mutate();csn_ok($name,!provider_client_stackcp_current($p));}finally{db()->rollBack();unset($GLOBALS['cwp_settings']);}});
    csn_client();csn_ok($name.' restored current authority and unchanged history',csn_digest()===$before&&csn_calls()===$calls&&csn_review($permissionId)['available']);
}
if ($worker) {
    try {
        $raw=stream_get_contents(STDIN,8193);if ($raw===false||strlen($raw)>8192) exit(2);$r=json_decode($raw,true,6,JSON_THROW_ON_ERROR);
        if (!is_array($r)||!isset($r['admission_id'],$r['permission_id'],$r['expires_epoch'],$r['review_proof'])) exit(2);
        csn_client();echo json_encode(['ready'=>true,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR)."\n";fflush(STDOUT);
        $outcome='completed';try {csn_open($r);}catch(RuntimeException $e){if ($e instanceof PDOException) throw $e;$outcome='refused';}
        echo json_encode(['outcome'=>$outcome,'launch_dns'=>$launchDns,'launch_calls'=>$launchCalls],JSON_THROW_ON_ERROR);exit(0);
    }catch(Throwable){exit(1);}
}
try {
    csn_ok('Separate disposable namespace starts empty',(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(CSLN_PREFIX),CSLN_PREFIX])->fetchColumn()===0);
    foreach (schema_statements(CSLN_PREFIX) as $name=>$sql) if (!str_starts_with($name,'provider_')) db()->exec($sql);
    foreach (default_settings() as $name=>$value) save_setting($name,$value);save_setting('studio_schema_version','2');
    foreach ([1=>'owner',2=>'administrator',3=>'owner'] as $id=>$role) {
        query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Client launch staff '.$id,'clientlaunchstaff'.$id.'@example.invalid',password_hash(CSLN_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
        if ($id===1) staff_seed_owner(1);else query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (?,?,1,?)',[$id,$role,json_encode(array_keys(staff_permissions()),JSON_THROW_ON_ERROR)]);
    }
    csn_owner();csn_ok('Actual client writers establish separate primary clients',studio_client_save(0,'','Client launch one','clientlaunch1@example.invalid',true)===1&&studio_client_save(0,'','Client launch two','clientlaunch2@example.invalid',true)===2);
    provider_settings_migrate(CSLN_PASSWORD);provider_inventory_migrate(CSLN_PASSWORD);$profile=provider_settings_save('',0,'Synthetic client StackCP',true,CSLN_GENERAL,CSLN_PASSWORD);
    provider_stackcp_auth_prepare(CSLN_PASSWORD);csn_auth($profile);provider_stackcp_assignment_prepare(CSLN_PASSWORD);
    csn_ok('Actual initial package read is complete',csn_packages($profile)['state']==='complete');
    provider_inventory_link($profile,3501,1,1,CSLN_PASSWORD);provider_inventory_link($profile,3502,2,1,CSLN_PASSWORD);csn_assign($profile);csn_assign($profile,3502,'stack-user:352');provider_stackcp_access_prepare(CSLN_PASSWORD);
    csn_ok('Actual post-assignment inventory and owner access reads are complete',csn_packages($profile)['state']==='complete'&&csn_access($profile)['state']==='complete'&&csn_access($profile,B::GRANTS)['state']==='complete');
    provider_stackcp_launch_prepare(CSLN_PASSWORD);$activeReview=provider_stackcp_launch_review($profile,3501);
    $ownerResult=provider_stackcp_launch_open($activeReview['admission_id'],$profile,3501,$activeReview['expires_epoch'],$activeReview['review_proof'],true,CSLN_PASSWORD,'csn_deliver','csn_lookup','csn_factory');
    csn_ok('Original owner launch works before client permission exists',$ownerResult['state']==='handoff_attempted');
    $ownerRows=all('SELECT * FROM '.table('provider_stackcp_launch_events').' ORDER BY ordinal');$ownerAudits=all("SELECT * FROM ".table('audit')." WHERE action IN ('provider.stackcp_launch_prepared','provider.stackcp_launch_event') ORDER BY id");
    $stage='client preparation';csn_client();$before=csn_digest();csn_ok('Unprepared client status is local and read only',!provider_client_stackcp_status(1)&&csn_digest()===$before);
    csn_deny('Client cannot prepare permission storage',fn()=>provider_client_stackcp_prepare(CSLN_PASSWORD));csn_owner(2);csn_deny('Ordinary administrator cannot prepare client launch',fn()=>provider_client_stackcp_prepare(CSLN_PASSWORD));
    csn_owner();csn_deny('Client launch preparation requires current owner password',fn()=>provider_client_stackcp_prepare('wrong'));
    // Exact empty DDL represents an interrupted explicit setup, never seeded business history.
    foreach (provider_client_stackcp_schema(CSLN_PREFIX) as $sql) db()->exec($sql);
    db()->exec('ALTER TABLE '.table('provider_client_stackcp_events').' ADD COLUMN unexpected INT NULL');
    try {csn_deny('Malformed empty interrupted schema is not adopted',fn()=>provider_client_stackcp_prepare(CSLN_PASSWORD));}
    finally {db()->exec('ALTER TABLE '.table('provider_client_stackcp_events').' DROP COLUMN unexpected');}
    db()->exec('CREATE TABLE '.table('provider_client_stackcp_unexpected').' (id INT NOT NULL) ENGINE=InnoDB');
    try {csn_deny('Unknown empty namespace table cannot be adopted',fn()=>provider_client_stackcp_prepare(CSLN_PASSWORD));}
    finally {db()->exec('DROP TABLE '.table('provider_client_stackcp_unexpected'));}
    $before=csn_digest();csn_ok('Real setup audit failure rolls back every marker and checkpoint',csn_fault('prepared','admission',fn()=>provider_client_stackcp_prepare(CSLN_PASSWORD))&&!csn_ready()&&csn_digest()===$before);
    provider_client_stackcp_prepare(CSLN_PASSWORD);$before=csn_digest();provider_client_stackcp_prepare(CSLN_PASSWORD);csn_ok('Exact empty interruption resumes once and preparation is idempotent',csn_ready()&&csn_digest()===$before);
    $r=provider_client_stackcp_permission_review($profile,3501);csn_deny('Owner delegation requires explicit whole-user confirmation',fn()=>provider_client_stackcp_permission_change($profile,3501,$r['enable_review'],true,false,CSLN_PASSWORD));
    csn_deny('Owner delegation requires the current password',fn()=>provider_client_stackcp_permission_change($profile,3501,$r['enable_review'],true,true,'wrong'));
    $permission=csn_enable($profile);$permissionId=$permission['permission_id'];csn_client();$review=csn_review($permissionId);$otherWindow=csn_review($permissionId);$before=csn_digest();$calls=csn_calls();
    csn_ok('Client review history and permission list are read only',csn_review($permissionId)['available']&&provider_client_stackcp_history($permissionId,1)['items']===[]&&count(provider_client_stackcp_client_snapshot(1)['items'])===1&&csn_digest()===$before&&csn_calls()===$calls);
    csn_deny('Unchecked client consent refuses before admission',fn()=>csn_open($review,false));
    foreach (['admission_id'=>str_repeat('f',32),'permission_id'=>str_repeat('e',32),'expires_epoch'=>time()-1,'review_proof'=>str_repeat('0',64)] as $field=>$value) {$bad=$review;$bad[$field]=$value;csn_deny('Signed client form rejects changed '.$field,fn()=>csn_open($bad));}
    foreach (['contact_id','contact_auth','uid'] as $key) {$_SESSION[$key]=null;csn_deny('Raw '.$key.' presence refuses before normalization',fn()=>csn_open($review));csn_client();}
    $_GET['support']='synthetic-support';csn_deny('Support provenance cannot open client StackCP',fn()=>csn_open($review));csn_client(2);csn_deny('Other primary client cannot use original permission',fn()=>csn_open($review));csn_owner();csn_deny('Owner session cannot impersonate the client',fn()=>csn_open($review));csn_client();
    csn_ok('All pre-admission denials preserve rows and effects',csn_digest()===$before&&csn_calls()===$calls);
    csn_ok('Real admitted audit failure rolls back event checkpoint and audit',csn_fault('admitted','admission',fn()=>csn_open($review))&&csn_digest()===$before&&csn_calls()===$calls);
    $stage='client one use';$counts=$launchCalls;$result=csn_open($review);$state=csn_state($review['admission_id']);
    csn_ok('One client consent performs exactly two reads three launch phases and one delivery',$result['state']==='handoff_attempted'&&array_map(fn($n,$old)=>$n-$old,$launchCalls,$counts)===[1,1,1,1,1,1]);
    csn_ok('Twelve real events retain actual client actor and read witnesses',count($state['events'])===12&&count(array_filter($state['events'],fn($e)=>$e['actor']['kind']==='client'&&$e['actor']['id']===1))===12);
    csn_ok('Client events use client index and audit user zero',(int)query('SELECT COUNT(*) FROM '.table('provider_client_stackcp_events').' WHERE admission_id=? AND actor_client_id=1 AND actor_staff_id IS NULL',[$review['admission_id']])->fetchColumn()===12&&(int)query("SELECT COUNT(*) FROM ".table('audit')." a JOIN ".table('provider_client_stackcp_events')." e ON BINARY a.detail=BINARY CONCAT('Client StackCP event ',e.id,' ',e.document_sha256,' ',e.proof) WHERE e.admission_id=? AND a.user_id=0",[$review['admission_id']])->fetchColumn()===12);
    csn_replay('Completed client admission never resends or redelivers',$review,$result);csn_deny('Other-window proof must acknowledge new prior outcome',fn()=>csn_open($otherWindow));
    csn_ok('Original owner history remains exact after client launches',all('SELECT * FROM '.table('provider_stackcp_launch_events').' ORDER BY ordinal')===$ownerRows&&all("SELECT * FROM ".table('audit')." WHERE action IN ('provider.stackcp_launch_prepared','provider.stackcp_launch_event') ORDER BY id")===$ownerAudits);
    $stage='stable delegation';$permissionBefore=staff_locked(fn()=>provider_client_stackcp_permission($permissionId));csn_packages($profile);csn_client();
    csn_ok('Normal inventory refresh preserves explicit client permission',csn_review($permissionId)['available']&&staff_locked(fn()=>provider_client_stackcp_permission($permissionId))===$permissionBefore);
    $stage='phase refusal';
    foreach (['membership-missing'=>['not_issued','membership'],'grants-extra'=>['not_issued','grants'],'grants-auth'=>['not_issued','grants'],'brand-custom'=>['not_issued','brand'],'token-timeout'=>['unknown','token'],'session-timeout'=>['unknown','session']] as $mode=>$expected) {
        $r=csn_review($permissionId);$launchMode=$mode;$b=$launchCalls['browser'];$out=csn_open($r);$launchMode='ok';csn_ok($mode.' retains precise local uncertainty',$out['state']===$expected[0]&&$out['phase']===$expected[1]&&$launchCalls['browser']===$b);csn_replay($mode.' cannot retry the consumed admission',$r,$out);
        if ($out['state']==='unknown') csn_ok($mode.' new form discloses prior uncertainty',csn_review($permissionId)['previous_unknown']);
    }
    foreach ([['accepted','membership','interrupted'],['accepted','session','unknown'],['terminal','delivery','unknown']] as [$kind,$phase,$expected]) {
        $r=csn_review($permissionId);$b=$launchCalls['browser'];csn_ok($kind.' '.$phase.' real inserted event audit rollback',csn_fault($kind,$phase,fn()=>csn_open($r)));
        $out=provider_client_stackcp_launch_summary(csn_state($r['admission_id']));csn_ok($kind.' '.$phase.' is consumed without browser work',$out['state']===$expected&&$launchCalls['browser']===$b);csn_replay($kind.' '.$phase.' never resumes after interruption',$r,$out);
    }
    $r=csn_review($permissionId);$launchMode='delivery-throw';csn_deny('Throwing browser callback follows durable terminal',fn()=>csn_open($r));$launchMode='ok';csn_replay('Throwing browser callback cannot redeliver',$r,provider_client_stackcp_launch_summary(csn_state($r['admission_id'])));
    $stage='admission rate';$rateRows=[];$launchMode='membership-missing';
    csn_admission_cool(true);
    $r=provider_client_stackcp_review($permissionId,1);$rateRows[]=[$r,csn_open($r)];
    // A single locked fill writes the remaining real admitted events through the
    // same append seam opens use, so a slow shared runner cannot roll the first
    // admission out of the 60-second window before the limit is exercised.
    staff_locked(function()use($permissionId):void {
        staff_transaction(function()use($permissionId):void {
            $actor=provider_client_stackcp_launch_actor(1);
            for ($i=0;$i<4;++$i) {
                $events=provider_client_stackcp_events();$states=provider_client_stackcp_project($events)['admissions'];
                $permission=provider_client_stackcp_permission($permissionId,$events);
                $id=bin2hex(random_bytes(16));$expires=time()+300;
                $approval=provider_client_stackcp_launch_approval($id,$expires,$permission,$actor,provider_client_stackcp_launch_previous($states,$permission));
                provider_client_stackcp_append('admitted','admission',['approval'=>$approval,'review_proof'=>provider_client_stackcp_hmac('review',provider_client_stackcp_json($approval))],['id'=>$id,'permission_id'=>$permissionId,'actor'=>$actor]);
            }
        });
    });
    $r=provider_client_stackcp_review($permissionId,1);$before=csn_digest();$counts=csn_calls();
    csn_deny('Six real failed admissions within the window are limited before effect',fn()=>csn_open($r));
    csn_ok('Rate refusal leaves original journal and all transport counters unchanged',csn_digest()===$before&&csn_calls()===$counts);
    csn_replay('Exact replay at exhausted capacity remains inert',$rateRows[0][0],$rateRows[0][1]);
    $retained=all('SELECT * FROM '.table('provider_client_stackcp_events').' ORDER BY ordinal');$waitStarted=microtime(true);csn_admission_cool(true);
    csn_ok('Genuine elapsed admission window preserves every original event',microtime(true)-$waitStarted>=1&&all('SELECT * FROM '.table('provider_client_stackcp_events').' ORDER BY ordinal')===$retained);
    $r=provider_client_stackcp_review($permissionId,1);csn_ok('Actual new admission is allowed after genuine window expiry',csn_open($r)['state']==='not_issued');$launchMode='ok';
    $stage='current authority';
    csn_current_probe('Disabled original client revokes permission',$permissionId,fn()=>query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1'));
    csn_current_probe('Rotated original client auth revokes permission',$permissionId,fn()=>query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]));
    csn_current_probe('Disabled delegating owner revokes permission',$permissionId,fn()=>query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1'));
    csn_current_probe('Rotated delegating owner auth revokes permission',$permissionId,fn()=>query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]));
    csn_current_probe('Current general key revision change revokes permission',$permissionId,fn()=>query('UPDATE '.table('provider_profiles').' SET revision=revision+1 WHERE id=?',[$profile]));
    // Actual DB authority changes at each outbound boundary; restore only fixture client auth afterwards.
    foreach (['membership','grants','brand','token','session'] as $phase) {
        $r=csn_review($permissionId);$old=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=1')['auth_version'];$before=$launchCalls;
        $duringLaunch=static function(string $at)use($phase):void {if ($at===$phase) query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);};
        try {$out=csn_open($r);}finally {$duringLaunch=null;query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[$old]);csn_client();}
        csn_ok('Client auth revoked after '.$phase.' prevents next phase and browser',$out['state']===(in_array($phase,['token','session'],true)?'unknown':'not_issued')&&$out['phase']===$phase&&$launchCalls['browser']===$before['browser']);
        csn_replay('Revoked '.$phase.' admission remains consumed after fixture restoration',$r,$out);
    }
    $r=csn_review($permissionId);$old=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=1')['auth_version'];$counts=$launchCalls;
    $duringLookup=static fn()=>query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);
    try {$out=csn_open($r);}finally {query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[$old]);csn_client();}
    csn_ok('Client auth changed during DNS prevents any credential send',$out['state']==='not_issued'&&$launchCalls===$counts);
    $r=csn_review($permissionId);$old=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=1')['auth_version'];$counts=$launchCalls;
    $trigger='csln_terminal_revoke';if (one('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND TRIGGER_NAME=?',[$trigger])) throw new LogicException('Existing final guard trigger.');
    db()->exec('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF BINARY NEW.action='provider.client_stackcp_event' AND EXISTS (SELECT 1 FROM ".table('provider_client_stackcp_events')." WHERE BINARY kind='terminal' AND BINARY phase='delivery' AND BINARY NEW.detail=BINARY CONCAT('Client StackCP event ',id,' ',document_sha256,' ',proof)) THEN UPDATE ".table('studio_clients')." SET auth_version='".bin2hex(random_bytes(16))."' WHERE id=1; SET @csln_delivery_revoke=1; END IF; END");
    query('SET @csln_delivery_revoke=0');
    try {csn_deny('Authority change during terminal commit prevents browser delivery',fn()=>csn_open($r));csn_ok('Real terminal trigger reached current client change',(int)query('SELECT @csln_delivery_revoke')->fetchColumn()===1&&$launchCalls['browser']===$counts['browser']);}
    finally {db()->exec('DROP TRIGGER '.$trigger);query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[$old]);csn_client();}
    csn_replay('Terminal guard failure remains consumed after exact fixture restoration',$r,provider_client_stackcp_launch_summary(csn_state($r['admission_id'])));
    $stage='retained integrity';$event=one('SELECT * FROM '.table('provider_client_stackcp_events').' ORDER BY ordinal LIMIT 1');
    foreach (['schema_version','prepared','prepared_proof','count','permission_count','admission_count','head','checkpoint_proof'] as $key) csn_damage('Missing client journal '.$key.' fails closed',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_client_stackcp_'.$key]));
    csn_damage('Unknown client journal setting fails closed',fn()=>save_setting('provider_client_stackcp_unknown','1'));
    csn_damage('Client journal event HMAC tamper fails closed',fn()=>query('UPDATE '.table('provider_client_stackcp_events').' SET proof=? WHERE id=?',[str_repeat('0',64),$event['id']]));
    csn_damage('Client event index tamper fails closed',fn()=>query('UPDATE '.table('provider_client_stackcp_events').' SET client_id=2 WHERE id=?',[$event['id']]));
    csn_damage('Missing event audit fails closed',fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.client_stackcp_event',provider_client_stackcp_detail($event)]));
    csn_damage('Paired original permission and audit loss contradict retained head',function()use($event):void {query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.client_stackcp_event',provider_client_stackcp_detail($event)]);query('DELETE FROM '.table('provider_client_stackcp_events').' WHERE id=?',[$event['id']]);});
    $before=csn_digest();db()->exec('RENAME TABLE '.table('provider_client_stackcp_events').' TO '.table('client_stackcp_retained'));
    try {csn_owner();csn_deny('Missing journal is not silently recreated',fn()=>provider_client_stackcp_prepare(CSLN_PASSWORD));}finally{db()->exec('RENAME TABLE '.table('client_stackcp_retained').' TO '.table('provider_client_stackcp_events'));csn_client();}
    csn_ok('Exact retained journal restores without repair',csn_ready()&&csn_digest()===$before);
    $stage='real revocation and race';csn_packages($profile);csn_access($profile);csn_access($profile,B::GRANTS);csn_client();$r=csn_review($permissionId);$counts=csn_calls();
    $child=staff_locked(function()use($r,$profile):array {$child=csn_spawn($r);csn_blocked($child);csn_ok('Actual client process is observed waiting on owning mutex',true);csn_enable($profile,false);return $child;});
    $workerResult=csn_join($child);csn_client();csn_ok('Owner revocation while client is blocked prevents all effects',$workerResult['outcome']==='refused'&&$workerResult['launch_dns']===0&&array_sum($workerResult['launch_calls'])===0&&csn_calls()===$counts);
    csn_ok('Revoked permission cannot authorize replay',!provider_client_stackcp_review($permissionId,1)['available']);csn_deny('Original replay requires still-current delegation',fn()=>csn_open($review));
    // Refresh and explicitly delegate again: old permission identity can never be reused.
    csn_access($profile);csn_access($profile,B::GRANTS);$new=csn_enable($profile);csn_client();csn_ok('New explicit delegation gets new identity',$new['permission_id']!==$permissionId&&csn_review($new['permission_id'])['available']);
    csn_ok('New explicit permission retains prior outcome and permits a new one-use admission',csn_open(csn_review($new['permission_id']))['state']==='handoff_attempted');
    csn_owner();csn_auth($profile,false);csn_client();csn_ok('Actual auth configuration revocation denies the existing delegation',!provider_client_stackcp_review($new['permission_id'],1)['available']);
    csn_owner();csn_auth($profile);csn_client();csn_ok('Restoring identical key text cannot revive original delegation',!provider_client_stackcp_review($new['permission_id'],1)['available']);
    csn_owner();csn_assign($profile);csn_packages($profile);csn_access($profile);csn_access($profile,B::GRANTS);$new=csn_enable($profile);csn_client();csn_ok('Fresh assignment and explicit new owner permission restore eligible launch',csn_review($new['permission_id'])['available']);
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=3501',[$profile]);csn_owner();provider_inventory_unlink($link['id'],CSLN_PASSWORD);provider_inventory_link($profile,3501,2,1,CSLN_PASSWORD);csn_client();
    csn_ok('Actual unlink and reassignment revoke original client launch',!provider_client_stackcp_review($new['permission_id'],1)['available']);
    csn_client(2);csn_deny('New assignee cannot borrow prior client permission',fn()=>provider_client_stackcp_review($new['permission_id'],2));csn_client();
    $stage='confidentiality';$retained=json_encode([all('SELECT * FROM '.table('provider_client_stackcp_events')),all('SELECT * FROM '.table('audit')),provider_client_stackcp_history($permissionId,1)],JSON_THROW_ON_ERROR);$safe=true;
    foreach ([CSLN_GENERAL,CSLN_AUTH,CSLN_TOKEN,CSLN_SESSION,base64_encode(CSLN_GENERAL),base64_encode(CSLN_AUTH),base64_encode(CSLN_TOKEN),'PHPSESSID=','/login/sso?'] as $secret) $safe=$safe&&!str_contains($retained,$secret);
    csn_ok('Retained client rows audits and summaries contain no transient secrets or URL',$safe);
    csn_ok('No generic provider operation or grant ledger is used',!csn_exists('provider_operations')&&!csn_exists('provider_operation_grants'));
    csn_ok('Owner observations use genuine cooldowns without timestamp edits',$readCalls===10&&$readDns===10&&$readCredentials===10);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected native failure during '.$stage,'passed'=>false,'error_class'=>get_class($e),'line'=>$e->getLine()];}
$passed=$checks!==[]&&!array_filter($checks,fn($c)=>$c['passed']!==true);
$out=['passed'=>$passed,'scope'=>'Actual disposable Linux SQL, real owner permission/client sessions, immutable phase journals, current DB authority and blocked mutex worker; synthetic fixed reads/token/session and callback only. No provider account or real StackCP login.','checks'=>$checks];
$bytes=json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";$handle=fopen($report,'xb');if (!$handle) exit(1);$written=fwrite($handle,$bytes);fclose($handle);if ($written!==strlen($bytes)) exit(1);
echo ($passed?'PASS':'FAIL').': '.count($checks)." client StackCP native checks\n";exit($passed?0:1);
