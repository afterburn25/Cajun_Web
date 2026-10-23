<?php
/** Actual disposable SQL/client/owner/gateway journey; synthetic provider transport only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
    ||getenv('CWP_TEST_CLOUD_SITE_REQUESTS')!=='1'||(getenv('CWP_TEST_HOST')?:'127.0.0.1')!=='127.0.0.1')exit(2);
$worker=count($argv)===2&&$argv[1]==='--worker';if(count($argv)!==($worker?2:1))exit(2);
$port=getenv('CWP_TEST_PORT')?:'33306';if(!ctype_digit($port)||(int)$port<1||(int)$port>65535)exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/includes/bootstrap.php';
require __DIR__.'/../site/database/schema/base.php';
require __DIR__.'/../site/app/providers/cloud_site_requests.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyI\Operations\{CloudWebsiteCreate,TwentyIOperationAdapter};
use CajunWebPro\Foundation\TwentyIInventory\{InventoryRead,InventorySnapshot};
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;
const CWRI_PREFIX='cwri_';
const CWRI_PASSWORD='Synthetic client cloud website owner passphrase';
const CWRI_SECRET='SYNTHETIC_CLIENT_CLOUD_WEBSITE_KEY';
$root=realpath(__DIR__.'/..');if(!$root)exit(2);
$runtime=$root.'/test-runtime';$qa=$root.'/qa';$private=$runtime.'/provider-cloud-site-requests-private';
$report=$qa.'/provider-cloud-site-requests-native-results.json';
foreach([$runtime,$qa] as $path){if(is_link($path)||(file_exists($path)&&!is_dir($path)))exit(2);if(!is_dir($path)&&!mkdir($path,0700))exit(2);}
if(is_link($private)||($worker?!is_dir($private):file_exists($private))||(!$worker&&(file_exists($report)||is_link($report))))exit(2);
if(!$worker&&!mkdir($private,0700))exit(2);
$wireFile=$private.'/provider-wire.jsonl';if(is_link($wireFile)||($worker?!is_file($wireFile):file_exists($wireFile)))exit(2);
if(!$worker){$f=fopen($wireFile,'xb');if(!$f)exit(2);fclose($f);chmod($wireFile,0600);}
$GLOBALS['cwp_config']=['site_url'=>'http://127.0.0.1:8091/cloud-site-requests','storage'=>realpath($private),
    'key'=>str_repeat('synthetic-cloud-site-request-',4),'db'=>['host'=>'127.0.0.1','port'=>(int)$port,'name'=>'cwp_test',
    'user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>CWRI_PREFIX]];
$_SERVER+=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT']=$root.'/site';start_session();
$checks=[];$stage='preflight';$dns=0;$gets=0;$posts=0;$boundaryErrors=[];$readStatus=200;$readBody='';$writeMode='accepted';$allocated=8801;
$duringDns=null;$duringCredential=null;$process=null;$pipes=[];
$expiryProbe=['requested'=>getenv('CWP_TEST_CLOUD_INTAKE_EXPIRED')==='1','executed'=>false];

function cwri_id(int $n):string{return sprintf('%032x',$n);}
function cwri_ok(string $name,bool $passed):void{
    global $checks;if(in_array($name,array_column($checks,'name'),true))throw new LogicException('Duplicate fixture check.');
    $checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);
}
function cwri_deny(string $name,callable $call):void{
    $caught=false;try{$call();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$caught=true;}
    cwri_ok($name,$caught);
}
function cwri_owner(int $id=1):void{
    $u=one('SELECT * FROM '.table('users').' WHERE id=?',[$id]);if(!$u)throw new LogicException('Fixture owner missing.');
    $_SESSION=['uid'=>$id,'auth_version'=>$u['auth_version'],'login_at'=>time(),'last_activity'=>time()];$_GET=[];
    unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function cwri_client(int $id=1):void{
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);if(!$c)throw new LogicException('Fixture client missing.');
    $_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_login'=>time(),'client_last'=>time()];$_GET=[];
    unset($GLOBALS['cwp_support'],$GLOBALS['cwp_settings']);
}
function cwri_mutex():bool{$key='cwp-staff-'.substr(hash('sha256','cwp_test|'.CWRI_PREFIX),0,40);return (int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$key])->fetchColumn()===1;}
function cwri_locked(callable $call):mixed{return staff_locked(fn()=>staff_transaction($call));}
function cwri_count(string $name):int{return (int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
function cwri_digest():string{
    $rows=[];foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(CWRI_PREFIX),CWRI_PREFIX]) as $t){
        $name=$t['TABLE_NAME'];if(!preg_match('/\Acwri_[a-z0-9_]+\z/D',$name))throw new LogicException('Unexpected fixture table.');
        $data=all('SELECT * FROM `'.$name.'`');usort($data,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$data;
    }return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
function cwri_wire():array{global $wireFile;$out=[];foreach(file($wireFile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line)$out[]=json_decode($line,true,8,JSON_THROW_ON_ERROR);return $out;}
function cwri_effects():array{global $dns,$gets,$posts;return [$dns,$gets,$posts,count(cwri_wire()),cwri_digest()];}
function cwri_boundary(callable $authorize):void{
    global $boundaryErrors;$authorize();if(db()->inTransaction()||!cwri_mutex()){$boundaryErrors[]='transport_guard';throw new LogicException('Transport needs the owning mutex without a transaction.');}
}
/** No real DNS; the production endpoint policy validates this fixed public test address. */
function cwri_dns():string{global $dns,$duringDns;if(db()->inTransaction())throw new LogicException('DNS inside transaction.');$dns++;if($duringDns){$fn=$duringDns;$duringDns=null;$fn();}return '8.8.8.8';}
function cwri_wait(string $profile):void{
    if(db()->inTransaction()||cwri_mutex())throw new LogicException('Cooldown must be outside mutation guards.');
    $at=(int)query('SELECT COALESCE(MAX(started_epoch),0) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();
    if($at<0||$at>time())throw new RuntimeException('Unexpected future fixture refresh.');
    $deadline=microtime(true)+32;while(time()<$at+30){if(microtime(true)>$deadline)throw new RuntimeException('Refresh cooldown exceeded.');usleep(100000);}
}
final class CloudRequestReadTransport implements HttpTransport{
    public function __construct(private $authorize,private string $url){}
    public function send(TransportRequest $request):TransportResponse{
        global $gets,$readStatus,$readBody,$boundaryErrors;cwri_boundary($this->authorize);
        if($request->method!=='GET'||$request->url!==$this->url||$request->body!==null||$request->headers!==['Authorization'=>'Bearer '.base64_encode(CWRI_SECRET),'Accept'=>'application/json']){$boundaryErrors[]='read_wire';throw new LogicException('Unexpected synthetic inventory wire.');}
        $gets++;return new TransportResponse($readStatus,[],$readBody);
    }
}
function cwri_read(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,string $operation):InventorySnapshot{
    cwri_boundary($authorize);if($key!==CWRI_SECRET||$address!=='8.8.8.8'||!in_array($operation,[InventoryRead::CLOUD_SERVERS_LIST,InventoryRead::PACKAGES_LIST],true))throw new LogicException('Unexpected synthetic inventory identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'],static function()use($key,$authorize):string{cwri_boundary($authorize);return $key;});
    return InventoryReader::read(new CloudRequestReadTransport($authorize,$operation===InventoryRead::CLOUD_SERVERS_LIST?'https://api.20i.com/cloud_server':'https://api.20i.com/package'),$credential,$operation,null,time());
}
final class CloudRequestWriteTransport implements HttpTransport{
    public function __construct(private $authorize,private ProviderOperation $operation){}
    public function send(TransportRequest $request):TransportResponse{
        global $posts,$writeMode,$allocated,$wireFile,$boundaryErrors;cwri_boundary($this->authorize);CloudWebsiteCreate::wire($request);
        $expected=CloudWebsiteCreate::request($this->operation);$ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$this->operation->idempotencyKey]);
        if($request->url!==$expected->url||$request->body!==$expected->body||($request->headers['Authorization']??null)!=='Bearer '.base64_encode(CWRI_SECRET)||!$ledger||$ledger['status']!=='claimed'){$boundaryErrors[]='write_wire_claim';throw new LogicException('Synthetic write is missing exact wire or durable claim.');}
        $line=json_encode(['key'=>$this->operation->idempotencyKey,'url'=>$request->url,'body'=>$request->body],JSON_THROW_ON_ERROR)."\n";
        if(file_put_contents($wireFile,$line,FILE_APPEND|LOCK_EX)!==strlen($line))throw new RuntimeException('Cannot preserve synthetic wire witness.');$posts++;
        if($writeMode==='timeout')throw new TransportException(TransportException::KIND_TIMEOUT);
        if($writeMode==='unknown')return new TransportResponse(200,[],'true');
        if($writeMode!=='accepted')throw new LogicException('Unexpected synthetic write mode.');
        return new TransportResponse(200,[],json_encode(['result'=>$allocated],JSON_THROW_ON_ERROR));
    }
}
function cwri_perform(array $profile,#[SensitiveParameter] string $key,string $address,callable $authorize,ProviderOperation $operation):OperationResult{
    global $duringCredential;cwri_boundary($authorize);if($key!==CWRI_SECRET||$address!=='8.8.8.8'||$operation->name!==CloudWebsiteCreate::OPERATION)throw new LogicException('Unexpected synthetic gateway identity.');
    $credential=new ProviderCredential('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($key),
        static function()use($key,$authorize):string{global $duringCredential;if($duringCredential){$fn=$duringCredential;$duringCredential=null;$fn();}cwri_boundary($authorize);return $key;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new CloudRequestWriteTransport($authorize,$operation),new ProviderOperationLedger()))->perform($credential,$operation);
}
function cwri_cloud_body():string{return json_encode(array_map(static fn($id)=>['id'=>$id,'name'=>'cloud'.$id.'.synthetic.invalid','location'=>'dc_location-uk','packageIds'=>[]],[1601,1602,1603,1604]),JSON_THROW_ON_ERROR);}
function cwri_refresh(string $profile,string $operation,string $body,int $status=200):array{
    global $readBody,$readStatus;cwri_owner();cwri_wait($profile);$readBody=$body;$readStatus=$status;
    return provider_inventory_refresh_attempt($profile,(int)provider_settings_load($profile)['revision'],CWRI_PASSWORD,'cwri_dns','cwri_read',$operation);
}
function cwri_option(int $client,string $link):array{
    cwri_client($client);foreach(provider_cloud_site_requests_options($client) as $o)if($o['binding']['link_id']===$link)return $o;
    throw new RuntimeException('Expected current client request option is unavailable.');
}
function cwri_history(string $id,?int $client=null):array{
    for($page=1;$page<=5;$page++){ $rows=provider_cloud_site_requests_history($client,$page);foreach($rows as $r)if($r['id']===$id)return $r;if(!$rows)break; }
    throw new RuntimeException('Expected request history is missing.');
}
function cwri_create(int $n,array $link,string $domain,int $client=1,array $aliases=[]):array{
    $o=cwri_option($client,$link['id']);$id=cwri_id($n);provider_cloud_site_requests_create($id,$client,$link['id'],$o['context'],$domain,$aliases,true);
    return ['id'=>$id,'client'=>$client,'link'=>$link['id'],'context'=>$o['context'],'domain'=>$domain,'aliases'=>$aliases];
}
function cwri_dispatch_args(array $request,int $dispatch,int $grant):array{
    cwri_owner();$h=cwri_history($request['id']);if(!$h['can_dispatch'])throw new RuntimeException('Expected pending request cannot be dispatched.');
    return [$request['id'],$h['latest_event'],cwri_id($dispatch),cwri_id($grant),$h['dispatch_context']];
}
function cwri_dispatch(array $args,?callable $resolver=null,?callable $perform=null):OperationResult{return provider_cloud_site_requests_dispatch(...[...$args,true,CWRI_PASSWORD,$resolver??'cwri_dns',$perform??'cwri_perform']);}
function cwri_replay_create(array $q):array{cwri_client($q['client']);return provider_cloud_site_requests_create($q['id'],$q['client'],$q['link'],$q['context'],$q['domain'],$q['aliases'],true);}
function cwri_event(string $id):array{$r=one('SELECT * FROM '.table('provider_cloud_site_request_events').' WHERE id=?',[$id]);if(!$r)throw new LogicException('Expected event missing.');return $r;}
/** Deliberately damage retained evidence in a transaction, then restore by rollback only. */
function cwri_corrupt(string $label,callable $change,callable $read):void{
    $before=cwri_digest();staff_locked(function()use($label,$change,$read):void{db()->beginTransaction();try{$change();cwri_deny($label,$read);}finally{if(db()->inTransaction())db()->rollBack();unset($GLOBALS['cwp_settings']);}});
    cwri_ok($label.' leaves original rows intact after fixture rollback',cwri_digest()===$before);
}
function cwri_fault(string $label,array $args,string $action):void{
    $before=cwri_effects();query('SET @cwri_hit=0,@cwri_event_seen=0,@cwri_grant_seen=0');
    $extra=$action==='provider.cloudsite_started'?'':" AND NEW.detail LIKE 'Cloud website request event ".$args[2]." %'";
    db()->exec("CREATE TRIGGER cwri_fail BEFORE INSERT ON ".table('audit')." FOR EACH ROW BEGIN IF NEW.action='".$action."'".$extra." THEN SET @cwri_hit=@cwri_hit+1; SET @cwri_event_seen=(SELECT COUNT(*) FROM ".table('provider_cloud_site_request_events')." WHERE id='".$args[2]."'); SET @cwri_grant_seen=(SELECT COUNT(*) FROM ".table('provider_operation_grants')." WHERE id='".$args[3]."'); SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic request audit failure'; END IF; END");
    $failed=false;try{cwri_dispatch($args);}catch(PDOException $e){$failed=($e->errorInfo[0]??'')==='45000';}finally{db()->exec('DROP TRIGGER cwri_fail');unset($GLOBALS['cwp_settings']);}
    $w=one('SELECT @cwri_hit AS hit,@cwri_event_seen AS event_seen,@cwri_grant_seen AS grant_seen');
    cwri_ok($label,$failed&&(int)$w['hit']===1&&(int)$w['event_seen']===1&&($action!=='provider.cloudsite_started'||(int)$w['grant_seen']===1)&&cwri_effects()===$before);
}

/** Exact row comparison permits only the real request, its audit, and the existing head value. */
function cwri_rows():array {
    $rows=[];foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ? ORDER BY TABLE_NAME',[strlen(CWRI_PREFIX),CWRI_PREFIX]) as $t){
        $name=$t['TABLE_NAME'];if(!preg_match('/\Acwri_[a-z0-9_]+\z/D',$name))throw new LogicException('Unexpected fixture table.');
        $data=all('SELECT * FROM `'.$name.'`');usort($data,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$rows[$name]=$data;
    }return $rows;
}
function cwri_only_intake(callable $create):array {
    global $dns,$gets,$posts;$before=cwri_rows();$wire=[$dns,$gets,$posts,count(cwri_wire())];$r=$create();$after=cwri_rows();$id=$r['request']['id'];$event=cwri_event($id);
    $events=CWRI_PREFIX.'provider_cloud_site_request_events';$audits=CWRI_PREFIX.'audit';$settings=CWRI_PREFIX.'settings';
    $newEvents=array_values(array_filter($after[$events],static fn($v)=>$v['id']===$id));
    $detail='Cloud website request event '.$id.' '.$event['fingerprint'];
    $newAudits=array_values(array_filter($after[$audits],static fn($v)=>$v['action']==='provider.cloud_site_request_event'&&$v['detail']===$detail));
    $oldHead=array_values(array_filter($before[$settings],static fn($v)=>$v['name']==='provider_cloud_site_request_head'));
    $newHead=array_values(array_filter($after[$settings],static fn($v)=>$v['name']==='provider_cloud_site_request_head'));
    $exact=count($newEvents)===1&&count($newAudits)===1&&(int)$newAudits[0]['user_id']===0&&count($oldHead)===1&&count($newHead)===1
        &&$newHead[0]['value']===$event['ordinal'].':'.$event['fingerprint']
        &&(int)$event['ordinal']===(int)explode(':',$oldHead[0]['value'],2)[0]+1;
    $after[$events]=array_values(array_filter($after[$events],static fn($v)=>$v['id']!==$id));
    $after[$audits]=array_values(array_filter($after[$audits],static fn($v)=>!($v['action']==='provider.cloud_site_request_event'&&$v['detail']===$detail)));
    if(count($oldHead)===1)foreach($after[$settings] as &$row)if($row['name']==='provider_cloud_site_request_head')$row['value']=$oldHead[0]['value'];unset($row);
    foreach($after as &$rows)usort($rows,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));unset($rows);
    return [$r,$exact&&$after===$before&&[$dns,$gets,$posts,count(cwri_wire())]===$wire];
}
/** A valid retained request is not a fresh owner review; call the real dispatcher to prove refusal. */
function cwri_static_case(string $label,int $id,array $link,string $domain):array {
    cwri_client(2);$before=cwri_effects();$o=cwri_option(2,$link['id']);
    cwri_ok($label.' retains local intake with no provider or database effect',cwri_effects()===$before&&$o['binding']['source_id']===$link['refresh_id']);
    [$r,$only]=cwri_only_intake(fn()=>provider_cloud_site_requests_create(cwri_id($id),2,$link['id'],$o['context'],$domain,[],true));
    cwri_ok($label.' adds only one pending event audit and head change',$r['stage']==='pending'&&$only);
    $q=['id'=>cwri_id($id),'client'=>2,'link'=>$link['id'],'context'=>$o['context'],'domain'=>$domain,'aliases'=>[]];
    cwri_owner();$before=cwri_effects();$h=cwri_history($q['id']);
    cwri_ok($label.' blocks owner dispatch with a refresh-required reason',!$h['can_dispatch']&&$h['dispatch_context']===''&&$h['reason']!=='');
    $context=cwri_locked(fn()=>provider_cloud_site_requests_context($r['request']['payload']['binding'],provider_cloud_site_requests_actor(null),$r));
    cwri_deny($label.' refuses real owner dispatcher before sending',fn()=>cwri_dispatch([$q['id'],$q['id'],cwri_id($id+1),cwri_id($id+2),$context]));
    cwri_ok($label.' dispatch refusal preserves every row grant ledger and provider counter',cwri_effects()===$before);
    return $q;
}
/** Opt-in actual elapsed time, outside transaction/mutex; never alter an authenticated timestamp. */
function cwri_expire(int $sourceEpoch,int $anchorEpoch):array {
    if(db()->inTransaction()||cwri_mutex()||$sourceEpoch>time()||$anchorEpoch>$sourceEpoch)throw new LogicException('Invalid expiry probe context.');
    $wall=time();$begin=hrtime(true);
    while(hrtime(true)-$begin<901000000000){if(time()<$wall||time()-$wall>930)throw new RuntimeException('Expiry probe wall clock left its bound.');usleep(100000);}
    $elapsed=hrtime(true)-$begin;$end=time();
    if($elapsed<901000000000||$elapsed>930000000000||$end-$wall<900||$end-$wall>930||$end-$sourceEpoch<=900||$end-$anchorEpoch<=900)throw new RuntimeException('Expiry probe did not establish genuine observation expiry.');
    return ['requested'=>true,'executed'=>true,'source_started_epoch'=>$sourceEpoch,'anchor_started_epoch'=>$anchorEpoch,'wait_started_epoch'=>$wall,'wait_finished_epoch'=>$end,'elapsed_ms'=>(int)($elapsed/1000000),'source_age_seconds'=>$end-$sourceEpoch,'anchor_age_seconds'=>$end-$anchorEpoch];
}

/** Exact e2c88d9d create body; only function name changed. Prior-function compatibility, not installed-upgrade evidence.
 * Original function SHA256 6b1ebbe9b644aa4da7ae4d72991756bcb6010a15c1b07400082956718c148a53; production storage/authentication remain actual.
 */
function cwri_e2_create(string $id,int $clientId,string $linkId,string $context,string $domain,array $aliases,bool $confirmed):array {
    return provider_cloud_assignment_inspect(function()use($id,$clientId,$linkId,$context,$domain,$aliases,$confirmed):array {
        $actor=provider_cloud_site_requests_actor($clientId);provider_cloud_site_requests_id($id);provider_cloud_site_requests_id($linkId);$state=provider_cloud_site_requests_state();
        if(!$confirmed)throw new InvalidArgumentException('Confirm this website request for studio review.');
        $existing=$state['requests'][$id]??null;
        if($existing){
            $p=$existing['request']['payload'];provider_cloud_site_requests_static_current($existing);
            if($existing['request']['client_id']!==$clientId||provider_cloud_site_requests_json($existing['request']['actor'])!==provider_cloud_site_requests_json($actor)||$p['binding']['link_id']!==$linkId||$p['domain']!==$domain||$p['aliases']!==$aliases||!hash_equals(provider_cloud_site_requests_context($p['binding'],$actor),$context))throw new RuntimeException('This request identity was already used for different details.');
            return $existing;
        }
        $b=provider_cloud_assignment_binding($linkId)->data;
        if($b['client_id']!==$clientId||!hash_equals(provider_cloud_site_requests_context($b,$actor),$context))throw new RuntimeException('Your server assignment or observation changed. Reload this form.');
        provider_cloud_site_requests_size($b,$domain,$aliases);$recent=0;
        foreach($state['requests'] as $r){
            if($r['request']['client_id']===$clientId&&$r['request']['recorded_epoch']>=time()-86400)++$recent;
            $p=$r['request']['payload'];if($p['binding']['profile_id']!==$b['profile_id']||!array_intersect([$domain,...$aliases],[$p['domain'],...$p['aliases']]))continue;
            if($r['stage']==='pending')throw new RuntimeException('A request for one of these domains is already awaiting review.');
            if($r['dispatch']!==null){$grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$r['dispatch']['payload']['grant_id']]);$result=provider_cloud_sites_result($grant);if(($result['status']??null)!=='failed')throw new RuntimeException('A creation attempt for one of these domains needs review before another request.');}
        }
        if($recent>=10)throw new RuntimeException('You have reached the daily website request limit. Please contact the studio.');
        provider_cloud_site_requests_append($id,$id,$clientId,'request',$actor,['binding'=>$b,'domain'=>$domain,'aliases'=>$aliases,'confirmed'=>true]);return provider_cloud_site_requests_state()['requests'][$id];
    });
}

if($worker){
    try{
        stream_set_timeout(STDIN,25);$args=json_decode(trim((string)fgets(STDIN)),true,8,JSON_THROW_ON_ERROR);
        if(!is_array($args)||!array_is_list($args)||count($args)!==5)exit(2);foreach(array_slice($args,0,4) as $value)provider_cloud_site_requests_id($value);
        if(!is_string($args[4])||!preg_match('/\A[a-f0-9]{64}\z/D',$args[4]))exit(2);cwri_owner();$allocated=8803;
        $r=cwri_dispatch($args,static function():string{if(db()->inTransaction()||cwri_mutex())throw new LogicException('Worker resolver holds mutation guard.');echo "ready\n";fflush(STDOUT);if(trim((string)fgets(STDIN))!=='continue')throw new RuntimeException('Worker synchronization failed.');return cwri_dns();});
        echo json_encode(['status'=>$r->status,'posts'=>$posts,'boundary_errors'=>$boundaryErrors],JSON_THROW_ON_ERROR);exit(0);
    }catch(Throwable $e){echo json_encode(['error'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()],JSON_THROW_ON_ERROR);exit(1);}
}

try{
    $existing=all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen(CWRI_PREFIX),CWRI_PREFIX]);
    cwri_ok('Exact disposable database and unused namespace are required',query('SELECT DATABASE()')->fetchColumn()==='cwp_test'&&$existing===[]);
    foreach(schema_statements(CWRI_PREFIX) as $name=>$sql)if(!str_starts_with($name,'provider_'))db()->exec($sql);
    foreach(default_settings() as $k=>$v)save_setting($k,$v);save_setting('studio_schema_version','2');
    foreach([1,2,3] as $i)query('INSERT INTO '.table('users').' (id,name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?,?)',[$i,'Cloud request staff '.$i,'cloud-request-owner'.$i.'@example.invalid',password_hash(CWRI_PASSWORD,PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    staff_seed_owner(1);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (2,?,1,?)',['owner','[]']);query('INSERT INTO '.table('staff_profiles').' (user_id,role,enabled,permissions) VALUES (3,?,1,?)',['administrator',json_encode(array_keys(staff_permissions()))]);
    foreach([1,2,3,4] as $i)query('INSERT INTO '.table('studio_clients').' (id,name,email,password_hash,auth_version,created_at) VALUES (?,?,?,?,?,?)',[$i,'Cloud request client '.$i,'cloud-request-client'.$i.'@example.invalid',password_hash('Synthetic cloud request client passphrase',PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);
    cwri_owner();provider_settings_migrate(CWRI_PASSWORD);$profile=provider_settings_save('',0,'Client cloud request fixture',true,CWRI_SECRET,CWRI_PASSWORD);
    provider_inventory_migrate(CWRI_PASSWORD);provider_operations_migrate(CWRI_PASSWORD);provider_cloud_assignment_prepare(CWRI_PASSWORD);provider_cloud_confirmation_prepare(CWRI_PASSWORD);
    $stage='preparation';$before=cwri_digest();$tables=all('SHOW TABLES');cwri_ok('Request readiness is local and does not silently prepare storage',!provider_cloud_site_requests_ready()&&all('SHOW TABLES')===$tables&&cwri_digest()===$before);
    cwri_deny('Wrong owner password cannot enable client website requests',fn()=>provider_cloud_site_requests_prepare('wrong'));
    cwri_owner(3);cwri_deny('Administrator cannot enable client website requests',fn()=>provider_cloud_site_requests_prepare(CWRI_PASSWORD));cwri_owner();
    db()->beginTransaction();save_setting('cwri_transaction_sentinel','pending');$refused=false;try{provider_cloud_site_requests_prepare(CWRI_PASSWORD);}catch(LogicException){$refused=true;}
    cwri_ok('Preparation refuses inherited transaction before DDL',$refused&&db()->inTransaction()&&all('SHOW TABLES')===$tables);db()->rollBack();unset($GLOBALS['cwp_settings']);
    cwri_ok('Refused preparation preserves unrelated transaction rollback',!one('SELECT value FROM '.table('settings').' WHERE name=?',['cwri_transaction_sentinel']));
    db()->exec("CREATE TRIGGER cwri_prepare_fail BEFORE INSERT ON ".table('audit')." FOR EACH ROW BEGIN IF NEW.action='provider.cloud_site_request_prepared' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic prepare audit failure'; END IF; END");
    $failed=false;try{provider_cloud_site_requests_prepare(CWRI_PASSWORD);}catch(PDOException $e){$failed=($e->errorInfo[0]??'')==='45000';}finally{db()->exec('DROP TRIGGER cwri_prepare_fail');unset($GLOBALS['cwp_settings']);}
    cwri_ok('Interrupted preparation retains only a recognized empty table',$failed&&!provider_cloud_site_requests_ready()&&cwri_count('provider_cloud_site_request_events')===0&&!one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_cloud_site_request_head']));
    provider_cloud_site_requests_prepare(CWRI_PASSWORD);cwri_ok('Explicit retry completes recognized empty request preparation',provider_cloud_site_requests_ready());$before=cwri_digest();provider_cloud_site_requests_prepare(CWRI_PASSWORD);cwri_ok('Repeated preparation preserves every established row',cwri_digest()===$before);
    $stage='intake';$cloud=cwri_refresh($profile,InventoryRead::CLOUD_SERVERS_LIST,cwri_cloud_body());cwri_ok('Real cloud inventory reader establishes complete current assignment source',$cloud['state']==='complete');
    $links=[];foreach([1,2,3,4] as $i){provider_cloud_link($profile,1600+$i,$i,1,$cloud['id'],CWRI_PASSWORD);$links[$i]=one('SELECT * FROM '.table('provider_cloud_links').' WHERE profile_id=? AND server_id=?',[$profile,1600+$i]);}
    $o=cwri_option(1,$links[1]['id']);$before=cwri_effects();
    cwri_deny('Unchecked client request makes no intake',fn()=>provider_cloud_site_requests_create(cwri_id(1),1,$links[1]['id'],$o['context'],'unreviewed.example',[],false));
    cwri_deny('Noncanonical client domain is not silently normalized',fn()=>provider_cloud_site_requests_create(cwri_id(2),1,$links[1]['id'],$o['context'],'Mixed.example',[],true));
    cwri_deny('Duplicate alias cannot enter local request history',fn()=>provider_cloud_site_requests_create(cwri_id(3),1,$links[1]['id'],$o['context'],'same.example',['same.example'],true));
    cwri_deny('Forged intake context cannot enter local request history',fn()=>provider_cloud_site_requests_create(cwri_id(4),1,$links[1]['id'],str_repeat('f',64),'forged.example',[],true));
    $longAliases=[];for($i=0;$i<20;$i++)$longAliases[]=str_repeat('a',60).'.'.str_repeat('b',60).'.'.str_repeat('c',60).'.alias'.$i.'.example';
    $validLarge=CloudWebsiteCreate::params(['cloudServerId'=>1601,'domain_name'=>'large.example','extra_domain_names'=>$longAliases]);
    cwri_ok('Oversize intake probe uses otherwise valid provider domain parameters',count($validLarge['extra_domain_names'])===20);
    cwri_deny('Valid long aliases cannot create an intake exceeding future grant limit',fn()=>provider_cloud_site_requests_create(cwri_id(9),1,$links[1]['id'],$o['context'],'large.example',$longAliases,true));
    $boundaryAliases=[];foreach($longAliases as $alias){$candidate=[...$boundaryAliases,$alias];try{provider_cloud_site_requests_size($o['binding'],'first.client-cloud.example',$candidate);$boundaryAliases=$candidate;}catch(InvalidArgumentException){break;}}
    cwri_ok('Finite largest fitting alias prefix is accepted before any intake',count($boundaryAliases)>0&&count($boundaryAliases)<count($longAliases));
    cwri_deny('One additional valid alias crosses the exact future grant bound',fn()=>provider_cloud_site_requests_size($o['binding'],'first.client-cloud.example',[...$boundaryAliases,$longAliases[count($boundaryAliases)]]));
    cwri_client(2);cwri_deny('Different actual client cannot submit for the original client',fn()=>provider_cloud_site_requests_create(cwri_id(5),1,$links[1]['id'],$o['context'],'foreign.example',[],true));
    cwri_client(1);$_SESSION['contact_id']=7;$_SESSION['contact_auth']=str_repeat('a',32);cwri_deny('Contact-context compatibility probe cannot submit as primary client',fn()=>provider_cloud_site_requests_create(cwri_id(6),1,$links[1]['id'],$o['context'],'contact.example',[],true));
    cwri_client(1);$_GET['support']='synthetic';cwri_deny('Support selector cannot submit a client decision',fn()=>provider_cloud_site_requests_create(cwri_id(7),1,$links[1]['id'],$o['context'],'support.example',[],true));cwri_client(1);
    $GLOBALS['cwp_support']=['client'=>one('SELECT * FROM '.table('studio_clients').' WHERE id=1')];cwri_deny('Established support context cannot submit a primary-client decision',fn()=>provider_cloud_site_requests_create(cwri_id(8),1,$links[1]['id'],$o['context'],'support-context.example',[],true));cwri_client(1);
    cwri_ok('All intake denials leave storage and provider counters unchanged',cwri_effects()===$before);
    $cancel=cwri_create(10,$links[1],'cancel.example');$before=cwri_effects();cwri_replay_create($cancel);cwri_ok('Exact pending intake replay adds no event or provider work',cwri_effects()===$before);
    provider_cloud_site_requests_change($cancel['id'],$cancel['id'],cwri_id(11),'cancel','',1);cwri_ok('Actual original client cancels only its pending request',cwri_history($cancel['id'],1)['stage']==='cancelled');
    $before=cwri_effects();cwri_replay_create($cancel);cwri_ok('Exact intake replay returns retained cancelled state without reopening',cwri_effects()===$before&&cwri_history($cancel['id'],1)['stage']==='cancelled');
    cwri_deny('Changed input cannot reuse a cancelled intake identity',fn()=>provider_cloud_site_requests_create($cancel['id'],1,$cancel['link'],$cancel['context'],'different.example',[],true));
    $decline=cwri_create(12,$links[1],'decline.example');cwri_owner();provider_cloud_site_requests_change($decline['id'],$decline['id'],cwri_id(13),'decline','Please review domain permission with the studio.',null,CWRI_PASSWORD);
    cwri_client();cwri_ok('Client receives actual owner decline and message',cwri_history($decline['id'],1)['stage']==='declined'&&str_contains(cwri_history($decline['id'],1)['note'],'domain permission'));cwri_deny('Client cannot cancel an already declined request',fn()=>provider_cloud_site_requests_change($decline['id'],$decline['id'],cwri_id(14),'cancel','',1));
    $first=cwri_create(20,$links[1],'first.client-cloud.example',1,$boundaryAliases);$args=cwri_dispatch_args($first,21,22);$stage='dispatch';
    $before=cwri_effects();cwri_owner(3);cwri_deny('Administrator cannot dispatch a client cloud website',fn()=>cwri_dispatch($args));cwri_client();cwri_deny('Client cannot dispatch its own provider write',fn()=>cwri_dispatch($args));cwri_owner();
    cwri_deny('Wrong current owner password refuses dispatch',fn()=>provider_cloud_site_requests_dispatch(...[...$args,true,'wrong','cwri_dns','cwri_perform']));
    cwri_deny('Missing owner capacity and domain review refuses dispatch',fn()=>provider_cloud_site_requests_dispatch(...[...$args,false,CWRI_PASSWORD,'cwri_dns','cwri_perform']));
    cwri_ok('Owner dispatch denials make no grant DNS or send',cwri_effects()===$before);
    cwri_fault('Dispatch audit failure rolls event head and grant back before DNS',$args,'provider.cloud_site_request_event');
    cwri_fault('Shared start audit failure rolls admitted event and inserted grant back',$args,'provider.cloudsite_started');
    $allocated=8801;$r=cwri_dispatch($args);cwri_ok('Owner dispatch uses actual shared gateway and retains exact allocated ID',$r->status==='accepted_pending'&&$r->externalId==='8801'&&$r->attempts===1);
    $grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$args[3]]);$doc=provider_cloud_sites_document($grant);$intake=cwri_event($first['id']);$dispatch=cwri_event($args[2]);
    $meta=$doc['client_request']??[];cwri_ok('Signed grant binds original intake and owner dispatch HMACs',count($meta)===4&&($meta['request_id']??null)===$first['id']&&($meta['request_hash']??null)===$intake['fingerprint']&&($meta['dispatch_id']??null)===$args[2]&&($meta['dispatch_hash']??null)===$dispatch['fingerprint']);
    cwri_ok('Actual boundary grant retains full aliases within unchanged 4096 byte storage bound',strlen($grant['scope_params'])>3800&&strlen($grant['scope_params'])<=4096&&$doc['params']['extra_domain_names']===$boundaryAliases);
    $q=json_decode($intake['document'],true,16,JSON_THROW_ON_ERROR);$d=json_decode($dispatch['document'],true,16,JSON_THROW_ON_ERROR);
    cwri_ok('Client request and owner authorization retain distinct truthful actors',$q['actor']['kind']==='client'&&$q['actor']['id']===1&&$d['actor']['kind']==='owner'&&$d['actor']['id']===1&&(int)$grant['granted_by']===1);
    cwri_ok('Provider acknowledgement creates no client package assignment',cwri_count('provider_package_links')===0&&cwri_count('provider_cloud_confirmations')===0);
    $before=cwri_effects();$r=cwri_dispatch($args);cwri_ok('Exact owner form replay returns original result without DNS or resend',$r->status==='accepted_pending'&&$r->externalId==='8801'&&cwri_effects()===$before);
    $other=$args;$other[2]=cwri_id(23);$other[3]=cwri_id(24);cwri_deny('New dispatch and grant IDs cannot send an already dispatched intake',fn()=>cwri_dispatch($other));
    cwri_client();$before=cwri_effects();cwri_replay_create($first);cwri_ok('Original client intake replay after dispatch cannot reopen or resend',cwri_effects()===$before&&cwri_history($first['id'],1)['stage']==='dispatched');
    cwri_deny('Client cannot cancel a committed dispatch',fn()=>provider_cloud_site_requests_change($first['id'],$first['id'],cwri_id(25),'cancel','',1));
    cwri_owner();cwri_deny('Owner cannot decline a committed dispatch',fn()=>provider_cloud_site_requests_change($first['id'],$first['id'],cwri_id(26),'decline','Cannot reopen.',null,CWRI_PASSWORD));
    $stage='concurrency';$parallel=cwri_create(30,$links[1],'parallel.client-cloud.example');$parallelArgs=cwri_dispatch_args($parallel,31,32);$beforeWire=count(cwri_wire());
    $process=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$private.'/worker.stderr','x']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Cannot start isolated request worker.');stream_set_timeout($pipes[1],25);
    fwrite($pipes[0],json_encode($parallelArgs,JSON_THROW_ON_ERROR)."\n");fflush($pipes[0]);$line=fgets($pipes[1]);
    if($line!=="ready\n")throw new RuntimeException('Worker did not reach durable admission boundary.');
    $before=cwri_effects();$parallelResult=cwri_dispatch($parallelArgs);
    cwri_ok('Concurrent owner duplicate sees committed dispatch without DNS or second send',$parallelResult->status==='uncertain'&&cwri_effects()===$before&&cwri_event($parallelArgs[2])['subject_id']===$parallel['id']);
    fwrite($pipes[0],"continue\n");fclose($pipes[0]);$workerOutput=stream_get_contents($pipes[1]);$timedOut=stream_get_meta_data($pipes[1])['timed_out'];fclose($pipes[1]);$exit=proc_close($process);$process=null;
    file_put_contents($private.'/worker.stdout',$line.$workerOutput);$w=json_decode($workerOutput,true,8,JSON_THROW_ON_ERROR);
    cwri_ok('Synchronized original worker alone sends through shared durable ledger',!$timedOut&&$exit===0&&$w===['status'=>'accepted_pending','posts'=>1,'boundary_errors'=>[]]&&count(cwri_wire())===$beforeWire+1&&filesize($private.'/worker.stderr')===0);
    $before=cwri_effects();$parallelResult=cwri_dispatch($parallelArgs);cwri_ok('Completed concurrent request replay retains allocated ID with zero work',$parallelResult->status==='accepted_pending'&&$parallelResult->externalId==='8803'&&cwri_effects()===$before);
    $stage='outcomes';$unknown=cwri_create(40,$links[1],'unknown.client-cloud.example');$unknownArgs=cwri_dispatch_args($unknown,41,42);$writeMode='unknown';$r=cwri_dispatch($unknownArgs);$writeMode='accepted';
    cwri_ok('Unverified provider acknowledgement stays uncertain with one durable attempt',$r->status==='uncertain'&&one('SELECT attempts FROM '.table('provider_operations').' WHERE idempotency_key=?',['cloudsite:'.$unknownArgs[3]])['attempts']==1);
    $before=cwri_effects();cwri_dispatch($unknownArgs);cwri_ok('Unknown outcome exact replay makes no second send',cwri_effects()===$before);
    $o=cwri_option(1,$links[1]['id']);cwri_deny('Fresh intake cannot silently retry an uncertain domain',fn()=>provider_cloud_site_requests_create(cwri_id(43),1,$links[1]['id'],$o['context'],$unknown['domain'],[],true));
    $preSend=cwri_create(50,$links[1],'presend.client-cloud.example');$preArgs=cwri_dispatch_args($preSend,51,52);$beforeWire=count(cwri_wire());
    $r=cwri_dispatch($preArgs,static fn():string=>'127.0.0.1');$row=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['cloudsite:'.$preArgs[3]]);
    cwri_ok('Endpoint refusal retains a definite zero-attempt result without provider send',$r->status==='failed'&&$r->attempts===0&&(int)$row['attempts']===0&&count(cwri_wire())===$beforeWire);
    $before=cwri_effects();cwri_dispatch($preArgs);cwri_ok('Definite failure original form is historical replay only',cwri_effects()===$before);
    $interrupted=cwri_create(60,$links[1],'interrupted.client-cloud.example');$interruptedArgs=cwri_dispatch_args($interrupted,61,62);$beforeWire=count(cwri_wire());
    $crash=static function(array $profile,string $secret,string $address,callable $authorize,ProviderOperation $op):OperationResult{
        cwri_boundary($authorize);$fp=ProviderGateway::requestFingerprint('twenty-i',$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($secret),$op);
        if((new ProviderOperationLedger())->claim($op->idempotencyKey,$fp,time())->state!=='claimed')throw new LogicException('Could not create real interrupted claim.');
        throw new PDOException('Synthetic process interruption after actual durable claim.');
    };
    $stopped=false;try{cwri_dispatch($interruptedArgs,null,$crash);}catch(PDOException){$stopped=true;}
    $row=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['cloudsite:'.$interruptedArgs[3]]);
    cwri_ok('Interrupted execution retains original dispatch and genuine unresolved claim',$stopped&&$row['status']==='claimed'&&(int)$row['attempts']===0&&$row['resolved_epoch']===null&&count(cwri_wire())===$beforeWire);
    $before=cwri_effects();$r=cwri_dispatch($interruptedArgs);cwri_ok('Unresolved original claim remains fenced with no resend or fabricated result',$r->status==='uncertain'&&cwri_effects()===$before);
    cwri_client();$mine=provider_cloud_site_requests_history(1);cwri_ok('Client status is bounded and omits grants credentials and owner identity',in_array($unknown['id'],array_column($mine,'id'),true)&&!str_contains(json_encode($mine),CWRI_SECRET)&&!str_contains(json_encode($mine),$profile)&&array_filter($mine,static fn($r)=>$r['grant_id']!==null||$r['package_id']!==null)===[]);
    cwri_client(2);cwri_ok('Other actual client cannot see first client requests',provider_cloud_site_requests_history(2)===[]);cwri_owner();
    $stage='confirmation';cwri_deny('Allocated ID alone cannot enable exact package confirmation',fn()=>provider_cloud_confirmation_review($args[3]));
    $package=['id'=>8801,'name'=>$first['domain'],'names'=>[$first['domain'],...$first['aliases']],'created'=>'2026-01-01T00:00:00+00:00','enabled'=>false,'typeRef'=>42,'stackUsers'=>[],'packageLabels'=>[],'productSpec'=>null];
    $hosting=cwri_refresh($profile,InventoryRead::PACKAGES_LIST,json_encode([$package],JSON_THROW_ON_ERROR));cwri_ok('Real subsequent hosting refresh observes the exact returned package',$hosting['state']==='complete');
    $review=provider_cloud_confirmation_review($args[3]);$beforeWire=count(cwri_wire());$beforeDns=$dns;
    provider_cloud_confirmation_confirm($args[3],$hosting['id'],$review['review'],true,CWRI_PASSWORD);
    $confirmed=one('SELECT * FROM '.table('provider_cloud_confirmations').' WHERE id=?',[$args[3]]);$packageLink=one('SELECT * FROM '.table('provider_package_links').' WHERE id=?',[$confirmed['link_id']]);
    cwri_ok('Existing confirmation atomically links exact package to original requesting client',(int)$packageLink['package_id']===8801&&(int)$packageLink['client_id']===1&&$packageLink['profile_id']===$profile&&count(cwri_wire())===$beforeWire&&$dns===$beforeDns);
    cwri_client();$h=cwri_history($first['id'],1);cwri_ok('Client request joins original confirmation without inventing health',$h['confirmation']['assigned']===true&&$h['confirmation']['status']==='Allocated package observed and assigned'&&$h['package_id']===null);cwri_owner();
    $before=cwri_effects();cwri_deny('Repeated confirmation cannot duplicate or restore assignments',fn()=>provider_cloud_confirmation_confirm($args[3],$hosting['id'],$review['review'],true,CWRI_PASSWORD));cwri_ok('Rejected confirmation replay makes no extra provider or stored effect',cwri_effects()===$before);
    $stage='newest-observation';$pending=cwri_create(70,$links[1],'after-read.client-cloud.example');$pendingArgs=cwri_dispatch_args($pending,71,72);
    $failedRead=cwri_refresh($profile,InventoryRead::CLOUD_SERVERS_LIST,'[]',503);cwri_ok('A real newest failed cloud refresh is retained',$failedRead['state']!=='complete');
    cwri_client();$before=cwri_effects();$o=cwri_option(1,$first['link']);cwri_ok('New intake options retain exact local assignment after latest cloud failure',$o['context']===$first['context']&&cwri_effects()===$before);
    [$local,$only]=cwri_only_intake(fn()=>provider_cloud_site_requests_create(cwri_id(73),1,$first['link'],$first['context'],'stale-form.example',[],true));cwri_ok('Saved static intake form queues a pending request after newer failed observation',$local['stage']==='pending'&&$local['request']['id']===cwri_id(73));cwri_ok('Local failed-read intake adds exactly one event head audit and no grant ledger DNS or send',$only);
    cwri_owner();$h=cwri_history($pending['id']);cwri_ok('Owner pending request retains visible refresh-required reason',!$h['can_dispatch']&&$h['reason']!=='');
    cwri_deny('Old owner review cannot dispatch after latest cloud read failure',fn()=>cwri_dispatch($pendingArgs));
    $before=cwri_effects();$r=cwri_dispatch($args);cwri_ok('Original completed dispatch replays despite a later failed observation',$r->status==='accepted_pending'&&$r->externalId==='8801'&&cwri_effects()===$before);
    $newCloud=cwri_refresh($profile,InventoryRead::CLOUD_SERVERS_LIST,cwri_cloud_body());cwri_ok('Real refreshed cloud source restores owner dispatch eligibility',$newCloud['state']==='complete'&&cwri_history($pending['id'])['can_dispatch']);
    $before=cwri_effects();$r=cwri_dispatch($args);cwri_ok('Original owner context pair replays after a newer complete read',$r->status==='accepted_pending'&&cwri_effects()===$before);
    cwri_deny('Superseded owner review requires a newly reviewed observation',fn()=>cwri_dispatch($pendingArgs));$pendingArgs=cwri_dispatch_args($pending,71,72);$allocated=8804;$r=cwri_dispatch($pendingArgs);cwri_ok('Pending request may use fresh observation with unchanged original assignment',$r->status==='accepted_pending'&&$r->externalId==='8804');
    $stage='static-intake-compatibility';cwri_client(2);
    $legacyBinding=cwri_locked(fn()=>provider_cloud_assignment_binding($links[2]['id'])->data);
    $legacyContext=cwri_locked(fn()=>provider_cloud_site_requests_context($legacyBinding,provider_cloud_site_requests_actor(2)));
    $legacy=['id'=>cwri_id(120),'client'=>2,'link'=>$links[2]['id'],'context'=>$legacyContext,'domain'=>'prior-function.client-cloud.example','aliases'=>[]];
    [$oldRequest,$only]=cwri_only_intake(fn()=>cwri_e2_create($legacy['id'],2,$legacy['link'],$legacyContext,$legacy['domain'],[],true));
    $static=cwri_option(2,$legacy['link']);
    cwri_ok('Exact prior e2 create body stores real post-assignment full binding with only local intake effects',$only&&provider_cloud_site_requests_binding($oldRequest['request']['payload']['binding'])===$legacyBinding&&$legacyBinding['source_id']===$newCloud['id']&&$legacyBinding['source_id']!==$static['binding']['source_id']&&$legacyContext!==$static['context']);
    $before=cwri_effects();$oldReplay=cwri_replay_create($legacy);
    cwri_ok('Successor exact replay preserves the real prior-function full-binding request and context',$oldReplay===$oldRequest&&cwri_effects()===$before);
    $stage='unfinished-intake';cwri_owner();cwri_wait($profile);$beforeReads=cwri_count('provider_refreshes');$beforeGets=$gets;$interruptedRead=null;
    try{provider_inventory_refresh_attempt($profile,(int)provider_settings_load($profile)['revision'],CWRI_PASSWORD,static function()use($profile,&$interruptedRead):string{
        global $dns;if(db()->inTransaction()||cwri_mutex())throw new LogicException('Refresh resolver must follow committed intent outside mutation guards.');$dns++;
        $interruptedRead=one('SELECT * FROM '.table('provider_refreshes').' WHERE profile_id=? AND operation=? ORDER BY seq DESC LIMIT 1',[$profile,InventoryRead::CLOUD_SERVERS_LIST]);
        if(!$interruptedRead||one('SELECT id FROM '.table('provider_refresh_results').' WHERE id=?',[$interruptedRead['id']]))throw new LogicException('Expected genuinely unfinished refresh intent.');
        throw new PDOException('Synthetic interruption after real read admission.');
    },'cwri_read',InventoryRead::CLOUD_SERVERS_LIST);}catch(PDOException $e){if($e->getMessage()!=='Synthetic interruption after real read admission.')throw $e;}
    cwri_ok('Real interrupted refresh retains authenticated start intent without result or GET',$interruptedRead!==null&&cwri_count('provider_refreshes')===$beforeReads+1&&!one('SELECT id FROM '.table('provider_refresh_results').' WHERE id=?',[$interruptedRead['id']])&&$gets===$beforeGets&&cwri_locked(fn()=>provider_cloud_assignment_ready()));
    cwri_static_case('Newest unfinished read',130,$links[2],'unfinished.client-cloud.example');
    $stage='omitted-server-intake';$omitted=cwri_refresh($profile,InventoryRead::CLOUD_SERVERS_LIST,json_encode(array_values(array_filter(json_decode(cwri_cloud_body(),true,8,JSON_THROW_ON_ERROR),static fn($v)=>$v['id']!==1602)),JSON_THROW_ON_ERROR));
    cwri_ok('Real latest complete cloud read omits the still-assigned second server',$omitted['state']==='complete'&&!one('SELECT id FROM '.table('provider_observation_cloud').' WHERE refresh_id=? AND server_id=?',[$omitted['id'],1602])&&one('SELECT id FROM '.table('provider_cloud_links').' WHERE id=?',[$links[2]['id']])!==null);
    cwri_static_case('Newest complete omission',140,$links[2],'omitted.client-cloud.example');
    $before=cwri_effects();$oldReplay=cwri_replay_create($legacy);
    cwri_ok('Prior-function request still replays exactly after unfinished and server-omitting reads',$oldReplay===$oldRequest&&cwri_effects()===$before);
    $restored=cwri_refresh($profile,InventoryRead::CLOUD_SERVERS_LIST,cwri_cloud_body());
    cwri_ok('Actual full refresh restores fresh dispatch after local-only intake scenarios',$restored['state']==='complete'&&cwri_history(cwri_id(130))['can_dispatch']&&cwri_history(cwri_id(140))['can_dispatch']);
    if($expiryProbe['requested']){
        $stage='genuine-expired-intake';$latest=provider_cloud_assignment_source($restored['id']);$anchor=provider_cloud_assignment_source($links[2]['refresh_id']);$before=cwri_effects();
        $expiryProbe=cwri_expire((int)$latest['started_epoch'],(int)$anchor['started_epoch']);
        cwri_ok('Opt-in expiry waits over 900 real monotonic seconds and expires original and latest anchors',$expiryProbe['elapsed_ms']>=901000&&$expiryProbe['source_age_seconds']>900&&$expiryProbe['anchor_age_seconds']>900&&cwri_effects()===$before);
        cwri_static_case('Genuinely expired complete read',150,$links[2],'expired.client-cloud.example');
        $restored=cwri_refresh($profile,InventoryRead::CLOUD_SERVERS_LIST,cwri_cloud_body());
        cwri_ok('Opt-in expiry ends with genuine fresh observation before original authority tests',$restored['state']==='complete'&&cwri_history(cwri_id(150))['can_dispatch']);
    }
    $stage='history-integrity';cwri_owner();
    cwri_corrupt('Missing request marker refuses established history',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_cloud_site_request_schema_version']),fn()=>provider_cloud_site_requests_history());
    cwri_corrupt('Missing request head refuses established history',fn()=>query('DELETE FROM '.table('settings').' WHERE name=?',['provider_cloud_site_request_head']),fn()=>provider_cloud_site_requests_history());
    cwri_corrupt('Changed immutable intake document refuses history',fn()=>query('UPDATE '.table('provider_cloud_site_request_events').' SET document=? WHERE id=?',['{}',$first['id']]),fn()=>provider_cloud_site_requests_history());
    cwri_corrupt('Paired intake and its audit loss cannot erase signed origin',function()use($first,$intake):void{query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.cloud_site_request_event','Cloud website request event '.$first['id'].' '.$intake['fingerprint']]);query('DELETE FROM '.table('provider_cloud_site_request_events').' WHERE id=?',[$first['id']]);},fn()=>provider_cloud_site_requests_history());
    cwri_corrupt('Paired dispatch and its audit loss cannot erase signed grant origin',function()use($args,$dispatch):void{query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=?',['provider.cloud_site_request_event','Cloud website request event '.$args[2].' '.$dispatch['fingerprint']]);query('DELETE FROM '.table('provider_cloud_site_request_events').' WHERE id=?',[$args[2]]);},fn()=>provider_cloud_site_requests_history());
    cwri_corrupt('Changed signed grant origin cannot fall back to owner-only history',function()use($unknownArgs):void{$g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$unknownArgs[3]]);$d=json_decode($g['scope_params'],true,16,JSON_THROW_ON_ERROR);unset($d['client_request']);query('UPDATE '.table('provider_operation_grants').' SET scope_params=? WHERE id=?',[provider_cloud_site_requests_json($d),$g['id']]);},fn()=>provider_cloud_site_requests_history());
    cwri_corrupt('Missing unconfirmed original grant refuses intake history',fn()=>query('DELETE FROM '.table('provider_operation_grants').' WHERE id=?',[$unknownArgs[3]]),fn()=>provider_cloud_site_requests_history());
    cwri_corrupt('Missing shared start audit refuses client request history',fn()=>query('DELETE FROM '.table('audit').' WHERE BINARY action=? AND detail LIKE ?',['provider.cloudsite_started','Cloud website request '.$unknownArgs[3].' %']),fn()=>provider_cloud_site_requests_history());
    cwri_corrupt('Missing exact confirmation witness refuses current assignment claim',fn()=>query('DELETE FROM '.table('provider_cloud_confirmations').' WHERE id=?',[$args[3]]),fn()=>provider_cloud_site_requests_history());
    $before=cwri_digest();staff_locked(function()use($unknownArgs,$unknown):void{
        db()->beginTransaction();try{
            query('DELETE FROM '.table('provider_operations').' WHERE idempotency_key=?',['cloudsite:'.$unknownArgs[3]]);$h=cwri_history($unknown['id']);
            cwri_ok('Missing outcome stays uncertain rather than reconstructed',$h['status']==='uncertain');
            $g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$unknownArgs[3]]);$r=provider_cloud_sites_replay($g);cwri_ok('Missing-outcome historical replay does not insert a ledger',$r->status==='uncertain'&&!one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',[$g['consumed_key']]));
        }finally{if(db()->inTransaction())db()->rollBack();}
    });
    cwri_ok('Outcome-loss probe restores all original business rows',cwri_digest()===$before);
    $stage='authority-boundaries';$dnsRevoked=cwri_create(80,$links[3],'client-revoked.example',3);$dnsArgs=cwri_dispatch_args($dnsRevoked,81,82);$beforeWire=count(cwri_wire());
    $duringDns=static function():void{$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=3');studio_client_save(3,$c['auth_version'],$c['name'],$c['email'],false);};
    $r=cwri_dispatch($dnsArgs);cwri_ok('Real client deactivation at DNS prevents credential send',$r->status==='failed'&&$r->attempts===0&&count(cwri_wire())===$beforeWire);
    $before=cwri_effects();cwri_deny('Revoked original client prevents outcome replay authority',fn()=>cwri_dispatch($dnsArgs));cwri_ok('Denied revoked-client replay still sends nothing',cwri_effects()===$before);
    $credentialRevoked=cwri_create(90,$links[4],'credential-client-revoked.example',4);$credentialArgs=cwri_dispatch_args($credentialRevoked,91,92);$beforeWire=count(cwri_wire());
    $duringCredential=static function():void{$c=one('SELECT * FROM '.table('studio_clients').' WHERE id=4');studio_client_save(4,$c['auth_version'],$c['name'],$c['email'],true);};
    $r=cwri_dispatch($credentialArgs);cwri_ok('Real active-client auth rotation at credential callback prevents transport',in_array($r->status,['failed','uncertain'],true)&&count(cwri_wire())===$beforeWire);
    $ownerChanged=cwri_create(95,$links[2],'owner-revoked.example',2);cwri_owner(2);$h=cwri_history($ownerChanged['id']);$ownerArgs=[$ownerChanged['id'],$h['latest_event'],cwri_id(96),cwri_id(97),$h['dispatch_context']];$beforeWire=count(cwri_wire());
    $duringDns=static function():void{staff_save(2,'Cloud request staff 2','cloud-request-owner2@example.invalid','administrator',true,[],CWRI_PASSWORD);};
    $r=cwri_dispatch($ownerArgs);cwri_ok('Real owner role revocation during DNS prevents the original approval send',$r->status==='failed'&&$r->attempts===0&&count(cwri_wire())===$beforeWire);
    cwri_owner(2);$before=cwri_effects();cwri_deny('Revoked owner cannot replay its original creation result',fn()=>cwri_dispatch($ownerArgs));cwri_ok('Revoked-owner replay refusal makes no mutation or send',cwri_effects()===$before);cwri_owner();
    $linkChanged=cwri_create(100,$links[2],'assignment-changed.example',2);$linkArgs=cwri_dispatch_args($linkChanged,101,102);$beforeWire=count(cwri_wire());
    $duringDns=static function()use($links):void{provider_cloud_unlink($links[2]['id'],(int)$links[2]['revision'],CWRI_PASSWORD);};
    $r=cwri_dispatch($linkArgs);cwri_ok('Actual assignment unlink during DNS prevents provider send',$r->status==='failed'&&$r->attempts===0&&count(cwri_wire())===$beforeWire);
    cwri_client(2);cwri_ok('Changed assignment hides private request outcome from client',provider_cloud_site_requests_history(2)===[]);cwri_owner();
    $keyChanged=cwri_create(110,$links[1],'key-changed.example');$keyArgs=cwri_dispatch_args($keyChanged,111,112);$beforeWire=count(cwri_wire());
    $duringDns=static function()use($profile):void{$p=provider_settings_load($profile);provider_settings_save($profile,(int)$p['revision'],$p['label'],true,'SYNTHETIC_CHANGED_CLOUD_WEBSITE_KEY',CWRI_PASSWORD);};
    $r=cwri_dispatch($keyArgs);cwri_ok('Actual provider credential change during DNS prevents old-account send',$r->status==='failed'&&$r->attempts===0&&count(cwri_wire())===$beforeWire);
    $before=cwri_effects();cwri_deny('Changed provider account cannot replay original outcome as current authority',fn()=>cwri_dispatch($args));cwri_ok('Changed-account replay refusal preserves original records',cwri_effects()===$before);
    $stage='final';cwri_owner();$stored=json_encode([all('SELECT * FROM '.table('provider_cloud_site_request_events')),all('SELECT * FROM '.table('provider_operation_grants')),all('SELECT * FROM '.table('provider_operations')),all('SELECT * FROM '.table('audit'))],JSON_THROW_ON_ERROR);
    cwri_ok('Request grant ledger and audit exclude synthetic passwords and API keys',!str_contains($stored,CWRI_PASSWORD)&&!str_contains($stored,CWRI_SECRET)&&!str_contains($stored,'SYNTHETIC_CHANGED_CLOUD_WEBSITE_KEY'));
    cwri_ok('All typed transport boundary assertions remained satisfied',$boundaryErrors===[]);
    cwri_ok('Actual final local event history remains authenticated',provider_cloud_site_requests_ready());
}catch(Throwable $e){$failure=['stage'=>$stage,'class'=>get_class($e),'file'=>basename($e->getFile()),'line'=>$e->getLine()];}
finally{if(is_resource($process)){proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}}
$result=['passed'=>!isset($failure),'scope'=>'Native disposable SQL with synthetic provider; no live provider, HTTP, payment or health claim','checks'=>$checks,'expiry_probe'=>$expiryProbe];if(isset($failure))$result['failure']=$failure;
$encoded=json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";$handle=fopen($report,'xb');if(!$handle)exit(2);if(fwrite($handle,$encoded)!==strlen($encoded)){fclose($handle);exit(2);}fclose($handle);echo $encoded;exit(isset($failure)?1:0);
