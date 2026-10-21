<?php
/** Disposable Linux SQL + actual support launch and original owner/client services; every remote response is synthetic. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Linux' || getenv('CWP_TEST_SUPPORT_STACKCP') !== '1'
    || getenv('CWP_TEST_DATABASE') !== 'cwp_test' || (getenv('CWP_TEST_HOST') ?: '127.0.0.1') !== '127.0.0.1') exit(2);
$worker = count($argv) === 2 && $argv[1] === '--worker';
if (count($argv) !== ($worker ? 2 : 1) || !function_exists('posix_geteuid') || posix_geteuid()===0) exit(2);
$port = getenv('CWP_TEST_PORT') ?: '33306';
if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) exit(2);
$root = realpath(__DIR__.'/..'); if ($root === false) exit(2);
$runtime = $root.'/test-runtime'; $qa = $root.'/qa';
$private = $runtime.'/support-stackcp-private'; $report = $qa.'/support-stackcp-native-results.json';
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
require $root.'/site/app/providers/support_stackcp_launch.php';
require $root.'/site/app/providers/client_stackcp_launch.php';
require_once $root.'/site/app/support/sessions.php';
use CajunWebPro\Foundation\Provider\{HttpTransport, ProviderCredential, TransportRequest, TransportResponse, TransportException};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead, InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
use CajunWebPro\Foundation\TwentyIStackCpAccess\{StackCpAccessBinding as B, StackCpAccessObservation as O, StackCpAccessRead as AccessReader};
use CajunWebPro\Foundation\TwentyISupportHandoff\{StackCpLaunchTransport,ClientStackCpAccessTransport};
const SSLN_PREFIX = 'ssln_';
const SSLN_PASSWORD = 'Synthetic StackCP launch owner passphrase 2026';
const SSLN_GENERAL = 'SYNTHETIC_STACKCP_LAUNCH_GENERAL_KEY';
const SSLN_AUTH = 'SYNTHETIC_STACKCP_LAUNCH_AUTH_KEY';
const SSLN_TOKEN = 'SYNTHETIC_STACKCP_LAUNCH_ACCESS_TOKEN';
const SSLN_SESSION = 'SYNTHETIC_STACKCP_LAUNCH_SESSION_ID';
$GLOBALS['cwp_config'] = ['site_url'=>'http://127.0.0.1:8091/stackcp-launch', 'storage'=>realpath($private),
    'key'=>str_repeat('synthetic-stackcp-launch-', 4), 'db'=>['host'=>'127.0.0.1', 'port'=>(int)$port,
    'name'=>'cwp_test', 'user'=>getenv('CWP_TEST_USER') ?: 'cwp_test',
    'pass'=>getenv('CWP_TEST_PASSWORD') ?: 'local-ci-only', 'prefix'=>SSLN_PREFIX]];
$_SERVER += ['REMOTE_ADDR'=>'127.0.0.1', 'HTTP_HOST'=>'127.0.0.1:8091', 'SCRIPT_NAME'=>'/index.php', 'REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT'] = $root.'/site'; start_session();
$checks = []; $stage = 'preflight'; $readCalls = 0; $readDns = 0; $readCredentials = 0;
$membershipBody = '[{"identity":"stack-user:351"}]'; $grantsBody = '["package:3501"]'; $readStatus = 200;
$launchCalls = ['membership'=>0,'grants'=>0,'brand'=>0,'token'=>0,'session'=>0,'browser'=>0]; $launchDns = 0;
$supportId='';$supportBinding='';$launchMode = 'ok'; $duringLookup = null; $duringLaunch = null; $activeReview = null;

function ssn_ok(string $name, bool $passed): void {
    global $checks;
    foreach ($checks as $check) if ($check['name'] === $name) throw new LogicException('Duplicate native check name.');
    $checks[] = ['name'=>$name, 'passed'=>$passed];
    if (!$passed) throw new RuntimeException($name);
}

function ssn_deny(string $name, callable $call, ?string $message = null): void {
    $caught = null;
    try { $call(); } catch (LogicException|RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        $caught = $e;
    }
    ssn_ok($name, $caught !== null && ($message === null || $caught->getMessage() === $message));
}

function ssn_owner(int $id = 1): void {
    $u = one('SELECT * FROM '.table('users').' WHERE id=?', [$id]);
    if (!$u) throw new LogicException('Missing synthetic owner.');
    $_SESSION = ['uid'=>$id, 'auth_version'=>$u['auth_version'], 'login_at'=>time(), 'last_activity'=>time()];
    $_GET = []; unset($GLOBALS['cwp_support'], $GLOBALS['cwp_settings']);
}

function ssn_staff(int $id=2):void {
    global $supportId,$supportBinding;
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);if(!$u)throw new LogicException('Missing fixture staff.');
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];
    if($supportBinding!=='')$_SESSION['support_binding']=$supportBinding;
    $_GET=$supportId===''?[]:['support'=>$supportId];unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function ssn_begin(int $client=1):void {
    global $supportId,$supportBinding;ssn_staff();if($supportId!=='')support_end($supportId);
    $supportId=support_start($client);$supportBinding=$_SESSION['support_binding'];$_GET=['support'=>$supportId];
}
function ssn_session():void {
    global $supportId;ssn_staff();$r=$supportId===''?null:one('SELECT * FROM '.table('support_sessions').' WHERE id=?',[$supportId]);
    // Explicit fixture navigation starts a new session between scenarios; launch never touches idle time.
    if(!$r||$r['ended_at']!==null||time()-(int)$r['last_seen_at']>=240)ssn_begin();
}

function ssn_mutex(): bool {
    $name = 'cwp-staff-'.substr(hash('sha256', 'cwp_test|'.SSLN_PREFIX), 0, 40);
    return (int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()', [$name])->fetchColumn() === 1;
}

function ssn_locked(callable $call): mixed { return staff_locked(fn()=>staff_transaction($call)); }

function ssn_exists(string $name): bool {
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY table_name=BINARY ?', [SSLN_PREFIX.$name]) !== null;
}

function ssn_count(string $name): int { return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn(); }

function ssn_digest(): string {
    $rows = [];
    foreach (all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME', [strlen(SSLN_PREFIX), SSLN_PREFIX]) as $row) {
        $name = $row['TABLE_NAME'];
        if (!preg_match('/\Assln_[a-z0-9_]+\z/', $name)) throw new LogicException('Unexpected fixture table.');
        $data = all('SELECT * FROM `'.$name.'`');
        usort($data, fn($a, $b)=>strcmp(json_encode($a, JSON_THROW_ON_ERROR), json_encode($b, JSON_THROW_ON_ERROR)));
        $rows[$name] = $data;
    }
    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
}

function ssn_calls(): array {
    global $readCalls, $readDns, $readCredentials, $launchCalls, $launchDns;
    return [$readCalls, $readDns, $readCredentials, $launchCalls, $launchDns];
}

function ssn_cool(string $profile): void {
    if (db()->inTransaction() || ssn_mutex()) throw new LogicException('Cooldown inside mutation boundary.');
    $epoch = (int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?', [$profile])->fetchColumn();
    if ($epoch < 0 || $epoch > time()) throw new RuntimeException('Unexpected future fixture read.');
    $deadline = microtime(true) + 32;
    while (time() < $epoch + 31) {
        if (microtime(true) > $deadline) throw new RuntimeException('Fixture cooldown bound exceeded.');
        usleep(100000);
    }
}

function ssn_read_dns(): string {
    global $readDns;
    if (db()->inTransaction()) throw new LogicException('Read DNS inside transaction.');
    ++$readDns; return '8.8.8.8';
}

final class SsnReadTransport implements HttpTransport {
    public function __construct(private string $operation, private $authorize, private ?B $binding = null) {}
    public function send(TransportRequest $request): TransportResponse {
        global $readCalls, $membershipBody, $grantsBody, $readStatus;
        ($this->authorize)();
        if (db()->inTransaction() || !ssn_mutex() || $request->method !== 'GET' || $request->body !== null
            || ($request->headers['Authorization'] ?? null) !== 'Bearer '.base64_encode(SSLN_GENERAL)) {
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

function ssn_read(array $row, string $secret, string $address, callable $authorize, string $operation, ?B $stackcpAccessContext = null): InventorySnapshot|O {
    if ($secret !== SSLN_GENERAL || $address !== '8.8.8.8' || !in_array($operation, [InventoryRead::PACKAGES_LIST, B::MEMBERSHIP, B::GRANTS], true)) throw new LogicException('Unexpected synthetic reader input.');
    $credential = new ProviderCredential('twenty-i', $row['id'], function() use ($secret, $authorize): string {
        global $readCredentials; $authorize(); ++$readCredentials; return $secret;
    });
    $transport = new SsnReadTransport($operation, $authorize, $stackcpAccessContext);
    if ($stackcpAccessContext) return AccessReader::read($transport, $credential, $stackcpAccessContext, time());
    return InventoryReader::read($transport, $credential, $operation, 0, time());
}

function ssn_packages(string $profile): array {
    ssn_cool($profile); ssn_owner();
    return provider_inventory_refresh_attempt($profile, (int)provider_settings_load($profile)['revision'], SSLN_PASSWORD, 'ssn_read_dns', 'ssn_read');
}

function ssn_access(string $profile, string $operation = B::MEMBERSHIP, int $package = 3501): array {
    ssn_cool($profile); ssn_owner();
    $binding = staff_locked(fn()=>provider_stackcp_access_binding($profile, $package, $operation));
    return provider_stackcp_access_refresh($profile, $package, $operation, $binding->sha256, SSLN_PASSWORD, 'ssn_read_dns', 'ssn_read');
}

function ssn_auth(string $profile, bool $enabled = true, string $key = SSLN_AUTH): array {
    return provider_stackcp_auth_change($profile, provider_stackcp_auth_status($profile)['review_sha256'], $enabled, $enabled ? $key : '', SSLN_PASSWORD);
}

function ssn_assign(string $profile, int $package = 3501, string $identity = 'stack-user:351', bool $revoke = false): array {
    $link = one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=?', [$profile, $package]);
    if (!$link) throw new LogicException('Missing synthetic package assignment.');
    return provider_stackcp_assignment_change($profile, $package, provider_stackcp_assignment_status($profile, $package)['review_sha256'], $link['id'], $identity, $revoke, SSLN_PASSWORD);
}

function ssn_spawn(array $review): array {
    $process = proc_open([PHP_BINARY, __FILE__, '--worker'], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException('Could not create native launch worker.');
    $review['fixture_binding']=$GLOBALS['supportBinding'];$json = json_encode($review, JSON_THROW_ON_ERROR);
    if (fwrite($pipes[0], $json) !== strlen($json)) throw new RuntimeException('Could not write bounded worker input.');
    fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
    return ['process'=>$process, 'pipes'=>$pipes, 'stdout'=>'', 'stderr'=>'', 'connection'=>null];
}

function ssn_blocked(array &$child): void {
    if (!ssn_mutex() || db()->inTransaction()) throw new LogicException('Contention observer requires mutex without transaction.');
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
            if ($row && stripos((string)$row['INFO'], 'GET_LOCK') !== false && stripos((string)$row['STATE'], 'lock') !== false && ssn_mutex()) return;
        }
        if (!proc_get_status($child['process'])['running']) throw new RuntimeException('Worker left before observed mutex contention.');
        if (microtime(true) > $deadline) throw new RuntimeException('Actual blocked worker was not observed.');
        usleep(20000);
    } while (true);
}

function ssn_join(array &$child): array {
    $exit = null; $deadline = microtime(true) + 150;
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
    if ($exit !== 0 || $child['stderr'] !== '' || strlen($child['stdout']) > 8192) throw new RuntimeException('Native worker failed. exit='.var_export($exit,true).' stderr='.substr($child['stderr'],0,400).' stdout='.substr($child['stdout'],0,400));
    $result = json_decode(trim($child['stdout']), true, 5, JSON_THROW_ON_ERROR);
    if (!is_array($result) || array_keys($result) !== ['outcome','launch_dns','launch_calls']
        || !in_array($result['outcome'], ['completed','refused'], true) || !is_int($result['launch_dns'])
        || $result['launch_dns'] < 0 || !is_array($result['launch_calls']) || array_keys($result['launch_calls']) !== ['membership','grants','brand','token','session','browser']) throw new RuntimeException('Invalid native worker result.');
    foreach ($result['launch_calls'] as $n) if (!is_int($n) || $n < 0) throw new RuntimeException('Invalid native worker call count.');
    return $result;
}

function ssn_ready():bool {return staff_locked(fn()=>provider_support_stackcp_ready());}
function ssn_state(string $id):array {return staff_locked(fn()=>provider_support_stackcp_project(provider_support_stackcp_events())['admissions'][$id]);}
function ssn_phase(string $phase):void {
    global $activeReview;
    if (!ssn_mutex()||db()->inTransaction()) throw new LogicException('Remote effect requires owning mutex and no transaction.');
    if (isset($activeReview['fixture_client'])) $s=provider_client_stackcp_project(provider_client_stackcp_events())['admissions'][$activeReview['admission_id']];
    elseif (isset($activeReview['profile_id'])) $s=provider_stackcp_launch_project(provider_stackcp_launch_events())[$activeReview['admission_id']];
    else $s=provider_support_stackcp_project(provider_support_stackcp_events())['admissions'][$activeReview['admission_id']];
    $last=$s['events'][array_key_last($s['events'])];
    if ($last['phase']!==$phase||$last['kind']!==($phase==='delivery'?'terminal':'started')) throw new LogicException('Missing durable pre-effect phase.');
}
function ssn_lookup(string $host):array {
    global $launchDns,$duringLookup;
    if (!ssn_mutex()||db()->inTransaction()||!in_array($host,['api.20i.com','auth-api.20i.com','www.stackcp.com'],true)) throw new LogicException('Invalid support launch DNS boundary.');
    $r=$GLOBALS['activeReview'];$states=isset($r['fixture_client'])?provider_client_stackcp_project(provider_client_stackcp_events())['admissions']:(isset($r['profile_id'])?provider_stackcp_launch_project(provider_stackcp_launch_events()):provider_support_stackcp_project(provider_support_stackcp_events())['admissions']);ssn_phase($states[$r['admission_id']]['phase']);
    ++$launchDns;if ($duringLookup!==null) {$callback=$duringLookup;$duringLookup=null;$callback();}
    return [['type'=>'A','ip'=>'8.8.8.8']];
}
final class SsnLaunchTransport implements HttpTransport {
    public function __construct(private string $phase,private $authorize){}
    public function send(#[SensitiveParameter] TransportRequest $request):TransportResponse {
        global $launchCalls,$launchMode,$duringLaunch;
        ($this->authorize)();ssn_phase($this->phase);
        $read=in_array($this->phase,['membership','grants'],true);
        $fixed=$read?new ClientStackCpAccessTransport($this->phase,3501,'stack-user:351','8.8.8.8',$this->authorize):new StackCpLaunchTransport($this->phase,'8.8.8.8',$this->authorize);
        (new ReflectionMethod($fixed,'validate'))->invoke($fixed,$request);
        $secret=match($this->phase){'membership','grants','brand'=>SSLN_GENERAL,'token'=>SSLN_AUTH,'session'=>SSLN_TOKEN};
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
            'token'=>json_encode(['access_token'=>SSLN_TOKEN,'token_type'=>'bearer','expires_in'=>300,'scope'=>'interactive'],JSON_THROW_ON_ERROR),
            'session'=>json_encode(['session_id'=>SSLN_SESSION],JSON_THROW_ON_ERROR),
        };
        return new TransportResponse(200,[],$body);
    }
}
function ssn_factory(string $phase,string $address,callable $authorize):HttpTransport {if ($address!=='8.8.8.8') throw new LogicException('Wrong pinned address.');ssn_phase($phase);return new SsnLaunchTransport($phase,$authorize);}
function ssn_deliver(#[SensitiveParameter] string $url):void {
    global $launchCalls,$launchMode;ssn_phase('delivery');
    if ($url!=='https://www.stackcp.com/login/sso?PHPSESSID='.rawurlencode(SSLN_SESSION)) throw new LogicException('Unexpected client browser destination.');
    ++$launchCalls['browser'];if ($launchMode==='delivery-throw') throw new RuntimeException('Synthetic browser interruption.');
}
function ssn_open(array $review,bool $confirmed=true):array {
    global $activeReview;$activeReview=$review;
    return provider_support_stackcp_open($review['admission_id'],$review['permission_id'],$review['support_id'],$review['expires_epoch'],$review['review_proof'],$confirmed,'ssn_deliver','ssn_lookup','ssn_factory','ssn_factory');
}
/** Real rolling admission cooldown, outside the mutex; original timestamps remain untouched. */
function ssn_admission_cool(bool $emptyWindow=false):void {
    if (ssn_mutex()||db()->inTransaction()) throw new LogicException('Admission cooldown inside mutation boundary.');
    $deadline=microtime(true)+62;
    do {
        $times=staff_locked(function():array {$events=provider_support_stackcp_events();$times=[];foreach (provider_support_stackcp_project($events)['admissions'] as $state) if ($state['admission']['staff_id']===2&&$state['admission']['recorded_epoch']>time()-60) $times[]=$state['admission']['recorded_epoch'];return $times;});
        if (($emptyWindow?$times===[]:count($times)<5)) return;
        if (microtime(true)>$deadline) throw new RuntimeException('Real client admission cooldown exceeded.');
        sleep(1);
    }while(true);
}
function ssn_review(string $permissionId):array {ssn_admission_cool();ssn_session();$r=provider_support_stackcp_review($permissionId,$GLOBALS['supportId']);if (!$r['available']) throw new LogicException('Support permission is not current.');return $r;}
function ssn_enable(string $profile,bool $enabled=true):array {
    ssn_owner();$r=provider_support_stackcp_permission_review($profile,3501,2);
    return provider_support_stackcp_permission_change($profile,3501,2,$r[$enabled?'enable_review':'revoke_review'],$enabled,true,SSLN_PASSWORD);
}
function ssn_replay(string $name,array $r,array $expected):void {$before=ssn_digest();$calls=ssn_calls();ssn_ok($name,ssn_open($r)===$expected&&ssn_digest()===$before&&ssn_calls()===$calls);}
function ssn_damage(string $name,callable $mutate):void {
    $before=ssn_digest();$calls=ssn_calls();staff_locked(function()use($name,$mutate):void {db()->beginTransaction();try {$mutate();ssn_deny($name,fn()=>provider_support_stackcp_ready());}finally{db()->rollBack();unset($GLOBALS['cwp_settings']);}});
    ssn_ok($name.' exact rows restored with no effects',ssn_ready()&&ssn_digest()===$before&&ssn_calls()===$calls);
}
function ssn_fault(string $kind,string $phase,callable $call):bool {
    if (!in_array([$kind,$phase],[['prepared','admission'],['admitted','admission'],['accepted','membership'],['accepted','session'],['terminal','delivery']],true)) throw new LogicException('Unknown audit fault.');
    $trigger='ssln_event_fault';if (one('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND TRIGGER_NAME=?',[$trigger])) throw new LogicException('Existing trigger.');
    $before=ssn_count('provider_support_stackcp_events');$prepared=$kind==='prepared';
    $action=$prepared?'provider.support_stackcp_prepared':'provider.support_stackcp_event';
    $condition=$prepared?'1=1':"EXISTS (SELECT 1 FROM ".table('provider_support_stackcp_events')." WHERE BINARY kind='".$kind."' AND BINARY phase='".$phase."' AND BINARY NEW.detail=BINARY CONCAT('Support StackCP event ',id,' ',document_sha256,' ',proof))";
    db()->exec('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF BINARY NEW.action='".$action."' AND ".$condition." THEN SET @ssln_fault_hits=COALESCE(@ssln_fault_hits,0)+1; SET @ssln_fault_rows=(SELECT COUNT(*) FROM ".table('provider_support_stackcp_events')."); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic support launch audit fault'; END IF; END");
    query('SET @ssln_fault_hits=0,@ssln_fault_rows=0');$caught=false;
    try {$call();}catch(PDOException $e){$w=one('SELECT @ssln_fault_hits AS hits,@ssln_fault_rows AS rows_seen');$caught=$e->getCode()==='45000'&&str_contains($e->getMessage(),'Synthetic support launch audit fault')&&(int)$w['hits']===1&&($prepared?(int)$w['rows_seen']===0:(int)$w['rows_seen']>$before);}
    finally {db()->exec('DROP TRIGGER '.$trigger);}return $caught;
}
function ssn_current_probe(string $name,string $permissionId,callable $mutate):void {
    $before=ssn_digest();$calls=ssn_calls();staff_locked(function()use($name,$permissionId,$mutate):void {db()->beginTransaction();try {$p=provider_support_stackcp_permission($permissionId);$mutate();ssn_ok($name,!provider_support_stackcp_current($p));}finally{db()->rollBack();unset($GLOBALS['cwp_settings']);}});
    ssn_staff();ssn_ok($name.' restored current authority and unchanged history',ssn_digest()===$before&&ssn_calls()===$calls&&ssn_review($permissionId)['available']);
}
if ($worker) {
    try {
        $raw=stream_get_contents(STDIN,8193);if ($raw===false||strlen($raw)>8192) exit(2);$r=json_decode($raw,true,6,JSON_THROW_ON_ERROR);
        if (!is_array($r)||!isset($r['admission_id'],$r['permission_id'],$r['expires_epoch'],$r['review_proof'])) exit(2);
        if(!is_string($r['fixture_binding']??null)||preg_match('/\A[a-f0-9]{64}\z/',$r['fixture_binding'])!==1)exit(2);$supportId=$r['support_id'];$supportBinding=$r['fixture_binding'];ssn_staff();echo json_encode(['ready'=>true,'connection'=>(int)query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR)."\n";fflush(STDOUT);
        $outcome='completed';for($budget=microtime(true)+120;;)try {ssn_open($r);break;}catch(InvalidArgumentException|RuntimeException $e){if ($e instanceof PDOException) throw $e;if ($e->getMessage()!=='Another account change is in progress. Please retry.'){$outcome='refused';break;}if (microtime(true)>$budget) throw $e;}
        echo json_encode(['outcome'=>$outcome,'launch_dns'=>$launchDns,'launch_calls'=>$launchCalls],JSON_THROW_ON_ERROR);exit(0);
    }catch(Throwable){exit(1);}
}

try {
    ssn_ok('Fresh guarded namespace contains no prior application tables',(int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(SSLN_PREFIX),SSLN_PREFIX])->fetchColumn()===0);
    ssn_ok('Disposable SQL account is not root',!str_starts_with(strtolower((string)query('SELECT CURRENT_USER()')->fetchColumn()),'root@'));
    foreach(schema_statements(SSLN_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $name=>$value)save_setting($name,$value);save_setting('studio_schema_version','2');
    query('INSERT INTO '.table('users').' (name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?)',['Support launch owner','support-owner@example.invalid',password_hash(SSLN_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);staff_seed_owner(1);ssn_owner();
    $staff=staff_save(0,'Delegated support staff','support-staff@example.invalid','administrator',true,['support.login','support.stackcp'],SSLN_PASSWORD);
    $invite=staff_invite($staff,SSLN_PASSWORD);$token=substr($invite,strpos($invite,'#token=')+7);staff_activate($token,SSLN_PASSWORD);unset($invite,$token);
    ssn_ok('Actual invited activated staff is separate from owner',$staff===2&&one('SELECT password_hash FROM '.table('users').' WHERE id=2')['password_hash']!=='');
    ssn_owner();ssn_ok('Actual client writers establish separate active accounts',studio_client_save(0,'','Support client one','support-client1@example.invalid',true)===1&&studio_client_save(0,'','Support client two','support-client2@example.invalid',true)===2);
    support_migrate(SSLN_PASSWORD);support_policy(1,true,SSLN_PASSWORD);support_policy(2,true,SSLN_PASSWORD);
    provider_settings_migrate(SSLN_PASSWORD);provider_inventory_migrate(SSLN_PASSWORD);$profile=provider_settings_save('',0,'Synthetic support StackCP',true,SSLN_GENERAL,SSLN_PASSWORD);
    provider_stackcp_auth_prepare(SSLN_PASSWORD);ssn_auth($profile);provider_stackcp_assignment_prepare(SSLN_PASSWORD);
    ssn_ok('Actual initial provider inventory is complete',ssn_packages($profile)['state']==='complete');provider_inventory_link($profile,3501,1,1,SSLN_PASSWORD);provider_inventory_link($profile,3502,2,1,SSLN_PASSWORD);ssn_assign($profile);ssn_assign($profile,3502,'stack-user:352');provider_stackcp_access_prepare(SSLN_PASSWORD);
    ssn_ok('Actual assigned package membership and singleton grants are observed',ssn_packages($profile)['state']==='complete'&&ssn_access($profile)['state']==='complete'&&ssn_access($profile,B::GRANTS)['state']==='complete');
    provider_stackcp_launch_prepare(SSLN_PASSWORD);$activeReview=provider_stackcp_launch_review($profile,3501);
    $ownerResult=provider_stackcp_launch_open($activeReview['admission_id'],$profile,3501,$activeReview['expires_epoch'],$activeReview['review_proof'],true,SSLN_PASSWORD,'ssn_deliver','ssn_lookup','ssn_factory');
    ssn_ok('Actual original owner launch works before support preparation',$ownerResult['state']==='handoff_attempted');
    provider_client_stackcp_prepare(SSLN_PASSWORD);$r=provider_client_stackcp_permission_review($profile,3501);$cp=provider_client_stackcp_permission_change($profile,3501,$r['enable_review'],true,true,SSLN_PASSWORD);
    $client=one('SELECT * FROM '.table('studio_clients').' WHERE id=1');$_SESSION=['client_id'=>1,'client_auth'=>$client['auth_version'],'client_login'=>time(),'client_last'=>time()];$_GET=[];
    $activeReview=provider_client_stackcp_review($cp['permission_id'],1)+['fixture_client'=>true];
    ssn_ok('Actual original primary client launch works before support preparation',provider_client_stackcp_open($activeReview['admission_id'],$cp['permission_id'],1,$activeReview['expires_epoch'],$activeReview['review_proof'],true,'ssn_deliver','ssn_lookup','ssn_factory','ssn_factory')['state']==='handoff_attempted');
    $original=[];foreach(['provider_stackcp_launch_events','provider_client_stackcp_events'] as $name)$original[$name]=all('SELECT * FROM '.table($name).' ORDER BY ordinal');
    $originalAudits=all("SELECT * FROM ".table('audit')." WHERE action IN ('provider.stackcp_launch_prepared','provider.stackcp_launch_event','provider.client_stackcp_prepared','provider.client_stackcp_event') ORDER BY id");
    $stage='support preparation';ssn_begin();$before=ssn_digest();$calls=ssn_calls();ssn_ok('Unprepared support status is local and leaves idle clock unchanged',!provider_support_stackcp_status($supportId)&&ssn_digest()===$before&&ssn_calls()===$calls);
    ssn_deny('Delegated staff cannot prepare launch storage',fn()=>provider_support_stackcp_prepare(SSLN_PASSWORD));ssn_owner();ssn_deny('Preparation requires current owner password',fn()=>provider_support_stackcp_prepare('wrong'));
    foreach(provider_support_stackcp_schema(SSLN_PREFIX) as $sql)db()->exec($sql);
    db()->exec('ALTER TABLE '.table('provider_support_stackcp_events').' ADD COLUMN unexpected INT NULL');try{ssn_deny('Malformed empty setup is not adopted',fn()=>provider_support_stackcp_prepare(SSLN_PASSWORD));}finally{db()->exec('ALTER TABLE '.table('provider_support_stackcp_events').' DROP COLUMN unexpected');}
    $before=ssn_digest();ssn_ok('Actual setup audit failure rolls back marker and head',ssn_fault('prepared','admission',fn()=>provider_support_stackcp_prepare(SSLN_PASSWORD))&&!ssn_ready()&&ssn_digest()===$before);
    provider_support_stackcp_prepare(SSLN_PASSWORD);$before=ssn_digest();provider_support_stackcp_prepare(SSLN_PASSWORD);ssn_ok('Recognized empty interrupted setup resumes exactly once',ssn_ready()&&ssn_digest()===$before);
    $r=provider_support_stackcp_permission_review($profile,3501,2);ssn_deny('Whole-user owner delegation requires explicit consent',fn()=>provider_support_stackcp_permission_change($profile,3501,2,$r['enable_review'],true,false,SSLN_PASSWORD));
    $permission=ssn_enable($profile);$permissionId=$permission['permission_id'];ssn_staff();$review=ssn_review($permissionId);$otherWindow=ssn_review($permissionId);$before=ssn_digest();$calls=ssn_calls();
    ssn_ok('Support review history and local list perform no writes or remote work',provider_support_stackcp_review($permissionId,$supportId)['available']&&provider_support_stackcp_history($permissionId,$supportId)['items']===[]&&count(provider_support_stackcp_staff_snapshot($supportId)['items'])===1&&ssn_digest()===$before&&ssn_calls()===$calls);
    ssn_deny('Unchecked support consent refuses before admission',fn()=>ssn_open($review,false));
    foreach(['admission_id'=>str_repeat('f',32),'support_id'=>str_repeat('e',32),'permission_id'=>str_repeat('d',32),'expires_epoch'=>time()-1,'review_proof'=>str_repeat('0',64)] as $key=>$value){$bad=$review;$bad[$key]=$value;ssn_deny('Original signed form rejects changed '.$key,fn()=>ssn_open($bad));}
    foreach(['contact_id','contact_auth'] as $key){$_SESSION[$key]=null;ssn_deny('Raw '.$key.' presence refuses before normalization',fn()=>ssn_open($review));ssn_staff();}
    $_SESSION['client_id']=2;ssn_ok('Ambient unrelated client cookie cannot change actual support client',provider_support_stackcp_review($permissionId,$supportId)['available']);ssn_staff();ssn_owner();ssn_deny('Owner cookie cannot impersonate delegated support staff',fn()=>ssn_open($review));ssn_staff();
    $oldBinding=$supportBinding;$_SESSION['support_binding']=str_repeat('0',64);ssn_deny('Different browser binding cannot use the signed support form',fn()=>ssn_open($review));ssn_staff();
    ssn_ok('All invalid forms leave exact rows and effects unchanged',ssn_digest()===$before&&ssn_calls()===$calls);
    ssn_ok('Actual admitted audit fault rolls back admission and all checkpoints',ssn_fault('admitted','admission',fn()=>ssn_open($review))&&ssn_digest()===$before&&ssn_calls()===$calls);
    $stage='support one use';$counts=$launchCalls;$sessionBefore=one('SELECT * FROM '.table('support_sessions').' WHERE id=?',[$supportId]);$result=ssn_open($review);$state=ssn_state($review['admission_id']);
    ssn_ok('Explicit support launch performs exact two reads three exchange phases one delivery',$result['state']==='handoff_attempted'&&array_map(fn($n,$old)=>$n-$old,$launchCalls,$counts)===[1,1,1,1,1,1]);
    ssn_ok('All twelve phases retain actual support staff and exact browser session',count($state['events'])===12&&count(array_filter($state['events'],fn($e)=>$e['actor']['kind']==='support'&&$e['actor']['id']===2&&$e['actor']['client_id']===1&&$e['actor']['support_id']===$supportId))===12);
    ssn_ok('All native event audit rows attribute real staff rather than client or owner',(int)query("SELECT COUNT(*) FROM ".table('audit')." a JOIN ".table('provider_support_stackcp_events')." e ON BINARY a.detail=BINARY CONCAT('Support StackCP event ',e.id,' ',e.document_sha256,' ',e.proof) WHERE e.admission_id=? AND a.user_id=2 AND e.actor_staff_id=2 AND e.staff_id=2",[$review['admission_id']])->fetchColumn()===12);
    ssn_ok('Successful launch never extends support idle or absolute expiry',one('SELECT * FROM '.table('support_sessions').' WHERE id=?',[$supportId])===$sessionBefore);
    ssn_replay('Exact support admission replay has zero sends or browser redelivery',$review,$result);ssn_deny('Other-window proof cannot ignore newer outcome',fn()=>ssn_open($otherWindow));
    $r=ssn_review($permissionId);$counts=$launchCalls;$raceResult=null;$child=staff_locked(function()use($r,&$raceResult):array{$child=ssn_spawn($r);ssn_blocked($child);ssn_ok('Duplicate admission process waits on the real owning mutex',true);$raceResult=ssn_open($r);return $child;});$workerResult=ssn_join($child);ssn_ok('Concurrent exact admission causes one exchange and inert worker replay',$raceResult['state']==='handoff_attempted'&&$workerResult['outcome']==='completed'&&$workerResult['launch_dns']===0&&array_sum($workerResult['launch_calls'])===0&&array_map(fn($n,$old)=>$n-$old,$launchCalls,$counts)===[1,1,1,1,1,1]);
    $stage='stable delegation';$permissionBefore=staff_locked(fn()=>provider_support_stackcp_permission($permissionId));ssn_packages($profile);ssn_staff();ssn_ok('Normal package observation preserves explicit support delegation',ssn_review($permissionId)['available']&&staff_locked(fn()=>provider_support_stackcp_permission($permissionId))===$permissionBefore);
    foreach(['membership-missing'=>['not_issued','membership'],'grants-extra'=>['not_issued','grants'],'token-timeout'=>['unknown','token'],'session-timeout'=>['unknown','session']] as $mode=>$expected){$r=ssn_review($permissionId);$launchMode=$mode;$b=$launchCalls['browser'];$out=ssn_open($r);$launchMode='ok';ssn_ok($mode.' retains precise disposition without delivery',$out['state']===$expected[0]&&$out['phase']===$expected[1]&&$launchCalls['browser']===$b);ssn_replay($mode.' never retries consumed phases',$r,$out);}
    foreach([['accepted','membership','interrupted'],['accepted','session','unknown'],['terminal','delivery','unknown']] as [$kind,$phase,$expected]){$r=ssn_review($permissionId);$b=$launchCalls['browser'];ssn_ok($kind.' '.$phase.' witnesses real inserted audit rollback',ssn_fault($kind,$phase,fn()=>ssn_open($r)));$out=provider_support_stackcp_launch_summary(ssn_state($r['admission_id']));ssn_ok($kind.' '.$phase.' preserves consumed truthful outcome',$out['state']===$expected&&$launchCalls['browser']===$b);ssn_replay($kind.' '.$phase.' cannot resume or redeliver',$r,$out);}
    $r=ssn_review($permissionId);$launchMode='delivery-throw';ssn_deny('Terminating delivery callback follows committed handoff terminal',fn()=>ssn_open($r));$launchMode='ok';ssn_replay('Browser interruption never redelivers session',$r,provider_support_stackcp_launch_summary(ssn_state($r['admission_id'])));
    $stage='current authority';
    foreach(['Disabled original client'=>fn()=>query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1'),'Rotated client auth'=>fn()=>query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]),'Disabled delegating owner'=>fn()=>query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=1'),'Rotated delegating owner'=>fn()=>query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]),'Rotated general key revision'=>fn()=>query('UPDATE '.table('provider_profiles').' SET revision=revision+1 WHERE id=?',[$profile]),'Disabled delegated staff'=>fn()=>query('UPDATE '.table('staff_profiles').' SET enabled=0 WHERE user_id=2'),'Rotated delegated staff'=>fn()=>query('UPDATE '.table('users').' SET auth_version=? WHERE id=2',[bin2hex(random_bytes(16))])] as $name=>$mutate)ssn_current_probe($name.' invalidates stable permission',$permissionId,$mutate);
    foreach(['membership','grants','brand','token','session'] as $phase){$r=ssn_review($permissionId);$old=one('SELECT auth_version FROM '.table('users').' WHERE id=2')['auth_version'];$before=$launchCalls;$duringLaunch=static function(string $at)use($phase):void{if($at===$phase)query('UPDATE '.table('users').' SET auth_version=? WHERE id=2',[bin2hex(random_bytes(16))]);};try{$out=ssn_open($r);}finally{$duringLaunch=null;query('UPDATE '.table('users').' SET auth_version=? WHERE id=2',[$old]);ssn_staff();}ssn_ok('Staff auth changed after '.$phase.' stops next phase and delivery',$out['state']===(in_array($phase,['token','session'],true)?'unknown':'not_issued')&&$out['phase']===$phase&&$launchCalls['browser']===$before['browser']);ssn_replay('Restored fixture cannot resume revoked '.$phase.' admission',$r,$out);}
    $r=ssn_review($permissionId);$old=one('SELECT allowed FROM '.table('support_clients').' WHERE client_id=1')['allowed'];$counts=$launchCalls;$duringLookup=static fn()=>query('UPDATE '.table('support_clients').' SET allowed=0 WHERE client_id=1');try{$out=ssn_open($r);}finally{query('UPDATE '.table('support_clients').' SET allowed=? WHERE client_id=1',[$old]);ssn_staff();}ssn_ok('Support policy changed during DNS prevents all remote effects',$out['state']==='not_issued'&&$launchCalls===$counts);
    $r=ssn_review($permissionId);$old=one('SELECT permissions FROM '.table('staff_profiles').' WHERE user_id=2')['permissions'];$counts=$launchCalls;$trigger='ssln_terminal_revoke';
    if(one('SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND TRIGGER_NAME=?',[$trigger]))throw new LogicException('Existing fixture trigger.');
    db()->exec('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF BINARY NEW.action='provider.support_stackcp_event' AND EXISTS (SELECT 1 FROM ".table('provider_support_stackcp_events')." WHERE BINARY kind='terminal' AND BINARY phase='delivery' AND BINARY NEW.detail=BINARY CONCAT('Support StackCP event ',id,' ',document_sha256,' ',proof)) THEN UPDATE ".table('staff_profiles')." SET permissions='[\"support.login\"]' WHERE user_id=2; SET @ssln_terminal_revoke=1; END IF; END");query('SET @ssln_terminal_revoke=0');
    try{ssn_deny('Final committed permission revocation blocks browser delivery',fn()=>ssn_open($r));ssn_ok('Actual final guard trigger reached staff permission change',(int)query('SELECT @ssln_terminal_revoke')->fetchColumn()===1&&$launchCalls['browser']===$counts['browser']);}finally{db()->exec('DROP TRIGGER '.$trigger);query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[$old]);ssn_staff();}ssn_replay('Final guard refusal remains consumed after exact restoration',$r,provider_support_stackcp_launch_summary(ssn_state($r['admission_id'])));
    $stage='actual session boundaries';$r=ssn_review($permissionId);$duringLaunch=static function(string $phase):void{if($phase==='token')support_end($GLOBALS['supportId']);};$b=$launchCalls['browser'];try{$out=ssn_open($r);}finally{$duringLaunch=null;}ssn_ok('Actual session end after token records uncertainty and prevents delivery',$out['state']==='unknown'&&$launchCalls['browser']===$b);ssn_deny('Ended support session cannot replay prior admission',fn()=>ssn_open($r));ssn_begin();
    $replacement=provider_support_stackcp_review($permissionId,$supportId);$bad=$r;$bad['support_id']=$supportId;ssn_deny('New session cannot substitute into old admission',fn()=>ssn_open($bad));
    $r=ssn_review($permissionId);ssn_owner();support_policy(1,false,SSLN_PASSWORD);ssn_staff();$before=ssn_digest();$calls=ssn_calls();ssn_deny('Actual owner client-policy revocation denies support launch',fn()=>ssn_open($r));ssn_ok('Policy refusal produces no provider or admission effects',ssn_digest()===$before&&ssn_calls()===$calls);ssn_owner();support_policy(1,true,SSLN_PASSWORD);ssn_begin();
    $stage='rate window';ssn_admission_cool(true);ssn_session();$launchMode='membership-missing';$rate=[];for($i=0;$i<5;++$i){$r=provider_support_stackcp_review($permissionId,$supportId);$rate[]=[$r,ssn_open($r)];}$r=provider_support_stackcp_review($permissionId,$supportId);$before=ssn_digest();$calls=ssn_calls();ssn_deny('Sixth actual staff admission is rate limited before effects',fn()=>ssn_open($r));ssn_ok('Rate refusal leaves exact rows and counters intact',ssn_digest()===$before&&ssn_calls()===$calls);ssn_replay('Exact replay remains inert at exhausted rate capacity',$rate[0][0],$rate[0][1]);$retained=all('SELECT * FROM '.table('provider_support_stackcp_events').' ORDER BY ordinal');ssn_admission_cool(true);ssn_ok('Real elapsed rate window does not rewrite timestamps',all('SELECT * FROM '.table('provider_support_stackcp_events').' ORDER BY ordinal')===$retained);$launchMode='ok';
    $stage='idle timeout';ssn_begin();$r=ssn_review($permissionId);$session=one('SELECT * FROM '.table('support_sessions').' WHERE id=?',[$supportId]);$deadline=microtime(true)+302;while(time()<(int)$session['last_seen_at']+300){if(microtime(true)>$deadline)throw new RuntimeException('Fixture idle wait bound exceeded.');sleep(1);}$before=ssn_digest();$calls=ssn_calls();ssn_deny('Genuinely elapsed support idle timeout refuses original approval',fn()=>ssn_open($r));ssn_ok('Timeout refusal preserves rows and no idle extension or remote effect',ssn_digest()===$before&&ssn_calls()===$calls);ssn_begin();
    $stage='retained integrity';$event=one('SELECT * FROM '.table('provider_support_stackcp_events').' ORDER BY ordinal LIMIT 1');
    foreach(['schema_version','prepared','prepared_proof','count','permission_count','admission_count','head','checkpoint_proof'] as $key)ssn_damage('Missing support journal '.$key.' fails closed',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_support_stackcp_'.$key]));
    ssn_damage('Support event HMAC tamper fails closed',fn()=>query('UPDATE '.table('provider_support_stackcp_events').' SET proof=? WHERE id=?',[str_repeat('0',64),$event['id']]));
    ssn_damage('Missing support event audit fails closed',fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.support_stackcp_event',provider_support_stackcp_detail($event)]));
    $before=ssn_digest();db()->exec('RENAME TABLE '.table('provider_support_stackcp_events').' TO '.table('support_stackcp_retained'));try{ssn_owner();ssn_deny('Missing established history cannot be recreated',fn()=>provider_support_stackcp_prepare(SSLN_PASSWORD));}finally{db()->exec('RENAME TABLE '.table('support_stackcp_retained').' TO '.table('provider_support_stackcp_events'));ssn_staff();}ssn_ok('Exact history restores without reset',ssn_ready()&&ssn_digest()===$before);
    $stage='mutex contender';$r=ssn_review($permissionId);$counts=ssn_calls();$child=staff_locked(function()use($r,$profile):array{$child=ssn_spawn($r);ssn_blocked($child);ssn_ok('Independent support process waits on actual owning mutex',true);ssn_enable($profile,false);return $child;});$workerResult=ssn_join($child);ssn_staff();ssn_ok('Revoked delegation while worker waits prevents every effect',$workerResult['outcome']==='refused'&&$workerResult['launch_dns']===0&&array_sum($workerResult['launch_calls'])===0&&ssn_calls()===$counts);
    ssn_ok('Revocation leaves history visible but launch unavailable',!provider_support_stackcp_review($permissionId,$supportId)['available']&&provider_support_stackcp_history($permissionId,$supportId)['items']!==[]);
    ssn_owner();ssn_assign($profile);ssn_staff();ssn_ok('Actual newer StackCP assignment cannot revive revoked old delegation',!provider_support_stackcp_review($permissionId,$supportId)['available']);
    $link=one('SELECT * FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=3501',[$profile]);ssn_owner();provider_inventory_unlink($link['id'],SSLN_PASSWORD);provider_inventory_link($profile,3501,2,1,SSLN_PASSWORD);ssn_staff();ssn_ok('Actual hosting reassignment cannot revive original client delegation',!provider_support_stackcp_review($permissionId,$supportId)['available']);
    $stage='original compatibility';foreach($original as $name=>$rows)ssn_ok('Original '.$name.' retained byte for byte',all('SELECT * FROM '.table($name).' ORDER BY ordinal')===$rows);ssn_ok('Original owner and primary-client audits unchanged',all("SELECT * FROM ".table('audit')." WHERE action IN ('provider.stackcp_launch_prepared','provider.stackcp_launch_event','provider.client_stackcp_prepared','provider.client_stackcp_event') ORDER BY id")===$originalAudits);
    $retained=json_encode([all('SELECT * FROM '.table('provider_support_stackcp_events')),all('SELECT * FROM '.table('audit')),all('SELECT * FROM '.table('support_events')),provider_support_stackcp_history($permissionId,$supportId)],JSON_THROW_ON_ERROR);$safe=true;foreach([SSLN_GENERAL,SSLN_AUTH,SSLN_TOKEN,SSLN_SESSION,base64_encode(SSLN_GENERAL),base64_encode(SSLN_AUTH),base64_encode(SSLN_TOKEN),'PHPSESSID=','/login/sso?'] as $secret)$safe=$safe&&!str_contains($retained,$secret);ssn_ok('Persisted journal audit and DTO contain no key token session or destination URL',$safe);
    ssn_ok('Only five prerequisite owner reads use original real cooldowns',$readCalls===5&&$readDns===5&&$readCredentials===5);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected native failure during '.$stage,'passed'=>false,'error_class'=>get_class($e),'line'=>$e->getLine()];}
$passed=$checks!==[]&&!array_filter($checks,fn($c)=>$c['passed']!==true);
$out=['passed'=>$passed,'scope'=>'Actual disposable nonroot Linux SQL, real owner delegation and staff support sessions, immutable phase journals and blocked mutex worker; synthetic fixed provider responses/browser callback only. No provider account or remote login.','checks'=>$checks];
$bytes=json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";$handle=fopen($report,'xb');if(!$handle)exit(1);$written=fwrite($handle,$bytes);fclose($handle);if($written!==strlen($bytes))exit(1);
echo ($passed?'PASS':'FAIL').': '.count($checks)." support StackCP native checks\n";exit($passed?0:1);
